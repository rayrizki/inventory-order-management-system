<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\MySqlCategoryRepository;
use App\Repository\MySqlProductRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Menyentuh MySQL sungguhan (TEST-02). Setiap baris yang dibuat test ini
 * dihapus lagi di tearDown supaya tidak mencemari data demo (§7.1) di
 * database bersama. Butuh satu kategori nyata (FK category_id NOT NULL) -
 * dibuat sendiri di setUp, bukan mengandalkan data seed yang bisa berubah.
 */
final class MySqlProductRepositoryTest extends TestCase
{
    private PDO $pdo;

    private int $categoryId;

    private ?int $createdId = null;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../config/database.php';
        $this->pdo = createPdoConnection();

        $categoryRepository = new MySqlCategoryRepository($this->pdo);
        $category = $categoryRepository->save(new Category(null, 'Test Kategori Produk ZZZ', null));
        $this->categoryId = $category->id;
    }

    protected function tearDown(): void
    {
        if ($this->createdId !== null) {
            // product_stock tidak punya ON DELETE CASCADE ke products (lihat
            // schema-and-seed.sql) - dihapus dulu di sini (no-op kalau tidak
            // pernah ada baris stok), baru products, supaya FK tidak gagal
            // untuk test yang menyisipkan baris stok (mis. sumInventoryValue()).
            $this->pdo->prepare('DELETE FROM product_stock WHERE product_id = :id')->execute(['id' => $this->createdId]);
            $statement = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
            $statement->execute(['id' => $this->createdId]);
        }

        $statement = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
        $statement->execute(['id' => $this->categoryId]);
    }

    public function testSaveInsertsNewProductAsActiveByDefault(): void
    {
        $repository = new MySqlProductRepository($this->pdo);

        $saved = $repository->save(new Product(null, 'TEST-SKU-ZZZ', 'Test Produk Integrasi', $this->categoryId, 'pcs', 10000, 15000, 5, null, true));
        $this->createdId = $saved->id;

        self::assertNotNull($saved->id);

        $found = $repository->findById($saved->id);
        self::assertNotNull($found);
        self::assertSame('TEST-SKU-ZZZ', $found->sku);
        self::assertSame(10000.0, $found->buyPrice);
        self::assertTrue($found->isActive);
    }

    public function testFindBySkuReturnsMatchingProduct(): void
    {
        $repository = new MySqlProductRepository($this->pdo);

        $saved = $repository->save(new Product(null, 'TEST-SKU-ZZZ', 'Test Produk', $this->categoryId, 'pcs', 10000, 15000, 5, null, true));
        $this->createdId = $saved->id;

        $found = $repository->findBySku('TEST-SKU-ZZZ');
        self::assertNotNull($found);
        self::assertSame($saved->id, $found->id);

        self::assertNull($repository->findBySku('SKU-TIDAK-ADA'));
    }

    public function testSaveUpdatesFieldsAndImagePathWithoutTouchingActiveStatus(): void
    {
        $repository = new MySqlProductRepository($this->pdo);

        $saved = $repository->save(new Product(null, 'TEST-SKU-ZZZ', 'Sebelum Update', $this->categoryId, 'pcs', 10000, 15000, 5, null, true));
        $this->createdId = $saved->id;
        $repository->setActive($saved->id, false);

        $repository->save(new Product($saved->id, 'TEST-SKU-ZZZ-2', 'Sesudah Update', $this->categoryId, 'box', 20000, 30000, 10, '/uploads/products/updated.jpg', true));

        $found = $repository->findById($saved->id);
        self::assertSame('TEST-SKU-ZZZ-2', $found->sku);
        self::assertSame('Sesudah Update', $found->name);
        self::assertSame(20000.0, $found->buyPrice);
        self::assertSame('/uploads/products/updated.jpg', $found->imagePath, 'PRD-01: image_path harus ikut ter-update - sebelumnya bug, UPDATE tidak pernah menyentuh kolom ini sama sekali');
        self::assertFalse($found->isActive, 'save() untuk UPDATE tidak boleh mengubah is_active - itu tugas setActive()');
    }

    public function testSetActiveTogglesStatus(): void
    {
        $repository = new MySqlProductRepository($this->pdo);

        $saved = $repository->save(new Product(null, 'TEST-SKU-ZZZ', 'Test Toggle', $this->categoryId, 'pcs', 10000, 15000, 5, null, true));
        $this->createdId = $saved->id;

        $repository->setActive($saved->id, false);
        self::assertFalse($repository->findById($saved->id)->isActive);

        $repository->setActive($saved->id, true);
        self::assertTrue($repository->findById($saved->id)->isActive);
    }

    public function testListAllFiltersBySearchCategoryAndActiveStatus(): void
    {
        $repository = new MySqlProductRepository($this->pdo);

        $saved = $repository->save(new Product(null, 'TEST-SKU-ZZZ', 'Test Produk Unik ZZZ', $this->categoryId, 'pcs', 10000, 15000, 5, null, true));
        $this->createdId = $saved->id;

        $bySku = array_map(
            static fn (Product $product): string => $product->name,
            $repository->listAll(search: 'TEST-SKU-ZZZ'),
        );
        self::assertContains('Test Produk Unik ZZZ', $bySku, 'search harus mencocokkan kolom sku, bukan cuma name');

        $byCategory = array_map(
            static fn (Product $product): string => $product->name,
            $repository->listAll(categoryId: $this->categoryId),
        );
        self::assertContains('Test Produk Unik ZZZ', $byCategory);

        $repository->setActive($saved->id, false);
        $activeOnly = array_map(
            static fn (Product $product): string => $product->name,
            $repository->listAll(search: 'Test Produk Unik ZZZ', isActive: true),
        );
        self::assertNotContains('Test Produk Unik ZZZ', $activeOnly, 'harus tersaring saat filter isActive=true karena baris ini dinonaktifkan');
    }

    /**
     * FIND-01: filter status stok (low/normal) - produk tanpa baris
     * product_stock SAMA SEKALI (belum pernah goods receipt) harus dianggap
     * stok 0, jadi otomatis masuk kategori 'low' selama reorder_point > 0.
     * Ini membuktikan LEFT JOIN + COALESCE bekerja, bukan cuma INNER JOIN
     * yang diam-diam mengecualikan produk tanpa stok dari kedua filter.
     */
    public function testListAllFiltersByStockStatus(): void
    {
        $repository = new MySqlProductRepository($this->pdo);

        $saved = $repository->save(new Product(null, 'TEST-SKU-ZZZ', 'Test Produk Tanpa Stok ZZZ', $this->categoryId, 'pcs', 10000, 15000, 5, null, true));
        $this->createdId = $saved->id;

        $lowNames = array_map(
            static fn (Product $product): string => $product->name,
            $repository->listAll(search: 'Test Produk Tanpa Stok ZZZ', stockStatus: 'low'),
        );
        self::assertContains('Test Produk Tanpa Stok ZZZ', $lowNames, 'produk tanpa baris product_stock harus dianggap stok 0 (LOW), bukan dikecualikan');

        $normalNames = array_map(
            static fn (Product $product): string => $product->name,
            $repository->listAll(search: 'Test Produk Tanpa Stok ZZZ', stockStatus: 'normal'),
        );
        self::assertNotContains('Test Produk Tanpa Stok ZZZ', $normalNames);

        // countAll() punya bentuk query BEDA dari listAll() (subquery
        // derived table yang cuma SELECT p.id, tanpa p.reorder_point) -
        // MySQL's functional-dependency exception untuk HAVING ternyata
        // cuma berlaku kalau kolomnya ADA di SELECT list (listAll() selalu
        // memilih reorder_point untuk hydrate(), jadi HAVING-nya kebetulan
        // valid meski reorder_point tidak dibungkus agregat) - countAll()
        // sempat gagal dengan error 1054 "Unknown column" sampai
        // reorder_point dibungkus MAX() di buildFilter(). Assert count()
        // di sini secara eksplisit supaya regresi ini tidak lolos lagi
        // tanpa terdeteksi test (listAll() saja tidak cukup untuk
        // membuktikan countAll() bekerja).
        self::assertSame(1, $repository->countAll(search: 'Test Produk Tanpa Stok ZZZ', stockStatus: 'low'));
        self::assertSame(0, $repository->countAll(search: 'Test Produk Tanpa Stok ZZZ', stockStatus: 'normal'));
    }

    /**
     * DASH-01. Delta before/after (bukan angka mutlak) karena product_stock
     * sudah berisi seed nyata (30 produk + 25 order, §7.1).
     */
    public function testSumInventoryValueReflectsQuantityTimesBuyPrice(): void
    {
        $repository = new MySqlProductRepository($this->pdo);
        $before = $repository->sumInventoryValue();

        $saved = $repository->save(new Product(null, 'TEST-SKU-ZZZ', 'Test Produk Nilai Inventori ZZZ', $this->categoryId, 'pcs', 10000, 15000, 5, null, true));
        $this->createdId = $saved->id;

        $warehouseId = (new \App\Repository\MySqlWarehouseRepository($this->pdo))->save(new \App\Entity\Warehouse(null, 'Test Gudang Nilai Inventori ZZZ', null, true))->id;
        $this->pdo->prepare('INSERT INTO product_stock (product_id, warehouse_id, quantity) VALUES (:product_id, :warehouse_id, :quantity)')
            ->execute(['product_id' => $saved->id, 'warehouse_id' => $warehouseId, 'quantity' => 7]);

        $after = $repository->sumInventoryValue();

        // buy_price 10000 * quantity 7 = 70000.
        self::assertSame(70000.0, $after - $before);

        $this->pdo->prepare('DELETE FROM product_stock WHERE warehouse_id = :id')->execute(['id' => $warehouseId]);
        $this->pdo->prepare('DELETE FROM warehouses WHERE id = :id')->execute(['id' => $warehouseId]);
    }
}
