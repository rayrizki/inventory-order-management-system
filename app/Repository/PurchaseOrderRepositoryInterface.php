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
}
