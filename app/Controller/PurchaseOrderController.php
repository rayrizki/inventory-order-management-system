<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PurchaseOrderStatus;
use App\Entity\Role;
use App\Exception\ConflictException;
use App\Exception\ValidationException;
use App\Service\GoodsReceiptService;
use App\Service\ProductService;
use App\Service\PurchaseOrderService;
use App\Service\SupplierService;
use App\Service\WarehouseService;
use App\Session\AuthGuard;

final class PurchaseOrderController
{
    /** Dropdown dianggap "semua" - jauh di atas jumlah data realistis. */
    private const SUPPLIER_DROPDOWN_LIMIT = 1000;
    private const WAREHOUSE_DROPDOWN_LIMIT = 1000;
    private const PRODUCT_DROPDOWN_LIMIT = 1000;

    private const ALLOWED_PER_PAGE = [5, 10, 25, 50, 100];

    /**
     * §1.2 "Membuat Purchase Order": Admin "Boleh", Warehouse Staff "Boleh
     * mengusulkan", Sales tidak sama sekali. Melihat daftar/detail, membuat
     * draf, dan memproses goods receipt ("Memproses goods receipt (PO)":
     * Warehouse Staff "Boleh") masuk ke sini.
     */
    private const ALLOWED_ROLES = [Role::Admin, Role::WarehouseStaff];

    /**
     * Mengikat PO ke supplier (Draft -> Ordered) dan membatalkannya bukan
     * bagian dari "mengusulkan" - itu keputusan komersial yang menjadikan PO
     * sebagai komitmen. Dibatasi ke Admin supaya yang mengusulkan dan yang
     * memutuskan tidak bisa orang yang sama, sejalan dengan pemisahan yang
     * sama pada Sales Order (pembuat tidak boleh menyetujui).
     */
    private const COMMIT_ROLES = [Role::Admin];

    private const STATUS_FILTERS = [
        'draft' => PurchaseOrderStatus::Draft,
        'ordered' => PurchaseOrderStatus::Ordered,
        'partially_received' => PurchaseOrderStatus::PartiallyReceived,
        'received' => PurchaseOrderStatus::Received,
        'cancelled' => PurchaseOrderStatus::Cancelled,
        'all' => null,
    ];

    private const LIST_URL = '/purchase-orders';

