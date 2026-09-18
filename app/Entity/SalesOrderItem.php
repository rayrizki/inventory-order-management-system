<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Beda dari PurchaseOrderItem: tidak ada receivedQty/remainingQty - schema
 * sales_order_items tidak punya kolom penerimaan sebagian (lihat
 * database/schema-and-seed.sql). Goods issue SO-01 memang all-or-nothing
 * per order, bukan per item seperti goods receipt PO-01.
 */
final class SalesOrderItem
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $salesOrderId,
        public readonly int $productId,
        public readonly int $qty,
        public readonly float $sellPrice,
    ) {
    }
}
