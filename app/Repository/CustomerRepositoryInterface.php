<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;

interface CustomerRepositoryInterface
{
    public function findById(int $id): ?Customer;

    /**
     * Insert kalau $customer->id null, update kalau sudah ada - mengembalikan
     * instance baru dengan id terisi (Customer immutable, id auto-increment
     * baru diketahui setelah INSERT).
     */
    public function save(Customer $customer): Customer;

    /**
     * @param bool|null $isActive null = semua status, true/false = filter status
     * @return Customer[]
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
