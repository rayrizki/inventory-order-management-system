<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use PDO;

final class MySqlCustomerRepository implements CustomerRepositoryInterface
{
    /** Kolom yang boleh dipakai untuk ORDER BY - nama kolom tidak bisa di-bind lewat prepared statement. */
    private const SORTABLE_COLUMNS = ['name', 'contact'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?Customer
    {
        $statement = $this->pdo->prepare('SELECT id, name, contact, address, is_active FROM customers WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(Customer $customer): Customer
    {
        if ($customer->id === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO customers (name, contact, address, is_active) VALUES (:name, :contact, :address, :is_active)'
            );
            $statement->execute([
                'name' => $customer->name,
                'contact' => $customer->contact,
                'address' => $customer->address,
                'is_active' => (int) $customer->isActive,
            ]);

            return new Customer(
                (int) $this->pdo->lastInsertId(),
                $customer->name,
                $customer->contact,
                $customer->address,
                $customer->isActive,
            );
        }

        $statement = $this->pdo->prepare(
            'UPDATE customers SET name = :name, contact = :contact, address = :address WHERE id = :id'
        );
        $statement->execute([
            'name' => $customer->name,
            'contact' => $customer->contact,
            'address' => $customer->address,
            'id' => $customer->id,
        ]);

        return $customer;
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
            "SELECT id, name, contact, address, is_active FROM customers {$where}
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

        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM customers {$where}");
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function setActive(int $id, bool $isActive): void
    {
        $statement = $this->pdo->prepare('UPDATE customers SET is_active = :is_active WHERE id = :id');
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
            // Placeholder ditulis tiga kali (bukan dipakai ulang) karena koneksi
            // ini pakai native prepared statement (EMULATE_PREPARES => false) -
            // MySQL native tidak mendukung binding satu named placeholder ke
            // lebih dari satu posisi dalam query yang sama.
            $conditions[] = '(name LIKE :search_name OR contact LIKE :search_contact OR address LIKE :search_address)';
            $params['search_name'] = '%' . $search . '%';
            $params['search_contact'] = '%' . $search . '%';
            $params['search_address'] = '%' . $search . '%';
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
    private function hydrate(array $row): Customer
    {
        return new Customer(
            id: (int) $row['id'],
            name: (string) $row['name'],
            contact: $row['contact'] !== null ? (string) $row['contact'] : null,
            address: $row['address'] !== null ? (string) $row['address'] : null,
            isActive: (bool) $row['is_active'],
        );
    }
}
