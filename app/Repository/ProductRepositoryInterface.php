<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    public function findBySku(string $sku): ?Product;

    /**
     * Insert kalau $product->id null, update kalau sudah ada - mengembalikan
     * instance baru dengan id terisi (Product immutable, id auto-increment
     * baru diketahui setelah INSERT).
     */
    public function save(Product $product): Product;

    /**
     * @param bool|null $isActive null = semua status, true/false = filter status
     * @param string|null $stockStatus FIND-01: null = semua, 'low' = total stok
     *     (dijumlah lintas gudang) di bawah reorder_point, 'normal' = di atas
     *     atau sama dengan reorder_point
     * @return Product[]
     */
    public function listAll(
        ?string $search = null,
        ?int $categoryId = null,
        ?bool $isActive = null,
        ?string $stockStatus = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array;

    public function countAll(?string $search = null, ?int $categoryId = null, ?bool $isActive = null, ?string $stockStatus = null): int;

    public function setActive(int $id, bool $isActive): void;
}
