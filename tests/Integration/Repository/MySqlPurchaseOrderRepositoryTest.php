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

    /**
     * @param PurchaseOrderItem[]|null $items
     */
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

    public function testTransitionStatusChangesStatusWhenCurrentStatusMatches(): void
    {
        $repository = new MySqlPurchaseOrderRepository($this->pdo);
        $saved = $repository->save($this->makePurchaseOrder());

        self::assertTrue($repository->transitionStatus($saved->id, [PurchaseOrderStatus::Draft], PurchaseOrderStatus::Ordered));
        self::assertSame(PurchaseOrderStatus::Ordered, $repository->findById($saved->id)->status);
    }

    public function testTransitionStatusRejectsWhenCurrentStatusNoLongerMatches(): void
    {
        $repository = new MySqlPurchaseOrderRepository($this->pdo);
        $saved = $repository->save($this->makePurchaseOrder());
        $repository->transitionStatus($saved->id, [PurchaseOrderStatus::Draft], PurchaseOrderStatus::Ordered);

        // Transisi kedua memakai syarat lama (Draft) - persis situasi request
        // konkuren yang membaca status sebelum request pertama commit.
        $second = $repository->transitionStatus($saved->id, [PurchaseOrderStatus::Draft], PurchaseOrderStatus::Cancelled);

        self::assertFalse($second);
        self::assertSame(PurchaseOrderStatus::Ordered, $repository->findById($saved->id)->status, 'status tidak boleh tertimpa transisi yang syaratnya sudah basi');
    }

    public function testIncrementItemReceivedQtyAccumulates(): void
    {
        $repository = new MySqlPurchaseOrderRepository($this->pdo);
        $saved = $repository->save($this->makePurchaseOrder());
        $itemId = $saved->items[0]->id;

        self::assertTrue($repository->incrementItemReceivedQtyIfWithinOrdered($itemId, 4));
        self::assertTrue($repository->incrementItemReceivedQtyIfWithinOrdered($itemId, 3));

        $found = $repository->findById($saved->id);
        self::assertSame(7, $found->items[0]->receivedQty);
        self::assertSame(3, $found->items[0]->remainingQty());
    }

    public function testIncrementItemReceivedQtyRejectsWhenItWouldExceedOrderedQty(): void
    {
        $repository = new MySqlPurchaseOrderRepository($this->pdo);
        $saved = $repository->save($this->makePurchaseOrder());
        $itemId = $saved->items[0]->id;
        $orderedQty = $saved->items[0]->qty;

        self::assertTrue($repository->incrementItemReceivedQtyIfWithinOrdered($itemId, $orderedQty));

        // Penerimaan kedua atas item yang sudah penuh - inilah yang dulu bisa
        // membuat received_qty melebihi qty yang dipesan saat dua goods receipt
        // berjalan bersamaan.
        self::assertFalse($repository->incrementItemReceivedQtyIfWithinOrdered($itemId, 1));

        $found = $repository->findById($saved->id);
        self::assertSame($orderedQty, $found->items[0]->receivedQty);
        self::assertSame(0, $found->items[0]->remainingQty());
    }

    public function testListAllFiltersBySearchAndStatus(): void
    {
        $repository = new MySqlPurchaseOrderRepository($this->pdo);
        $saved = $repository->save($this->makePurchaseOrder());
        $repository->transitionStatus($saved->id, [PurchaseOrderStatus::Draft], PurchaseOrderStatus::Ordered);

        $bySupplierName = $repository->listAll(search: 'Test Supplier PO ZZZ');
        self::assertNotEmpty(array_filter($bySupplierName, static fn (PurchaseOrder $po): bool => $po->id === $saved->id));

        $byId = $repository->listAll(search: (string) $saved->id);
        self::assertNotEmpty(array_filter($byId, static fn (PurchaseOrder $po): bool => $po->id === $saved->id));

        $byStatus = $repository->listAll(status: PurchaseOrderStatus::Draft);
        self::assertEmpty(array_filter($byStatus, static fn (PurchaseOrder $po): bool => $po->id === $saved->id), 'status sudah Ordered, tidak boleh muncul di filter Draft');
    }

    /**
     * DASH-01. Dibandingkan sebagai delta before/after (bukan angka mutlak)
     * karena tabel purchase_orders sudah berisi seed 15 PO (§7.1, tech-debt
     * #6) - assert angka pasti akan rapuh terhadap perubahan seed di masa
     * depan.
     */
    public function testCountByStatusIncludesAllStatusesAndReflectsNewRow(): void
    {
        $repository = new MySqlPurchaseOrderRepository($this->pdo);
        $before = $repository->countByStatus();

        $repository->save($this->makePurchaseOrder());

        $after = $repository->countByStatus();

        self::assertArrayHasKey('Cancelled', $after, 'seluruh status harus selalu ada di hasil, termasuk yang kebetulan 0');
        self::assertSame($before['Draft'] + 1, $after['Draft']);
    }

    public function testListForReportFiltersByOrderDateRange(): void
    {
        $repository = new MySqlPurchaseOrderRepository($this->pdo);
        // makePurchaseOrder() default order_date 2026-09-17 - di luar
        // rentang seed 25-order (2026-08-01 s.d. 2026-09-16), jadi baris
        // ini dijamin satu-satunya yang cocok filter tanggal di bawah.
        $saved = $repository->save($this->makePurchaseOrder());

        $rows = $repository->listForReport('2026-09-17', '2026-09-17');

        self::assertCount(1, $rows);
        self::assertSame($saved->id, $rows[0]->id);
        self::assertSame([], $rows[0]->items, 'listForReport() cuma header, konsisten dengan listAll()');

        self::assertSame([], $repository->listForReport('2020-01-01', '2020-01-01'));
    }
}
