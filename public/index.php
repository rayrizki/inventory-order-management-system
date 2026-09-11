<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Exception\ForbiddenException;
use App\Exception\UnauthenticatedException;
use App\Repository\MySqlUserRepository;
use App\Service\AuthService;
use App\Session\AuthGuard;
use App\Session\PhpSessionAdapter;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// PHP built-in server calls this router for every request, including static
// assets, when a router script is given to `php -S`. Hand existing files
// (CSS/JS/images under public/) back to the server instead of 404-ing them.
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

$method = $_SERVER['REQUEST_METHOD'];

// Wiring manual (constructor injection, tanpa DI container) - ARCH-01.
$pdo = createPdoConnection();
$session = new PhpSessionAdapter();
$userRepository = new MySqlUserRepository($pdo);
$authService = new AuthService($userRepository);
$authGuard = new AuthGuard($session);

$authController = new AuthController($authService, $session);
$dashboardController = new DashboardController($authGuard);

$routes = [
    'GET' => [
        '/login' => [$authController, 'showLoginForm'],
        '/dashboard' => [$dashboardController, 'index'],
    ],
    'POST' => [
        '/login' => [$authController, 'login'],
        '/logout' => [$authController, 'logout'],
    ],
];

$handler = $routes[$method][$path] ?? null;

if ($handler === null) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

try {
    $handler();
} catch (UnauthenticatedException) {
    // Akses tanpa login diarahkan ke login (ERR-01).
    header('Location: /login', true, 303);
    exit;
} catch (ForbiddenException) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
}
