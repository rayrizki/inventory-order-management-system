<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Warehouse;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\WarehouseRepositoryInterface;

final class WarehouseService
{
    use NormalizesSearchTerm;

    public const PER_PAGE = 10;

    public function __construct(private readonly WarehouseRepositoryInterface $warehouses)
    {
    }

    /**
     * @return Warehouse[]
     */
    public function listWarehouses(
        ?string $search = null,
        ?bool $isActive = null,
        int $page = 1,
        int $perPage = self::PER_PAGE,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $perPage = max(1, $perPage);
        $offset = (max(1, $page) - 1) * $perPage;

        return $this->warehouses->listAll($this->normalizeSearch($search), $isActive, $perPage, $offset, $sortBy, $sortDir);
    }

    public function countWarehouses(?string $search = null, ?bool $isActive = null): int
    {
        return $this->warehouses->countAll($this->normalizeSearch($search), $isActive);
    }

    public function getWarehouseById(int $id): Warehouse
    {
        $warehouse = $this->warehouses->findById($id);

        if ($warehouse === null) {
            throw new NotFoundException();
        }

        return $warehouse;
    }

    public function createWarehouse(string $name, ?string $location): Warehouse
    {
        $this->validate($name);

        return $this->warehouses->save(new Warehouse(null, trim($name), $this->normalizeLocation($location), true));
    }

    public function updateWarehouse(int $id, string $name, ?string $location): Warehouse
    {
        $existing = $this->getWarehouseById($id);
        $this->validate($name);

        return $this->warehouses->save(new Warehouse($id, trim($name), $this->normalizeLocation($location), $existing->isActive));
    }

    public function setActive(int $id, bool $isActive): void
    {
        $this->getWarehouseById($id);
        $this->warehouses->setActive($id, $isActive);
    }

    private function validate(string $name): void
    {
        if (trim($name) === '') {
            throw new ValidationException(['name' => 'Nama gudang wajib diisi.']);
        }
    }

    private function normalizeLocation(?string $location): ?string
    {
        $trimmed = trim((string) $location);

        return $trimmed === '' ? null : $trimmed;
    }
}