    private const STATUS_MESSAGES = [
        'created' => ['type' => 'success', 'text' => 'Purchase Order berhasil dibuat sebagai Draft.'],
        'ordered' => ['type' => 'info', 'text' => 'Purchase Order berhasil diajukan ke supplier.'],
        'cancelled' => ['type' => 'warning', 'text' => 'Purchase Order berhasil dibatalkan.'],
        'received' => ['type' => 'success', 'text' => 'Barang berhasil diterima penuh - PO selesai.'],
        'partially_received' => ['type' => 'info', 'text' => 'Sebagian barang berhasil diterima - PO menunggu sisa qty.'],
        'cannot_transition' => ['type' => 'error', 'text' => 'Aksi tidak bisa dilakukan pada status PO saat ini.'],
    ];

    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly GoodsReceiptService $goodsReceiptService,
        private readonly SupplierService $supplierService,
        private readonly WarehouseService $warehouseService,
        private readonly ProductService $productService,
        private readonly AuthGuard $guard,
    ) {
    }

    public function index(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, self::ALLOWED_ROLES);

        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $statusKey = (string) ($_GET['status'] ?? 'all');
        if (!array_key_exists($statusKey, self::STATUS_FILTERS)) {
            $statusKey = 'all';
        }
        $status = self::STATUS_FILTERS[$statusKey];

        $perPage = (int) ($_GET['per_page'] ?? PurchaseOrderService::PER_PAGE);
        if (!in_array($perPage, self::ALLOWED_PER_PAGE, true)) {
            $perPage = PurchaseOrderService::PER_PAGE;
        }

        $sortDir = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $totalPurchaseOrders = $this->purchaseOrderService->countPurchaseOrders($search, $status);
        $totalPages = max(1, (int) ceil($totalPurchaseOrders / $perPage));

        if ($totalPurchaseOrders > 0 && $page > $totalPages) {
            $query = ['page' => $totalPages, 'per_page' => $perPage, 'dir' => $sortDir, 'status' => $statusKey];
            if ($search !== '') {
                $query['q'] = $search;
            }

            header('Location: ' . self::LIST_URL . '?' . http_build_query($query), true, 303);
            exit;
        }

        $purchaseOrders = $this->purchaseOrderService->listPurchaseOrders($search, $status, $page, $perPage, $sortDir);
        $supplierNames = $this->supplierNameMap();
        $statusMessage = self::STATUS_MESSAGES[$_GET['result'] ?? ''] ?? null;

        require __DIR__ . '/../../views/purchase-orders/index.php';
    }

    public function show(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, self::ALLOWED_ROLES);

        $this->renderShow($currentUser, (int) $id);
    }

    public function showCreateForm(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, self::ALLOWED_ROLES);

        $values = ['supplier_id' => '', 'warehouse_id' => '', 'order_date' => date('Y-m-d'), 'items' => []];
        $errors = [];
        $suppliers = $this->supplierService->listSuppliers(isActive: true, perPage: self::SUPPLIER_DROPDOWN_LIMIT);
        $warehouses = $this->warehouseService->listWarehouses(isActive: true, perPage: self::WAREHOUSE_DROPDOWN_LIMIT);
        $products = $this->productService->listProducts(isActive: true, perPage: self::PRODUCT_DROPDOWN_LIMIT);

        require __DIR__ . '/../../views/purchase-orders/form.php';
    }

    public function create(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, self::ALLOWED_ROLES);

        $values = $this->readInput();

        try {
            $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder($values, $currentUser->id);
            header('Location: ' . self::LIST_URL . '/' . $purchaseOrder->id . '?result=created', true, 303);
            exit;
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $suppliers = $this->supplierService->listSuppliers(isActive: true, perPage: self::SUPPLIER_DROPDOWN_LIMIT);
            $warehouses = $this->warehouseService->listWarehouses(isActive: true, perPage: self::WAREHOUSE_DROPDOWN_LIMIT);
            $products = $this->productService->listProducts(isActive: true, perPage: self::PRODUCT_DROPDOWN_LIMIT);

            require __DIR__ . '/../../views/purchase-orders/form.php';
        }
    }

    public function markOrdered(string $id): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), self::COMMIT_ROLES);

        try {
            $this->purchaseOrderService->markOrdered((int) $id);
            header('Location: ' . self::LIST_URL . '/' . $id . '?result=ordered', true, 303);
        } catch (ConflictException) {
            header('Location: ' . self::LIST_URL . '/' . $id . '?result=cannot_transition', true, 303);
        }
        exit;
    }

    public function cancel(string $id): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), self::COMMIT_ROLES);

        try {
            $this->purchaseOrderService->cancel((int) $id);
            header('Location: ' . self::LIST_URL . '/' . $id . '?result=cancelled', true, 303);
        } catch (ConflictException) {
            header('Location: ' . self::LIST_URL . '/' . $id . '?result=cannot_transition', true, 303);
        }
        exit;
    }

    public function receiveGoods(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, self::ALLOWED_ROLES);

        $receivedQtyByItemId = [];
        if (isset($_POST['received_qty']) && is_array($_POST['received_qty'])) {
            foreach ($_POST['received_qty'] as $itemId => $qty) {
                if (ctype_digit((string) $itemId)) {
                    $receivedQtyByItemId[(int) $itemId] = (int) $qty;
                }
            }
        }

        try {
            $purchaseOrder = $this->goodsReceiptService->receive((int) $id, $receivedQtyByItemId, $currentUser->id);
            $result = $purchaseOrder->status === PurchaseOrderStatus::Received ? 'received' : 'partially_received';
            header('Location: ' . self::LIST_URL . '/' . $id . '?result=' . $result, true, 303);
            exit;
        } catch (ValidationException $exception) {
            $this->renderShow($currentUser, (int) $id, $exception->errors());
        } catch (ConflictException $exception) {
            $this->renderShow($currentUser, (int) $id, ['_general' => $exception->getMessage()]);
        }
    }

    /**
     * @param array<string, string> $receiptErrors
     */
    private function renderShow(\App\Session\CurrentUser $currentUser, int $id, array $receiptErrors = []): void
    {
        $purchaseOrder = $this->purchaseOrderService->getPurchaseOrderById($id);
        $supplier = $this->supplierService->getSupplierById($purchaseOrder->supplierId);
        $warehouse = $this->warehouseService->getWarehouseById($purchaseOrder->warehouseId);

        $productNames = [];
        foreach ($purchaseOrder->items as $item) {
            $productNames[$item->productId] ??= $this->productService->getProductById($item->productId)->name;
        }

        $receiptHistory = $this->goodsReceiptService->getReceiptHistory($id);
        $statusMessage = self::STATUS_MESSAGES[$_GET['result'] ?? ''] ?? null;

        require __DIR__ . '/../../views/purchase-orders/show.php';
    }

    /**
     * @return array<int, string> supplierId => nama, dipakai daftar PO untuk
     *     menghindari N+1 - termasuk supplier yang sudah nonaktif (histori PO
     *     tidak boleh kehilangan nama pihak terkait hanya karena dinonaktifkan).
     */
    private function supplierNameMap(): array
    {
        $names = [];
        foreach ($this->supplierService->listSuppliers(perPage: self::SUPPLIER_DROPDOWN_LIMIT) as $supplier) {
            $names[$supplier->id] = $supplier->name;
        }

        return $names;
    }

    /**
     * @return array{supplier_id: string, warehouse_id: string, order_date: string, items: array<int, array{product_id: string, qty: string, buy_price: string}>}
     */
    private function readInput(): array
    {
        $items = [];
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                $items[] = [
                    'product_id' => (string) ($item['product_id'] ?? ''),
                    'qty' => (string) ($item['qty'] ?? ''),
                    'buy_price' => (string) ($item['buy_price'] ?? ''),
                ];
            }
        }

        return [
            'supplier_id' => (string) ($_POST['supplier_id'] ?? ''),
            'warehouse_id' => (string) ($_POST['warehouse_id'] ?? ''),
            'order_date' => (string) ($_POST['order_date'] ?? ''),
            'items' => $items,
        ];
    }
}
