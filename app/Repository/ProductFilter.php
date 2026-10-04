<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Kriteria penyaringan daftar Produk (FIND-01).
 *
 * Dibuat karena `listAll()` dan `countAll()` HARUS selalu dipanggil dengan
 * nilai filter yang persis sama - kalau tidak, jumlah halaman pada pagination
 * tidak cocok dengan baris yang benar-benar tampil. Sebelumnya keempat nilai
 * itu dikirim sebagai parameter terpisah ke dua method berbeda, sehingga
 * kecocokannya hanya dijaga kebiasaan. Dibungkus jadi satu objek, keduanya
 * tidak bisa lagi menyimpang tanpa disengaja - dan `listAll()` ikut turun
 * dari 8 parameter menjadi 5 (SonarQube php:S107).
 *
 * Sengaja hanya memuat dimensi PENYARINGAN. Limit/offset/sort tetap jadi
 * parameter tersendiri karena itu urusan penyajian, bukan kriteria data:
 * `countAll()` butuh filternya tapi tidak pernah butuh pagination.
 */
final class ProductFilter
{
    /**
     * @param string|null $search null = tanpa pencarian; dicocokkan ke nama atau SKU
     * @param bool|null $isActive null = semua status, true/false = filter status aktif
     * @param string|null $stockStatus null = semua; 'low' = total stok lintas gudang
     *     di bawah reorder_point, 'normal' = di atas atau sama dengan reorder_point
     */
    public readonly ?string $search;

    public function __construct(
        ?string $search = null,
        public readonly ?int $categoryId = null,
        public readonly ?bool $isActive = null,
        public readonly ?string $stockStatus = null,
    ) {
        // Kata kunci dirapikan di sini, bukan di pemanggil: "  " dan "" sama
        // saja dengan "tanpa pencarian", dan Repository perlu bisa membedakan
        // "tidak ada filter" (null) dari "cari string kosong". Dengan normalisasi
        // melekat pada nilainya, tidak ada pemanggil yang bisa lupa melakukannya.
        $search = trim((string) $search);
        $this->search = $search === '' ? null : $search;
    }
}
