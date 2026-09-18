<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockLedgerEntry;
use App\Entity\StockMovementType;
use PDO;

final class MySqlStockLedgerRepository implements StockLedgerRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(StockLedgerEntry $entry): StockLedgerEntry
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO stock_ledger (product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by)
             VALUES (:product_id, :warehouse_id, :movement_type, :quantity, :reference_type, :reference_id, :performed_by)'
        );
        $statement->execute([
            'product_id' => $entry->productId,
            'warehouse_id' => $entry->warehouseId,
            'movement_type' => $entry->movementType->value,
            'quantity' => $entry->quantity,
            'reference_type' => $entry->referenceType,
            'reference_id' => $entry->referenceId,
            'performed_by' => $entry->performedBy,
        ]);

        return new StockLedgerEntry(
            (int) $this->pdo->lastInsertId(),
            $entry->productId,
            $entry->warehouseId,
            $entry->movementType,
            $entry->quantity,
            $entry->referenceType,
            $entry->referenceId,
            $entry->performedBy,
            $entry->createdAt,
        );
    }

    public function findByReference(string $referenceType, int $referenceId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by, created_at
             FROM stock_ledger WHERE reference_type = :reference_type AND reference_id = :reference_id
             ORDER BY created_at ASC, id ASC'
        );
        $statement->execute(['reference_type' => $referenceType, 'reference_id' => $referenceId]);

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    public function listForReport(string $fromDate, string $toDate): array
    {
        // Sengaja BUKAN `WHERE DATE(created_at) BETWEEN :from_date AND
        // :to_date` - predikat non-sargable (modul SQL Ch1-2/11: membungkus
        // kolom dalam fungsi mencegah MySQL memakai index apa pun pada
        // created_at). Batas atas eksklusif +1 hari supaya seluruh baris
        // PADA tanggal :to_date tetap ikut tanpa membungkus created_at.
        $statement = $this->pdo->prepare(
            'SELECT id, product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by, created_at
             FROM stock_ledger
             WHERE created_at >= :from_date AND created_at < DATE_ADD(:to_date, INTERVAL 1 DAY)
             ORDER BY created_at ASC, id ASC'
        );
        $statement->execute(['from_date' => $fromDate, 'to_date' => $toDate]);

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): StockLedgerEntry
    {
        return new StockLedgerEntry(
            id: (int) $row['id'],
            productId: (int) $row['product_id'],
            warehouseId: (int) $row['warehouse_id'],
            movementType: StockMovementType::from((string) $row['movement_type']),
            quantity: (int) $row['quantity'],
            referenceType: (string) $row['reference_type'],
            referenceId: (int) $row['reference_id'],
            performedBy: (int) $row['performed_by'],
            createdAt: (string) $row['created_at'],
        );
    }
}
