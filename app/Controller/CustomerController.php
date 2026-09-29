<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Exception\ValidationException;
use App\Service\CustomerService;
use App\Session\AuthGuard;

final class CustomerController
{
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

    private const LIST_URL = '/customers';

    private const STATUS_MESSAGES = [
        'created' => ['type' => 'success', 'text' => 'Customer berhasil ditambahkan.'],
        'updated' => ['type' => 'info', 'text' => 'Customer berhasil diperbarui.'],
        'activated' => ['type' => 'success', 'text' => 'Customer berhasil diaktifkan.'],
        'deactivated' => ['type' => 'warning', 'text' => 'Customer berhasil dinonaktifkan.'],
    ];

    public function __construct(
        private readonly CustomerService $customerService,
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

        $perPage = (int) ($_GET['per_page'] ?? CustomerService::PER_PAGE);
        if (!in_array($perPage, self::ALLOWED_PER_PAGE, true)) {
            $perPage = CustomerService::PER_PAGE;
        }

        $sortBy = (string) ($_GET['sort'] ?? 'name');
        if (!in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortBy = 'name';
        }
        $sortDir = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $totalCustomers = $this->customerService->countCustomers($search, $isActive);
        $totalPages = max(1, (int) ceil($totalCustomers / $perPage));

        if ($totalCustomers > 0 && $page > $totalPages) {
            $query = ['page' => $totalPages, 'per_page' => $perPage, 'sort' => $sortBy, 'dir' => $sortDir, 'status' => $status];
            if ($search !== '') {
                $query['q'] = $search;
            }

            header('Location: ' . self::LIST_URL . '?' . http_build_query($query), true, 303);
            exit;
        }

        $customers = $this->customerService->listCustomers($search, $isActive, $page, $perPage, $sortBy, $sortDir);
        $statusMessage = self::STATUS_MESSAGES[$_GET['result'] ?? ''] ?? null;

        require __DIR__ . '/../../views/customers/index.php';
    }

    public function showCreateForm(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $customer = null;
        $values = ['name' => '', 'contact' => '', 'address' => ''];
        $errors = [];

        require __DIR__ . '/../../views/customers/form.php';
    }

    public function create(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $name = (string) ($_POST['name'] ?? '');
        $contact = (string) ($_POST['contact'] ?? '');
        $address = (string) ($_POST['address'] ?? '');

        try {
            $this->customerService->createCustomer($name, $contact, $address);
            header('Location: ' . self::LIST_URL . '?result=created', true, 303);
            exit;
        } catch (ValidationException $exception) {
            $customer = null;
            $values = ['name' => $name, 'contact' => $contact, 'address' => $address];
            $errors = $exception->errors();

            require __DIR__ . '/../../views/customers/form.php';
        }
    }

    public function showEditForm(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $customer = $this->customerService->getCustomerById((int) $id);
        $values = ['name' => $customer->name, 'contact' => $customer->contact ?? '', 'address' => $customer->address ?? ''];
        $errors = [];

        require __DIR__ . '/../../views/customers/form.php';
    }

    public function update(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $name = (string) ($_POST['name'] ?? '');
        $contact = (string) ($_POST['contact'] ?? '');
        $address = (string) ($_POST['address'] ?? '');

        try {
            $this->customerService->updateCustomer((int) $id, $name, $contact, $address);
            header('Location: ' . self::LIST_URL . '?result=updated', true, 303);
            exit;
        } catch (ValidationException $exception) {
            $customer = $this->customerService->getCustomerById((int) $id);
            $values = ['name' => $name, 'contact' => $contact, 'address' => $address];
            $errors = $exception->errors();

            require __DIR__ . '/../../views/customers/form.php';
        }
    }

    public function toggleActive(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $customer = $this->customerService->getCustomerById((int) $id);
        $this->customerService->setActive((int) $id, !$customer->isActive);

        $result = $customer->isActive ? 'deactivated' : 'activated';
        header('Location: ' . self::LIST_URL . '?result=' . $result, true, 303);
        exit;
    }
}
