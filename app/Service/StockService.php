<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ProductStockRepositoryInterface;
use App\Repository\WarehouseRepositoryInterface;

/**
 * WH-01: rincian stok satu produk di seluruh gudang aktif, plus total.
 * Gudang tanpa baris product_stock dianggap quantity 0 (belum pernah
 * menerima barang) - bukan error, karena baris memang baru tercipta lewat
 * goods receipt (PO-01), yang belum dibangun saat modul ini ditulis.
 */
final class StockService
{
    /** Gudang dianggap "semua" untuk kebutuhan breakdown - jauh di atas jumlah gudang realistis. */
    private const WAREHOUSE_LIMIT = 1000;

    public function __construct(
        private readonly ProductStockRepositoryInterface $stockRepository,
        private readonly WarehouseRepositoryInterface $warehouseRepository,
    ) {
    }

    /**
     * @return array{total: int, warehouses: list<array{warehouseId: int, warehouseName: string, quantity: int}>}
     */
    public function getStockSummary(int $productId): array
    {
        $warehouses = $this->warehouseRepository->listAll(isActive: true, limit: self::WAREHOUSE_LIMIT);

        $quantityByWarehouse = [];
        foreach ($this->stockRepository->findByProduct($productId) as $stock) {
            $quantityByWarehouse[$stock->warehouseId] = $stock->quantity;
        }

        $breakdown = [];
        $total = 0;
        foreach ($warehouses as $warehouse) {
            $quantity = $quantityByWarehouse[$warehouse->id] ?? 0;
            $breakdown[] = [
                'warehouseId' => $warehouse->id,
                'warehouseName' => $warehouse->name,
                'quantity' => $quantity,
            ];
            $total += $quantity;
        }

        return ['total' => $total, 'warehouses' => $breakdown];
    }
}
