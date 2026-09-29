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

    /**
     * Nomor PO seperti yang dilihat user (PO-000012). Sebelumnya format ini
     * ditulis ulang dengan str_pad() di tiap view yang menampilkannya, dan
     * pencarian FIND-01 hanya mencocokkan id mentah - jadi mengetik nomor
     * persis seperti yang tampil di layar justru tidak menemukan apa pun.
     * Satu tempat format ini didefinisikan supaya tampilan dan pencarian
     * tidak bisa lagi berbeda.
     */
    public const NUMBER_PREFIX = 'PO-';

    public const NUMBER_DIGITS = 6;

    public function number(): string
    {
        return self::NUMBER_PREFIX . str_pad((string) $this->id, self::NUMBER_DIGITS, '0', STR_PAD_LEFT);
    }
}
