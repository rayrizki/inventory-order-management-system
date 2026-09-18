<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    /**
     * Insert kalau $user->id null, update kalau sudah ada (tidak menyentuh
     * is_active - itu tugas setActive()). Mengembalikan instance baru
     * dengan id terisi untuk insert.
     */
    public function save(User $user): User;

    public function setActive(int $id, bool $isActive): void;

    /**
     * @return User[]
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
}
