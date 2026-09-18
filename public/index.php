<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

use App\Controller\AuthController;
use App\Controller\CategoryController;
use App\Controller\CustomerController;
use App\Controller\DashboardController;
use App\Controller\ProductAvailabilityApiController;
use App\Controller\ProductController;
use App\Controller\PurchaseOrderController;
use App\Controller\ReportController;
use App\Controller\SalesOrderController;
use App\Controller\SupplierController;
use App\Controller\UserController;
use App\Controller\WarehouseController;
use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use App\Exception\UnauthenticatedException;
use App\Http\Router;
use App\Repository\MySqlCategoryRepository;
use App\Repository\MySqlCustomerRepository;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlProductStockRepository;
use App\Repository\MySqlPurchaseOrderRepository;
use App\Repository\MySqlSalesOrderRepository;
use App\Repository\MySqlStockLedgerRepository;
use App\Repository\MySqlSupplierRepository;
use App\Repository\MySqlUserRepository;
use App\Repository\MySqlWarehouseRepository;
use App\Service\AuthService;
use App\Service\CategoryService;
use App\Service\CustomerService;
use App\Service\DashboardService;
use App\Service\GoodsIssueService;
use App\Service\GoodsReceiptService;
use App\Service\ProductService;
use App\Service\PurchaseOrderService;
use App\Service\ReportService;
use App\Service\SalesOrderService;
use App\Service\StockService;
use App\Service\SupplierService;
use App\Service\UserService;
use App\Service\WarehouseService;
use App\Session\AuthGuard;
use App\Session\CsrfToken;
use App\Session\PhpSessionAdapter;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// PHP built-in server calls this router for every request, including static
// assets, when a router script is given to `php -S`. Hand existing files
// (CSS/JS/images under public/) back to the server instead of 404-ing them.
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

/**
 * ERR-01: exception tak terduga (bug kode, koneksi DB putus, dst) tidak
 * boleh menampilkan stack trace ke user - dicatat ke error log server,
 * ditampilkan sebagai 500 generik. display_errors juga dimatikan di level
 * PHP (lihat docker/errors.ini) sebagai lapis kedua.
 */
function renderServerError(\Throwable $exception): never
{
    error_log(sprintf('[500] %s in %s:%d', $exception->getMessage(), $exception->getFile(), $exception->getLine()));
    http_response_code(500);
    echo '500 Internal Server Error';
    exit;
}

// Koneksi database dibungkus try/catch sendiri (bukan cuma try/catch di
// sekitar dispatch() di bawah) - kegagalan koneksi terjadi SEBELUM router
// sempat jalan sama sekali, jadi harus ditangkap terpisah supaya tidak
// lolos sebagai halaman kosong bawaan PHP.
try {
    $pdo = createPdoConnection();
} catch (\Throwable $exception) {
    renderServerError($exception);
}

// Wiring manual (constructor injection, tanpa DI container) - ARCH-01.
$session = new PhpSessionAdapter();
$authGuard = new AuthGuard($session);

$userRepository = new MySqlUserRepository($pdo);
$authService = new AuthService($userRepository);
$authController = new AuthController($authService, $session);
$userService = new UserService($userRepository);
$userController = new UserController($userService, $authGuard);

$categoryRepository = new MySqlCategoryRepository($pdo);
$categoryService = new CategoryService($categoryRepository);
$categoryController = new CategoryController($categoryService, $authGuard);

$warehouseRepository = new MySqlWarehouseRepository($pdo);
$warehouseService = new WarehouseService($warehouseRepository);
$warehouseController = new WarehouseController($warehouseService, $authGuard);

$supplierRepository = new MySqlSupplierRepository($pdo);
$supplierService = new SupplierService($supplierRepository);
$supplierController = new SupplierController($supplierService, $authGuard);

$customerRepository = new MySqlCustomerRepository($pdo);
$customerService = new CustomerService($customerRepository);
$customerController = new CustomerController($customerService, $authGuard);

