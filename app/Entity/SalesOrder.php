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

    /**
     * Nomor SO seperti yang dilihat user (SO-000012) - lihat alasan lengkap
     * di PurchaseOrder::number().
     */
    public const NUMBER_PREFIX = 'SO-';

    public const NUMBER_DIGITS = 6;

    public function number(): string
    {
        return self::NUMBER_PREFIX . str_pad((string) $this->id, self::NUMBER_DIGITS, '0', STR_PAD_LEFT);
    }
}
