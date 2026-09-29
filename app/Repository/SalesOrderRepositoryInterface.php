<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderStatus;

interface SalesOrderRepositoryInterface
{
    /**
     * Memuat SO beserta seluruh item-nya (agregat utuh).
     */
    public function findById(int $id): ?SalesOrder;

    /**
     * Insert SO baru (selalu status Draft) beserta seluruh item sekaligus,
     * dalam satu transaksi internal - sama seperti PurchaseOrderRepositoryInterface,
     * SO tidak diedit setelah dibuat.
     */
    public function save(SalesOrder $salesOrder): SalesOrder;

    /**
     * ARCH-02: transisi status BERSYARAT - UPDATE hanya mengenai baris yang
     * status-nya masih salah satu dari $expected, dan mengembalikan false
     * kalau tidak ada baris yang berubah. Sengaja tidak ada varian tanpa
     * syarat: membaca status lalu menulisnya di query terpisah membuka celah
     * balapan (dua request sama-sama membaca "Approved", dua-duanya menulis
     * "Fulfilled" - satu SO dipenuhi dua kali, ledger mencatat dua kali qty).
     * Guard di WHERE membuat cek-dan-tulis jadi satu operasi atomik, pola
     * yang sama dengan ProductStockRepositoryInterface::decrementIfSufficient().
     *
     * @param SalesOrderStatus[] $expected status yang masih boleh ditransisikan
     */
    public function transitionStatus(int $id, array $expected, SalesOrderStatus $next): bool;

    /**
     * Approve mengubah status DAN mencatat siapa yang menyetujui sekaligus -
     * satu UPDATE, bukan dua panggilan terpisah. Dijaga syarat yang sama
     * seperti transitionStatus(): hanya SO yang masih PendingApproval yang
     * berubah, supaya dua Admin yang menyetujui bersamaan tidak saling
     * menimpa `approved_by`.
     */
    public function approve(int $id, int $approvedBy): bool;

    /**
     * @param int|null $createdBy filter kepemilikan (Sales cuma boleh lihat
     *     order miliknya sendiri, brief §1.2) - null berarti tanpa filter
     *     (Admin/Warehouse Staff lihat semua)
     * @return SalesOrder[] items selalu [] (kosong) - daftar tidak memuat
     *     item untuk menghindari N+1; pakai findById() untuk detail.
     */
    public function listAll(
        ?string $search = null,
        ?SalesOrderStatus $status = null,
        ?int $createdBy = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
    ): array;

    public function countAll(?string $search = null, ?SalesOrderStatus $status = null, ?int $createdBy = null): int;

    /**
     * DASH-01: jumlah SO per status. `$createdBy` dipakai ringkasan Sales
     * ("ringkasan order miliknya per status", §2.5) - null berarti seluruh
     * SO (dipakai ringkasan Admin/Warehouse Staff). SELURUH status
     * eksplisit ada di hasil (0 kalau tidak ada baris).
     *
     * @return array<string, int> SalesOrderStatus::value => jumlah
     */
    public function countByStatus(?int $createdBy = null): array;

    /**
     * REPORT-01: seluruh SO dalam rentang tanggal dibuat, TIDAK dipaginasi
     * dan items selalu [] (konsisten dengan listAll()).
     *
     * @return SalesOrder[]
     */
    public function listForReport(string $fromDate, string $toDate): array;
}
