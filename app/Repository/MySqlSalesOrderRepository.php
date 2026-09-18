<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\SalesOrderStatus;
use PDO;

final class MySqlSalesOrderRepository implements SalesOrderRepositoryInterface
{
    /** FIND-01 untuk SO cuma minta sort tanggal naik/turun - satu-satunya kolom yang di-allowlist. */
    private const SORTABLE_COLUMNS = ['created_at'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?SalesOrder
    {
        $statement = $this->pdo->prepare(
            'SELECT id, customer_id, warehouse_id, status, created_by, approved_by, created_at FROM sales_orders WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        $itemStatement = $this->pdo->prepare(
            'SELECT id, sales_order_id, product_id, qty, sell_price
             FROM sales_order_items WHERE sales_order_id = :sales_order_id ORDER BY id'
        );
        $itemStatement->execute(['sales_order_id' => $id]);
        $items = array_map($this->hydrateItem(...), $itemStatement->fetchAll());

        return $this->hydrate($row, $items);
    }

    public function save(SalesOrder $salesOrder): SalesOrder
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO sales_orders (customer_id, warehouse_id, status, created_by)
                 VALUES (:customer_id, :warehouse_id, :status, :created_by)'
            );
            $statement->execute([
                'customer_id' => $salesOrder->customerId,
                'warehouse_id' => $salesOrder->warehouseId,
                'status' => $salesOrder->status->value,
                'created_by' => $salesOrder->createdBy,
            ]);
            $soId = (int) $this->pdo->lastInsertId();

            $itemStatement = $this->pdo->prepare(
                'INSERT INTO sales_order_items (sales_order_id, product_id, qty, sell_price)
                 VALUES (:sales_order_id, :product_id, :qty, :sell_price)'
            );
            $items = [];
            foreach ($salesOrder->items as $item) {
                $itemStatement->execute([
                    'sales_order_id' => $soId,
                    'product_id' => $item->productId,
                    'qty' => $item->qty,
                    'sell_price' => $item->sellPrice,
                ]);
                $items[] = new SalesOrderItem((int) $this->pdo->lastInsertId(), $soId, $item->productId, $item->qty, $item->sellPrice);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $saved = $this->findById($soId);
        assert($saved !== null);

        return $saved;
    }

    public function updateStatus(int $id, SalesOrderStatus $status): void
    {
        $statement = $this->pdo->prepare('UPDATE sales_orders SET status = :status WHERE id = :id');
        $statement->execute(['status' => $status->value, 'id' => $id]);
    }

    public function approve(int $id, int $approvedBy): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE sales_orders SET status = :status, approved_by = :approved_by WHERE id = :id'
        );
        $statement->execute(['status' => SalesOrderStatus::Approved->value, 'approved_by' => $approvedBy, 'id' => $id]);
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
        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'created_at';
        $direction = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';

        [$where, $params] = $this->buildFilter($search, $status, $createdBy);

        $statement = $this->pdo->prepare(
            "SELECT so.id, so.customer_id, so.warehouse_id, so.status, so.created_by, so.approved_by, so.created_at
             FROM sales_orders so
             JOIN customers c ON c.id = so.customer_id
             {$where}
             ORDER BY so.{$column} {$direction}, so.id {$direction}
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): SalesOrder => $this->hydrate($row, []), $statement->fetchAll());
    }

    public function countAll(?string $search = null, ?SalesOrderStatus $status = null, ?int $createdBy = null): int
    {
        [$where, $params] = $this->buildFilter($search, $status, $createdBy);

        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM sales_orders so JOIN customers c ON c.id = so.customer_id {$where}"
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function countByStatus(?int $createdBy = null): array
    {
        $counts = array_fill_keys(array_map(static fn (SalesOrderStatus $s): string => $s->value, SalesOrderStatus::cases()), 0);

        $where = $createdBy !== null ? 'WHERE created_by = :created_by' : '';
        $statement = $this->pdo->prepare("SELECT status, COUNT(*) AS total FROM sales_orders {$where} GROUP BY status");
        $statement->execute($createdBy !== null ? ['created_by' => $createdBy] : []);
        foreach ($statement->fetchAll() as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function listForReport(string $fromDate, string $toDate): array
    {
        // Sengaja BUKAN `WHERE DATE(created_at) BETWEEN :from_date AND
        // :to_date` - membungkus kolom dalam fungsi membuat predikat
        // non-sargable (modul SQL Ch1-2/11: MySQL tidak bisa memanfaatkan
        // index apa pun pada created_at kalau kolomnya dievaluasi lewat
        // fungsi per baris). Batas atas dibuat eksklusif satu hari setelah
        // :to_date supaya seluruh baris PADA tanggal :to_date (jam berapa
        // pun) tetap ikut, tanpa perlu membungkus created_at sama sekali.
        $statement = $this->pdo->prepare(
            'SELECT id, customer_id, warehouse_id, status, created_by, approved_by, created_at
             FROM sales_orders
             WHERE created_at >= :from_date AND created_at < DATE_ADD(:to_date, INTERVAL 1 DAY)
             ORDER BY created_at ASC, id ASC'
        );
        $statement->execute(['from_date' => $fromDate, 'to_date' => $toDate]);

        return array_map(fn (array $row): SalesOrder => $this->hydrate($row, []), $statement->fetchAll());
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilter(?string $search, ?SalesOrderStatus $status, ?int $createdBy): array
    {
        $conditions = [];
        $params = [];

        if ($search !== null && $search !== '') {
            // FIND-01: "pencarian nomor/pihak terkait" - nomor SO (id) atau
            // nama customer. Dua placeholder terpisah (native prepares).
            $conditions[] = '(CAST(so.id AS CHAR) LIKE :search_id OR c.name LIKE :search_customer)';
            $params['search_id'] = '%' . $search . '%';
            $params['search_customer'] = '%' . $search . '%';
        }

        if ($status !== null) {
            $conditions[] = 'so.status = :status';
            $params['status'] = $status->value;
        }

        if ($createdBy !== null) {
            $conditions[] = 'so.created_by = :created_by';
            $params['created_by'] = $createdBy;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $row
     * @param SalesOrderItem[] $items
     */
    private function hydrate(array $row, array $items): SalesOrder
    {
        return new SalesOrder(
            id: (int) $row['id'],
            customerId: (int) $row['customer_id'],
            warehouseId: (int) $row['warehouse_id'],
            status: SalesOrderStatus::from((string) $row['status']),
            createdBy: (int) $row['created_by'],
            approvedBy: $row['approved_by'] !== null ? (int) $row['approved_by'] : null,
            createdAt: $row['created_at'] !== null ? (string) $row['created_at'] : null,
            items: $items,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateItem(array $row): SalesOrderItem
    {
        return new SalesOrderItem(
            id: (int) $row['id'],
            salesOrderId: (int) $row['sales_order_id'],
            productId: (int) $row['product_id'],
            qty: (int) $row['qty'],
            sellPrice: (float) $row['sell_price'],
        );
    }
}
