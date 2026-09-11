<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /**
     * @param User[] $users
     */
    public function __construct(private array $users = [])
    {
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }

    public function findById(int $id): ?User
    {
        foreach ($this->users as $user) {
            if ($user->id === $id) {
                return $user;
            }
        }

        return null;
    }
}
