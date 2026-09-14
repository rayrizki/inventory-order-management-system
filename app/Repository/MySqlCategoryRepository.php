<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use PDO;

final class MySqlCategoryRepository implements CategoryRepositoryInterface
{
    /** Kolom yang boleh dipakai untuk ORDER BY - nama kolom tidak bisa di-bind lewat prepared statement. */
    private const SORTABLE_COLUMNS = ['name', 'description'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?Category
    {
        $statement = $this->pdo->prepare('SELECT id, name, description FROM categories WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(Category $category): Category
    {
        if ($category->id === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO categories (name, description) VALUES (:name, :description)'
            );
            $statement->execute([
                'name' => $category->name,
                'description' => $category->description,
            ]);

            return new Category((int) $this->pdo->lastInsertId(), $category->name, $category->description);
        }

        $statement = $this->pdo->prepare(
            'UPDATE categories SET name = :name, description = :description WHERE id = :id'
        );
        $statement->execute([
            'name' => $category->name,
            'description' => $category->description,
            'id' => $category->id,
        ]);

        return $category;
    }

    public function listAll(
        ?string $search = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        if ($search === null || $search === '') {
            $statement = $this->pdo->prepare(
                "SELECT id, name, description FROM categories ORDER BY {$column} {$direction} LIMIT :limit OFFSET :offset"
            );
            $statement->bindValue('limit', $limit, PDO::PARAM_INT);
            $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        } else {
            $statement = $this->pdo->prepare(
                "SELECT id, name, description FROM categories WHERE name LIKE :search
                 ORDER BY {$column} {$direction} LIMIT :limit OFFSET :offset"
            );
            $statement->bindValue('search', '%' . $search . '%');
            $statement->bindValue('limit', $limit, PDO::PARAM_INT);
            $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        }

        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    public function countAll(?string $search = null): int
    {
        if ($search === null || $search === '') {
            $statement = $this->pdo->query('SELECT COUNT(*) FROM categories');
        } else {
            $statement = $this->pdo->prepare('SELECT COUNT(*) FROM categories WHERE name LIKE :search');
            $statement->execute(['search' => '%' . $search . '%']);
        }

        return (int) $statement->fetchColumn();
    }

    public function isInUse(int $id): bool
    {
        $statement = $this->pdo->prepare('SELECT EXISTS(SELECT 1 FROM products WHERE category_id = :id) AS in_use');
        $statement->execute(['id' => $id]);

        return (bool) $statement->fetchColumn();
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Category
    {
        return new Category(
            id: (int) $row['id'],
            name: (string) $row['name'],
            description: $row['description'] !== null ? (string) $row['description'] : null,
        );
    }
}
