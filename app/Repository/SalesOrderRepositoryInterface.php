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

    public function updateStatus(int $id, SalesOrderStatus $status): void;

    /**
     * Approve mengubah status DAN mencatat siapa yang menyetujui sekaligus -
     * satu UPDATE, bukan dua panggilan terpisah.
     */
    public function approve(int $id, int $approvedBy): void;

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
