<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductStock;
use PDO;

final class MySqlProductStockRepository implements ProductStockRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByProduct(int $productId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT product_id, warehouse_id, quantity FROM product_stock WHERE product_id = :product_id'
        );
        $statement->execute(['product_id' => $productId]);

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ProductStock
    {
        return new ProductStock(
            productId: (int) $row['product_id'],
            warehouseId: (int) $row['warehouse_id'],
            quantity: (int) $row['quantity'],
        );
    }
}
