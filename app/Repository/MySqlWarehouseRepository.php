<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Warehouse;
use PDO;

final class MySqlWarehouseRepository implements WarehouseRepositoryInterface
{
    /** Kolom yang boleh dipakai untuk ORDER BY - nama kolom tidak bisa di-bind lewat prepared statement. */
    private const SORTABLE_COLUMNS = ['name', 'location'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?Warehouse
    {
        $statement = $this->pdo->prepare('SELECT id, name, location, is_active FROM warehouses WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(Warehouse $warehouse): Warehouse
    {
        if ($warehouse->id === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO warehouses (name, location, is_active) VALUES (:name, :location, :is_active)'
            );
            $statement->execute([
                'name' => $warehouse->name,
                'location' => $warehouse->location,
                'is_active' => (int) $warehouse->isActive,
            ]);

            return new Warehouse((int) $this->pdo->lastInsertId(), $warehouse->name, $warehouse->location, $warehouse->isActive);
        }

        $statement = $this->pdo->prepare(
            'UPDATE warehouses SET name = :name, location = :location WHERE id = :id'
        );
        $statement->execute([
            'name' => $warehouse->name,
            'location' => $warehouse->location,
            'id' => $warehouse->id,
        ]);

        return $warehouse;
    }

    public function listAll(
        ?string $search = null,
        ?bool $isActive = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        [$where, $params] = $this->buildFilter($search, $isActive);

        $statement = $this->pdo->prepare(
            "SELECT id, name, location, is_active FROM warehouses {$where}
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

    public function countAll(?string $search = null, ?bool $isActive = null): int
    {
        [$where, $params] = $this->buildFilter($search, $isActive);

        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM warehouses {$where}");
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function setActive(int $id, bool $isActive): void
    {
        $statement = $this->pdo->prepare('UPDATE warehouses SET is_active = :is_active WHERE id = :id');
        $statement->execute([
            'is_active' => (int) $isActive,
            'id' => $id,
        ]);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilter(?string $search, ?bool $isActive): array
    {
        $conditions = [];
        $params = [];

        if ($search !== null && $search !== '') {
            // Placeholder ditulis dua kali (bukan dipakai ulang) karena koneksi
            // ini pakai native prepared statement (EMULATE_PREPARES => false) -
            // MySQL native tidak mendukung binding satu named placeholder ke
            // lebih dari satu posisi dalam query yang sama.
            $conditions[] = '(name LIKE :search_name OR location LIKE :search_location)';
            $params['search_name'] = '%' . $search . '%';
            $params['search_location'] = '%' . $search . '%';
        }

        if ($isActive !== null) {
            $conditions[] = 'is_active = :is_active';
            $params['is_active'] = (int) $isActive;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Warehouse
    {
        return new Warehouse(
            id: (int) $row['id'],
            name: (string) $row['name'],
            location: $row['location'] !== null ? (string) $row['location'] : null,
            isActive: (bool) $row['is_active'],
        );
    }
}
