<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use PDO;

final class MySqlProductRepository implements ProductRepositoryInterface
{
    private const COLUMNS = 'id, sku, name, category_id, unit, buy_price, sell_price, reorder_point, image_path, is_active';

    /** Kolom yang boleh dipakai untuk ORDER BY - nama kolom tidak bisa di-bind lewat prepared statement. */
    private const SORTABLE_COLUMNS = ['sku', 'name'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?Product
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM products WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findBySku(string $sku): ?Product
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM products WHERE sku = :sku');
        $statement->execute(['sku' => $sku]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(Product $product): Product
    {
        if ($product->id === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO products (sku, name, category_id, unit, buy_price, sell_price, reorder_point, image_path, is_active)
                 VALUES (:sku, :name, :category_id, :unit, :buy_price, :sell_price, :reorder_point, :image_path, :is_active)'
            );
            $statement->execute($this->bindings($product));

            return new Product(
                (int) $this->pdo->lastInsertId(),
                $product->sku,
                $product->name,
                $product->categoryId,
                $product->unit,
                $product->buyPrice,
                $product->sellPrice,
                $product->reorderPoint,
                $product->imagePath,
                $product->isActive,
            );
        }

        $statement = $this->pdo->prepare(
            'UPDATE products SET sku = :sku, name = :name, category_id = :category_id, unit = :unit,
                buy_price = :buy_price, sell_price = :sell_price, reorder_point = :reorder_point,
                image_path = :image_path
             WHERE id = :id'
        );
        $statement->execute([
            'sku' => $product->sku,
            'name' => $product->name,
            'category_id' => $product->categoryId,
            'unit' => $product->unit,
            'buy_price' => $product->buyPrice,
            'sell_price' => $product->sellPrice,
            'reorder_point' => $product->reorderPoint,
            'image_path' => $product->imagePath,
            'id' => $product->id,
        ]);

        return $product;
    }

    public function listAll(
        ?string $search = null,
        ?int $categoryId = null,
        ?bool $isActive = null,
        ?string $stockStatus = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? 'p.' . $sortBy : 'p.name';
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        [$where, $having, $params] = $this->buildFilter($search, $categoryId, $isActive, $stockStatus);

        // LEFT JOIN + GROUP BY dipakai untuk SEMUA query (bukan cuma saat
        // stockStatus diisi) supaya cuma ada satu bentuk query untuk
        // listAll() - stockStatus null tetap benar (HAVING kosong berarti
        // seluruh grup lolos), lebih sederhana daripada dua jalur SQL
        // terpisah yang bisa saling menyimpang.
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.sku, p.name, p.category_id, p.unit, p.buy_price, p.sell_price, p.reorder_point, p.image_path, p.is_active
             FROM products p
             LEFT JOIN product_stock ps ON ps.product_id = p.id
             ' . $where . '
             GROUP BY p.id
             ' . $having . "
             ORDER BY {$column} {$direction}, p.id {$direction}
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    public function countAll(?string $search = null, ?int $categoryId = null, ?bool $isActive = null, ?string $stockStatus = null): int
    {
        [$where, $having, $params] = $this->buildFilter($search, $categoryId, $isActive, $stockStatus);

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM (
                SELECT p.id
                FROM products p
                LEFT JOIN product_stock ps ON ps.product_id = p.id
                ' . $where . '
                GROUP BY p.id
                ' . $having . '
             ) counted'
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function setActive(int $id, bool $isActive): void
    {
        $statement = $this->pdo->prepare('UPDATE products SET is_active = :is_active WHERE id = :id');
        $statement->execute([
            'is_active' => (int) $isActive,
            'id' => $id,
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function buildFilter(?string $search, ?int $categoryId, ?bool $isActive, ?string $stockStatus = null): array
    {
        $conditions = [];
        $params = [];

        if ($search !== null && $search !== '') {
            // Placeholder ditulis dua kali (bukan dipakai ulang) karena koneksi
            // ini pakai native prepared statement (EMULATE_PREPARES => false) -
            // MySQL native tidak mendukung binding satu named placeholder ke
            // lebih dari satu posisi dalam query yang sama.
            $conditions[] = '(p.sku LIKE :search_sku OR p.name LIKE :search_name)';
            $params['search_sku'] = '%' . $search . '%';
            $params['search_name'] = '%' . $search . '%';
        }

        if ($categoryId !== null) {
            $conditions[] = 'p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        if ($isActive !== null) {
            $conditions[] = 'p.is_active = :is_active';
            $params['is_active'] = (int) $isActive;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        // HAVING (bukan WHERE) karena total stok adalah hasil agregasi
        // (SUM lintas baris product_stock per produk) - baru bisa dievaluasi
        // setelah GROUP BY, tidak bisa difilter di level baris mentah.
        // COALESCE(SUM(...), 0) diperlukan karena produk tanpa baris
        // product_stock sama sekali (belum pernah ada goods receipt) harus
        // dianggap stok 0 (LEFT JOIN menghasilkan NULL, bukan 0).
        // p.reorder_point dibungkus MAX() sekalipun sudah functionally
        // dependent ke p.id (primary key, sudah di GROUP BY) - MySQL HANYA
        // mengizinkan functional dependency itu di SELECT list, HAVING
        // tetap menolak kolom non-agregat yang tidak eksplisit ada di
        // GROUP BY (`Unknown column` 1054, bukan error functional
        // dependency yang lebih jelas - ditemukan lewat reproduksi manual).
        $having = match ($stockStatus) {
            'low' => 'HAVING COALESCE(SUM(ps.quantity), 0) < MAX(p.reorder_point)',
            'normal' => 'HAVING COALESCE(SUM(ps.quantity), 0) >= MAX(p.reorder_point)',
            default => '',
        };

        return [$where, $having, $params];
    }

    /**
     * @return array<string, mixed>
     */
    private function bindings(Product $product): array
    {
        return [
            'sku' => $product->sku,
            'name' => $product->name,
            'category_id' => $product->categoryId,
            'unit' => $product->unit,
            'buy_price' => $product->buyPrice,
            'sell_price' => $product->sellPrice,
            'reorder_point' => $product->reorderPoint,
            'image_path' => $product->imagePath,
            'is_active' => (int) $product->isActive,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Product
    {
        return new Product(
            id: (int) $row['id'],
            sku: (string) $row['sku'],
            name: (string) $row['name'],
            categoryId: (int) $row['category_id'],
            unit: (string) $row['unit'],
            buyPrice: (float) $row['buy_price'],
            sellPrice: (float) $row['sell_price'],
            reorderPoint: (int) $row['reorder_point'],
            imagePath: $row['image_path'] !== null ? (string) $row['image_path'] : null,
            isActive: (bool) $row['is_active'],
        );
    }
}
