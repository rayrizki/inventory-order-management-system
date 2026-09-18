<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductStock;

interface ProductStockRepositoryInterface
{
    /**
     * @return ProductStock[]
     */
    public function findByProduct(int $productId): array;

    /**
     * Upsert atomik (INSERT ... ON DUPLICATE KEY UPDATE) - baris belum tentu
     * ada sebelum goods receipt pertama untuk kombinasi produk+gudang itu.
     * Dipanggil GoodsReceiptService (PO-01) di dalam transaksi yang sama
     * dengan penulisan StockLedger (ARCH-02); $delta negatif dipakai goods
     * issue (SO-01) - guard "tidak boleh oversell" ada di lapisan pemanggil,
     * bukan di sini.
     */
    public function incrementQuantity(int $productId, int $warehouseId, int $delta): void;
}