$productRepository = new MySqlProductRepository($pdo);
$productService = new ProductService($productRepository, $categoryRepository);
$productStockRepository = new MySqlProductStockRepository($pdo);
$stockService = new StockService($productStockRepository, $warehouseRepository);
$productController = new ProductController($productService, $categoryService, $stockService, $authGuard);
$productAvailabilityApiController = new ProductAvailabilityApiController($productService, $stockService, $authGuard);

$purchaseOrderRepository = new MySqlPurchaseOrderRepository($pdo);
$purchaseOrderService = new PurchaseOrderService($purchaseOrderRepository, $supplierRepository, $warehouseRepository, $productRepository);
$stockLedgerRepository = new MySqlStockLedgerRepository($pdo);
$goodsReceiptService = new GoodsReceiptService($purchaseOrderRepository, $productStockRepository, $stockLedgerRepository, $pdo);
$purchaseOrderController = new PurchaseOrderController($purchaseOrderService, $goodsReceiptService, $supplierService, $warehouseService, $productService, $authGuard);

$salesOrderRepository = new MySqlSalesOrderRepository($pdo);
$salesOrderService = new SalesOrderService($salesOrderRepository, $customerRepository, $warehouseRepository, $productRepository);
$goodsIssueService = new GoodsIssueService($salesOrderRepository, $productStockRepository, $stockLedgerRepository, $pdo);
$salesOrderController = new SalesOrderController($salesOrderService, $goodsIssueService, $customerService, $warehouseService, $productService, $authGuard);

$dashboardService = new DashboardService($productRepository, $purchaseOrderRepository, $salesOrderRepository);
$dashboardController = new DashboardController($dashboardService, $authGuard);

$reportService = new ReportService($stockLedgerRepository, $purchaseOrderRepository, $salesOrderRepository, $productRepository, $warehouseRepository, $supplierRepository, $customerRepository, $userRepository);
$reportController = new ReportController($reportService, $authGuard);

$router = new Router(new CsrfToken($session));

$router->get('/login', [$authController, 'showLoginForm']);
$router->post('/login', [$authController, 'login']);
$router->post('/logout', [$authController, 'logout']);

$router->get('/dashboard', [$dashboardController, 'index']);

$router->get('/categories', [$categoryController, 'index']);
$router->get('/categories/create', [$categoryController, 'showCreateForm']);
$router->post('/categories', [$categoryController, 'create']);
$router->get('/categories/{id}/edit', [$categoryController, 'showEditForm']);
$router->post('/categories/{id}', [$categoryController, 'update']);
$router->post('/categories/{id}/delete', [$categoryController, 'delete']);

$router->get('/warehouses', [$warehouseController, 'index']);
$router->get('/warehouses/create', [$warehouseController, 'showCreateForm']);
$router->post('/warehouses', [$warehouseController, 'create']);
$router->get('/warehouses/{id}/edit', [$warehouseController, 'showEditForm']);
$router->post('/warehouses/{id}', [$warehouseController, 'update']);
$router->post('/warehouses/{id}/toggle-active', [$warehouseController, 'toggleActive']);

$router->get('/suppliers', [$supplierController, 'index']);
$router->get('/suppliers/create', [$supplierController, 'showCreateForm']);
$router->post('/suppliers', [$supplierController, 'create']);
$router->get('/suppliers/{id}/edit', [$supplierController, 'showEditForm']);
$router->post('/suppliers/{id}', [$supplierController, 'update']);
$router->post('/suppliers/{id}/toggle-active', [$supplierController, 'toggleActive']);

$router->get('/customers', [$customerController, 'index']);
$router->get('/customers/create', [$customerController, 'showCreateForm']);
$router->post('/customers', [$customerController, 'create']);
$router->get('/customers/{id}/edit', [$customerController, 'showEditForm']);
$router->post('/customers/{id}', [$customerController, 'update']);
$router->post('/customers/{id}/toggle-active', [$customerController, 'toggleActive']);

