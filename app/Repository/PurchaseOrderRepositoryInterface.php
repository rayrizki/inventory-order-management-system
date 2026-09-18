<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderStatus;

interface PurchaseOrderRepositoryInterface
{
    /**
     * Memuat PO beserta seluruh item-nya (agregat utuh) - dipakai halaman
     * detail dan sebelum transisi status/goods receipt.
     */
    public function findById(int $id): ?PurchaseOrder;

    /**
     * Insert PO baru (selalu status Draft) beserta seluruh item sekaligus,
     * dalam satu transaksi internal. Tidak ada "update" untuk header/item -
     * PO tidak diedit setelah dibuat, hanya status dan receivedQty per item
     * yang berubah (lewat updateStatus()/incrementItemReceivedQty()).
     */
    public function save(PurchaseOrder $purchaseOrder): PurchaseOrder;

    public function updateStatus(int $id, PurchaseOrderStatus $status): void;

    public function incrementItemReceivedQty(int $itemId, int $delta): void;

    /**
     * @return PurchaseOrder[] items selalu [] (kosong) - daftar tidak
     *     memuat item untuk menghindari N+1; pakai findById() untuk detail.
     */
    public function listAll(
        ?string $search = null,
        ?PurchaseOrderStatus $status = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'order_date',
        string $sortDir = 'desc',
    ): array;

    public function countAll(?string $search = null, ?PurchaseOrderStatus $status = null): int;

    /**
     * DASH-01: jumlah PO per status ("order pending per status") - SELURUH
     * status eksplisit ada di hasil (0 kalau tidak ada baris), bukan cuma
     * status yang kebetulan punya data, supaya dashboard tidak perlu
     * menebak status mana yang mungkin hilang dari array.
     *
     * @return array<string, int> PurchaseOrderStatus::value => jumlah
     */
    public function countByStatus(): array;

    /**
     * REPORT-01: seluruh PO dalam rentang tanggal order, TIDAK dipaginasi
     * (laporan butuh semua baris) dan items selalu [] (konsisten dengan
     * listAll() - laporan cuma butuh header, bukan detail item).
     *
     * @return PurchaseOrder[]
     */
    public function listForReport(string $fromDate, string $toDate): array;
}
