<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Role;
use App\Entity\User;
use PDO;

final class MySqlUserRepository implements UserRepositoryInterface
{
    /** Kolom yang boleh dipakai untuk ORDER BY - nama kolom tidak bisa di-bind lewat prepared statement. */
    private const SORTABLE_COLUMNS = ['name', 'email'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = :email'
        );
        $statement->execute(['email' => $email]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findById(int $id): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, email, password_hash, role, is_active FROM users WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(User $user): User
    {
        if ($user->id === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (:name, :email, :password_hash, :role, :is_active)'
            );
            $statement->execute([
                'name' => $user->name,
                'email' => $user->email,
                'password_hash' => $user->passwordHash,
                'role' => $user->role->value,
                'is_active' => (int) $user->isActive,
            ]);

            return new User((int) $this->pdo->lastInsertId(), $user->name, $user->email, $user->passwordHash, $user->role, $user->isActive);
        }

        $statement = $this->pdo->prepare(
            'UPDATE users SET name = :name, email = :email, password_hash = :password_hash, role = :role WHERE id = :id'
        );
        $statement->execute([
            'name' => $user->name,
            'email' => $user->email,
            'password_hash' => $user->passwordHash,
            'role' => $user->role->value,
            'id' => $user->id,
        ]);

        return $user;
    }

    public function setActive(int $id, bool $isActive): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
        $statement->execute(['is_active' => (int) $isActive, 'id' => $id]);
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
            "SELECT id, name, email, password_hash, role, is_active FROM users {$where}
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

        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM users {$where}");
        $statement->execute($params);

        return (int) $statement->fetchColumn();
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
            $conditions[] = '(name LIKE :search_name OR email LIKE :search_email)';
            $params['search_name'] = '%' . $search . '%';
            $params['search_email'] = '%' . $search . '%';
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
    private function hydrate(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            name: (string) $row['name'],
            email: (string) $row['email'],
            passwordHash: (string) $row['password_hash'],
            role: Role::from((string) $row['role']),
            isActive: (bool) $row['is_active'],
        );
    }
}
