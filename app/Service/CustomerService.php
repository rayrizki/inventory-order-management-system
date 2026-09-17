<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\CustomerRepositoryInterface;

final class CustomerService
{
    public const PER_PAGE = 10;

    public function __construct(private readonly CustomerRepositoryInterface $customers)
    {
    }

    /**
     * @return Customer[]
     */
    public function listCustomers(
        ?string $search = null,
        ?bool $isActive = null,
        int $page = 1,
        int $perPage = self::PER_PAGE,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $perPage = max(1, $perPage);
        $offset = (max(1, $page) - 1) * $perPage;

        return $this->customers->listAll($this->normalizeSearch($search), $isActive, $perPage, $offset, $sortBy, $sortDir);
    }

    public function countCustomers(?string $search = null, ?bool $isActive = null): int
    {
        return $this->customers->countAll($this->normalizeSearch($search), $isActive);
    }

    public function getCustomerById(int $id): Customer
    {
        $customer = $this->customers->findById($id);

        if ($customer === null) {
            throw new NotFoundException();
        }

        return $customer;
    }

    public function createCustomer(string $name, ?string $contact, ?string $address): Customer
    {
        $this->validate($name);

        return $this->customers->save(new Customer(
            null,
            trim($name),
            $this->normalizeOptional($contact),
            $this->normalizeOptional($address),
            true,
        ));
    }

    public function updateCustomer(int $id, string $name, ?string $contact, ?string $address): Customer
    {
        $existing = $this->getCustomerById($id);
        $this->validate($name);

        return $this->customers->save(new Customer(
            $id,
            trim($name),
            $this->normalizeOptional($contact),
            $this->normalizeOptional($address),
            $existing->isActive,
        ));
    }

    public function setActive(int $id, bool $isActive): void
    {
        $this->getCustomerById($id);
        $this->customers->setActive($id, $isActive);
    }

    private function validate(string $name): void
    {
        if (trim($name) === '') {
            throw new ValidationException(['name' => 'Nama customer wajib diisi.']);
        }
    }

    private function normalizeOptional(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeSearch(?string $search): ?string
    {
        $search = trim((string) $search);

        return $search === '' ? null : $search;
    }
}
