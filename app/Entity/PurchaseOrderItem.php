<?php

declare(strict_types=1);

namespace App\Entity;

final class PurchaseOrderItem
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $purchaseOrderId,
        public readonly int $productId,
        public readonly int $qty,
        public readonly float $buyPrice,
        public readonly int $receivedQty,
    ) {
    }

    public function remainingQty(): int
    {
        return $this->qty - $this->receivedQty;
    }
}
