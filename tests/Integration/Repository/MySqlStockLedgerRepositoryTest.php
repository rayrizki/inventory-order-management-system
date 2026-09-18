<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\StockLedgerEntry;
use App\Entity\StockMovementType;
use App\Entity\Warehouse;
use App\Repository\MySqlCategoryRepository;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlStockLedgerRepository;
use App\Repository\MySqlWarehouseRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Menyentuh MySQL sungguhan (TEST-02). Butuh baris Product+Warehouse nyata
 * (FK NOT NULL) - dibuat sendiri di setUp, dihapus lagi di tearDown.
 */
final class MySqlStockLedgerRepositoryTest extends TestCase
{
    private PDO $pdo;

    private int $categoryId;

    private int $productId;

    private int $warehouseId;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../config/database.php';
        $this->pdo = createPdoConnection();

        $this->categoryId = (new MySqlCategoryRepository($this->pdo))->save(new Category(null, 'Test Kategori Ledger ZZZ', null))->id;
        $this->productId = (new MySqlProductRepository($this->pdo))->save(
            new Product(null, 'TEST-LEDGER-SKU-ZZZ', 'Test Produk Ledger', $this->categoryId, 'pcs', 10000, 15000, 5, null, true)
        )->id;
        $this->warehouseId = (new MySqlWarehouseRepository($this->pdo))->save(new Warehouse(null, 'Test Gudang Ledger ZZZ', null, true))->id;
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM stock_ledger WHERE product_id = :product_id')->execute(['product_id' => $this->productId]);
        $this->pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $this->productId]);
        $this->pdo->prepare('DELETE FROM warehouses WHERE id = :id')->execute(['id' => $this->warehouseId]);
        $this->pdo->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $this->categoryId]);
    }

    public function testRecordInsertsEntryAndAssignsId(): void
    {
        $repository = new MySqlStockLedgerRepository($this->pdo);

        $saved = $repository->record(new StockLedgerEntry(null, $this->productId, $this->warehouseId, StockMovementType::Receipt, 10, 'purchase_order', 999, 1, null));

        self::assertNotNull($saved->id);
    }

    public function testFindByReferenceReturnsOnlyMatchingEntriesInOrder(): void
    {
        $repository = new MySqlStockLedgerRepository($this->pdo);
        $repository->record(new StockLedgerEntry(null, $this->productId, $this->warehouseId, StockMovementType::Receipt, 5, 'purchase_order', 111, 1, null));
        $repository->record(new StockLedgerEntry(null, $this->productId, $this->warehouseId, StockMovementType::Receipt, 3, 'purchase_order', 111, 1, null));
        $repository->record(new StockLedgerEntry(null, $this->productId, $this->warehouseId, StockMovementType::Receipt, 99, 'purchase_order', 222, 1, null));

        $entries = $repository->findByReference('purchase_order', 111);

        self::assertCount(2, $entries);
        self::assertSame(5, $entries[0]->quantity);
        self::assertSame(3, $entries[1]->quantity);
        self::assertNotNull($entries[0]->createdAt);
    }
}
