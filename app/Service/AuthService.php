<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;

final class AuthService
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function authenticate(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !$user->isActive) {
            return null;
        }

        return $user->verifyPassword($password) ? $user : null;
    }
}
