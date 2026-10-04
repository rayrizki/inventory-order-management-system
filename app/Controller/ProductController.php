<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Exception\ValidationException;
use App\Service\CategoryService;
use App\Service\ProductService;
use App\Service\StockService;
use App\Session\AuthGuard;

final class ProductController
{
    /** Kategori dianggap "semua" untuk kebutuhan dropdown - jauh di atas jumlah kategori realistis. */
    private const CATEGORY_DROPDOWN_LIMIT = 1000;

    /** Pilihan baris per halaman yang boleh diminta lewat ?per_page= - selain ini diabaikan. */
    private const ALLOWED_PER_PAGE = [5, 10, 25, 50, 100];

    /** Kolom yang boleh diminta lewat ?sort= - selain ini jatuh ke default 'name'. */
    private const ALLOWED_SORT_COLUMNS = ['sku', 'name'];

    /** Nilai ?status= yang valid, dipetakan ke filter is_active repository. */
    private const STATUS_FILTERS = [
        'active' => true,
        'inactive' => false,
        'all' => null,
    ];

    /** Nilai ?stock_status= yang valid (FIND-01) - selain ini jatuh ke 'all'. */
    private const STOCK_STATUS_FILTERS = ['low', 'normal', 'all'];

    private const LIST_URL = '/products';

    private const STATUS_MESSAGES = [
        'created' => ['type' => 'success', 'text' => 'Produk berhasil ditambahkan.'],
        'updated' => ['type' => 'info', 'text' => 'Produk berhasil diperbarui.'],
        'activated' => ['type' => 'success', 'text' => 'Produk berhasil diaktifkan.'],
        'deactivated' => ['type' => 'warning', 'text' => 'Produk berhasil dinonaktifkan.'],
    ];

    public function __construct(
        private readonly ProductService $productService,
        private readonly CategoryService $categoryService,
        private readonly StockService $stockService,
        private readonly AuthGuard $guard,
    ) {
    }

    public function index(): void
    {
        $currentUser = $this->guard->requireLogin();

        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $categoryId = isset($_GET['category_id']) && ctype_digit((string) $_GET['category_id'])
            ? (int) $_GET['category_id']
            : null;

        $status = (string) ($_GET['status'] ?? 'all');
        if (!array_key_exists($status, self::STATUS_FILTERS)) {
            $status = 'all';
        }
        $isActive = self::STATUS_FILTERS[$status];

        $stockStatus = (string) ($_GET['stock_status'] ?? 'all');
        if (!in_array($stockStatus, self::STOCK_STATUS_FILTERS, true)) {
            $stockStatus = 'all';
        }
        $stockStatusFilter = $stockStatus === 'all' ? null : $stockStatus;

        $perPage = (int) ($_GET['per_page'] ?? ProductService::PER_PAGE);
        if (!in_array($perPage, self::ALLOWED_PER_PAGE, true)) {
            $perPage = ProductService::PER_PAGE;
        }

        $sortBy = (string) ($_GET['sort'] ?? 'name');
        if (!in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortBy = 'name';
        }
        $sortDir = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $totalProducts = $this->productService->countProducts($search, $categoryId, $isActive, $stockStatusFilter);
        $totalPages = max(1, (int) ceil($totalProducts / $perPage));

        if ($totalProducts > 0 && $page > $totalPages) {
            $query = [
                'page' => $totalPages, 'per_page' => $perPage, 'sort' => $sortBy, 'dir' => $sortDir, 'status' => $status, 'stock_status' => $stockStatus,
            ];
            if ($search !== '') {
                $query['q'] = $search;
            }
            if ($categoryId !== null) {
                $query['category_id'] = $categoryId;
            }

            header('Location: ' . self::LIST_URL . '?' . http_build_query($query), true, 303);
            exit;
        }

        $products = $this->productService->listProducts($search, $categoryId, $isActive, $stockStatusFilter, $page, $perPage, $sortBy, $sortDir);
        $categories = $this->categoryService->listCategories(perPage: self::CATEGORY_DROPDOWN_LIMIT);
        $categoryNames = [];
        foreach ($categories as $category) {
            $categoryNames[$category->id] = $category->name;
        }
        // Angka stok untuk baris yang sedang tampil - satu query untuk satu
        // halaman, bukan per produk. Tanpa ini filter "status stok" bekerja
        // benar tapi hasilnya terlihat seperti tidak berubah, karena tabel
        // tidak menampilkan angka yang jadi dasar penyaringan.
        $stockTotals = $this->stockService->getTotalsForProducts(
            array_map(static fn ($product): int => (int) $product->id, $products)
        );
        $statusMessage = self::STATUS_MESSAGES[$_GET['result'] ?? ''] ?? null;

        require_once __DIR__ . '/../../views/products/index.php';
    }

