<?php

declare(strict_types=1);

namespace App\Entity;

final class StockLedgerEntry
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $productId,
        public readonly int $warehouseId,
        public readonly StockMovementType $movementType,
        public readonly int $quantity,
        public readonly string $referenceType,
        public readonly int $referenceId,
        public readonly int $performedBy,
        public readonly ?string $createdAt,
    ) {
    }
}
