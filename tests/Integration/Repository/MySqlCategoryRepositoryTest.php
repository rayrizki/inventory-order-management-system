<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Entity\Category;
use App\Repository\MySqlCategoryRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Menyentuh MySQL sungguhan (TEST-02). Setiap baris yang dibuat test ini
 * dihapus lagi di tearDown supaya tidak mencemari data demo (§7.1) di
 * database bersama.
 */
final class MySqlCategoryRepositoryTest extends TestCase
{
    private PDO $pdo;

    private ?int $createdId = null;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../config/database.php';
        $this->pdo = createPdoConnection();
    }

    protected function tearDown(): void
    {
        if ($this->createdId !== null) {
            $statement = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
            $statement->execute(['id' => $this->createdId]);
        }
    }

    public function testSaveInsertsNewCategoryAndAssignsId(): void
    {
        $repository = new MySqlCategoryRepository($this->pdo);

        $saved = $repository->save(new Category(null, 'Test Kategori Integrasi', 'Deskripsi test'));
        $this->createdId = $saved->id;

        self::assertNotNull($saved->id);

        $found = $repository->findById($saved->id);
        self::assertNotNull($found);
        self::assertSame('Test Kategori Integrasi', $found->name);
    }

    public function testSaveUpdatesExistingCategory(): void
    {
        $repository = new MySqlCategoryRepository($this->pdo);

        $saved = $repository->save(new Category(null, 'Sebelum Update', null));
        $this->createdId = $saved->id;

        $repository->save(new Category($saved->id, 'Sesudah Update', 'Ada deskripsi'));

        $found = $repository->findById($saved->id);
        self::assertSame('Sesudah Update', $found->name);
        self::assertSame('Ada deskripsi', $found->description);
    }

    public function testListAllIncludesNewlyCreatedCategory(): void
    {
        $repository = new MySqlCategoryRepository($this->pdo);

        $saved = $repository->save(new Category(null, 'Test Kategori Unik ZZZ', null));
        $this->createdId = $saved->id;

        // Cari lewat nama, bukan listAll() polos - tabel categories sudah
        // berisi lebih dari satu halaman (default LIMIT 10), jadi baris baru
        // ini tidak akan ikut ke halaman pertama kalau tidak difilter.
        $names = array_map(
            static fn (Category $category): string => $category->name,
            $repository->listAll(search: 'Test Kategori Unik ZZZ'),
        );

        self::assertContains('Test Kategori Unik ZZZ', $names);
    }
}
