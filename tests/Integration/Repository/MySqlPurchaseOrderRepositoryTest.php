<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;
use App\Entity\Supplier;
use App\Entity\Warehouse;
use App\Repository\MySqlCategoryRepository;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlPurchaseOrderRepository;
use App\Repository\MySqlSupplierRepository;
use App\Repository\MySqlWarehouseRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Menyentuh MySQL sungguhan (TEST-02). purchase_orders butuh supplier,
 * warehouse, product, dan user (seed admin) nyata (FK NOT NULL) - dibuat
 * sendiri di setUp, dihapus lagi di tearDown (urutan: items -> po ->
 * product/warehouse/supplier -> category) supaya tidak mencemari data
 * demo (brief bagian 7.1).
 */
final class MySqlPurchaseOrderRepositoryTest extends TestCase
{
    private PDO $pdo;

    private int $categoryId;

    private int $productId;

    private int $warehouseId;

    private int $supplierId;

    private int $adminUserId;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../config/database.php';
        $this->pdo = createPdoConnection();

        $this->categoryId = (new MySqlCategoryRepository($this->pdo))->save(new Category(null, 'Test Kategori PO ZZZ', null))->id;
        $this->productId = (new MySqlProductRepository($this->pdo))->save(
            new Product(null, 'TEST-PO-SKU-ZZZ', 'Test Produk PO', $this->categoryId, 'pcs', 10000, 15000, 5, null, true)
        )->id;
        $this->warehouseId = (new MySqlWarehouseRepository($this->pdo))->save(new Warehouse(null, 'Test Gudang PO ZZZ', null, true))->id;
        $this->supplierId = (new MySqlSupplierRepository($this->pdo))->save(new Supplier(null, 'Test Supplier PO ZZZ', null, null, true))->id;

        $statement = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
        $statement->execute(['email' => 'admin@iom.test']);
        $this->adminUserId = (int) $statement->fetchColumn();
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE poi FROM purchase_order_items poi JOIN purchase_orders po ON po.id = poi.purchase_order_id WHERE po.supplier_id = :supplier_id')
            ->execute(['supplier_id' => $this->supplierId]);
        $this->pdo->prepare('DELETE FROM purchase_orders WHERE supplier_id = :supplier_id')->execute(['supplier_id' => $this->supplierId]);
        $this->pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $this->productId]);
        $this->pdo->prepare('DELETE FROM warehouses WHERE id = :id')->execute(['id' => $this->warehouseId]);
        $this->pdo->prepare('DELETE FROM suppliers WHERE id = :id')->execute(['id' => $this->supplierId]);
        $this->pdo->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $this->categoryId]);
    }

    private function makePurchaseOrder(?array $items = null): PurchaseOrder
    {
        $items ??= [new PurchaseOrderItem(null, null, $this->productId, 10, 10000, 0)];

        return new PurchaseOrder(null, $this->supplierId, $this->warehouseId, PurchaseOrderStatus::Draft, '2026-09-17', $this->adminUserId, $items);
    }

    public function testSaveInsertsHeaderAndItemsTogether(): void
    {
        $repository = new MySqlPurchaseOrderRepository($this->pdo);

        $saved = $repository->save($this->makePurchaseOrder());

        self::assertNotNull($saved->id);
        self::assertCount(1, $saved->items);
        self::assertNotNull($saved->items[0]->id);
        self::assertSame(0, $saved->items[0]->receivedQty);
    }

    public function testFindByIdReturnsPurchaseOrderWithItems(): void
    {
        $repository = new MySqlPurchaseOrderRepository($this->pdo);
        $saved = $repository->save($this->makePurchaseOrder());

        $found = $repository->findById($saved->id);

        self::assertNotNull($found);
        self::assertSame(PurchaseOrderStatus::Draft, $found->status);
        self::assertCount(1, $found->items);
        self::assertSame($this->productId, $found->items[0]->productId);
    }

    public function testUpdateStatusChangesStatus(): void
    {
        $repository = new MySqlPurchaseOrderRepository($this->pdo);
        $saved = $repository->save($this->makePurchaseOrder());

        $repository->updateStatus($saved->id, PurchaseOrderStatus::Ordered);

        self::assertSame(PurchaseOrderStatus::Ordered, $repository->findById($saved->id)->status);
    }

    public function testIncrementItemReceivedQtyAccumulates(): void
    {
        $repository = new MySqlPurchaseOrderRepository($this->pdo);
        $saved = $repository->save($this->makePurchaseOrder());
        $itemId = $saved->items[0]->id;

        $repository->incrementItemReceivedQty($itemId, 4);
        $repository->incrementItemReceivedQty($itemId, 3);

        $found = $repository->findById($saved->id);
        self::assertSame(7, $found->items[0]->receivedQty);
        self::assertSame(3, $found->items[0]->remainingQty());
    }

    public function testListAllFiltersBySearchAndStatus(): void
    {
        $repository = new MySqlPurchaseOrderRepository($this->pdo);
        $saved = $repository->save($this->makePurchaseOrder());
        $repository->updateStatus($saved->id, PurchaseOrderStatus::Ordered);

        $bySupplierName = $repository->listAll(search: 'Test Supplier PO ZZZ');
        self::assertNotEmpty(array_filter($bySupplierName, static fn (PurchaseOrder $po): bool => $po->id === $saved->id));

        $byId = $repository->listAll(search: (string) $saved->id);
        self::assertNotEmpty(array_filter($byId, static fn (PurchaseOrder $po): bool => $po->id === $saved->id));

        $byStatus = $repository->listAll(status: PurchaseOrderStatus::Draft);
        self::assertEmpty(array_filter($byStatus, static fn (PurchaseOrder $po): bool => $po->id === $saved->id), 'status sudah Ordered, tidak boleh muncul di filter Draft');
    }
}
