<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\SalesOrderStatus;
use App\Exception\ConflictException;
use App\Repository\InMemoryProductStockRepository;
use App\Repository\InMemorySalesOrderRepository;
use App\Repository\InMemoryStockLedgerRepository;
use App\Service\GoodsIssueService;
use PHPUnit\Framework\TestCase;

/**
 * Cuma menguji assertCanIssue() - logic murni, tanpa PDO/koneksi database
 * sungguhan (ARCH-01). issue() sendiri (yang butuh transaksi PDO nyata dan
 * membuktikan pencegahan oversell) diuji lewat integration test - lihat
 * tests/Integration/Service/GoodsIssueServiceTest.
 */
final class GoodsIssueServiceTest extends TestCase
{
    private function makeService(): GoodsIssueService
    {
        $pdo = $this->createStub(\PDO::class);

        return new GoodsIssueService(
            new InMemorySalesOrderRepository(),
            new InMemoryProductStockRepository(),
            new InMemoryStockLedgerRepository(),
            $pdo,
        );
    }

    private function makeSalesOrder(SalesOrderStatus $status): SalesOrder
    {
        return new SalesOrder(1, 1, 1, $status, 2, null, null, [
            new SalesOrderItem(1, 1, 1, 5, 15000),
        ]);
    }

    public function testAssertCanIssueAcceptsApprovedStatus(): void
    {
        $service = $this->makeService();

        $service->assertCanIssue($this->makeSalesOrder(SalesOrderStatus::Approved));

        $this->addToAssertionCount(1);
    }

    public function testAssertCanIssueRejectsDraftStatus(): void
    {
        $service = $this->makeService();

        $this->expectException(ConflictException::class);

        $service->assertCanIssue($this->makeSalesOrder(SalesOrderStatus::Draft));
    }

    public function testAssertCanIssueRejectsPendingApprovalStatus(): void
    {
        $service = $this->makeService();

        $this->expectException(ConflictException::class);

        $service->assertCanIssue($this->makeSalesOrder(SalesOrderStatus::PendingApproval));
    }

    public function testAssertCanIssueRejectsAlreadyFulfilledStatus(): void
    {
        $service = $this->makeService();

        $this->expectException(ConflictException::class);

        $service->assertCanIssue($this->makeSalesOrder(SalesOrderStatus::Fulfilled));
    }

    public function testAssertCanIssueRejectsCancelledStatus(): void
    {
        $service = $this->makeService();

        $this->expectException(ConflictException::class);

        $service->assertCanIssue($this->makeSalesOrder(SalesOrderStatus::Cancelled));
    }
}
