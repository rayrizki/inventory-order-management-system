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

    public function totalQuantityByProducts(array $productIds): array
    {
        $totals = [];

        foreach ($this->rows as $row) {
            if (!in_array($row->productId, $productIds, true)) {
                continue;
            }

            $totals[$row->productId] = ($totals[$row->productId] ?? 0) + $row->quantity;
        }

        return $totals;
    }

    public function incrementQuantity(int $productId, int $warehouseId, int $delta): void
    {
        foreach ($this->rows as $index => $row) {
            if ($row->productId === $productId && $row->warehouseId === $warehouseId) {
                $this->rows[$index] = new ProductStock($productId, $warehouseId, $row->quantity + $delta);

                return;
            }
        }

        $this->rows[] = new ProductStock($productId, $warehouseId, $delta);
    }

    public function decrementIfSufficient(int $productId, int $warehouseId, int $qty): bool
    {
        foreach ($this->rows as $index => $row) {
            if ($row->productId === $productId && $row->warehouseId === $warehouseId) {
                if ($row->quantity < $qty) {
                    return false;
                }

                $this->rows[$index] = new ProductStock($productId, $warehouseId, $row->quantity - $qty);

                return true;
            }
        }

        return false;
    }
}
