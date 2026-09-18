<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;

final class InMemoryPurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    /** @var array<int, PurchaseOrder> */
    private array $purchaseOrders = [];

    private int $nextId = 1;

    private int $nextItemId = 1;

    /**
     * @param PurchaseOrder[] $purchaseOrders
     */
    public function __construct(array $purchaseOrders = [])
    {
        foreach ($purchaseOrders as $purchaseOrder) {
            $this->purchaseOrders[$purchaseOrder->id] = $purchaseOrder;
            $this->nextId = max($this->nextId, $purchaseOrder->id + 1);
            foreach ($purchaseOrder->items as $item) {
                $this->nextItemId = max($this->nextItemId, $item->id + 1);
            }
        }
    }

    public function findById(int $id): ?PurchaseOrder
    {
        return $this->purchaseOrders[$id] ?? null;
    }

    public function save(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $id = $this->nextId++;
        $items = [];
        foreach ($purchaseOrder->items as $item) {
            $items[] = new PurchaseOrderItem($this->nextItemId++, $id, $item->productId, $item->qty, $item->buyPrice, 0);
        }

        $saved = new PurchaseOrder($id, $purchaseOrder->supplierId, $purchaseOrder->warehouseId, $purchaseOrder->status, $purchaseOrder->orderDate, $purchaseOrder->createdBy, $items);
        $this->purchaseOrders[$id] = $saved;

        return $saved;
    }

    public function updateStatus(int $id, PurchaseOrderStatus $status): void
    {
        $po = $this->purchaseOrders[$id] ?? null;
        if ($po !== null) {
            $this->purchaseOrders[$id] = new PurchaseOrder($po->id, $po->supplierId, $po->warehouseId, $status, $po->orderDate, $po->createdBy, $po->items);
        }
    }

    public function incrementItemReceivedQty(int $itemId, int $delta): void
    {
        foreach ($this->purchaseOrders as $poId => $po) {
            foreach ($po->items as $index => $item) {
                if ($item->id === $itemId) {
                    $items = $po->items;
                    $items[$index] = new PurchaseOrderItem($item->id, $item->purchaseOrderId, $item->productId, $item->qty, $item->buyPrice, $item->receivedQty + $delta);
                    $this->purchaseOrders[$poId] = new PurchaseOrder($po->id, $po->supplierId, $po->warehouseId, $po->status, $po->orderDate, $po->createdBy, $items);

                    return;
                }
            }
        }
    }

    public function listAll(
        ?string $search = null,
        ?PurchaseOrderStatus $status = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'order_date',
        string $sortDir = 'desc',
    ): array {
        $purchaseOrders = $this->filtered($search, $status);

        usort($purchaseOrders, static function (PurchaseOrder $a, PurchaseOrder $b) use ($sortDir): int {
            $result = strcmp($a->orderDate, $b->orderDate) ?: ($a->id <=> $b->id);

            return $sortDir === 'asc' ? $result : -$result;
        });

        return array_slice($purchaseOrders, $offset, $limit);
    }

    public function countAll(?string $search = null, ?PurchaseOrderStatus $status = null): int
    {
        return count($this->filtered($search, $status));
    }

    /**
     * @return PurchaseOrder[]
     */
    private function filtered(?string $search, ?PurchaseOrderStatus $status): array
    {
        $purchaseOrders = array_values($this->purchaseOrders);

        if ($search !== null && $search !== '') {
            $purchaseOrders = array_values(array_filter(
                $purchaseOrders,
                static fn (PurchaseOrder $po): bool => str_contains((string) $po->id, $search),
            ));
        }

        if ($status !== null) {
            $purchaseOrders = array_values(array_filter(
                $purchaseOrders,
                static fn (PurchaseOrder $po): bool => $po->status === $status,
            ));
        }

        return $purchaseOrders;
    }
}
