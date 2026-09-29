<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\SalesOrderStatus;
use App\Entity\Warehouse;
use App\Exception\ConflictException;
use App\Repository\MySqlCategoryRepository;
use App\Repository\MySqlCustomerRepository;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlProductStockRepository;
use App\Repository\MySqlSalesOrderRepository;
use App\Repository\MySqlStockLedgerRepository;
use App\Repository\MySqlWarehouseRepository;
use App\Service\GoodsIssueService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * TEST-02 bukti eksplisit brief bagian 6: "goods issue kedua ditolak ketika
 * stok sudah habis oleh goods issue pertama". Menyentuh MySQL sungguhan
 * lewat transaksi nyata (beginTransaction/commit/rollBack di
 * GoodsIssueService::issue(), ARCH-02) - bukan fake repository, supaya
 * benar-benar membuktikan decrementIfSufficient() mencegah oversell dan
 * bahwa kegagalan salah satu item me-rollback SELURUH transaksi (all-or-
 * nothing, beda dari goods receipt PO-01 yang boleh parsial).
 */
final class GoodsIssueServiceTest extends TestCase
{
    private PDO $pdo;

    private int $categoryId;

    private int $productId;

    private int $secondProductId;

    private int $warehouseId;

    private int $customerId;

    private int $salesUserId;

    private int $warehouseStaffId;

    private MySqlSalesOrderRepository $salesOrders;

    private MySqlProductStockRepository $stocks;

    private MySqlStockLedgerRepository $ledger;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../config/database.php';
        $this->pdo = createPdoConnection();

        $this->categoryId = (new MySqlCategoryRepository($this->pdo))->save(new Category(null, 'Test Kategori GI ZZZ', null))->id;
        $this->productId = (new MySqlProductRepository($this->pdo))->save(
            new Product(null, 'TEST-GI-SKU-ZZZ', 'Test Produk Goods Issue', $this->categoryId, 'pcs', 10000, 15000, 5, null, true)
        )->id;
        $this->secondProductId = (new MySqlProductRepository($this->pdo))->save(
            new Product(null, 'TEST-GI-SKU2-ZZZ', 'Test Produk Goods Issue Dua', $this->categoryId, 'pcs', 10000, 15000, 5, null, true)
        )->id;
        $this->warehouseId = (new MySqlWarehouseRepository($this->pdo))->save(new Warehouse(null, 'Test Gudang GI ZZZ', null, true))->id;
        $this->customerId = (new MySqlCustomerRepository($this->pdo))->save(new Customer(null, 'Test Customer GI ZZZ', null, null, true))->id;

        $statement = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
        $statement->execute(['email' => 'sales1@iom.test']);
        $this->salesUserId = (int) $statement->fetchColumn();
        $statement->execute(['email' => 'warehouse1@iom.test']);
        $this->warehouseStaffId = (int) $statement->fetchColumn();

