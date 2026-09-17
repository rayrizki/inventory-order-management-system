<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Supplier;

final class InMemorySupplierRepository implements SupplierRepositoryInterface
{
    /** @var array<int, Supplier> */
    private array $suppliers = [];

    private int $nextId = 1;

    /**
     * @param Supplier[] $suppliers
     */
    public function __construct(array $suppliers = [])
    {
        foreach ($suppliers as $supplier) {
            $this->suppliers[$supplier->id] = $supplier;
            $this->nextId = max($this->nextId, $supplier->id + 1);
        }
    }

    public function findById(int $id): ?Supplier
    {
        return $this->suppliers[$id] ?? null;
    }

    public function save(Supplier $supplier): Supplier
    {
        if ($supplier->id === null) {
            $supplier = new Supplier($this->nextId++, $supplier->name, $supplier->contact, $supplier->address, $supplier->isActive);
        }

        $this->suppliers[$supplier->id] = $supplier;

        return $supplier;
    }

    public function listAll(
        ?string $search = null,
        ?bool $isActive = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $suppliers = $this->filtered($search, $isActive);

        usort($suppliers, static function (Supplier $a, Supplier $b) use ($sortBy, $sortDir): int {
            $valueA = $sortBy === 'contact' ? (string) $a->contact : $a->name;
            $valueB = $sortBy === 'contact' ? (string) $b->contact : $b->name;
            $result = strcasecmp($valueA, $valueB);

            return $sortDir === 'desc' ? -$result : $result;
        });

        return array_slice($suppliers, $offset, $limit);
    }

    public function countAll(?string $search = null, ?bool $isActive = null): int
    {
        return count($this->filtered($search, $isActive));
    }

    public function setActive(int $id, bool $isActive): void
    {
        $supplier = $this->suppliers[$id] ?? null;

        if ($supplier !== null) {
            $this->suppliers[$id] = new Supplier($supplier->id, $supplier->name, $supplier->contact, $supplier->address, $isActive);
        }
    }

    /**
     * @return Supplier[]
     */
    private function filtered(?string $search, ?bool $isActive): array
    {
        $suppliers = array_values($this->suppliers);

        if ($search !== null && $search !== '') {
            $suppliers = array_values(array_filter(
                $suppliers,
                static fn (Supplier $supplier): bool => stripos($supplier->name, $search) !== false
                    || stripos((string) $supplier->contact, $search) !== false
                    || stripos((string) $supplier->address, $search) !== false,
            ));
        }

        if ($isActive !== null) {
            $suppliers = array_values(array_filter(
                $suppliers,
                static fn (Supplier $supplier): bool => $supplier->isActive === $isActive,
            ));
        }

        return $suppliers;
    }
}
