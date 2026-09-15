<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Entity\Warehouse;
use App\Repository\MySqlWarehouseRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Menyentuh MySQL sungguhan (TEST-02). Setiap baris yang dibuat test ini
 * dihapus lagi di tearDown supaya tidak mencemari data demo (§7.1) di
 * database bersama.
 */
final class MySqlWarehouseRepositoryTest extends TestCase
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
            $statement = $this->pdo->prepare('DELETE FROM warehouses WHERE id = :id');
            $statement->execute(['id' => $this->createdId]);
        }
    }

    public function testSaveInsertsNewWarehouseAsActiveByDefault(): void
    {
        $repository = new MySqlWarehouseRepository($this->pdo);

        $saved = $repository->save(new Warehouse(null, 'Test Gudang Integrasi', 'Jakarta', true));
        $this->createdId = $saved->id;

        self::assertNotNull($saved->id);

        $found = $repository->findById($saved->id);
        self::assertNotNull($found);
        self::assertSame('Test Gudang Integrasi', $found->name);
        self::assertTrue($found->isActive);
    }

    public function testSaveUpdatesNameAndLocationWithoutTouchingActiveStatus(): void
    {
        $repository = new MySqlWarehouseRepository($this->pdo);

        $saved = $repository->save(new Warehouse(null, 'Sebelum Update', null, true));
        $this->createdId = $saved->id;
        $repository->setActive($saved->id, false);

        $repository->save(new Warehouse($saved->id, 'Sesudah Update', 'Surabaya', true));

        $found = $repository->findById($saved->id);
        self::assertSame('Sesudah Update', $found->name);
        self::assertSame('Surabaya', $found->location);
        self::assertFalse($found->isActive, 'save() untuk UPDATE tidak boleh mengubah is_active - itu tugas setActive()');
    }

    public function testSetActiveTogglesStatus(): void
    {
        $repository = new MySqlWarehouseRepository($this->pdo);

        $saved = $repository->save(new Warehouse(null, 'Test Toggle Aktif', null, true));
        $this->createdId = $saved->id;

        $repository->setActive($saved->id, false);
        self::assertFalse($repository->findById($saved->id)->isActive);

        $repository->setActive($saved->id, true);
        self::assertTrue($repository->findById($saved->id)->isActive);
    }

    public function testListAllFiltersBySearchAndActiveStatus(): void
    {
        $repository = new MySqlWarehouseRepository($this->pdo);

        $saved = $repository->save(new Warehouse(null, 'Test Gudang Unik ZZZ', 'Kota Unik ZZZ', true));
        $this->createdId = $saved->id;
        $repository->setActive($saved->id, false);

        $namesActiveOnly = array_map(
            static fn (Warehouse $warehouse): string => $warehouse->name,
            $repository->listAll(search: 'Test Gudang Unik ZZZ', isActive: true),
        );
        self::assertNotContains('Test Gudang Unik ZZZ', $namesActiveOnly, 'harus tersaring saat filter isActive=true karena baris ini dinonaktifkan');

        $namesInactiveOnly = array_map(
            static fn (Warehouse $warehouse): string => $warehouse->name,
            $repository->listAll(search: 'Test Gudang Unik ZZZ', isActive: false),
        );
        self::assertContains('Test Gudang Unik ZZZ', $namesInactiveOnly);

        $namesByLocation = array_map(
            static fn (Warehouse $warehouse): string => $warehouse->name,
            $repository->listAll(search: 'Kota Unik ZZZ'),
        );
        self::assertContains('Test Gudang Unik ZZZ', $namesByLocation, 'search harus mencocokkan kolom location, bukan cuma name');
    }
}
