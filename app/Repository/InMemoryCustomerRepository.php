<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;

final class InMemoryCustomerRepository implements CustomerRepositoryInterface
{
    /** @var array<int, Customer> */
    private array $customers = [];

    private int $nextId = 1;

    /**
     * @param Customer[] $customers
     */
    public function __construct(array $customers = [])
    {
        foreach ($customers as $customer) {
            $this->customers[$customer->id] = $customer;
            $this->nextId = max($this->nextId, $customer->id + 1);
        }
    }

    public function findById(int $id): ?Customer
    {
        return $this->customers[$id] ?? null;
    }

    public function save(Customer $customer): Customer
    {
        if ($customer->id === null) {
            $customer = new Customer($this->nextId++, $customer->name, $customer->contact, $customer->address, $customer->isActive);
        }

        $this->customers[$customer->id] = $customer;

        return $customer;
    }

    public function listAll(
        ?string $search = null,
        ?bool $isActive = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $customers = $this->filtered($search, $isActive);

        usort($customers, static function (Customer $a, Customer $b) use ($sortBy, $sortDir): int {
            $valueA = $sortBy === 'contact' ? (string) $a->contact : $a->name;
            $valueB = $sortBy === 'contact' ? (string) $b->contact : $b->name;
            $result = strcasecmp($valueA, $valueB);

            return $sortDir === 'desc' ? -$result : $result;
        });

        return array_slice($customers, $offset, $limit);
    }

    public function countAll(?string $search = null, ?bool $isActive = null): int
    {
        return count($this->filtered($search, $isActive));
    }

    public function setActive(int $id, bool $isActive): void
    {
        $customer = $this->customers[$id] ?? null;

        if ($customer !== null) {
            $this->customers[$id] = new Customer($customer->id, $customer->name, $customer->contact, $customer->address, $isActive);
        }
    }

    /**
     * @return Customer[]
     */
    private function filtered(?string $search, ?bool $isActive): array
    {
        $customers = array_values($this->customers);

        if ($search !== null && $search !== '') {
            $customers = array_values(array_filter(
                $customers,
                static fn (Customer $customer): bool => stripos($customer->name, $search) !== false
                    || stripos((string) $customer->contact, $search) !== false
                    || stripos((string) $customer->address, $search) !== false,
            ));
        }

        if ($isActive !== null) {
            $customers = array_values(array_filter(
                $customers,
                static fn (Customer $customer): bool => $customer->isActive === $isActive,
            ));
        }

        return $customers;
    }
}
