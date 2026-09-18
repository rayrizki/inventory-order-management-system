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
}
