<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Customer;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\InMemoryCustomerRepository;
use App\Service\CustomerService;
use PHPUnit\Framework\TestCase;

final class CustomerServiceTest extends TestCase
{
    public function testCreateCustomerSucceedsWithValidNameAndDefaultsToActive(): void
    {
        $service = new CustomerService(new InMemoryCustomerRepository());

        $customer = $service->createCustomer('Toko Maju Jaya', '021-1234567', 'Jakarta');

        self::assertNotNull($customer->id);
        self::assertSame('Toko Maju Jaya', $customer->name);
        self::assertTrue($customer->isActive);
    }

    public function testCreateCustomerTrimsWhitespaceAndBlankFieldsBecomeNull(): void
    {
        $service = new CustomerService(new InMemoryCustomerRepository());

        $customer = $service->createCustomer('  Toko Maju Jaya  ', '   ', '   ');

        self::assertSame('Toko Maju Jaya', $customer->name);
        self::assertNull($customer->contact);
        self::assertNull($customer->address);
    }

    public function testCreateCustomerRejectsEmptyName(): void
    {
        $service = new CustomerService(new InMemoryCustomerRepository());

        $this->expectException(ValidationException::class);

        $service->createCustomer('   ', '021-1234567', 'Jakarta');
    }

    public function testUpdateCustomerThrowsNotFoundForUnknownId(): void
    {
        $service = new CustomerService(new InMemoryCustomerRepository());

        $this->expectException(NotFoundException::class);

        $service->updateCustomer(999, 'Nama baru', null, null);
    }

    public function testUpdateCustomerPreservesActiveStatus(): void
    {
        $repository = new InMemoryCustomerRepository();
        $service = new CustomerService($repository);
        $created = $service->createCustomer('Toko Maju Jaya', null, null);
        $service->setActive($created->id, false);

        $updated = $service->updateCustomer($created->id, 'Toko Maju Jaya Baru', '021-9999999', 'Bandung');

        self::assertSame('Toko Maju Jaya Baru', $updated->name);
        self::assertSame('021-9999999', $updated->contact);
        self::assertFalse($updated->isActive, 'update tidak boleh diam-diam mengaktifkan kembali customer yang nonaktif');
    }

    public function testSetActiveTogglesStatus(): void
    {
        $repository = new InMemoryCustomerRepository();
        $service = new CustomerService($repository);
        $created = $service->createCustomer('Toko Maju Jaya', null, null);

        $service->setActive($created->id, false);

        self::assertFalse($service->getCustomerById($created->id)->isActive);
    }

    public function testSetActiveThrowsNotFoundForUnknownId(): void
    {
        $service = new CustomerService(new InMemoryCustomerRepository());

        $this->expectException(NotFoundException::class);

        $service->setActive(999, false);
    }

    public function testListCustomersFiltersByActiveStatus(): void
    {
        $repository = new InMemoryCustomerRepository([
            new Customer(1, 'Customer Aktif', null, null, true),
            new Customer(2, 'Customer Nonaktif', null, null, false),
        ]);
        $service = new CustomerService($repository);

        $activeOnly = $service->listCustomers(isActive: true);

        self::assertCount(1, $activeOnly);
        self::assertSame('Customer Aktif', $activeOnly[0]->name);
    }
}
