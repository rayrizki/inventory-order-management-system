<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductStock;

final class InMemoryProductStockRepository implements ProductStockRepositoryInterface
{
    /**
     * @param ProductStock[] $rows
     */
    public function __construct(private array $rows = [])
    {
    }

    public function findByProduct(int $productId): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (ProductStock $row): bool => $row->productId === $productId,
        ));
    }
}
