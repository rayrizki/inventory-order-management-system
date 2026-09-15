<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

use App\Controller\AuthController;
use App\Controller\CategoryController;
use App\Controller\DashboardController;
use App\Controller\WarehouseController;
use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use App\Exception\UnauthenticatedException;
use App\Http\Router;
use App\Repository\MySqlCategoryRepository;
use App\Repository\MySqlUserRepository;
use App\Repository\MySqlWarehouseRepository;
use App\Service\AuthService;
use App\Service\CategoryService;
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

// Wiring manual (constructor injection, tanpa DI container) - ARCH-01.
$pdo = createPdoConnection();
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
}
