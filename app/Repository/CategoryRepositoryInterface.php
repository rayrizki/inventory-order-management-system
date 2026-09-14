<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;

interface CategoryRepositoryInterface
{
    public function findById(int $id): ?Category;

    /**
     * Insert kalau $category->id null, update kalau sudah ada - mengembalikan
     * instance baru dengan id terisi (Category immutable, id auto-increment
     * baru diketahui setelah INSERT).
     */
    public function save(Category $category): Category;

    /**
     * @return Category[]
     */
    public function listAll(
        ?string $search = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array;

    public function countAll(?string $search = null): int;

    /**
     * True kalau kategori ini masih dipakai produk mana pun - dipakai
     * Service untuk menolak penghapusan (kategori satu-satunya master data
     * tanpa status aktif/nonaktif, jadi delete-nya permanen).
     */
    public function isInUse(int $id): bool;

    public function delete(int $id): void;
}
