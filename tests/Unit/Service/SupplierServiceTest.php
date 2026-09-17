<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Supplier;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\InMemorySupplierRepository;
use App\Service\SupplierService;
use PHPUnit\Framework\TestCase;

final class SupplierServiceTest extends TestCase
{
    public function testCreateSupplierSucceedsWithValidNameAndDefaultsToActive(): void
    {
        $service = new SupplierService(new InMemorySupplierRepository());

        $supplier = $service->createSupplier('PT Sumber Elektronik', '021-1234567', 'Jakarta');

        self::assertNotNull($supplier->id);
        self::assertSame('PT Sumber Elektronik', $supplier->name);
        self::assertTrue($supplier->isActive);
    }

    public function testCreateSupplierTrimsWhitespaceAndBlankFieldsBecomeNull(): void
    {
        $service = new SupplierService(new InMemorySupplierRepository());

        $supplier = $service->createSupplier('  PT Sumber Elektronik  ', '   ', '   ');

        self::assertSame('PT Sumber Elektronik', $supplier->name);
        self::assertNull($supplier->contact);
        self::assertNull($supplier->address);
    }

    public function testCreateSupplierRejectsEmptyName(): void
    {
        $service = new SupplierService(new InMemorySupplierRepository());

        $this->expectException(ValidationException::class);

        $service->createSupplier('   ', '021-1234567', 'Jakarta');
    }

    public function testUpdateSupplierThrowsNotFoundForUnknownId(): void
    {
        $service = new SupplierService(new InMemorySupplierRepository());

        $this->expectException(NotFoundException::class);

        $service->updateSupplier(999, 'Nama baru', null, null);
    }

    public function testUpdateSupplierPreservesActiveStatus(): void
    {
        $repository = new InMemorySupplierRepository();
        $service = new SupplierService($repository);
        $created = $service->createSupplier('PT Sumber Elektronik', null, null);
        $service->setActive($created->id, false);

        $updated = $service->updateSupplier($created->id, 'PT Sumber Elektronik Baru', '021-9999999', 'Bandung');

        self::assertSame('PT Sumber Elektronik Baru', $updated->name);
        self::assertSame('021-9999999', $updated->contact);
        self::assertFalse($updated->isActive, 'update tidak boleh diam-diam mengaktifkan kembali supplier yang nonaktif');
    }

    public function testSetActiveTogglesStatus(): void
    {
        $repository = new InMemorySupplierRepository();
        $service = new SupplierService($repository);
        $created = $service->createSupplier('PT Sumber Elektronik', null, null);

        $service->setActive($created->id, false);

        self::assertFalse($service->getSupplierById($created->id)->isActive);
    }

    public function testSetActiveThrowsNotFoundForUnknownId(): void
    {
        $service = new SupplierService(new InMemorySupplierRepository());

        $this->expectException(NotFoundException::class);

        $service->setActive(999, false);
    }

    public function testListSuppliersFiltersByActiveStatus(): void
    {
        $repository = new InMemorySupplierRepository([
            new Supplier(1, 'Supplier Aktif', null, null, true),
            new Supplier(2, 'Supplier Nonaktif', null, null, false),
        ]);
        $service = new SupplierService($repository);

        $activeOnly = $service->listSuppliers(isActive: true);

        self::assertCount(1, $activeOnly);
        self::assertSame('Supplier Aktif', $activeOnly[0]->name);
    }
}
