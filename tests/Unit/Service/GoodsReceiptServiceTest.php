<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;
use App\Exception\ConflictException;
use App\Exception\ValidationException;
use App\Repository\InMemoryProductStockRepository;
use App\Repository\InMemoryPurchaseOrderRepository;
use App\Repository\InMemoryStockLedgerRepository;
use App\Service\GoodsReceiptService;
use PHPUnit\Framework\TestCase;

/**
 * Cuma menguji computeReceiptPlan() - logic murni, tanpa PDO/koneksi
 * database sungguhan (ARCH-01: business logic harus testable tanpa DB).
 * receive() sendiri (yang butuh transaksi PDO nyata) diuji lewat
 * integration test - lihat tests/Integration/Service/GoodsReceiptServiceTest.
 */
final class GoodsReceiptServiceTest extends TestCase
{
    private function makeService(): GoodsReceiptService
    {
        // PDO tidak pernah dipanggil oleh computeReceiptPlan(), jadi cukup
        // dummy tanpa koneksi nyata - construct tanpa argumen memicu error
        // hanya jika benar-benar dipakai, yang tidak terjadi di test ini.
        $pdo = $this->createStub(\PDO::class);

        return new GoodsReceiptService(
            new InMemoryPurchaseOrderRepository(),
            new InMemoryProductStockRepository(),
            new InMemoryStockLedgerRepository(),
            $pdo,
        );
    }

    private function makePurchaseOrder(PurchaseOrderStatus $status, int $qty = 10, int $receivedQty = 0): PurchaseOrder
    {
        return new PurchaseOrder(1, 1, 1, $status, '2026-09-17', 1, [
            new PurchaseOrderItem(1, 1, 1, $qty, 10000, $receivedQty),
        ]);
    }

    public function testComputeReceiptPlanRejectsDraftStatus(): void
    {
        $service = $this->makeService();
        $po = $this->makePurchaseOrder(PurchaseOrderStatus::Draft);

        $this->expectException(ConflictException::class);

        $service->computeReceiptPlan($po, [1 => 5]);
    }

    public function testComputeReceiptPlanRejectsCancelledStatus(): void
    {
        $service = $this->makeService();
        $po = $this->makePurchaseOrder(PurchaseOrderStatus::Cancelled);

        $this->expectException(ConflictException::class);

        $service->computeReceiptPlan($po, [1 => 5]);
    }

    public function testComputeReceiptPlanAcceptsPartialReceiptOnOrderedStatus(): void
    {
        $service = $this->makeService();
        $po = $this->makePurchaseOrder(PurchaseOrderStatus::Ordered, qty: 10);

        $plan = $service->computeReceiptPlan($po, [1 => 4]);

        self::assertSame(4, $plan[1]['qty']);
        self::assertSame(1, $plan[1]['item']->id);
    }

    public function testComputeReceiptPlanRejectsQtyExceedingRemaining(): void
    {
        $service = $this->makeService();
        $po = $this->makePurchaseOrder(PurchaseOrderStatus::PartiallyReceived, qty: 10, receivedQty: 7);

        $this->expectException(ValidationException::class);

        $service->computeReceiptPlan($po, [1 => 5]);
    }

    public function testComputeReceiptPlanRejectsUnknownItemId(): void
    {
        $service = $this->makeService();
        $po = $this->makePurchaseOrder(PurchaseOrderStatus::Ordered);

        $this->expectException(ValidationException::class);

        $service->computeReceiptPlan($po, [999 => 5]);
    }

    public function testComputeReceiptPlanRejectsWhenNoQtyProvided(): void
    {
        $service = $this->makeService();
        $po = $this->makePurchaseOrder(PurchaseOrderStatus::Ordered);

        $this->expectException(ValidationException::class);

        $service->computeReceiptPlan($po, [1 => 0]);
    }

    public function testComputeReceiptPlanIgnoresZeroQtyRowsWithoutError(): void
    {
        $service = $this->makeService();
        $po = new PurchaseOrder(1, 1, 1, PurchaseOrderStatus::Ordered, '2026-09-17', 1, [
            new PurchaseOrderItem(1, 1, 1, 10, 10000, 0),
            new PurchaseOrderItem(2, 1, 2, 5, 20000, 0),
        ]);

        $plan = $service->computeReceiptPlan($po, [1 => 3, 2 => 0]);

        self::assertCount(1, $plan);
        self::assertArrayHasKey(1, $plan);
        self::assertArrayNotHasKey(2, $plan);
    }
}
