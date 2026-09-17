<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductStock;

/**
 * Baca-saja untuk saat ini (WH-01). Baris product_stock nanti ditulis oleh
 * StockService lewat alur goods receipt (PO-01) / goods issue (SO-01) dalam
 * satu transaksi bersama StockLedger (ARCH-02) - bukan lewat method di sini.
 */
interface ProductStockRepositoryInterface
{
    /**
     * @return ProductStock[]
     */
    public function findByProduct(int $productId): array;
}
