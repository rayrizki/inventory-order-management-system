<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Exception\ValidationException;
use App\Service\UserService;
use App\Session\AuthGuard;

final class UserController
{
    /** Pilihan baris per halaman yang boleh diminta lewat ?per_page= - selain ini diabaikan. */
    private const ALLOWED_PER_PAGE = [5, 10, 25, 50, 100];

    /** Kolom yang boleh diminta lewat ?sort= - selain ini jatuh ke default 'name'. */
    private const ALLOWED_SORT_COLUMNS = ['name', 'email'];

    /** Nilai ?status= yang valid, dipetakan ke filter is_active repository. */
    private const STATUS_FILTERS = [
        'active' => true,
        'inactive' => false,
        'all' => null,
    ];

    private const LIST_URL = '/users';

    private const STATUS_MESSAGES = [
        'created' => ['type' => 'success', 'text' => 'User berhasil ditambahkan.'],
        'updated' => ['type' => 'info', 'text' => 'User berhasil diperbarui.'],
        'activated' => ['type' => 'success', 'text' => 'User berhasil diaktifkan.'],
        'deactivated' => ['type' => 'warning', 'text' => 'User berhasil dinonaktifkan.'],
    ];

    public function __construct(
        private readonly UserService $userService,
        private readonly AuthGuard $guard,
    ) {
    }

    public function index(): void
    {
        // USR-01: "Sales dan Warehouse Staff tidak dapat membuka halaman
        // atau endpoint administrasi user" - beda dari Produk, modul ini
        // Admin-only sepenuhnya termasuk untuk baca.
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $status = (string) ($_GET['status'] ?? 'all');
        if (!array_key_exists($status, self::STATUS_FILTERS)) {
            $status = 'all';
        }
        $isActive = self::STATUS_FILTERS[$status];

        $perPage = (int) ($_GET['per_page'] ?? UserService::PER_PAGE);
        if (!in_array($perPage, self::ALLOWED_PER_PAGE, true)) {
            $perPage = UserService::PER_PAGE;
        }

        $sortBy = (string) ($_GET['sort'] ?? 'name');
        if (!in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortBy = 'name';
        }
        $sortDir = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $totalUsers = $this->userService->countUsers($search, $isActive);
        $totalPages = max(1, (int) ceil($totalUsers / $perPage));

        if ($totalUsers > 0 && $page > $totalPages) {
            $query = ['page' => $totalPages, 'per_page' => $perPage, 'sort' => $sortBy, 'dir' => $sortDir, 'status' => $status];
            if ($search !== '') {
                $query['q'] = $search;
            }

            header('Location: ' . self::LIST_URL . '?' . http_build_query($query), true, 303);
            exit;
        }

        $users = $this->userService->listUsers($search, $isActive, $page, $perPage, $sortBy, $sortDir);
        $statusMessage = self::STATUS_MESSAGES[$_GET['result'] ?? ''] ?? null;

        require_once __DIR__ . '/../../views/users/index.php';
    }

    public function showCreateForm(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $user = null;
        $values = ['name' => '', 'email' => '', 'password' => '', 'role' => ''];
        $errors = [];

        require_once __DIR__ . '/../../views/users/form.php';
    }

    public function create(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $values = $this->readInput();

        try {
            $this->userService->createUser($values);
            header('Location: ' . self::LIST_URL . '?result=created', true, 303);
            exit;
        } catch (ValidationException $exception) {
            $user = null;
            $errors = $exception->errors();

            require_once __DIR__ . '/../../views/users/form.php';
        }
    }

    public function showEditForm(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $user = $this->userService->getUserById((int) $id);
        $values = ['name' => $user->name, 'email' => $user->email, 'password' => '', 'role' => $user->role->value];
        $errors = [];

        require_once __DIR__ . '/../../views/users/form.php';
    }

    public function update(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $values = $this->readInput();

        try {
            $this->userService->updateUser((int) $id, $values);
            header('Location: ' . self::LIST_URL . '?result=updated', true, 303);
            exit;
        } catch (ValidationException $exception) {
            $user = $this->userService->getUserById((int) $id);
            $errors = $exception->errors();

            require_once __DIR__ . '/../../views/users/form.php';
        }
    }

    public function toggleActive(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $user = $this->userService->getUserById((int) $id);
        $this->userService->setActive((int) $id, !$user->isActive);

        $result = $user->isActive ? 'deactivated' : 'activated';
        header('Location: ' . self::LIST_URL . '?result=' . $result, true, 303);
        exit;
    }

    /**
     * @return array{name: string, email: string, password: string, role: string}
     */
    private function readInput(): array
    {
        return [
            'name' => (string) ($_POST['name'] ?? ''),
            'email' => (string) ($_POST['email'] ?? ''),
            'password' => (string) ($_POST['password'] ?? ''),
            'role' => (string) ($_POST['role'] ?? ''),
        ];
    }
}
