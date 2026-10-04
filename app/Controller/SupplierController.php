<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Exception\ValidationException;
use App\Service\SupplierService;
use App\Session\AuthGuard;

final class SupplierController
{
    use SendsRedirects;

    /** Pilihan baris per halaman yang boleh diminta lewat ?per_page= - selain ini diabaikan. */
    private const ALLOWED_PER_PAGE = [5, 10, 25, 50, 100];

    /** Kolom yang boleh diminta lewat ?sort= - selain ini jatuh ke default 'name'. */
    private const ALLOWED_SORT_COLUMNS = ['name', 'contact'];

    /** Nilai ?status= yang valid, dipetakan ke filter is_active repository. */
    private const STATUS_FILTERS = [
        'active' => true,
        'inactive' => false,
        'all' => null,
    ];

    private const LIST_URL = '/suppliers';

    /** Form non-modal: fallback tanpa JavaScript dan tampilan saat validasi gagal. */
    private const FORM_VIEW = __DIR__ . '/../../views/suppliers/form.php';

    private const STATUS_MESSAGES = [
        'created' => ['type' => 'success', 'text' => 'Supplier berhasil ditambahkan.'],
        'updated' => ['type' => 'info', 'text' => 'Supplier berhasil diperbarui.'],
        'activated' => ['type' => 'success', 'text' => 'Supplier berhasil diaktifkan.'],
        'deactivated' => ['type' => 'warning', 'text' => 'Supplier berhasil dinonaktifkan.'],
    ];

    public function __construct(
        private readonly SupplierService $supplierService,
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

        $perPage = (int) ($_GET['per_page'] ?? SupplierService::PER_PAGE);
        if (!in_array($perPage, self::ALLOWED_PER_PAGE, true)) {
            $perPage = SupplierService::PER_PAGE;
        }

        $sortBy = (string) ($_GET['sort'] ?? 'name');
        if (!in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortBy = 'name';
        }
        $sortDir = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $totalSuppliers = $this->supplierService->countSuppliers($search, $isActive);
        $totalPages = max(1, (int) ceil($totalSuppliers / $perPage));

        if ($totalSuppliers > 0 && $page > $totalPages) {
            $query = ['page' => $totalPages, 'per_page' => $perPage, 'sort' => $sortBy, 'dir' => $sortDir, 'status' => $status];
            if ($search !== '') {
                $query['q'] = $search;
            }

            $this->redirect(self::LIST_URL . '?' . http_build_query($query));
        }

        $suppliers = $this->supplierService->listSuppliers($search, $isActive, $page, $perPage, $sortBy, $sortDir);
        $statusMessage = self::STATUS_MESSAGES[$_GET['result'] ?? ''] ?? null;

        require_once __DIR__ . '/../../views/suppliers/index.php';
    }

    public function showCreateForm(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $supplier = null;
        $values = ['name' => '', 'contact' => '', 'address' => ''];
        $errors = [];

        require_once self::FORM_VIEW;
    }

    public function create(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $name = (string) ($_POST['name'] ?? '');
        $contact = (string) ($_POST['contact'] ?? '');
        $address = (string) ($_POST['address'] ?? '');

        try {
            $this->supplierService->createSupplier($name, $contact, $address);
            $this->redirect(self::LIST_URL . '?result=created');
        } catch (ValidationException $exception) {
            $supplier = null;
            $values = ['name' => $name, 'contact' => $contact, 'address' => $address];
            $errors = $exception->errors();

            require_once self::FORM_VIEW;
        }
    }

    public function showEditForm(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $supplier = $this->supplierService->getSupplierById((int) $id);
        $values = ['name' => $supplier->name, 'contact' => $supplier->contact ?? '', 'address' => $supplier->address ?? ''];
        $errors = [];

        require_once self::FORM_VIEW;
    }

    public function update(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $name = (string) ($_POST['name'] ?? '');
        $contact = (string) ($_POST['contact'] ?? '');
        $address = (string) ($_POST['address'] ?? '');

        try {
            $this->supplierService->updateSupplier((int) $id, $name, $contact, $address);
            $this->redirect(self::LIST_URL . '?result=updated');
        } catch (ValidationException $exception) {
            $supplier = $this->supplierService->getSupplierById((int) $id);
            $values = ['name' => $name, 'contact' => $contact, 'address' => $address];
            $errors = $exception->errors();

            require_once self::FORM_VIEW;
        }
    }

    public function toggleActive(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $supplier = $this->supplierService->getSupplierById((int) $id);
        $this->supplierService->setActive((int) $id, !$supplier->isActive);

        $result = $supplier->isActive ? 'deactivated' : 'activated';
        $this->redirect(self::LIST_URL . '?result=' . $result);
    }
}
