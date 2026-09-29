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
     * Total stok (dijumlah lintas gudang) untuk beberapa produk sekaligus -
     * satu query GROUP BY, bukan findByProduct() per baris daftar (N+1).
     * Dipakai daftar Produk supaya angka stok dan status low/normal yang
     * jadi dasar filter FIND-01 benar-benar terlihat di tabel.
     *
     * @param int[] $productIds
     * @return array<int, int> productId => total quantity; produk yang belum
     *     punya baris stok sama sekali tidak muncul di hasil (anggap 0).
     */
    public function totalQuantityByProducts(array $productIds): array;

    /**
     * Upsert atomik (INSERT ... ON DUPLICATE KEY UPDATE) - baris belum tentu
     * ada sebelum goods receipt pertama untuk kombinasi produk+gudang itu.
     * Dipanggil GoodsReceiptService (PO-01) di dalam transaksi yang sama
     * dengan penulisan StockLedger (ARCH-02). Cuma untuk penambahan
     * (goods receipt) - tidak ada risiko oversell saat menambah stok, jadi
     * tidak butuh guard seperti decrementIfSufficient().
     */
    public function incrementQuantity(int $productId, int $warehouseId, int $delta): void;

    /**
     * ARCH-02: pengurangan atomik untuk goods issue (SO-01) - `UPDATE ...
     * WHERE quantity >= :qty` dalam satu statement, mengembalikan true kalau
     * ADA baris yang berubah (stok cukup) atau false kalau tidak (stok tidak
     * cukup ATAU baris belum pernah ada sama sekali - qty yang diminta pasti
     * > 0 sehingga baris dengan quantity 0/tidak ada tidak akan pernah
     * mencukupi). Sengaja method terpisah dari incrementQuantity() (bukan
     * dipanggil dengan delta negatif) supaya guard "tidak boleh oversell"
     * eksplisit di level query, bukan mengandalkan CHECK constraint
     * database gagal secara diam-diam sebagai satu-satunya pertahanan.
     */
    public function decrementIfSufficient(int $productId, int $warehouseId, int $qty): bool;
}
