<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\SalesOrderStatus;
use App\Repository\InMemoryProductRepository;
use App\Repository\InMemoryPurchaseOrderRepository;
use App\Repository\InMemorySalesOrderRepository;
use App\Service\DashboardService;
use PHPUnit\Framework\TestCase;

final class DashboardServiceTest extends TestCase
{
    private function makeProducts(): InMemoryProductRepository
    {
        return new InMemoryProductRepository(
            [
                new Product(1, 'SKU-001', 'Stok Rendah', 1, 'pcs', 10000, 15000, 10, null, true),
                new Product(2, 'SKU-002', 'Stok Normal', 1, 'pcs', 20000, 30000, 5, null, true),
            ],
            totalStockByProductId: [1 => 5, 2 => 50],
        );
    }

    private function makePurchaseOrders(): InMemoryPurchaseOrderRepository
    {
        return new InMemoryPurchaseOrderRepository([
            new PurchaseOrder(1, 1, 1, PurchaseOrderStatus::Draft, '2026-09-01', 1, [new PurchaseOrderItem(1, 1, 1, 5, 10000, 0)]),
            new PurchaseOrder(2, 1, 1, PurchaseOrderStatus::Ordered, '2026-09-02', 1, [new PurchaseOrderItem(2, 2, 1, 5, 10000, 0)]),
            new PurchaseOrder(3, 1, 1, PurchaseOrderStatus::PartiallyReceived, '2026-09-03', 1, [new PurchaseOrderItem(3, 3, 1, 5, 10000, 2)]),
            new PurchaseOrder(4, 1, 1, PurchaseOrderStatus::Ordered, '2026-09-04', 1, [new PurchaseOrderItem(4, 4, 1, 5, 10000, 0)]),
        ]);
    }

    private function makeSalesOrders(): InMemorySalesOrderRepository
    {
        return new InMemorySalesOrderRepository([
            new SalesOrder(1, 1, 1, SalesOrderStatus::Draft, 2, null, '2026-09-01', [new SalesOrderItem(1, 1, 1, 5, 15000)]),
            new SalesOrder(2, 1, 1, SalesOrderStatus::PendingApproval, 2, null, '2026-09-02', [new SalesOrderItem(2, 2, 1, 5, 15000)]),
            new SalesOrder(3, 1, 1, SalesOrderStatus::Approved, 3, null, '2026-09-03', [new SalesOrderItem(3, 3, 1, 5, 15000)]),
            new SalesOrder(4, 1, 1, SalesOrderStatus::Approved, 3, null, '2026-09-04', [new SalesOrderItem(4, 4, 1, 5, 15000)]),
        ]);
    }

    private function makeService(): DashboardService
    {
        return new DashboardService($this->makeProducts(), $this->makePurchaseOrders(), $this->makeSalesOrders());
    }

    public function testAdminSummaryComputesInventoryValueFromQuantityTimesBuyPrice(): void
    {
        $summary = $this->makeService()->getAdminSummary();

        // (5 * 10000) + (50 * 20000) = 50000 + 1000000
        self::assertSame(1050000.0, $summary['inventoryValue']);
    }

    public function testAdminSummaryCountsLowStockProducts(): void
    {
        $summary = $this->makeService()->getAdminSummary();

        self::assertSame(1, $summary['lowStockCount']);
        self::assertCount(1, $summary['lowStockProducts']);
        self::assertSame('Stok Rendah', $summary['lowStockProducts'][0]->name);
    }

    public function testAdminSummaryPurchaseOrderStatusBreakdownIncludesAllStatusesEvenWhenZero(): void
    {
        $summary = $this->makeService()->getAdminSummary();
        $counts = $summary['purchaseOrdersByStatus'];

        self::assertSame(1, $counts['Draft']);
        self::assertSame(2, $counts['Ordered']);
        self::assertSame(1, $counts['PartiallyReceived']);
        self::assertSame(0, $counts['Received'], 'status tanpa data harus tetap 0, bukan hilang dari array');
        self::assertSame(0, $counts['Cancelled']);
    }

    public function testAdminSummarySalesOrderStatusBreakdownCountsAllSalesRegardlessOfOwner(): void
    {
        $summary = $this->makeService()->getAdminSummary();
        $counts = $summary['salesOrdersByStatus'];

        self::assertSame(1, $counts['Draft']);
        self::assertSame(1, $counts['PendingApproval']);
        self::assertSame(2, $counts['Approved'], 'Admin harus lihat SELURUH SO, bukan cuma milik satu Sales');
    }

    public function testSalesSummaryScopesCountsToOwnOrdersOnly(): void
    {
        $summary = $this->makeService()->getSalesSummary(salesUserId: 2);
        $counts = $summary['salesOrdersByStatus'];

        self::assertSame(1, $counts['Draft']);
        self::assertSame(1, $counts['PendingApproval']);
        self::assertSame(0, $counts['Approved'], 'SO milik Sales lain (createdBy=3) tidak boleh ikut terhitung');
    }

    public function testWarehouseSummaryPendingReceiptCombinesOrderedAndPartiallyReceived(): void
    {
        $summary = $this->makeService()->getWarehouseSummary();

        // 2 Ordered + 1 PartiallyReceived = 3
        self::assertSame(3, $summary['pendingReceiptCount']);
    }

    public function testWarehouseSummaryPendingIssueCountsApprovedSalesOrders(): void
    {
        $summary = $this->makeService()->getWarehouseSummary();

        self::assertSame(2, $summary['pendingIssueCount']);
    }

    public function testWarehouseSummaryIncludesLowStockCountAndList(): void
    {
        $summary = $this->makeService()->getWarehouseSummary();

        self::assertSame(1, $summary['lowStockCount']);
        self::assertCount(1, $summary['lowStockProducts']);
    }
}
