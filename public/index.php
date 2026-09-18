<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

use App\Controller\AuthController;
use App\Controller\CategoryController;
use App\Controller\CustomerController;
use App\Controller\DashboardController;
use App\Controller\ProductController;
use App\Controller\PurchaseOrderController;
use App\Controller\SupplierController;
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
use App\Repository\MySqlStockLedgerRepository;
use App\Repository\MySqlSupplierRepository;
use App\Repository\MySqlUserRepository;
use App\Repository\MySqlWarehouseRepository;
use App\Service\AuthService;
use App\Service\CategoryService;
use App\Service\CustomerService;
use App\Service\GoodsReceiptService;
use App\Service\ProductService;
use App\Service\PurchaseOrderService;
use App\Service\StockService;
use App\Service\SupplierService;
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

$dashboardController = new DashboardController($authGuard);

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

$purchaseOrderRepository = new MySqlPurchaseOrderRepository($pdo);
$purchaseOrderService = new PurchaseOrderService($purchaseOrderRepository, $supplierRepository, $warehouseRepository, $productRepository);
$stockLedgerRepository = new MySqlStockLedgerRepository($pdo);
$goodsReceiptService = new GoodsReceiptService($purchaseOrderRepository, $productStockRepository, $stockLedgerRepository, $pdo);
$purchaseOrderController = new PurchaseOrderController($purchaseOrderService, $goodsReceiptService, $supplierService, $warehouseService, $productService, $authGuard);

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

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $path);
} catch (UnauthenticatedException) {
    // Akses tanpa login diarahkan ke login (ERR-01).
    header('Location: /login', true, 303);
    exit;
} catch (ForbiddenException) {
    http_response_code(403);
    echo '403 Forbidden';
} catch (NotFoundException) {
    http_response_code(404);
    echo '404 Not Found';
} catch (\Throwable $exception) {
    renderServerError($exception);
}
