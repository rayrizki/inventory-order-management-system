<?php

declare(strict_types=1);

namespace App\Entity;

final class Product
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $sku,
        public readonly string $name,
        public readonly int $categoryId,
        public readonly string $unit,
        public readonly float $buyPrice,
        public readonly float $sellPrice,
        public readonly int $reorderPoint,
        public readonly ?string $imagePath,
        public readonly bool $isActive,
    ) {
    }
}
