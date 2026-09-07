<?php

declare(strict_types=1);

namespace App\Controller;

class DashboardController
{
    public function index(): void
    {
        require __DIR__ . '/../../views/dashboard/index.php';
    }
}
