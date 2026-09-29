<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Entity\SalesOrderStatus;
use App\Exception\ConflictException;
use App\Exception\ForbiddenException;
use App\Exception\ValidationException;
use App\Service\CustomerService;
use App\Service\GoodsIssueService;
use App\Service\ProductService;
use App\Service\SalesOrderService;
use App\Service\WarehouseService;
use App\Session\AuthGuard;

final class SalesOrderController
{
    /** Dropdown dianggap "semua" - jauh di atas jumlah data realistis. */
    private const CUSTOMER_DROPDOWN_LIMIT = 1000;
    private const WAREHOUSE_DROPDOWN_LIMIT = 1000;
    private const PRODUCT_DROPDOWN_LIMIT = 1000;

    private const ALLOWED_PER_PAGE = [5, 10, 25, 50, 100];

    /** §1.2: Admin dan Sales boleh membuat/mengajukan/membatalkan SO; Warehouse Staff tidak. */
    private const CREATE_ROLES = [Role::Admin, Role::Sales];

    private const STATUS_FILTERS = [
        'draft' => SalesOrderStatus::Draft,
        'pending_approval' => SalesOrderStatus::PendingApproval,
        'approved' => SalesOrderStatus::Approved,
        'fulfilled' => SalesOrderStatus::Fulfilled,
        'cancelled' => SalesOrderStatus::Cancelled,
        'all' => null,
    ];

    private const LIST_URL = '/sales-orders';

    private const STATUS_MESSAGES = [
        'created' => ['type' => 'success', 'text' => 'Sales Order berhasil dibuat sebagai Draft.'],
        'submitted' => ['type' => 'info', 'text' => 'Sales Order berhasil diajukan untuk persetujuan.'],
        'approved' => ['type' => 'success', 'text' => 'Sales Order berhasil disetujui.'],
        'cancelled' => ['type' => 'warning', 'text' => 'Sales Order berhasil dibatalkan.'],
        'fulfilled' => ['type' => 'success', 'text' => 'Goods issue berhasil diproses - SO selesai dipenuhi.'],
        'cannot_transition' => ['type' => 'error', 'text' => 'Aksi tidak bisa dilakukan pada status SO saat ini.'],
    ];

    public function __construct(
        private readonly SalesOrderService $salesOrderService,
        private readonly GoodsIssueService $goodsIssueService,
        private readonly CustomerService $customerService,
        private readonly WarehouseService $warehouseService,
        private readonly ProductService $productService,
        private readonly AuthGuard $guard,
    ) {
    }

    public function index(): void
    {
        $currentUser = $this->guard->requireLogin();

        // §1.2: Sales cuma boleh lihat order miliknya sendiri - dipaksa di
        // sini, tidak bisa dioverride lewat query string. Admin dan
        // Warehouse Staff lihat semua (Warehouse Staff perlu melihat SO
        // Approved untuk memprosesnya).
        $createdBy = $currentUser->role === Role::Sales ? $currentUser->id : null;

        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $statusKey = (string) ($_GET['status'] ?? 'all');
        if (!array_key_exists($statusKey, self::STATUS_FILTERS)) {
            $statusKey = 'all';
        }
        $status = self::STATUS_FILTERS[$statusKey];

        $perPage = (int) ($_GET['per_page'] ?? SalesOrderService::PER_PAGE);
        if (!in_array($perPage, self::ALLOWED_PER_PAGE, true)) {
            $perPage = SalesOrderService::PER_PAGE;
        }

        $sortDir = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $totalSalesOrders = $this->salesOrderService->countSalesOrders($search, $status, $createdBy);
        $totalPages = max(1, (int) ceil($totalSalesOrders / $perPage));

        if ($totalSalesOrders > 0 && $page > $totalPages) {
            $query = ['page' => $totalPages, 'per_page' => $perPage, 'dir' => $sortDir, 'status' => $statusKey];
            if ($search !== '') {
                $query['q'] = $search;
            }

            header('Location: ' . self::LIST_URL . '?' . http_build_query($query), true, 303);
            exit;
        }

        $salesOrders = $this->salesOrderService->listSalesOrders($search, $status, $createdBy, $page, $perPage, $sortDir);
        $customerNames = $this->customerNameMap();
        $statusMessage = self::STATUS_MESSAGES[$_GET['result'] ?? ''] ?? null;

        require __DIR__ . '/../../views/sales-orders/index.php';
    }

    public function show(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $salesOrder = $this->salesOrderService->getSalesOrderById((int) $id);
        $this->assertCanView($currentUser, $salesOrder->createdBy);

        $this->renderShow($currentUser, (int) $id);
    }

    public function showCreateForm(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, self::CREATE_ROLES);

        $values = ['customer_id' => '', 'warehouse_id' => '', 'items' => []];
        $errors = [];
        $customers = $this->customerService->listCustomers(isActive: true, perPage: self::CUSTOMER_DROPDOWN_LIMIT);
        $warehouses = $this->warehouseService->listWarehouses(isActive: true, perPage: self::WAREHOUSE_DROPDOWN_LIMIT);
        $products = $this->productService->listProducts(isActive: true, perPage: self::PRODUCT_DROPDOWN_LIMIT);

