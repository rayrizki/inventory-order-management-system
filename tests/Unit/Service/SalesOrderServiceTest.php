<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\SalesOrderStatus;
use App\Entity\Warehouse;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\InMemoryCustomerRepository;
use App\Repository\InMemoryProductRepository;
use App\Repository\InMemorySalesOrderRepository;
use App\Repository\InMemoryWarehouseRepository;
use App\Service\SalesOrderService;
use PHPUnit\Framework\TestCase;

final class SalesOrderServiceTest extends TestCase
{
    private function makeService(?InMemorySalesOrderRepository $salesOrders = null): SalesOrderService
    {
        return new SalesOrderService(
            $salesOrders ?? new InMemorySalesOrderRepository(),
            new InMemoryCustomerRepository([new Customer(1, 'Customer Satu', null, null, true)]),
            new InMemoryWarehouseRepository([new Warehouse(1, 'Gudang Pusat', null, true)]),
            new InMemoryProductRepository([new Product(1, 'SKU-001', 'Kabel HDMI', 1, 'pcs', 10000, 15000, 5, null, true)]),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => '1',
            'warehouse_id' => '1',
            'items' => [
                ['product_id' => '1', 'qty' => '5', 'sell_price' => '15000'],
            ],
        ], $overrides);
    }

    public function testCreateSalesOrderSucceedsAsDraftWithItems(): void
    {
        $service = $this->makeService();

        $so = $service->createSalesOrder($this->validInput(), createdBy: 2);

        self::assertNotNull($so->id);
        self::assertSame(SalesOrderStatus::Draft, $so->status);
        self::assertSame(2, $so->createdBy);
        self::assertNull($so->approvedBy);
        self::assertCount(1, $so->items);
    }

    public function testCreateSalesOrderRejectsUnknownCustomerAndWarehouse(): void
    {
        $service = $this->makeService();

        try {
            $service->createSalesOrder($this->validInput(['customer_id' => '999', 'warehouse_id' => '888']), 2);
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            self::assertArrayHasKey('customer_id', $errors);
            self::assertArrayHasKey('warehouse_id', $errors);
        }
    }

    public function testCreateSalesOrderRejectsEmptyItems(): void
    {
        $service = $this->makeService();

        $this->expectException(ValidationException::class);

        $service->createSalesOrder($this->validInput(['items' => []]), 2);
    }

    public function testCreateSalesOrderRejectsInactiveProduct(): void
    {
        $salesOrders = new InMemorySalesOrderRepository();
        $service = new SalesOrderService(
            $salesOrders,
            new InMemoryCustomerRepository([new Customer(1, 'Customer Satu', null, null, true)]),
            new InMemoryWarehouseRepository([new Warehouse(1, 'Gudang Pusat', null, true)]),
            new InMemoryProductRepository([new Product(1, 'SKU-001', 'Kabel HDMI', 1, 'pcs', 10000, 15000, 5, null, false)]),
        );

        $this->expectException(ValidationException::class);

        $service->createSalesOrder($this->validInput(), 2);
    }

    public function testSubmitForApprovalTransitionsDraftToPendingApproval(): void
    {
        $service = $this->makeService();
        $so = $service->createSalesOrder($this->validInput(), 2);

        $updated = $service->submitForApproval($so->id);

        self::assertSame(SalesOrderStatus::PendingApproval, $updated->status);
    }

    public function testSubmitForApprovalRejectsNonDraftStatus(): void
    {
        $service = $this->makeService();
        $so = $service->createSalesOrder($this->validInput(), 2);
        $service->submitForApproval($so->id);

        $this->expectException(ConflictException::class);

        $service->submitForApproval($so->id);
    }

    public function testApproveTransitionsPendingApprovalToApprovedAndRecordsApprover(): void
    {
        $service = $this->makeService();
        $so = $service->createSalesOrder($this->validInput(), 2);
        $service->submitForApproval($so->id);

        $updated = $service->approve($so->id, approvedBy: 1);

        self::assertSame(SalesOrderStatus::Approved, $updated->status);
        self::assertSame(1, $updated->approvedBy);
    }

    public function testApproveRejectsDraftStatus(): void
    {
        $service = $this->makeService();
        $so = $service->createSalesOrder($this->validInput(), 2);

        $this->expectException(ConflictException::class);

        $service->approve($so->id, 1);
    }

    public function testCancelAllowedFromApprovedButNotFromFulfilled(): void
    {
        $salesOrders = new InMemorySalesOrderRepository([
            new SalesOrder(1, 1, 1, SalesOrderStatus::Fulfilled, 2, 1, null, [
                new SalesOrderItem(1, 1, 1, 5, 15000),
            ]),
        ]);
        $service = $this->makeService($salesOrders);

        $this->expectException(ConflictException::class);

        $service->cancel(1);
    }

    public function testCancelSucceedsFromApprovedStatus(): void
    {
        $service = $this->makeService();
        $so = $service->createSalesOrder($this->validInput(), 2);
        $service->submitForApproval($so->id);
        $service->approve($so->id, 1);

        $cancelled = $service->cancel($so->id);

        self::assertSame(SalesOrderStatus::Cancelled, $cancelled->status);
    }

    public function testGetSalesOrderByIdThrowsNotFoundForUnknownId(): void
    {
        $service = $this->makeService();

        $this->expectException(NotFoundException::class);

        $service->getSalesOrderById(999);
    }

    public function testListSalesOrdersFiltersByCreatedByForOwnershipScoping(): void
    {
        $service = $this->makeService();
        $ownOrder = $service->createSalesOrder($this->validInput(), createdBy: 2);
        $service->createSalesOrder($this->validInput(), createdBy: 3);

        $result = $service->listSalesOrders(createdBy: 2);

        self::assertCount(1, $result);
        self::assertSame($ownOrder->id, $result[0]->id);
    }

    public function testListSalesOrdersFiltersByStatus(): void
    {
        $service = $this->makeService();
        $draft = $service->createSalesOrder($this->validInput(), 2);
        $submitted = $service->createSalesOrder($this->validInput(), 2);
        $service->submitForApproval($submitted->id);

        $result = $service->listSalesOrders(status: SalesOrderStatus::PendingApproval);

        self::assertCount(1, $result);
        self::assertSame($submitted->id, $result[0]->id);
    }
}