        $this->salesOrders = new MySqlSalesOrderRepository($this->pdo);
        $this->stocks = new MySqlProductStockRepository($this->pdo);
        $this->ledger = new MySqlStockLedgerRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM stock_ledger WHERE product_id IN (:p1, :p2)')
            ->execute(['p1' => $this->productId, 'p2' => $this->secondProductId]);
        $this->pdo->prepare('DELETE FROM product_stock WHERE product_id IN (:p1, :p2)')
            ->execute(['p1' => $this->productId, 'p2' => $this->secondProductId]);
        $this->pdo->prepare('DELETE soi FROM sales_order_items soi JOIN sales_orders so ON so.id = soi.sales_order_id WHERE so.customer_id = :customer_id')
            ->execute(['customer_id' => $this->customerId]);
        $this->pdo->prepare('DELETE FROM sales_orders WHERE customer_id = :customer_id')->execute(['customer_id' => $this->customerId]);
        $this->pdo->prepare('DELETE FROM products WHERE id IN (:p1, :p2)')
            ->execute(['p1' => $this->productId, 'p2' => $this->secondProductId]);
        $this->pdo->prepare('DELETE FROM warehouses WHERE id = :id')->execute(['id' => $this->warehouseId]);
        $this->pdo->prepare('DELETE FROM customers WHERE id = :id')->execute(['id' => $this->customerId]);
        $this->pdo->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $this->categoryId]);
    }

    /**
     * @param SalesOrderItem[]|null $items
     */
    private function createApprovedSalesOrder(?array $items = null): SalesOrder
    {
        $items ??= [new SalesOrderItem(null, null, $this->productId, 10, 15000)];

        $saved = $this->salesOrders->save(new SalesOrder(
            null, $this->customerId, $this->warehouseId, SalesOrderStatus::Draft, $this->salesUserId, null, null, $items,
        ));
        $this->salesOrders->transitionStatus($saved->id, [SalesOrderStatus::Draft], SalesOrderStatus::PendingApproval);
        $this->salesOrders->approve($saved->id, $this->salesUserId);

        return $this->salesOrders->findById($saved->id);
    }

    public function testIssueDecrementsStockWritesLedgerAndSetsFulfilledStatus(): void
    {
        $this->stocks->incrementQuantity($this->productId, $this->warehouseId, 10);
        $so = $this->createApprovedSalesOrder();
        $service = new GoodsIssueService($this->salesOrders, $this->stocks, $this->ledger, $this->pdo);

        $updated = $service->issue($so->id, performedBy: $this->warehouseStaffId);

        self::assertSame(SalesOrderStatus::Fulfilled, $updated->status);

        $stockRows = $this->stocks->findByProduct($this->productId);
        self::assertCount(1, $stockRows);
        self::assertSame(0, $stockRows[0]->quantity, 'goods issue harus benar-benar mengurangi ProductStock end-to-end');

        $ledgerEntries = $this->ledger->findByReference('sales_order', $so->id);
        self::assertCount(1, $ledgerEntries);
        self::assertSame(10, $ledgerEntries[0]->quantity);
        self::assertSame(\App\Entity\StockMovementType::Issue, $ledgerEntries[0]->movementType);
    }

    /**
     * ARCH-02 bukti utama: dua SO Approved memperebutkan stok yang sama.
     * Goods issue pertama menghabiskan stok; goods issue kedua (SO terpisah)
     * HARUS ditolak dengan ConflictException, bukan malah membuat stok
     * menjadi negatif.
     */
    public function testSecondGoodsIssueRejectedWhenStockAlreadyDepletedByFirst(): void
    {
        $this->stocks->incrementQuantity($this->productId, $this->warehouseId, 10);

        $firstSo = $this->createApprovedSalesOrder([new SalesOrderItem(null, null, $this->productId, 10, 15000)]);
        $secondSo = $this->createApprovedSalesOrder([new SalesOrderItem(null, null, $this->productId, 5, 15000)]);

        $service = new GoodsIssueService($this->salesOrders, $this->stocks, $this->ledger, $this->pdo);

        $service->issue($firstSo->id, performedBy: $this->warehouseStaffId);

        $this->expectException(ConflictException::class);

        try {
            $service->issue($secondSo->id, performedBy: $this->warehouseStaffId);
        } finally {
            $stockRows = $this->stocks->findByProduct($this->productId);
            self::assertSame(0, $stockRows[0]->quantity, 'stok tidak boleh menjadi negatif akibat goods issue kedua yang ditolak');
            self::assertSame(SalesOrderStatus::Approved, $this->salesOrders->findById($secondSo->id)->status, 'status SO kedua tidak boleh berubah jadi Fulfilled saat ditolak');
        }
    }

    /**
     * ARCH-02, sisi yang berbeda dari test oversell di atas: di sini stok
     * SELALU cukup, jadi guard `quantity >= qty` tidak akan pernah menolak
     * apa pun. Yang dicegah adalah SATU SO diproses dua kali (double-fulfil).
     *
     * Skenario konkuren ditiru secara terkontrol lewat DUA koneksi PDO
     * terpisah - simulasi thread sungguhan tidak diwajibkan brief. Koneksi B
     * membaca SO selagi statusnya masih Approved (jadi pemeriksaan status
     * biasa akan meloloskannya), koneksi A menyelesaikan goods issue sampai
     * commit, lalu B baru bertindak atas hasil bacaan yang sudah basi itu.
     * Tanpa klaim status bersyarat, langkah terakhir B akan lolos dan
     * mengeluarkan stok untuk kedua kalinya.
     */
    public function testConcurrentIssueOfSameSalesOrderIsRejectedForTheStaleReader(): void
    {
        $this->stocks->incrementQuantity($this->productId, $this->warehouseId, 100);
        $so = $this->createApprovedSalesOrder([new SalesOrderItem(null, null, $this->productId, 10, 15000)]);

        require_once __DIR__ . '/../../../config/database.php';
        $pdoB = createPdoConnection();
        $salesOrdersB = new MySqlSalesOrderRepository($pdoB);

        // B membaca lebih dulu: dari sudut pandangnya SO ini layak diproses.
        self::assertSame(SalesOrderStatus::Approved, $salesOrdersB->findById($so->id)->status);

        // A menyelesaikan goods issue-nya sampai commit.
        (new GoodsIssueService($this->salesOrders, $this->stocks, $this->ledger, $this->pdo))
            ->issue($so->id, performedBy: $this->warehouseStaffId);

        // B lanjut atas bacaan basi tadi - klaim status harus gagal.
        $claimedByB = $salesOrdersB->transitionStatus($so->id, [SalesOrderStatus::Approved], SalesOrderStatus::Fulfilled);

        self::assertFalse($claimedByB, 'request kedua tidak boleh berhasil mengklaim SO yang sudah dipenuhi request pertama');

        $stockRows = $this->stocks->findByProduct($this->productId);
        self::assertSame(90, $stockRows[0]->quantity, 'stok hanya boleh berkurang sekali untuk satu SO');
        self::assertCount(1, $this->ledger->findByReference('sales_order', $so->id), 'hanya boleh ada satu baris ledger untuk satu SO');
    }

    /**
     * All-or-nothing: SO dengan dua item, item kedua stoknya tidak cukup -
     * SELURUH transaksi (termasuk item pertama yang sebenarnya cukup) harus
     * rollback, tidak ada goods issue separuh jalan.
     */
    public function testIssueRollsBackEntirelyWhenAnyItemHasInsufficientStock(): void
    {
        $this->stocks->incrementQuantity($this->productId, $this->warehouseId, 10);
        $this->stocks->incrementQuantity($this->secondProductId, $this->warehouseId, 2);

        $so = $this->createApprovedSalesOrder([
            new SalesOrderItem(null, null, $this->productId, 10, 15000),
            new SalesOrderItem(null, null, $this->secondProductId, 5, 15000),
        ]);
        $service = new GoodsIssueService($this->salesOrders, $this->stocks, $this->ledger, $this->pdo);

        try {
            $service->issue($so->id, performedBy: $this->warehouseStaffId);
            self::fail('Expected ConflictException');
        } catch (ConflictException) {
            // diharapkan
        }

        $firstProductStock = $this->stocks->findByProduct($this->productId);
        self::assertSame(10, $firstProductStock[0]->quantity, 'item pertama yang sebenarnya cukup harus ikut rollback, tidak boleh terlanjur berkurang');

        self::assertSame([], $this->ledger->findByReference('sales_order', $so->id), 'tidak boleh ada ledger sama sekali saat goods issue ditolak (all-or-nothing)');
        self::assertSame(SalesOrderStatus::Approved, $this->salesOrders->findById($so->id)->status, 'status SO tidak boleh berubah saat goods issue ditolak');
    }
}
