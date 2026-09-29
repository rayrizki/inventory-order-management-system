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

    public function totalQuantityByProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        // Placeholder dibuat sebanyak id yang diminta - jumlahnya berasal
        // dari hasil query daftar produk (maksimal satu halaman), bukan dari
        // input user mentah, dan setiap nilainya tetap di-bind.
        $placeholders = [];
        $params = [];
        foreach (array_values($productIds) as $index => $productId) {
            $placeholders[] = ":product_$index";
            $params["product_$index"] = $productId;
        }

        $statement = $this->pdo->prepare(sprintf(
            'SELECT product_id, SUM(quantity) AS total FROM product_stock
             WHERE product_id IN (%s) GROUP BY product_id',
            implode(', ', $placeholders),
        ));
        $statement->execute($params);

        $totals = [];
        foreach ($statement->fetchAll() as $row) {
            $totals[(int) $row['product_id']] = (int) $row['total'];
        }

        return $totals;
    }

    public function incrementQuantity(int $productId, int $warehouseId, int $delta): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO product_stock (product_id, warehouse_id, quantity) VALUES (:product_id, :warehouse_id, :quantity)
             ON DUPLICATE KEY UPDATE quantity = quantity + :quantity_update'
        );
        $statement->execute([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => $delta,
            'quantity_update' => $delta,
        ]);
    }

    public function decrementIfSufficient(int $productId, int $warehouseId, int $qty): bool
    {
        // WHERE quantity >= :qty membuat UPDATE ini atomik terhadap request
        // konkuren lain yang menyentuh baris yang sama - InnoDB mengunci
        // baris ini sepanjang transaksi berjalan, jadi dua goods issue untuk
        // produk+gudang yang sama tidak bisa dua-duanya lolos men-decrement
        // dari sisa stok yang sama (lihat ADR-0005 pola serupa untuk PO,
        // ADR baru untuk mekanisme oversell-prevention SO-01).
        $statement = $this->pdo->prepare(
            'UPDATE product_stock SET quantity = quantity - :qty WHERE product_id = :product_id AND warehouse_id = :warehouse_id AND quantity >= :qty_check'
        );
        $statement->execute([
            'qty' => $qty,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'qty_check' => $qty,
        ]);

        return $statement->rowCount() > 0;
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
