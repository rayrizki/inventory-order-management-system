<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Warehouse;

final class InMemoryWarehouseRepository implements WarehouseRepositoryInterface
{
    /** @var array<int, Warehouse> */
    private array $warehouses = [];

    private int $nextId = 1;

    /**
     * @param Warehouse[] $warehouses
     */
    public function __construct(array $warehouses = [])
    {
        foreach ($warehouses as $warehouse) {
            $this->warehouses[$warehouse->id] = $warehouse;
            $this->nextId = max($this->nextId, $warehouse->id + 1);
        }
    }

    public function findById(int $id): ?Warehouse
    {
        return $this->warehouses[$id] ?? null;
    }

    public function save(Warehouse $warehouse): Warehouse
    {
        if ($warehouse->id === null) {
            $warehouse = new Warehouse($this->nextId++, $warehouse->name, $warehouse->location, $warehouse->isActive);
        }

        $this->warehouses[$warehouse->id] = $warehouse;

        return $warehouse;
    }

    public function listAll(
        ?string $search = null,
        ?bool $isActive = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $warehouses = $this->filtered($search, $isActive);

        usort($warehouses, static function (Warehouse $a, Warehouse $b) use ($sortBy, $sortDir): int {
            $valueA = $sortBy === 'location' ? (string) $a->location : $a->name;
            $valueB = $sortBy === 'location' ? (string) $b->location : $b->name;
            $result = strcasecmp($valueA, $valueB);

            return $sortDir === 'desc' ? -$result : $result;
        });

        return array_slice($warehouses, $offset, $limit);
    }

    public function countAll(?string $search = null, ?bool $isActive = null): int
    {
        return count($this->filtered($search, $isActive));
    }

    public function setActive(int $id, bool $isActive): void
    {
        $warehouse = $this->warehouses[$id] ?? null;

        if ($warehouse !== null) {
            $this->warehouses[$id] = new Warehouse($warehouse->id, $warehouse->name, $warehouse->location, $isActive);
        }
    }

    /**
     * @return Warehouse[]
     */
    private function filtered(?string $search, ?bool $isActive): array
    {
        $warehouses = array_values($this->warehouses);

        if ($search !== null && $search !== '') {
            $warehouses = array_values(array_filter(
                $warehouses,
                static fn (Warehouse $warehouse): bool => stripos($warehouse->name, $search) !== false
                    || stripos((string) $warehouse->location, $search) !== false,
            ));
        }

        if ($isActive !== null) {
            $warehouses = array_values(array_filter(
                $warehouses,
                static fn (Warehouse $warehouse): bool => $warehouse->isActive === $isActive,
            ));
        }

        return $warehouses;
    }
}
