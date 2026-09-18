<?php

declare(strict_types=1);

namespace App\Entity;

final class SalesOrder
{
    /**
     * @param SalesOrderItem[] $items
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $customerId,
        public readonly int $warehouseId,
        public readonly SalesOrderStatus $status,
        public readonly int $createdBy,
        public readonly ?int $approvedBy,
        public readonly ?string $createdAt,
        public readonly array $items,
    ) {
    }
}
