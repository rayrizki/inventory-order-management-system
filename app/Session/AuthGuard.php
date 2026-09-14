<?php

declare(strict_types=1);

namespace App\Session;

use App\Entity\Role;
use App\Exception\ForbiddenException;
use App\Exception\UnauthenticatedException;

final class AuthGuard
{
    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function requireLogin(): CurrentUser
    {
        $userId = $this->session->get('user_id');
        $name = $this->session->get('user_name');
        $role = $this->session->get('user_role');

        if ($userId === null || $name === null || $role === null) {
            throw new UnauthenticatedException();
        }

        return new CurrentUser((int) $userId, (string) $name, Role::from((string) $role));
    }

    /**
     * @param Role[] $allowed
     */
    public function requireRole(CurrentUser $user, array $allowed): void
    {
        if (!in_array($user->role, $allowed, true)) {
            throw new ForbiddenException();
        }
    }
}
