<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockLedgerEntry;

final class InMemoryStockLedgerRepository implements StockLedgerRepositoryInterface
{
    /** @var StockLedgerEntry[] */
    private array $entries = [];

    private int $nextId = 1;

    /**
     * @param StockLedgerEntry[] $entries
     */
    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
    }

    public function record(StockLedgerEntry $entry): StockLedgerEntry
    {
        $saved = new StockLedgerEntry(
            $this->nextId++,
            $entry->productId,
            $entry->warehouseId,
            $entry->movementType,
            $entry->quantity,
            $entry->referenceType,
            $entry->referenceId,
            $entry->performedBy,
            $entry->createdAt,
        );
        $this->entries[] = $saved;

        return $saved;
    }

    public function findByReference(string $referenceType, int $referenceId): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (StockLedgerEntry $entry): bool => $entry->referenceType === $referenceType && $entry->referenceId === $referenceId,
        ));
    }

    public function listForReport(string $fromDate, string $toDate): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (StockLedgerEntry $entry): bool => substr((string) $entry->createdAt, 0, 10) >= $fromDate && substr((string) $entry->createdAt, 0, 10) <= $toDate,
        ));
    }
}
