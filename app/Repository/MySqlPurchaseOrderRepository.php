<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;
use PDO;

final class MySqlPurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    /** FIND-01 untuk PO cuma minta sort tanggal naik/turun - satu-satunya kolom yang di-allowlist. */
    private const SORTABLE_COLUMNS = ['order_date'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?PurchaseOrder
    {
        $statement = $this->pdo->prepare(
            'SELECT id, supplier_id, warehouse_id, status, order_date, created_by FROM purchase_orders WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        $itemStatement = $this->pdo->prepare(
            'SELECT id, purchase_order_id, product_id, qty, buy_price, received_qty
             FROM purchase_order_items WHERE purchase_order_id = :purchase_order_id ORDER BY id'
        );
        $itemStatement->execute(['purchase_order_id' => $id]);
        $items = array_map($this->hydrateItem(...), $itemStatement->fetchAll());

        return $this->hydrate($row, $items);
    }

    public function save(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by)
                 VALUES (:supplier_id, :warehouse_id, :status, :order_date, :created_by)'
            );
            $statement->execute([
                'supplier_id' => $purchaseOrder->supplierId,
                'warehouse_id' => $purchaseOrder->warehouseId,
                'status' => $purchaseOrder->status->value,
                'order_date' => $purchaseOrder->orderDate,
                'created_by' => $purchaseOrder->createdBy,
            ]);
            $poId = (int) $this->pdo->lastInsertId();

            $itemStatement = $this->pdo->prepare(
                'INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price, received_qty)
                 VALUES (:purchase_order_id, :product_id, :qty, :buy_price, 0)'
            );
            $items = [];
            foreach ($purchaseOrder->items as $item) {
                $itemStatement->execute([
                    'purchase_order_id' => $poId,
                    'product_id' => $item->productId,
                    'qty' => $item->qty,
                    'buy_price' => $item->buyPrice,
                ]);
                $items[] = new PurchaseOrderItem((int) $this->pdo->lastInsertId(), $poId, $item->productId, $item->qty, $item->buyPrice, 0);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return new PurchaseOrder($poId, $purchaseOrder->supplierId, $purchaseOrder->warehouseId, $purchaseOrder->status, $purchaseOrder->orderDate, $purchaseOrder->createdBy, $items);
    }

    public function transitionStatus(int $id, array $expected, PurchaseOrderStatus $next): bool
    {
        $placeholders = [];
        $params = ['next' => $next->value, 'id' => $id];
        foreach (array_values($expected) as $index => $status) {
            $placeholders[] = ":expected_$index";
            $params["expected_$index"] = $status->value;
        }

        $statement = $this->pdo->prepare(sprintf(
            'UPDATE purchase_orders SET status = :next WHERE id = :id AND status IN (%s)',
            implode(', ', $placeholders),
        ));
        $statement->execute($params);

        return $statement->rowCount() > 0;
    }

    public function incrementItemReceivedQtyIfWithinOrdered(int $itemId, int $delta): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE purchase_order_items SET received_qty = received_qty + :delta
             WHERE id = :id AND received_qty + :delta_check <= qty'
        );
        $statement->execute(['delta' => $delta, 'id' => $itemId, 'delta_check' => $delta]);

        return $statement->rowCount() > 0;
    }

    public function listAll(
        ?string $search = null,
        ?PurchaseOrderStatus $status = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'order_date',
        string $sortDir = 'desc',
    ): array {
        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'order_date';
        $direction = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';

        [$where, $params] = $this->buildFilter($search, $status);

        $statement = $this->pdo->prepare(
            "SELECT po.id, po.supplier_id, po.warehouse_id, po.status, po.order_date, po.created_by
             FROM purchase_orders po
             JOIN suppliers s ON s.id = po.supplier_id
             {$where}
             ORDER BY po.{$column} {$direction}, po.id {$direction}
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): PurchaseOrder => $this->hydrate($row, []), $statement->fetchAll());
    }

    public function countAll(?string $search = null, ?PurchaseOrderStatus $status = null): int
    {
        [$where, $params] = $this->buildFilter($search, $status);

        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id {$where}"
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function countByStatus(): array
    {
        $counts = array_fill_keys(array_map(static fn (PurchaseOrderStatus $s): string => $s->value, PurchaseOrderStatus::cases()), 0);

        $statement = $this->pdo->query('SELECT status, COUNT(*) AS total FROM purchase_orders GROUP BY status');
        foreach ($statement->fetchAll() as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function listForReport(string $fromDate, string $toDate): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, supplier_id, warehouse_id, status, order_date, created_by
             FROM purchase_orders
             WHERE order_date BETWEEN :from_date AND :to_date
             ORDER BY order_date ASC, id ASC'
        );
        $statement->execute(['from_date' => $fromDate, 'to_date' => $toDate]);

        return array_map(fn (array $row): PurchaseOrder => $this->hydrate($row, []), $statement->fetchAll());
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilter(?string $search, ?PurchaseOrderStatus $status): array
    {
        $conditions = [];
        $params = [];

        if ($search !== null && $search !== '') {
            // FIND-01: "pencarian nomor/pihak terkait" - nomor PO (id) atau
            // nama supplier. Dua placeholder terpisah (native prepares, lihat
            // catatan di repository master data lain untuk alasan lengkap).
            $conditions[] = '(CAST(po.id AS CHAR) LIKE :search_id OR s.name LIKE :search_supplier)';
            $params['search_id'] = '%' . $search . '%';
            $params['search_supplier'] = '%' . $search . '%';
        }

        if ($status !== null) {
            $conditions[] = 'po.status = :status';
            $params['status'] = $status->value;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $row
     * @param PurchaseOrderItem[] $items
     */
    private function hydrate(array $row, array $items): PurchaseOrder
    {
        return new PurchaseOrder(
            id: (int) $row['id'],
            supplierId: (int) $row['supplier_id'],
            warehouseId: (int) $row['warehouse_id'],
            status: PurchaseOrderStatus::from((string) $row['status']),
            orderDate: (string) $row['order_date'],
            createdBy: (int) $row['created_by'],
            items: $items,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateItem(array $row): PurchaseOrderItem
    {
        return new PurchaseOrderItem(
            id: (int) $row['id'],
            purchaseOrderId: (int) $row['purchase_order_id'],
            productId: (int) $row['product_id'],
            qty: (int) $row['qty'],
            buyPrice: (float) $row['buy_price'],
            receivedQty: (int) $row['received_qty'],
        );
    }
}
