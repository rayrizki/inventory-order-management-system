<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;
use App\Entity\Supplier;
use App\Entity\Warehouse;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\InMemoryProductRepository;
use App\Repository\InMemoryPurchaseOrderRepository;
use App\Repository\InMemorySupplierRepository;
use App\Repository\InMemoryWarehouseRepository;
use App\Service\PurchaseOrderService;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderServiceTest extends TestCase
{
    private function makeService(?InMemoryPurchaseOrderRepository $purchaseOrders = null): PurchaseOrderService
    {
        return new PurchaseOrderService(
            $purchaseOrders ?? new InMemoryPurchaseOrderRepository(),
            new InMemorySupplierRepository([new Supplier(1, 'Supplier Satu', null, null, true)]),
            new InMemoryWarehouseRepository([new Warehouse(1, 'Gudang Pusat', null, true)]),
            new InMemoryProductRepository([new Product(1, 'SKU-001', 'Kabel HDMI', 1, 'pcs', 10000, 15000, 5, null, true)]),
        );
    }

    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => '1',
            'warehouse_id' => '1',
            'order_date' => '2026-09-17',
            'items' => [
                ['product_id' => '1', 'qty' => '10', 'buy_price' => '10000'],
            ],
        ], $overrides);
    }

    public function testCreatePurchaseOrderSucceedsAsDraftWithItems(): void
    {
        $service = $this->makeService();

        $po = $service->createPurchaseOrder($this->validInput(), createdBy: 99);

        self::assertNotNull($po->id);
        self::assertSame(PurchaseOrderStatus::Draft, $po->status);
        self::assertCount(1, $po->items);
        self::assertSame(10, $po->items[0]->qty);
        self::assertSame(0, $po->items[0]->receivedQty);
    }

    public function testCreatePurchaseOrderRejectsUnknownSupplierAndWarehouse(): void
    {
        $service = $this->makeService();

        try {
            $service->createPurchaseOrder($this->validInput(['supplier_id' => '999', 'warehouse_id' => '888']), 1);
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            self::assertArrayHasKey('supplier_id', $errors);
            self::assertArrayHasKey('warehouse_id', $errors);
        }
    }

    public function testCreatePurchaseOrderRejectsEmptyItems(): void
    {
        $service = $this->makeService();

        $this->expectException(ValidationException::class);

        $service->createPurchaseOrder($this->validInput(['items' => []]), 1);
    }

    public function testCreatePurchaseOrderRejectsInvalidItemRowWithPerRowErrorKey(): void
    {
        $service = $this->makeService();

        try {
            $service->createPurchaseOrder($this->validInput([
                'items' => [['product_id' => '999', 'qty' => '-5', 'buy_price' => '-1']],
            ]), 1);
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            self::assertArrayHasKey('items.0.product_id', $errors);
            self::assertArrayHasKey('items.0.qty', $errors);
            self::assertArrayHasKey('items.0.buy_price', $errors);
        }
    }

    public function testCreatePurchaseOrderRejectsInvalidDateFormat(): void
    {
        $service = $this->makeService();

        $this->expectException(ValidationException::class);

        $service->createPurchaseOrder($this->validInput(['order_date' => '17-09-2026']), 1);
    }

    public function testMarkOrderedTransitionsDraftToOrdered(): void
    {
        $service = $this->makeService();
        $po = $service->createPurchaseOrder($this->validInput(), 1);

        $updated = $service->markOrdered($po->id);

        self::assertSame(PurchaseOrderStatus::Ordered, $updated->status);
    }

    public function testMarkOrderedRejectsNonDraftStatus(): void
    {
        $service = $this->makeService();
        $po = $service->createPurchaseOrder($this->validInput(), 1);
        $service->markOrdered($po->id);

        $this->expectException(ConflictException::class);

        $service->markOrdered($po->id);
    }

    public function testCancelAllowedBeforeReceivedButNotAfter(): void
    {
        $purchaseOrders = new InMemoryPurchaseOrderRepository([
            new PurchaseOrder(1, 1, 1, PurchaseOrderStatus::Received, '2026-09-17', 1, [
                new PurchaseOrderItem(1, 1, 1, 10, 10000, 10),
            ]),
        ]);
        $service = $this->makeService($purchaseOrders);

        $this->expectException(ConflictException::class);

        $service->cancel(1);
    }

    public function testCancelSucceedsFromOrderedStatus(): void
    {
        $service = $this->makeService();
        $po = $service->createPurchaseOrder($this->validInput(), 1);
        $service->markOrdered($po->id);

        $cancelled = $service->cancel($po->id);

        self::assertSame(PurchaseOrderStatus::Cancelled, $cancelled->status);
    }

    public function testGetPurchaseOrderByIdThrowsNotFoundForUnknownId(): void
    {
        $service = $this->makeService();

        $this->expectException(NotFoundException::class);

        $service->getPurchaseOrderById(999);
    }

    public function testListPurchaseOrdersFiltersByStatus(): void
    {
        $service = $this->makeService();
        $draft = $service->createPurchaseOrder($this->validInput(), 1);
        $ordered = $service->createPurchaseOrder($this->validInput(), 1);
        $service->markOrdered($ordered->id);

        $result = $service->listPurchaseOrders(status: PurchaseOrderStatus::Ordered);

        self::assertCount(1, $result);
        self::assertSame($ordered->id, $result[0]->id);
    }
}
