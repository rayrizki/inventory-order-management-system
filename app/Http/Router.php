<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Router manual super sederhana - mendukung path parameter seperti
 * /categories/{id}/edit lewat regex, tanpa dependency apa pun. Dipakai
 * karena banyak resource (Category, Warehouse, Product, ...) butuh
 * halaman detail/edit per-ID.
 */
final class Router
{
    /** @var array<string, array<int, array{pattern: string, handler: callable}>> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes['GET'][] = ['pattern' => $pattern, 'handler' => $handler];
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->routes['POST'][] = ['pattern' => $pattern, 'handler' => $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes[$method] ?? [] as $route) {
            $params = $this->match($route['pattern'], $path);

            if ($params !== null) {
                ($route['handler'])(...$params);

                return;
            }
        }

        http_response_code(404);
        echo '404 Not Found';
    }

    /**
     * @return string[]|null
     */
    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*\}#', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches) !== 1) {
            return null;
        }

        array_shift($matches);

        return $matches;
    }
}
