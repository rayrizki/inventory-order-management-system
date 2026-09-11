<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Role;
use App\Entity\User;
use PDO;

final class MySqlUserRepository implements UserRepositoryInterface
{
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