    public function show(string $id): void
    {
        $currentUser = $this->guard->requireLogin();

        $product = $this->productService->getProductById((int) $id);
        $categoryName = $this->categoryService->getCategoryById($product->categoryId)->name;
        $stock = $this->stockService->getStockSummary($product->id);

        require_once __DIR__ . '/../../views/products/show.php';
    }

    public function showCreateForm(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $product = null;
        $values = ['sku' => '', 'name' => '', 'category_id' => '', 'unit' => '', 'buy_price' => '', 'sell_price' => '', 'reorder_point' => ''];
        $errors = [];
        $categories = $this->categoryService->listCategories(perPage: self::CATEGORY_DROPDOWN_LIMIT);

        require_once __DIR__ . '/../../views/products/form.php';
    }

    public function create(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $values = $this->readInput();

        try {
            $this->productService->createProduct($values);
            header('Location: ' . self::LIST_URL . '?result=created', true, 303);
            exit;
        } catch (ValidationException $exception) {
            $product = null;
            $errors = $exception->errors();
            $categories = $this->categoryService->listCategories(perPage: self::CATEGORY_DROPDOWN_LIMIT);

            require_once __DIR__ . '/../../views/products/form.php';
        }
    }

    public function showEditForm(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $product = $this->productService->getProductById((int) $id);
        $values = [
            'sku' => $product->sku,
            'name' => $product->name,
            'category_id' => (string) $product->categoryId,
            'unit' => $product->unit,
            'buy_price' => (string) $product->buyPrice,
            'sell_price' => (string) $product->sellPrice,
            'reorder_point' => (string) $product->reorderPoint,
        ];
        $errors = [];
        $categories = $this->categoryService->listCategories(perPage: self::CATEGORY_DROPDOWN_LIMIT);

        require_once __DIR__ . '/../../views/products/form.php';
    }

    public function update(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $values = $this->readInput();

        try {
            $this->productService->updateProduct((int) $id, $values);
            header('Location: ' . self::LIST_URL . '?result=updated', true, 303);
            exit;
        } catch (ValidationException $exception) {
            $product = $this->productService->getProductById((int) $id);
            $errors = $exception->errors();
            $categories = $this->categoryService->listCategories(perPage: self::CATEGORY_DROPDOWN_LIMIT);

            require_once __DIR__ . '/../../views/products/form.php';
        }
    }

    public function toggleActive(string $id): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, [Role::Admin]);

        $product = $this->productService->getProductById((int) $id);
        $this->productService->setActive((int) $id, !$product->isActive);

        $result = $product->isActive ? 'deactivated' : 'activated';
        header('Location: ' . self::LIST_URL . '?result=' . $result, true, 303);
        exit;
    }

    /**
     * @return array{sku: string, name: string, category_id: string, unit: string, buy_price: string, sell_price: string, reorder_point: string, image: array{name: string, type: string, tmp_name: string, error: int, size: int}|null}
     */
    private function readInput(): array
    {
        /** @var array{name: string, type: string, tmp_name: string, error: int, size: int}|null $image */
        $image = $_FILES['image'] ?? null;

        return [
            'sku' => (string) ($_POST['sku'] ?? ''),
            'name' => (string) ($_POST['name'] ?? ''),
            'category_id' => (string) ($_POST['category_id'] ?? ''),
            'unit' => (string) ($_POST['unit'] ?? ''),
            'buy_price' => (string) ($_POST['buy_price'] ?? ''),
            'sell_price' => (string) ($_POST['sell_price'] ?? ''),
            'reorder_point' => (string) ($_POST['reorder_point'] ?? ''),
            'image' => $image,
        ];
    }
}
