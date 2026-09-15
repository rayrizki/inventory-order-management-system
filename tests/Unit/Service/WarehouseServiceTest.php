<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Warehouse;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\InMemoryWarehouseRepository;
use App\Service\WarehouseService;
use PHPUnit\Framework\TestCase;

final class WarehouseServiceTest extends TestCase
{
    public function testCreateWarehouseSucceedsWithValidNameAndDefaultsToActive(): void
    {
        $service = new WarehouseService(new InMemoryWarehouseRepository());

        $warehouse = $service->createWarehouse('Gudang Pusat Jakarta', 'Jakarta');

        self::assertNotNull($warehouse->id);
        self::assertSame('Gudang Pusat Jakarta', $warehouse->name);
        self::assertTrue($warehouse->isActive);
    }

    public function testCreateWarehouseTrimsWhitespaceAndBlankLocationBecomesNull(): void
    {
        $service = new WarehouseService(new InMemoryWarehouseRepository());

        $warehouse = $service->createWarehouse('  Gudang Pusat  ', '   ');

        self::assertSame('Gudang Pusat', $warehouse->name);
        self::assertNull($warehouse->location);
    }

    public function testCreateWarehouseRejectsEmptyName(): void
    {
        $service = new WarehouseService(new InMemoryWarehouseRepository());

        $this->expectException(ValidationException::class);

        $service->createWarehouse('   ', 'Jakarta');
    }

    public function testUpdateWarehouseThrowsNotFoundForUnknownId(): void
    {
        $service = new WarehouseService(new InMemoryWarehouseRepository());

        $this->expectException(NotFoundException::class);

        $service->updateWarehouse(999, 'Nama baru', null);
    }

    public function testUpdateWarehousePreservesActiveStatus(): void
    {
        $repository = new InMemoryWarehouseRepository();
        $service = new WarehouseService($repository);
        $created = $service->createWarehouse('Gudang Pusat', null);
        $service->setActive($created->id, false);

        $updated = $service->updateWarehouse($created->id, 'Gudang Pusat Baru', 'Bandung');

        self::assertSame('Gudang Pusat Baru', $updated->name);
        self::assertSame('Bandung', $updated->location);
        self::assertFalse($updated->isActive, 'update tidak boleh diam-diam mengaktifkan kembali gudang yang nonaktif');
    }

    public function testSetActiveTogglesStatus(): void
    {
        $repository = new InMemoryWarehouseRepository();
        $service = new WarehouseService($repository);
        $created = $service->createWarehouse('Gudang Pusat', null);

        $service->setActive($created->id, false);

        self::assertFalse($service->getWarehouseById($created->id)->isActive);
    }

    public function testSetActiveThrowsNotFoundForUnknownId(): void
    {
        $service = new WarehouseService(new InMemoryWarehouseRepository());

        $this->expectException(NotFoundException::class);

        $service->setActive(999, false);
    }

    public function testListWarehousesFiltersByActiveStatus(): void
    {
        $repository = new InMemoryWarehouseRepository([
            new Warehouse(1, 'Gudang Aktif', null, true),
            new Warehouse(2, 'Gudang Nonaktif', null, false),
        ]);
        $service = new WarehouseService($repository);

        $activeOnly = $service->listWarehouses(isActive: true);

        self::assertCount(1, $activeOnly);
        self::assertSame('Gudang Aktif', $activeOnly[0]->name);
    }
}
