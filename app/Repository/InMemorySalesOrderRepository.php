<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\SalesOrderStatus;

final class InMemorySalesOrderRepository implements SalesOrderRepositoryInterface
{
    /** @var array<int, SalesOrder> */
    private array $salesOrders = [];

    private int $nextId = 1;

    private int $nextItemId = 1;

    /**
     * @param SalesOrder[] $salesOrders
     */
    public function __construct(array $salesOrders = [])
    {
        foreach ($salesOrders as $salesOrder) {
            $this->salesOrders[$salesOrder->id] = $salesOrder;
            $this->nextId = max($this->nextId, $salesOrder->id + 1);
            foreach ($salesOrder->items as $item) {
                $this->nextItemId = max($this->nextItemId, $item->id + 1);
            }
        }
    }

    public function findById(int $id): ?SalesOrder
    {
        return $this->salesOrders[$id] ?? null;
    }

    public function save(SalesOrder $salesOrder): SalesOrder
    {
        $id = $this->nextId++;
        $items = [];
        foreach ($salesOrder->items as $item) {
            $items[] = new SalesOrderItem($this->nextItemId++, $id, $item->productId, $item->qty, $item->sellPrice);
        }

        $saved = new SalesOrder($id, $salesOrder->customerId, $salesOrder->warehouseId, $salesOrder->status, $salesOrder->createdBy, $salesOrder->approvedBy, $salesOrder->createdAt, $items);
        $this->salesOrders[$id] = $saved;

        return $saved;
    }

    public function transitionStatus(int $id, array $expected, SalesOrderStatus $next): bool
    {
        $so = $this->salesOrders[$id] ?? null;
        if ($so === null || !in_array($so->status, $expected, true)) {
            return false;
        }

        $this->salesOrders[$id] = new SalesOrder($so->id, $so->customerId, $so->warehouseId, $next, $so->createdBy, $so->approvedBy, $so->createdAt, $so->items);

        return true;
    }

    public function approve(int $id, int $approvedBy): bool
    {
        $so = $this->salesOrders[$id] ?? null;
        if ($so === null || $so->status !== SalesOrderStatus::PendingApproval) {
            return false;
        }

        $this->salesOrders[$id] = new SalesOrder($so->id, $so->customerId, $so->warehouseId, SalesOrderStatus::Approved, $so->createdBy, $approvedBy, $so->createdAt, $so->items);

        return true;
    }

    public function listAll(
        ?string $search = null,
        ?SalesOrderStatus $status = null,
        ?int $createdBy = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
    ): array {
        $salesOrders = $this->filtered($search, $status, $createdBy);

        usort($salesOrders, static function (SalesOrder $a, SalesOrder $b) use ($sortDir): int {
            $result = $a->id <=> $b->id;

            return $sortDir === 'asc' ? $result : -$result;
        });

        return array_slice($salesOrders, $offset, $limit);
    }

    public function countAll(?string $search = null, ?SalesOrderStatus $status = null, ?int $createdBy = null): int
    {
        return count($this->filtered($search, $status, $createdBy));
    }

    public function countByStatus(?int $createdBy = null): array
    {
        $counts = array_fill_keys(array_map(static fn (SalesOrderStatus $s): string => $s->value, SalesOrderStatus::cases()), 0);
        foreach ($this->salesOrders as $so) {
            if ($createdBy !== null && $so->createdBy !== $createdBy) {
                continue;
            }
            $counts[$so->status->value]++;
        }

        return $counts;
    }

    public function listForReport(string $fromDate, string $toDate): array
    {
        return array_values(array_filter(
            $this->salesOrders,
            static fn (SalesOrder $so): bool => substr((string) $so->createdAt, 0, 10) >= $fromDate && substr((string) $so->createdAt, 0, 10) <= $toDate,
        ));
    }

    /**
     * @return SalesOrder[]
     */
    private function filtered(?string $search, ?SalesOrderStatus $status, ?int $createdBy): array
    {
        $salesOrders = array_values($this->salesOrders);

        if ($search !== null && $search !== '') {
            $salesOrders = array_values(array_filter(
                $salesOrders,
                static fn (SalesOrder $so): bool => str_contains((string) $so->id, $search),
            ));
        }

        if ($status !== null) {
            $salesOrders = array_values(array_filter(
                $salesOrders,
                static fn (SalesOrder $so): bool => $so->status === $status,
            ));
        }

        if ($createdBy !== null) {
            $salesOrders = array_values(array_filter(
                $salesOrders,
                static fn (SalesOrder $so): bool => $so->createdBy === $createdBy,
            ));
        }

        return $salesOrders;
    }
}
