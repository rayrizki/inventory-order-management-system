<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Entity\Customer;
use App\Repository\MySqlCustomerRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Menyentuh MySQL sungguhan (TEST-02). Setiap baris yang dibuat test ini
 * dihapus lagi di tearDown supaya tidak mencemari data demo (§7.1) di
 * database bersama.
 */
final class MySqlCustomerRepositoryTest extends TestCase
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
            $statement = $this->pdo->prepare('DELETE FROM customers WHERE id = :id');
            $statement->execute(['id' => $this->createdId]);
        }
    }

    public function testSaveInsertsNewCustomerAsActiveByDefault(): void
    {
        $repository = new MySqlCustomerRepository($this->pdo);

        $saved = $repository->save(new Customer(null, 'Test Customer Integrasi', '021-1234567', 'Jakarta', true));
        $this->createdId = $saved->id;

        self::assertNotNull($saved->id);

        $found = $repository->findById($saved->id);
        self::assertNotNull($found);
        self::assertSame('Test Customer Integrasi', $found->name);
        self::assertTrue($found->isActive);
    }

    public function testSaveUpdatesFieldsWithoutTouchingActiveStatus(): void
    {
        $repository = new MySqlCustomerRepository($this->pdo);

        $saved = $repository->save(new Customer(null, 'Sebelum Update', null, null, true));
        $this->createdId = $saved->id;
        $repository->setActive($saved->id, false);

        $repository->save(new Customer($saved->id, 'Sesudah Update', '021-9999999', 'Surabaya', true));

        $found = $repository->findById($saved->id);
        self::assertSame('Sesudah Update', $found->name);
        self::assertSame('021-9999999', $found->contact);
        self::assertSame('Surabaya', $found->address);
        self::assertFalse($found->isActive, 'save() untuk UPDATE tidak boleh mengubah is_active - itu tugas setActive()');
    }

    public function testSetActiveTogglesStatus(): void
    {
        $repository = new MySqlCustomerRepository($this->pdo);

        $saved = $repository->save(new Customer(null, 'Test Toggle Aktif', null, null, true));
        $this->createdId = $saved->id;

        $repository->setActive($saved->id, false);
        self::assertFalse($repository->findById($saved->id)->isActive);

        $repository->setActive($saved->id, true);
        self::assertTrue($repository->findById($saved->id)->isActive);
    }

    public function testListAllFiltersBySearchAcrossNameContactAndAddress(): void
    {
        $repository = new MySqlCustomerRepository($this->pdo);

        $saved = $repository->save(new Customer(null, 'Test Customer Unik ZZZ', '0800-UNIK-ZZZ', 'Kota Unik ZZZ', true));
        $this->createdId = $saved->id;

        $byName = array_map(
            static fn (Customer $customer): string => $customer->name,
            $repository->listAll(search: 'Test Customer Unik ZZZ'),
        );
        self::assertContains('Test Customer Unik ZZZ', $byName);

        $byContact = array_map(
            static fn (Customer $customer): string => $customer->name,
            $repository->listAll(search: '0800-UNIK-ZZZ'),
        );
        self::assertContains('Test Customer Unik ZZZ', $byContact, 'search harus mencocokkan kolom contact');

        $byAddress = array_map(
            static fn (Customer $customer): string => $customer->name,
            $repository->listAll(search: 'Kota Unik ZZZ'),
        );
        self::assertContains('Test Customer Unik ZZZ', $byAddress, 'search harus mencocokkan kolom address');
    }
}
