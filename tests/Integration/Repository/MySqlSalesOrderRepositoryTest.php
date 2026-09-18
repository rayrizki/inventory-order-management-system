<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\SalesOrderStatus;
use App\Entity\Warehouse;
use App\Repository\MySqlCategoryRepository;
use App\Repository\MySqlCustomerRepository;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlSalesOrderRepository;
use App\Repository\MySqlWarehouseRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Menyentuh MySQL sungguhan (TEST-02). sales_orders butuh customer,
 * warehouse, product, dan user (seed sales) nyata (FK NOT NULL) - dibuat
 * sendiri di setUp, dihapus lagi di tearDown supaya tidak mencemari data
 * demo (brief bagian 7.1).
 */
final class MySqlSalesOrderRepositoryTest extends TestCase
{
    private PDO $pdo;

    private int $categoryId;

    private int $productId;

    private int $warehouseId;

    private int $customerId;

    private int $salesUserId;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../config/database.php';
        $this->pdo = createPdoConnection();

        $this->categoryId = (new MySqlCategoryRepository($this->pdo))->save(new Category(null, 'Test Kategori SO ZZZ', null))->id;
        $this->productId = (new MySqlProductRepository($this->pdo))->save(
            new Product(null, 'TEST-SO-SKU-ZZZ', 'Test Produk SO', $this->categoryId, 'pcs', 10000, 15000, 5, null, true)
        )->id;
        $this->warehouseId = (new MySqlWarehouseRepository($this->pdo))->save(new Warehouse(null, 'Test Gudang SO ZZZ', null, true))->id;
        $this->customerId = (new MySqlCustomerRepository($this->pdo))->save(new Customer(null, 'Test Customer SO ZZZ', null, null, true))->id;

