<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\DashboardController;

require __DIR__ . '/../app/Controller/AuthController.php';
require __DIR__ . '/../app/Controller/DashboardController.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// PHP built-in server calls this router for every request, including static
// assets, when a router script is given to `php -S`. Hand existing files
// (CSS/JS/images under public/) back to the server instead of 404-ing them.
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    'GET' => [
        '/login' => [AuthController::class, 'showLoginForm'],
        '/dashboard' => [DashboardController::class, 'index'],
    ],
];

$handler = $routes[$method][$path] ?? null;

if ($handler === null) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

[$controllerClass, $action] = $handler;
(new $controllerClass())->$action();
