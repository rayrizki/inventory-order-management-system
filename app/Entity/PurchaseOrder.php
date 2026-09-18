<?php

declare(strict_types=1);

namespace App\Entity;

final class PurchaseOrder
{
    /**
     * @param PurchaseOrderItem[] $items
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $supplierId,
        public readonly int $warehouseId,
        public readonly PurchaseOrderStatus $status,
        public readonly string $orderDate,
        public readonly int $createdBy,
        public readonly array $items,
    ) {
    }
}