        $statement = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
        $statement->execute(['email' => 'sales1@iom.test']);
        $this->salesUserId = (int) $statement->fetchColumn();
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE soi FROM sales_order_items soi JOIN sales_orders so ON so.id = soi.sales_order_id WHERE so.customer_id = :customer_id')
            ->execute(['customer_id' => $this->customerId]);
        $this->pdo->prepare('DELETE FROM sales_orders WHERE customer_id = :customer_id')->execute(['customer_id' => $this->customerId]);
        $this->pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $this->productId]);
        $this->pdo->prepare('DELETE FROM warehouses WHERE id = :id')->execute(['id' => $this->warehouseId]);
        $this->pdo->prepare('DELETE FROM customers WHERE id = :id')->execute(['id' => $this->customerId]);
        $this->pdo->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $this->categoryId]);
    }

    /**
     * @param SalesOrderItem[]|null $items
     */
    private function makeSalesOrder(?array $items = null): SalesOrder
    {
        $items ??= [new SalesOrderItem(null, null, $this->productId, 5, 15000)];

        return new SalesOrder(null, $this->customerId, $this->warehouseId, SalesOrderStatus::Draft, $this->salesUserId, null, null, $items);
    }

    public function testSaveInsertsHeaderAndItemsTogether(): void
    {
        $repository = new MySqlSalesOrderRepository($this->pdo);

        $saved = $repository->save($this->makeSalesOrder());

        self::assertNotNull($saved->id);
        self::assertCount(1, $saved->items);
        self::assertNotNull($saved->items[0]->id);
        self::assertSame(SalesOrderStatus::Draft, $saved->status);
    }

    public function testFindByIdReturnsSalesOrderWithItems(): void
    {
        $repository = new MySqlSalesOrderRepository($this->pdo);
        $saved = $repository->save($this->makeSalesOrder());

        $found = $repository->findById($saved->id);

        self::assertNotNull($found);
        self::assertSame(SalesOrderStatus::Draft, $found->status);
        self::assertCount(1, $found->items);
        self::assertSame($this->productId, $found->items[0]->productId);
        self::assertSame($this->salesUserId, $found->createdBy);
        self::assertNull($found->approvedBy);
    }

    public function testUpdateStatusChangesStatus(): void
    {
        $repository = new MySqlSalesOrderRepository($this->pdo);
        $saved = $repository->save($this->makeSalesOrder());

        $repository->updateStatus($saved->id, SalesOrderStatus::PendingApproval);

        self::assertSame(SalesOrderStatus::PendingApproval, $repository->findById($saved->id)->status);
    }

    public function testApproveSetsStatusAndApprovedByTogether(): void
    {
        $repository = new MySqlSalesOrderRepository($this->pdo);
        $saved = $repository->save($this->makeSalesOrder());
        $repository->updateStatus($saved->id, SalesOrderStatus::PendingApproval);

        $statement = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
        $statement->execute(['email' => 'admin@iom.test']);
        $adminId = (int) $statement->fetchColumn();

        $repository->approve($saved->id, $adminId);

        $found = $repository->findById($saved->id);
        self::assertSame(SalesOrderStatus::Approved, $found->status);
        self::assertSame($adminId, $found->approvedBy);
    }

    public function testListAllFiltersBySearchStatusAndCreatedBy(): void
    {
        $repository = new MySqlSalesOrderRepository($this->pdo);
        $saved = $repository->save($this->makeSalesOrder());
        $repository->updateStatus($saved->id, SalesOrderStatus::PendingApproval);

        $bySearch = $repository->listAll(search: 'Test Customer SO ZZZ');
        self::assertNotEmpty(array_filter($bySearch, static fn (SalesOrder $so): bool => $so->id === $saved->id));

        $byStatus = $repository->listAll(status: SalesOrderStatus::Draft);
        self::assertEmpty(array_filter($byStatus, static fn (SalesOrder $so): bool => $so->id === $saved->id), 'status sudah PendingApproval, tidak boleh muncul di filter Draft');

        $byOwnCreatedBy = $repository->listAll(createdBy: $this->salesUserId);
        self::assertNotEmpty(array_filter($byOwnCreatedBy, static fn (SalesOrder $so): bool => $so->id === $saved->id));

        $byOtherCreatedBy = $repository->listAll(createdBy: 999999);
        self::assertEmpty(array_filter($byOtherCreatedBy, static fn (SalesOrder $so): bool => $so->id === $saved->id), 'createdBy filter harus scoping kepemilikan Sales (§1.2)');
    }

    public function testListAllItemsAlwaysEmptyToAvoidNPlusOne(): void
    {
        $repository = new MySqlSalesOrderRepository($this->pdo);
        $saved = $repository->save($this->makeSalesOrder());

        $list = $repository->listAll(search: (string) $saved->id);

        self::assertNotEmpty($list);
        self::assertSame([], $list[0]->items, 'listAll() tidak boleh memuat item - pakai findById() untuk detail');
    }

    /**
     * DASH-01. Delta before/after (bukan angka mutlak) karena sales_orders
     * sudah berisi seed 10 SO (§7.1, tech-debt #6).
     */
    public function testCountByStatusIncludesAllStatusesAndScopesToCreatedBy(): void
    {
        $repository = new MySqlSalesOrderRepository($this->pdo);
        $beforeAll = $repository->countByStatus();
        $beforeOwn = $repository->countByStatus($this->salesUserId);

        $repository->save($this->makeSalesOrder());

        $afterAll = $repository->countByStatus();
        $afterOwn = $repository->countByStatus($this->salesUserId);

        self::assertArrayHasKey('Cancelled', $afterAll, 'seluruh status harus selalu ada di hasil, termasuk yang kebetulan 0');
        self::assertSame($beforeAll['Draft'] + 1, $afterAll['Draft']);
        self::assertSame($beforeOwn['Draft'] + 1, $afterOwn['Draft'], 'countByStatus(createdBy) harus scoping kepemilikan Sales (SS1.2)');
    }

    public function testListForReportFiltersByCreatedAtDateRange(): void
    {
        $repository = new MySqlSalesOrderRepository($this->pdo);
        $saved = $repository->save($this->makeSalesOrder());
        $today = date('Y-m-d');

        $rows = $repository->listForReport($today, $today);

        self::assertNotEmpty(array_filter($rows, static fn (SalesOrder $so): bool => $so->id === $saved->id));
        self::assertSame([], $repository->listForReport('2020-01-01', '2020-01-01'));
    }
}
