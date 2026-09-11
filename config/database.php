<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function createPdoConnection(): PDO
{
    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $database = env('DB_DATABASE', 'iom_db');
    $username = env('DB_USERNAME', 'root');
    $password = env('DB_PASSWORD', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
