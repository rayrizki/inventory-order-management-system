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
                buy_price = :buy_price, sell_price = :sell_price, reorder_point = :reorder_point
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
            'id' => $product->id,
        ]);

        return $product;
    }

    public function listAll(
        ?string $search = null,
        ?int $categoryId = null,
        ?bool $isActive = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        [$where, $params] = $this->buildFilter($search, $categoryId, $isActive);

        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . " FROM products {$where}
             ORDER BY {$column} {$direction} LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    public function countAll(?string $search = null, ?int $categoryId = null, ?bool $isActive = null): int
    {
        [$where, $params] = $this->buildFilter($search, $categoryId, $isActive);

        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM products {$where}");
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
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilter(?string $search, ?int $categoryId, ?bool $isActive): array
    {
        $conditions = [];
        $params = [];

        if ($search !== null && $search !== '') {
            // Placeholder ditulis dua kali (bukan dipakai ulang) karena koneksi
            // ini pakai native prepared statement (EMULATE_PREPARES => false) -
            // MySQL native tidak mendukung binding satu named placeholder ke
            // lebih dari satu posisi dalam query yang sama.
            $conditions[] = '(sku LIKE :search_sku OR name LIKE :search_name)';
            $params['search_sku'] = '%' . $search . '%';
            $params['search_name'] = '%' . $search . '%';
        }

        if ($categoryId !== null) {
            $conditions[] = 'category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        if ($isActive !== null) {
            $conditions[] = 'is_active = :is_active';
            $params['is_active'] = (int) $isActive;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
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
