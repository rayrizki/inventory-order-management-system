<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Service\DashboardService;
use App\Session\AuthGuard;

final class DashboardController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly AuthGuard $guard,
    ) {
    }

    public function index(): void
    {
        $currentUser = $this->guard->requireLogin();

        // DASH-01: "Admin melihat ... Sales melihat ringkasan order
        // miliknya ... Warehouse Staff melihat antrean goods receipt/issue
        // dan produk low-stock" - tiga bentuk ringkasan berbeda per role,
        // dipilih di sini (Controller yang tahu identitas/role), bukan di
        // Service (DashboardService tidak tahu apa-apa soal otorisasi).
        $summary = match ($currentUser->role) {
            Role::Admin => $this->dashboardService->getAdminSummary(),
            Role::Sales => $this->dashboardService->getSalesSummary($currentUser->id),
            Role::WarehouseStaff => $this->dashboardService->getWarehouseSummary(),
        };

        require __DIR__ . '/../../views/dashboard/index.php';
    }
}
