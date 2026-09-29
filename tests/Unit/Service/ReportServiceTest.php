<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;
use App\Entity\Role;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\SalesOrderStatus;
use App\Entity\StockLedgerEntry;
use App\Entity\StockMovementType;
use App\Entity\Supplier;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Repository\InMemoryCustomerRepository;
use App\Repository\InMemoryProductRepository;
use App\Repository\InMemoryPurchaseOrderRepository;
use App\Repository\InMemorySalesOrderRepository;
use App\Repository\InMemoryStockLedgerRepository;
use App\Repository\InMemorySupplierRepository;
use App\Repository\InMemoryUserRepository;
use App\Repository\InMemoryWarehouseRepository;
use App\Service\ReportService;
use PHPUnit\Framework\TestCase;

final class ReportServiceTest extends TestCase
{
    private function makeService(
        ?InMemoryStockLedgerRepository $ledger = null,
        ?InMemoryPurchaseOrderRepository $purchaseOrders = null,
        ?InMemorySalesOrderRepository $salesOrders = null,
    ): ReportService {
        return new ReportService(
            $ledger ?? new InMemoryStockLedgerRepository(),
            $purchaseOrders ?? new InMemoryPurchaseOrderRepository(),
            $salesOrders ?? new InMemorySalesOrderRepository(),
            new InMemoryProductRepository([new Product(1, 'SKU-001', 'Kabel HDMI', 1, 'pcs', 10000, 15000, 5, null, true)]),
            new InMemoryWarehouseRepository([new Warehouse(1, 'Gudang Pusat', null, true)]),
            new InMemorySupplierRepository([new Supplier(1, 'Supplier Satu', null, null, true)]),
            new InMemoryCustomerRepository([new Customer(1, 'Customer Satu', null, null, true)]),
            new InMemoryUserRepository([
                new User(1, 'Admin Utama', 'admin@iom.test', 'hash', Role::Admin, true),
                new User(2, 'Warehouse Satu', 'wh1@iom.test', 'hash', Role::WarehouseStaff, true),
            ]),
        );
    }

    public function testStockLedgerReportEnrichesEntriesWithProductWarehouseAndUserNames(): void
    {
        $ledger = new InMemoryStockLedgerRepository([
            new StockLedgerEntry(1, 1, 1, StockMovementType::Receipt, 10, 'purchase_order', 5, 2, '2026-09-05 10:00:00'),
        ]);
        $service = $this->makeService(ledger: $ledger);

        $rows = $service->getStockLedgerReport('2026-09-01', '2026-09-10');

        self::assertCount(1, $rows);
        self::assertSame('SKU-001', $rows[0]['sku']);
        self::assertSame('Kabel HDMI', $rows[0]['produk']);
        self::assertSame('Gudang Pusat', $rows[0]['gudang']);
        self::assertSame('Receipt', $rows[0]['tipe_pergerakan']);
        self::assertSame(10, $rows[0]['quantity']);
        self::assertSame('purchase_order #5', $rows[0]['referensi']);
        self::assertSame('Warehouse Satu', $rows[0]['dilakukan_oleh']);
    }

    public function testStockLedgerReportFallsBackToBlankWhenLookupMisses(): void
    {
        // productId 999 tidak ada di lookup - sel dikosongkan, bukan crash.
        $ledger = new InMemoryStockLedgerRepository([
            new StockLedgerEntry(1, 999, 1, StockMovementType::Adjustment, 5, 'manual', 1, 999, '2026-09-05 10:00:00'),
        ]);
        $service = $this->makeService(ledger: $ledger);

        $rows = $service->getStockLedgerReport('2026-09-01', '2026-09-10');

        self::assertSame('', $rows[0]['sku']);
        self::assertSame('', $rows[0]['dilakukan_oleh']);
    }

    public function testOrdersReportCombinesPurchaseAndSalesOrdersSortedByDate(): void
    {
        $purchaseOrders = new InMemoryPurchaseOrderRepository([
            new PurchaseOrder(1, 1, 1, PurchaseOrderStatus::Ordered, '2026-09-05', 1, [new PurchaseOrderItem(1, 1, 1, 5, 10000, 0)]),
        ]);
        $salesOrders = new InMemorySalesOrderRepository([
            new SalesOrder(1, 1, 1, SalesOrderStatus::Approved, 2, 1, '2026-09-03 08:00:00', [new SalesOrderItem(1, 1, 1, 5, 15000)]),
        ]);
        $service = $this->makeService(purchaseOrders: $purchaseOrders, salesOrders: $salesOrders);

        $rows = $service->getOrdersReport('2026-09-01', '2026-09-10');

        self::assertCount(2, $rows);
        // SO (03 Sep) harus lebih dulu dari PO (05 Sep) - diurutkan tanggal naik.
        self::assertSame('Sales Order', $rows[0]['tipe']);
        self::assertSame('SO-000001', $rows[0]['nomor']);
        self::assertSame('Customer Satu', $rows[0]['pihak_terkait']);
        self::assertSame('Approved', $rows[0]['status']);
        self::assertSame('Warehouse Satu', $rows[0]['dibuat_oleh']);
        self::assertSame('Admin Utama', $rows[0]['disetujui_oleh']);

        self::assertSame('Purchase Order', $rows[1]['tipe']);
        self::assertSame('PO-000001', $rows[1]['nomor']);
        self::assertSame('Supplier Satu', $rows[1]['pihak_terkait']);
        self::assertSame('Ordered', $rows[1]['status']);
        self::assertSame('', $rows[1]['disetujui_oleh'], 'PO tidak punya konsep approved_by - selnya dikosongkan');
    }

    /**
     * §1.2: Sales hanya boleh mengunduh "order miliknya" - Purchase Order
     * tidak termasuk sama sekali, dan Sales Order milik orang lain disaring.
     */
    public function testOrdersReportScopedToOneCreatorExcludesPurchaseOrdersAndOtherPeoplesOrders(): void
    {
        $purchaseOrders = new InMemoryPurchaseOrderRepository([
            new PurchaseOrder(1, 1, 1, PurchaseOrderStatus::Ordered, '2026-09-05', 1, [new PurchaseOrderItem(1, 1, 1, 5, 10000, 0)]),
        ]);
        $salesOrders = new InMemorySalesOrderRepository([
            new SalesOrder(1, 1, 1, SalesOrderStatus::Approved, 2, 1, '2026-09-03 08:00:00', [new SalesOrderItem(1, 1, 1, 5, 15000)]),
            new SalesOrder(2, 1, 1, SalesOrderStatus::Draft, 3, null, '2026-09-04 08:00:00', [new SalesOrderItem(2, 2, 1, 5, 15000)]),
        ]);
        $service = $this->makeService(purchaseOrders: $purchaseOrders, salesOrders: $salesOrders);

        $rows = $service->getOrdersReport('2026-09-01', '2026-09-10', onlyCreatedBy: 2);

        self::assertCount(1, $rows);
        self::assertSame('Sales Order', $rows[0]['tipe']);
        self::assertSame('SO-000001', $rows[0]['nomor']);
    }

    public function testOrdersReportExcludesOrdersOutsideDateRange(): void
    {
        $purchaseOrders = new InMemoryPurchaseOrderRepository([
            new PurchaseOrder(1, 1, 1, PurchaseOrderStatus::Draft, '2026-01-01', 1, [new PurchaseOrderItem(1, 1, 1, 5, 10000, 0)]),
        ]);
        $service = $this->makeService(purchaseOrders: $purchaseOrders);

        $rows = $service->getOrdersReport('2026-09-01', '2026-09-10');

        self::assertSame([], $rows);
    }
}
