<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\ProductStock;
use App\Entity\Warehouse;
use App\Repository\InMemoryProductStockRepository;
use App\Repository\InMemoryWarehouseRepository;
use App\Service\StockService;
use PHPUnit\Framework\TestCase;

final class StockServiceTest extends TestCase
{
    public function testGetStockSummaryDefaultsMissingWarehouseRowsToZero(): void
    {
        $warehouses = new InMemoryWarehouseRepository([
            new Warehouse(1, 'Gudang Jakarta', 'Jakarta', true),
        ]);
        $stock = new InMemoryProductStockRepository([]);
        $service = new StockService($stock, $warehouses);

        $summary = $service->getStockSummary(1);

        self::assertSame(0, $summary['total']);
        self::assertCount(1, $summary['warehouses']);
        self::assertSame('Gudang Jakarta', $summary['warehouses'][0]['warehouseName']);
        self::assertSame(0, $summary['warehouses'][0]['quantity']);
    }

    public function testGetStockSummarySumsQuantityAcrossWarehouses(): void
    {
        $warehouses = new InMemoryWarehouseRepository([
            new Warehouse(1, 'Gudang Jakarta', 'Jakarta', true),
            new Warehouse(2, 'Gudang Surabaya', 'Surabaya', true),
        ]);
        $stock = new InMemoryProductStockRepository([
            new ProductStock(productId: 5, warehouseId: 1, quantity: 30),
            new ProductStock(productId: 5, warehouseId: 2, quantity: 12),
        ]);
        $service = new StockService($stock, $warehouses);

        $summary = $service->getStockSummary(5);

        self::assertSame(42, $summary['total']);
    }

    public function testGetStockSummaryExcludesInactiveWarehouses(): void
    {
        $warehouses = new InMemoryWarehouseRepository([
            new Warehouse(1, 'Gudang Aktif', null, true),
            new Warehouse(2, 'Gudang Nonaktif', null, false),
        ]);
        $stock = new InMemoryProductStockRepository([
            new ProductStock(productId: 1, warehouseId: 2, quantity: 100),
        ]);
        $service = new StockService($stock, $warehouses);

        $summary = $service->getStockSummary(1);

        self::assertCount(1, $summary['warehouses']);
        self::assertSame('Gudang Aktif', $summary['warehouses'][0]['warehouseName']);
        self::assertSame(0, $summary['total'], 'Stok di gudang nonaktif tidak boleh ikut dijumlahkan ke total.');
    }

    public function testGetStockSummaryOnlyReturnsRowsForRequestedProduct(): void
    {
        $warehouses = new InMemoryWarehouseRepository([
            new Warehouse(1, 'Gudang Jakarta', null, true),
        ]);
        $stock = new InMemoryProductStockRepository([
            new ProductStock(productId: 1, warehouseId: 1, quantity: 10),
            new ProductStock(productId: 2, warehouseId: 1, quantity: 999),
        ]);
        $service = new StockService($stock, $warehouses);

        $summary = $service->getStockSummary(1);

        self::assertSame(10, $summary['total']);
    }
}
