<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    private int $nextId = 1;

    /**
     * @param User[] $users
     */
    public function __construct(private array $users = [])
    {
        foreach ($users as $user) {
            $this->nextId = max($this->nextId, $user->id + 1);
        }
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            // strcasecmp, bukan ===: kolom users.email memakai collation
            // utf8mb4 bawaan MySQL 8 yang TIDAK membedakan huruf besar/kecil,
            // dan unique key-nya ikut aturan itu. Fake yang case-sensitive
            // akan meloloskan "Admin@iom.test" saat "admin@iom.test" sudah
            // ada - unit test hijau, lalu INSERT sungguhan gagal di produksi.
            // Pola yang sama sudah dipakai InMemoryProductRepository::findBySku().
            if (strcasecmp($user->email, $email) === 0) {
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

    public function save(User $user): User
    {
        if ($user->id === null) {
            $user = new User($this->nextId++, $user->name, $user->email, $user->passwordHash, $user->role, $user->isActive);
        }

        foreach ($this->users as $index => $existing) {
            if ($existing->id === $user->id) {
                $this->users[$index] = $user;

                return $user;
            }
        }

        $this->users[] = $user;

        return $user;
    }

    public function setActive(int $id, bool $isActive): void
    {
        foreach ($this->users as $index => $user) {
            if ($user->id === $id) {
                $this->users[$index] = new User($user->id, $user->name, $user->email, $user->passwordHash, $user->role, $isActive);

                return;
            }
        }
    }

    public function listAll(
        ?string $search = null,
        ?bool $isActive = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $users = $this->filtered($search, $isActive);

        usort($users, static function (User $a, User $b) use ($sortBy, $sortDir): int {
            $valueA = $sortBy === 'email' ? $a->email : $a->name;
            $valueB = $sortBy === 'email' ? $b->email : $b->name;
            $result = strcasecmp($valueA, $valueB);

            return $sortDir === 'desc' ? -$result : $result;
        });

        return array_slice($users, $offset, $limit);
    }

    public function countAll(?string $search = null, ?bool $isActive = null): int
    {
        return count($this->filtered($search, $isActive));
    }

    /**
     * @return User[]
     */
    private function filtered(?string $search, ?bool $isActive): array
    {
        $users = array_values($this->users);

        if ($search !== null && $search !== '') {
            $users = array_values(array_filter(
                $users,
                static fn (User $user): bool => stripos($user->name, $search) !== false
                    || stripos($user->email, $search) !== false,
            ));
        }

        if ($isActive !== null) {
            $users = array_values(array_filter(
                $users,
                static fn (User $user): bool => $user->isActive === $isActive,
            ));
        }

        return $users;
    }
}
