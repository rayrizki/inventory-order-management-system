<?php

declare(strict_types=1);

namespace App\Controller;

class AuthController
{
    public function showLoginForm(): void
    {
        require __DIR__ . '/../../views/auth/login.php';
    }
}
