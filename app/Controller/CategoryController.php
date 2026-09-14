<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Exception\ConflictException;
use App\Exception\ValidationException;
use App\Service\CategoryService;
use App\Session\AuthGuard;

final class CategoryController
{
    /** Pilihan baris per halaman yang boleh diminta lewat ?per_page= - selain ini diabaikan. */
    private const ALLOWED_PER_PAGE = [5, 10, 25, 50, 100];

    /** Kolom yang boleh diminta lewat ?sort= - selain ini jatuh ke default 'name'. */
    private const ALLOWED_SORT_COLUMNS = ['name', 'description'];

    private const LIST_URL = '/categories';

    /**
     * Pesan untuk tiap nilai ?status= yang bisa muncul di halaman daftar setelah redirect aksi.
     * Warna dibedakan per jenis aksi - success (hijau) untuk data baru, info (biru) untuk
     * perubahan, warning (kuning) untuk penghapusan yang berhasil (tetap aksi destruktif
     * meski tidak error), error (merah) untuk aksi yang ditolak.
     */
    private const STATUS_MESSAGES = [
        'created' => ['type' => 'success', 'text' => 'Kategori berhasil ditambahkan.'],
        'updated' => ['type' => 'info', 'text' => 'Kategori berhasil diperbarui.'],
        'deleted' => ['type' => 'warning', 'text' => 'Kategori berhasil dihapus.'],
        'delete_blocked' => [
            'type' => 'error',
            'text' => 'Kategori tidak bisa dihapus karena masih dipakai oleh produk lain. '
                . 'Nonaktifkan produknya dari kategori ini terlebih dahulu, atau ubah kategori produk tersebut.',
        ],
    ];

    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly AuthGuard $guard,
    ) {
    }

    public function index(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $perPage = (int) ($_GET['per_page'] ?? CategoryService::PER_PAGE);
        if (!in_array($perPage, self::ALLOWED_PER_PAGE, true)) {
            $perPage = CategoryService::PER_PAGE;
        }

        $sortBy = (string) ($_GET['sort'] ?? 'name');
        if (!in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortBy = 'name';
        }
        $sortDir = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $totalCategories = $this->categoryService->countCategories($search);
        $totalPages = max(1, (int) ceil($totalCategories / $perPage));

        if ($totalCategories > 0 && $page > $totalPages) {
            $query = ['page' => $totalPages, 'per_page' => $perPage, 'sort' => $sortBy, 'dir' => $sortDir];
            if ($search !== '') {
                $query['q'] = $search;
            }

            header('Location: ' . self::LIST_URL . '?' . http_build_query($query), true, 303);
            exit;
        }

        $categories = $this->categoryService->listCategories($search, $page, $perPage, $sortBy, $sortDir);
        $statusMessage = self::STATUS_MESSAGES[$_GET['status'] ?? ''] ?? null;

        require __DIR__ . '/../../views/categories/index.php';
    }

    public function showCreateForm(): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), [Role::Admin]);

        $category = null;
        $values = ['name' => '', 'description' => ''];
        $errors = [];

        require __DIR__ . '/../../views/categories/form.php';
    }

    public function create(): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), [Role::Admin]);

        $name = (string) ($_POST['name'] ?? '');
        $description = (string) ($_POST['description'] ?? '');

        try {
            $this->categoryService->createCategory($name, $description);
            header('Location: ' . self::LIST_URL . '?status=created', true, 303);
            exit;
        } catch (ValidationException $exception) {
            $category = null;
            $values = ['name' => $name, 'description' => $description];
            $errors = $exception->errors();

            require __DIR__ . '/../../views/categories/form.php';
        }
    }

    public function showEditForm(string $id): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), [Role::Admin]);

        $category = $this->categoryService->getCategoryById((int) $id);
        $values = ['name' => $category->name, 'description' => $category->description ?? ''];
        $errors = [];

        require __DIR__ . '/../../views/categories/form.php';
    }

    public function update(string $id): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), [Role::Admin]);

        $name = (string) ($_POST['name'] ?? '');
        $description = (string) ($_POST['description'] ?? '');

        try {
            $this->categoryService->updateCategory((int) $id, $name, $description);
            header('Location: ' . self::LIST_URL . '?status=updated', true, 303);
            exit;
        } catch (ValidationException $exception) {
            $category = $this->categoryService->getCategoryById((int) $id);
            $values = ['name' => $name, 'description' => $description];
            $errors = $exception->errors();

            require __DIR__ . '/../../views/categories/form.php';
        }
    }

    public function delete(string $id): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), [Role::Admin]);

        try {
            $this->categoryService->deleteCategory((int) $id);
            header('Location: ' . self::LIST_URL . '?status=deleted', true, 303);
            exit;
        } catch (ConflictException) {
            header('Location: ' . self::LIST_URL . '?status=delete_blocked', true, 303);
            exit;
        }
    }
}
