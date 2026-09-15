<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Exception\ValidationException;
use App\Service\WarehouseService;
use App\Session\AuthGuard;

final class WarehouseController
{
    /** Pilihan baris per halaman yang boleh diminta lewat ?per_page= - selain ini diabaikan. */
    private const ALLOWED_PER_PAGE = [5, 10, 25, 50, 100];

    /** Kolom yang boleh diminta lewat ?sort= - selain ini jatuh ke default 'name'. */
    private const ALLOWED_SORT_COLUMNS = ['name', 'location'];

    /** Nilai ?status= yang valid, dipetakan ke filter is_active repository. */
    private const STATUS_FILTERS = [
        'active' => true,
        'inactive' => false,
        'all' => null,
    ];

    private const LIST_URL = '/warehouses';

    private const STATUS_MESSAGES = [
        'created' => ['type' => 'success', 'text' => 'Gudang berhasil ditambahkan.'],
        'updated' => ['type' => 'info', 'text' => 'Gudang berhasil diperbarui.'],
        'activated' => ['type' => 'success', 'text' => 'Gudang berhasil diaktifkan.'],
        'deactivated' => ['type' => 'warning', 'text' => 'Gudang berhasil dinonaktifkan.'],
    ];

    public function __construct(
        private readonly WarehouseService $warehouseService,
        private readonly AuthGuard $guard,
    ) {
    }

    public function index(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $status = (string) ($_GET['status'] ?? 'all');
        if (!array_key_exists($status, self::STATUS_FILTERS)) {
            $status = 'all';
        }
        $isActive = self::STATUS_FILTERS[$status];

        $perPage = (int) ($_GET['per_page'] ?? WarehouseService::PER_PAGE);
        if (!in_array($perPage, self::ALLOWED_PER_PAGE, true)) {
            $perPage = WarehouseService::PER_PAGE;
        }

        $sortBy = (string) ($_GET['sort'] ?? 'name');
        if (!in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortBy = 'name';
        }
        $sortDir = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $totalWarehouses = $this->warehouseService->countWarehouses($search, $isActive);
        $totalPages = max(1, (int) ceil($totalWarehouses / $perPage));

        if ($totalWarehouses > 0 && $page > $totalPages) {
            $query = ['page' => $totalPages, 'per_page' => $perPage, 'sort' => $sortBy, 'dir' => $sortDir, 'status' => $status];
            if ($search !== '') {
                $query['q'] = $search;
            }

            header('Location: ' . self::LIST_URL . '?' . http_build_query($query), true, 303);
            exit;
        }

        $warehouses = $this->warehouseService->listWarehouses($search, $isActive, $page, $perPage, $sortBy, $sortDir);
        $statusMessage = self::STATUS_MESSAGES[$_GET['result'] ?? ''] ?? null;

        require __DIR__ . '/../../views/warehouses/index.php';
    }

    public function showCreateForm(): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), [Role::Admin]);

        $warehouse = null;
        $values = ['name' => '', 'location' => ''];
        $errors = [];

        require __DIR__ . '/../../views/warehouses/form.php';
    }

    public function create(): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), [Role::Admin]);

        $name = (string) ($_POST['name'] ?? '');
        $location = (string) ($_POST['location'] ?? '');

        try {
            $this->warehouseService->createWarehouse($name, $location);
            header('Location: ' . self::LIST_URL . '?result=created', true, 303);
            exit;
        } catch (ValidationException $exception) {
            $warehouse = null;
            $values = ['name' => $name, 'location' => $location];
            $errors = $exception->errors();

            require __DIR__ . '/../../views/warehouses/form.php';
        }
    }

    public function showEditForm(string $id): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), [Role::Admin]);

        $warehouse = $this->warehouseService->getWarehouseById((int) $id);
        $values = ['name' => $warehouse->name, 'location' => $warehouse->location ?? ''];
        $errors = [];

        require __DIR__ . '/../../views/warehouses/form.php';
    }

    public function update(string $id): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), [Role::Admin]);

        $name = (string) ($_POST['name'] ?? '');
        $location = (string) ($_POST['location'] ?? '');

        try {
            $this->warehouseService->updateWarehouse((int) $id, $name, $location);
            header('Location: ' . self::LIST_URL . '?result=updated', true, 303);
            exit;
        } catch (ValidationException $exception) {
            $warehouse = $this->warehouseService->getWarehouseById((int) $id);
            $values = ['name' => $name, 'location' => $location];
            $errors = $exception->errors();

            require __DIR__ . '/../../views/warehouses/form.php';
        }
    }

    public function toggleActive(string $id): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), [Role::Admin]);

        $warehouse = $this->warehouseService->getWarehouseById((int) $id);
        $this->warehouseService->setActive((int) $id, !$warehouse->isActive);

        $result = $warehouse->isActive ? 'deactivated' : 'activated';
        header('Location: ' . self::LIST_URL . '?result=' . $result, true, 303);
        exit;
    }
}
