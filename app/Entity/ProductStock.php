<?php

declare(strict_types=1);

namespace App\Entity;

final class ProductStock
{
    public function __construct(
        public readonly int $productId,
        public readonly int $warehouseId,
        public readonly int $quantity,
    ) {
    }
}
