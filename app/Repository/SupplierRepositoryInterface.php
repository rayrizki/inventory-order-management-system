<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Supplier;

interface SupplierRepositoryInterface
{
    public function findById(int $id): ?Supplier;

    /**
     * Insert kalau $supplier->id null, update kalau sudah ada - mengembalikan
     * instance baru dengan id terisi (Supplier immutable, id auto-increment
     * baru diketahui setelah INSERT).
     */
    public function save(Supplier $supplier): Supplier;

    /**
     * @param bool|null $isActive null = semua status, true/false = filter status
     * @return Supplier[]
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
