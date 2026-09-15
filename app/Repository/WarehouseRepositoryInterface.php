<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Warehouse;

interface WarehouseRepositoryInterface
{
    public function findById(int $id): ?Warehouse;

    /**
     * Insert kalau $warehouse->id null, update kalau sudah ada - mengembalikan
     * instance baru dengan id terisi (Warehouse immutable, id auto-increment
     * baru diketahui setelah INSERT).
     */
    public function save(Warehouse $warehouse): Warehouse;

    /**
     * @param bool|null $isActive null = semua status, true/false = filter status
     * @return Warehouse[]
     */
    public function listAll(
        ?string $search = null,
        ?bool $isActive = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array;

    public function countAll(?string $search = null, ?bool $isActive = null): int;

    public function setActive(int $id, bool $isActive): void;
}
