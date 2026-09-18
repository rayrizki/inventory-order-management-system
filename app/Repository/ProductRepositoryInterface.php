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

    /**
     * DASH-01: nilai inventori (cost basis) - SUM(quantity * buy_price)
     * lintas SELURUH baris product_stock (termasuk produk yang sudah
     * dinonaktifkan - stok fisik yang masih ada di gudang tetap bernilai).
     * Ditaruh di sini (bukan ProductStockRepositoryInterface) karena butuh
     * buy_price yang cuma dimiliki Product - pola yang sama dengan filter
     * stockStatus di listAll()/countAll() yang juga JOIN ke product_stock.
     */
    public function sumInventoryValue(): float;
}