        require __DIR__ . '/../../views/sales-orders/form.php';
    }

    public function create(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, self::CREATE_ROLES);

        $values = $this->readInput();

        try {
            $salesOrder = $this->salesOrderService->createSalesOrder($values, $currentUser->id);
            header('Location: ' . self::LIST_URL . '/' . $salesOrder->id . '?result=created', true, 303);
            exit;
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $customers = $this->customerService->listCustomers(isActive: true, perPage: self::CUSTOMER_DROPDOWN_LIMIT);
            $warehouses = $this->warehouseService->listWarehouses(isActive: true, perPage: self::WAREHOUSE_DROPDOWN_LIMIT);
            $products = $this->productService->listProducts(isActive: true, perPage: self::PRODUCT_DROPDOWN_LIMIT);

            require __DIR__ . '/../../views/sales-orders/form.php';
        }
    }

    public function submitForApproval(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, self::CREATE_ROLES);

        $salesOrder = $this->salesOrderService->getSalesOrderById((int) $id);
        if ($currentUser->role === Role::Sales && $salesOrder->createdBy !== $currentUser->id) {
            throw new ForbiddenException();
        }

        try {
            $this->salesOrderService->submitForApproval((int) $id);
            header('Location: ' . self::LIST_URL . '/' . $id . '?result=submitted', true, 303);
        } catch (ConflictException) {
            header('Location: ' . self::LIST_URL . '/' . $id . '?result=cannot_transition', true, 303);
        }
        exit;
    }

    public function approve(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        // §1.2: "Sales tidak dapat menyetujui order - termasuk order
        // miliknya sendiri" - dipenuhi lewat role gate Admin-only; Sales
        // tidak pernah lolos ke titik ini sama sekali, jadi tidak perlu
        // pengecekan kepemilikan tambahan.
        $this->guard->requireRole($currentUser, [Role::Admin]);

        try {
            $this->salesOrderService->approve((int) $id, $currentUser->id);
            header('Location: ' . self::LIST_URL . '/' . $id . '?result=approved', true, 303);
        } catch (ConflictException) {
            header('Location: ' . self::LIST_URL . '/' . $id . '?result=cannot_transition', true, 303);
        }
        exit;
    }

    public function cancel(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, self::CREATE_ROLES);

        $salesOrder = $this->salesOrderService->getSalesOrderById((int) $id);

        if ($currentUser->role === Role::Sales) {
            if ($salesOrder->createdBy !== $currentUser->id) {
                throw new ForbiddenException();
            }
            // Sales cuma boleh membatalkan sebelum disetujui - begitu
            // Approved, keputusan sudah di tangan Admin/Warehouse Staff.
            if (!in_array($salesOrder->status, [SalesOrderStatus::Draft, SalesOrderStatus::PendingApproval], true)) {
                throw new ForbiddenException();
            }
        }

        try {
            $this->salesOrderService->cancel((int) $id);
            header('Location: ' . self::LIST_URL . '/' . $id . '?result=cancelled', true, 303);
        } catch (ConflictException) {
            header('Location: ' . self::LIST_URL . '/' . $id . '?result=cannot_transition', true, 303);
        }
        exit;
    }

    public function processGoodsIssue(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin, Role::WarehouseStaff]);

        try {
            $this->goodsIssueService->issue((int) $id, $currentUser->id);
            header('Location: ' . self::LIST_URL . '/' . $id . '?result=fulfilled', true, 303);
            exit;
        } catch (ConflictException $exception) {
            $this->renderShow($currentUser, (int) $id, $exception->getMessage());
        }
    }

    private function renderShow(\App\Session\CurrentUser $currentUser, int $id, ?string $issueError = null): void
    {
        $salesOrder = $this->salesOrderService->getSalesOrderById($id);
        $customer = $this->customerService->getCustomerById($salesOrder->customerId);
        $warehouse = $this->warehouseService->getWarehouseById($salesOrder->warehouseId);

        $productNames = [];
        foreach ($salesOrder->items as $item) {
            $productNames[$item->productId] ??= $this->productService->getProductById($item->productId)->name;
        }

        $issueHistory = $this->goodsIssueService->getIssueHistory($id);
        $statusMessage = self::STATUS_MESSAGES[$_GET['result'] ?? ''] ?? null;

        require __DIR__ . '/../../views/sales-orders/show.php';
    }

    private function assertCanView(\App\Session\CurrentUser $currentUser, int $createdBy): void
    {
        if ($currentUser->role === Role::Sales && $createdBy !== $currentUser->id) {
            throw new ForbiddenException();
        }
    }

    /**
     * @return array<int, string> customerId => nama, dipakai daftar SO untuk
     *     menghindari N+1 - termasuk customer yang sudah nonaktif.
     */
    private function customerNameMap(): array
    {
        $names = [];
        foreach ($this->customerService->listCustomers(perPage: self::CUSTOMER_DROPDOWN_LIMIT) as $customer) {
            $names[$customer->id] = $customer->name;
        }

        return $names;
    }

    /**
     * @return array{customer_id: string, warehouse_id: string, items: array<int, array{product_id: string, qty: string, sell_price: string}>}
     */
    private function readInput(): array
    {
        $items = [];
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                $items[] = [
                    'product_id' => (string) ($item['product_id'] ?? ''),
                    'qty' => (string) ($item['qty'] ?? ''),
                    'sell_price' => (string) ($item['sell_price'] ?? ''),
                ];
            }
        }

        return [
            'customer_id' => (string) ($_POST['customer_id'] ?? ''),
            'warehouse_id' => (string) ($_POST['warehouse_id'] ?? ''),
            'items' => $items,
        ];
    }
}
