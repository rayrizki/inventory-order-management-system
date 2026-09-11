<?php

declare(strict_types=1);

namespace App\Controller;

use App\Session\AuthGuard;

final class DashboardController
{
    public function __construct(private readonly AuthGuard $guard)
    {
    }

    public function index(): void
    {
        $currentUser = $this->guard->requireLogin();

        require __DIR__ . '/../../views/dashboard/index.php';
    }
}
