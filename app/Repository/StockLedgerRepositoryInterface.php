<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockLedgerEntry;

/**
 * Append-only (ARCH-02, "keputusan data": stok tidak pernah diubah langsung
 * oleh UI, cuma lewat service yang menulis baris ledger). Tidak ada
 * update()/delete() - riwayat pergerakan stok tidak boleh diubah setelah
 * tercatat.
 */
interface StockLedgerRepositoryInterface
{
    public function record(StockLedgerEntry $entry): StockLedgerEntry;

    /**
     * @return StockLedgerEntry[]
     */
    public function findByReference(string $referenceType, int $referenceId): array;
}