$router->get('/products', [$productController, 'index']);
$router->get('/products/create', [$productController, 'showCreateForm']);
$router->post('/products', [$productController, 'create']);
$router->get('/products/{id}/edit', [$productController, 'showEditForm']);
$router->get('/products/{id}', [$productController, 'show']);
$router->post('/products/{id}', [$productController, 'update']);
$router->post('/products/{id}/toggle-active', [$productController, 'toggleActive']);

$router->get('/purchase-orders', [$purchaseOrderController, 'index']);
$router->get('/purchase-orders/create', [$purchaseOrderController, 'showCreateForm']);
$router->post('/purchase-orders', [$purchaseOrderController, 'create']);
$router->get('/purchase-orders/{id}', [$purchaseOrderController, 'show']);
$router->post('/purchase-orders/{id}/mark-ordered', [$purchaseOrderController, 'markOrdered']);
$router->post('/purchase-orders/{id}/cancel', [$purchaseOrderController, 'cancel']);
$router->post('/purchase-orders/{id}/receive', [$purchaseOrderController, 'receiveGoods']);

$router->get('/sales-orders', [$salesOrderController, 'index']);
$router->get('/sales-orders/create', [$salesOrderController, 'showCreateForm']);
$router->post('/sales-orders', [$salesOrderController, 'create']);
$router->get('/sales-orders/{id}', [$salesOrderController, 'show']);
$router->post('/sales-orders/{id}/submit-for-approval', [$salesOrderController, 'submitForApproval']);
$router->post('/sales-orders/{id}/approve', [$salesOrderController, 'approve']);
$router->post('/sales-orders/{id}/cancel', [$salesOrderController, 'cancel']);
$router->post('/sales-orders/{id}/issue', [$salesOrderController, 'processGoodsIssue']);

$router->get('/reports', [$reportController, 'index']);
$router->get('/reports/stock-ledger.csv', [$reportController, 'exportStockLedgerCsv']);
$router->get('/reports/orders.csv', [$reportController, 'exportOrdersCsv']);

$router->get('/users', [$userController, 'index']);
$router->get('/users/create', [$userController, 'showCreateForm']);
$router->post('/users', [$userController, 'create']);
$router->get('/users/{id}/edit', [$userController, 'showEditForm']);
$router->post('/users/{id}', [$userController, 'update']);
$router->post('/users/{id}/toggle-active', [$userController, 'toggleActive']);

// API-01: satu endpoint JSON terpisah dari halaman HTML biasa.
$router->get('/api/products/{sku}/availability', [$productAvailabilityApiController, 'availability']);

// Request ke /api/* butuh format response error yang beda dari halaman HTML
// (JSON + kode status yang tepat, bukan redirect/halaman teks polos) -
// dicek sekali di sini, dipakai oleh keempat catch block di bawah.
$isApiRequest = str_starts_with($path, '/api/');

/**
 * @param array<string, mixed> $payload
 */
function renderJsonError(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $path);
} catch (UnauthenticatedException) {
    if ($isApiRequest) {
        renderJsonError(401, ['error' => 'Unauthenticated']);
    }
    // Akses tanpa login diarahkan ke login (ERR-01).
    header('Location: /login', true, 303);
    exit;
} catch (ForbiddenException) {
    if ($isApiRequest) {
        renderJsonError(403, ['error' => 'Forbidden']);
    }
    http_response_code(403);
    echo '403 Forbidden';
} catch (NotFoundException) {
    if ($isApiRequest) {
        renderJsonError(404, ['error' => 'Not Found']);
    }
    http_response_code(404);
    echo '404 Not Found';
} catch (\Throwable $exception) {
    if ($isApiRequest) {
        error_log(sprintf('[500] %s in %s:%d', $exception->getMessage(), $exception->getFile(), $exception->getLine()));
        renderJsonError(500, ['error' => 'Internal Server Error']);
    }
    renderServerError($exception);
}
