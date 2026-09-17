<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Warehouse;
use App\Repository\MySqlCategoryRepository;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlProductStockRepository;
use App\Repository\MySqlWarehouseRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Menyentuh MySQL sungguhan (TEST-02). product_stock butuh baris Product
 * dan Warehouse nyata (FK NOT NULL) - dibuat sendiri di setUp, dihapus lagi
 * di tearDown (urutan: stock -> product/warehouse -> category) supaya tidak
 * mencemari data demo (§7.1).
 */
final class MySqlProductStockRepositoryTest extends TestCase
{
    private PDO $pdo;

    private int $categoryId;

    private int $productId;

    private int $warehouseId;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../config/database.php';
        $this->pdo = createPdoConnection();

        $category = (new MySqlCategoryRepository($this->pdo))->save(new Category(null, 'Test Kategori Stok ZZZ', null));
        $this->categoryId = $category->id;

        $product = (new MySqlProductRepository($this->pdo))->save(
            new Product(null, 'TEST-STOCK-ZZZ', 'Test Produk Stok', $this->categoryId, 'pcs', 1000, 2000, 5, null, true)
        );
        $this->productId = $product->id;

        $warehouse = (new MySqlWarehouseRepository($this->pdo))->save(new Warehouse(null, 'Test Gudang Stok ZZZ', null, true));
        $this->warehouseId = $warehouse->id;
    }

    protected function tearDown(): void
    {
        $statement = $this->pdo->prepare('DELETE FROM product_stock WHERE product_id = :product_id');
        $statement->execute(['product_id' => $this->productId]);

        $this->pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $this->productId]);
        $this->pdo->prepare('DELETE FROM warehouses WHERE id = :id')->execute(['id' => $this->warehouseId]);
        $this->pdo->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $this->categoryId]);
    }

    public function testFindByProductReturnsRowsForThatProductOnly(): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO product_stock (product_id, warehouse_id, quantity) VALUES (:product_id, :warehouse_id, :quantity)'
        );
        $insert->execute(['product_id' => $this->productId, 'warehouse_id' => $this->warehouseId, 'quantity' => 25]);

        $repository = new MySqlProductStockRepository($this->pdo);
        $rows = $repository->findByProduct($this->productId);

        self::assertCount(1, $rows);
        self::assertSame($this->productId, $rows[0]->productId);
        self::assertSame($this->warehouseId, $rows[0]->warehouseId);
        self::assertSame(25, $rows[0]->quantity);
    }

    public function testFindByProductReturnsEmptyArrayWhenNoStockRowExists(): void
    {
        $repository = new MySqlProductStockRepository($this->pdo);

        self::assertSame([], $repository->findByProduct($this->productId));
    }
}
