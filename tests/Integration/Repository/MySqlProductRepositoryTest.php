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

    public function testSaveUpdatesFieldsWithoutTouchingActiveStatusOrImagePath(): void
    {
        $repository = new MySqlProductRepository($this->pdo);

        $saved = $repository->save(new Product(null, 'TEST-SKU-ZZZ', 'Sebelum Update', $this->categoryId, 'pcs', 10000, 15000, 5, null, true));
        $this->createdId = $saved->id;
        $repository->setActive($saved->id, false);

        $repository->save(new Product($saved->id, 'TEST-SKU-ZZZ-2', 'Sesudah Update', $this->categoryId, 'box', 20000, 30000, 10, null, true));

        $found = $repository->findById($saved->id);
        self::assertSame('TEST-SKU-ZZZ-2', $found->sku);
        self::assertSame('Sesudah Update', $found->name);
        self::assertSame(20000.0, $found->buyPrice);
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
}
