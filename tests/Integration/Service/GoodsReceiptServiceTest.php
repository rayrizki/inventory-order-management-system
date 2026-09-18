<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;
use App\Entity\Supplier;
use App\Entity\Warehouse;
use App\Repository\MySqlCategoryRepository;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlProductStockRepository;
use App\Repository\MySqlPurchaseOrderRepository;
use App\Repository\MySqlStockLedgerRepository;
use App\Repository\MySqlSupplierRepository;
use App\Repository\MySqlWarehouseRepository;
use App\Service\GoodsReceiptService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * TEST-02 bukti eksplisit: "goods receipt benar-benar menambah stok
 * end-to-end". Menyentuh MySQL sungguhan lewat transaksi nyata
 * (beginTransaction/commit di GoodsReceiptService::receive(), ARCH-02) -
 * bukan fake repository, supaya benar-benar membuktikan tiga tabel
 * (purchase_order_items, product_stock, stock_ledger) konsisten satu sama
 * lain setelah satu aksi goods receipt.
 */
final class GoodsReceiptServiceTest extends TestCase
{
    private PDO $pdo;

    private int $categoryId;

    private int $productId;

    private int $warehouseId;

    private int $supplierId;

    private int $adminUserId;

    private MySqlPurchaseOrderRepository $purchaseOrders;

    private MySqlProductStockRepository $stocks;

    private MySqlStockLedgerRepository $ledger;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../config/database.php';
        $this->pdo = createPdoConnection();

        $this->categoryId = (new MySqlCategoryRepository($this->pdo))->save(new Category(null, 'Test Kategori GR ZZZ', null))->id;
        $this->productId = (new MySqlProductRepository($this->pdo))->save(
            new Product(null, 'TEST-GR-SKU-ZZZ', 'Test Produk Goods Receipt', $this->categoryId, 'pcs', 10000, 15000, 5, null, true)
        )->id;
        $this->warehouseId = (new MySqlWarehouseRepository($this->pdo))->save(new Warehouse(null, 'Test Gudang GR ZZZ', null, true))->id;
        $this->supplierId = (new MySqlSupplierRepository($this->pdo))->save(new Supplier(null, 'Test Supplier GR ZZZ', null, null, true))->id;

        $statement = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
        $statement->execute(['email' => 'admin@iom.test']);
        $this->adminUserId = (int) $statement->fetchColumn();

        $this->purchaseOrders = new MySqlPurchaseOrderRepository($this->pdo);
        $this->stocks = new MySqlProductStockRepository($this->pdo);
        $this->ledger = new MySqlStockLedgerRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM stock_ledger WHERE product_id = :product_id')->execute(['product_id' => $this->productId]);
        $this->pdo->prepare('DELETE FROM product_stock WHERE product_id = :product_id')->execute(['product_id' => $this->productId]);
        $this->pdo->prepare('DELETE poi FROM purchase_order_items poi JOIN purchase_orders po ON po.id = poi.purchase_order_id WHERE po.supplier_id = :supplier_id')
            ->execute(['supplier_id' => $this->supplierId]);
        $this->pdo->prepare('DELETE FROM purchase_orders WHERE supplier_id = :supplier_id')->execute(['supplier_id' => $this->supplierId]);
        $this->pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $this->productId]);
        $this->pdo->prepare('DELETE FROM warehouses WHERE id = :id')->execute(['id' => $this->warehouseId]);
        $this->pdo->prepare('DELETE FROM suppliers WHERE id = :id')->execute(['id' => $this->supplierId]);
        $this->pdo->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $this->categoryId]);
    }

    private function createOrderedPurchaseOrder(int $qty): PurchaseOrder
    {
        $saved = $this->purchaseOrders->save(new PurchaseOrder(
            null,
            $this->supplierId,
            $this->warehouseId,
            PurchaseOrderStatus::Draft,
            '2026-09-17',
            $this->adminUserId,
            [new PurchaseOrderItem(null, null, $this->productId, $qty, 10000, 0)],
        ));
        $this->purchaseOrders->updateStatus($saved->id, PurchaseOrderStatus::Ordered);

        return $this->purchaseOrders->findById($saved->id);
    }

    public function testPartialReceiptAddsStockWritesLedgerAndSetsPartiallyReceivedStatus(): void
    {
        $po = $this->createOrderedPurchaseOrder(qty: 10);
        $itemId = $po->items[0]->id;
        $service = new GoodsReceiptService($this->purchaseOrders, $this->stocks, $this->ledger, $this->pdo);

        $updated = $service->receive($po->id, [$itemId => 4], performedBy: $this->adminUserId);

        self::assertSame(PurchaseOrderStatus::PartiallyReceived, $updated->status);
        self::assertSame(4, $updated->items[0]->receivedQty);
        self::assertSame(6, $updated->items[0]->remainingQty());

        $stockRows = $this->stocks->findByProduct($this->productId);
        self::assertCount(1, $stockRows);
        self::assertSame(4, $stockRows[0]->quantity, 'goods receipt harus benar-benar menambah ProductStock end-to-end');

        $ledgerEntries = $this->ledger->findByReference('purchase_order', $po->id);
        self::assertCount(1, $ledgerEntries);
        self::assertSame(4, $ledgerEntries[0]->quantity);
        self::assertSame(\App\Entity\StockMovementType::Receipt, $ledgerEntries[0]->movementType);
    }

    public function testReceivingRemainingQtyAfterPartialSetsReceivedStatusAndAccumulatesStock(): void
    {
        $po = $this->createOrderedPurchaseOrder(qty: 10);
        $itemId = $po->items[0]->id;
        $service = new GoodsReceiptService($this->purchaseOrders, $this->stocks, $this->ledger, $this->pdo);

        $service->receive($po->id, [$itemId => 4], performedBy: $this->adminUserId);
        $final = $service->receive($po->id, [$itemId => 6], performedBy: $this->adminUserId);

        self::assertSame(PurchaseOrderStatus::Received, $final->status);
        self::assertSame(10, $final->items[0]->receivedQty);
        self::assertSame(0, $final->items[0]->remainingQty());

        $stockRows = $this->stocks->findByProduct($this->productId);
        self::assertSame(10, $stockRows[0]->quantity, 'dua penerimaan parsial (4+6) harus terakumulasi jadi 10, bukan menimpa');

        $ledgerEntries = $this->ledger->findByReference('purchase_order', $po->id);
        self::assertCount(2, $ledgerEntries, 'dua aksi goods receipt harus menghasilkan dua baris ledger terpisah, bukan digabung');
    }

    public function testReceiveRejectsQtyExceedingRemainingAndWritesNothing(): void
    {
        $po = $this->createOrderedPurchaseOrder(qty: 10);
        $itemId = $po->items[0]->id;
        $service = new GoodsReceiptService($this->purchaseOrders, $this->stocks, $this->ledger, $this->pdo);

        try {
            $service->receive($po->id, [$itemId => 999], performedBy: $this->adminUserId);
            self::fail('Expected ValidationException');
        } catch (\App\Exception\ValidationException) {
            // diharapkan
        }

        self::assertSame([], $this->stocks->findByProduct($this->productId), 'penerimaan yang ditolak validasi tidak boleh menyentuh ProductStock sama sekali');
        self::assertSame([], $this->ledger->findByReference('purchase_order', $po->id));
        self::assertSame(PurchaseOrderStatus::Ordered, $this->purchaseOrders->findById($po->id)->status, 'status PO tidak boleh berubah saat penerimaan ditolak');
    }
}
