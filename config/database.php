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
        // ARCH-02: seluruh write bersyarat di repository (transitionStatus,
        // decrementIfSufficient, incrementItemReceivedQtyIfWithinOrdered)
        // memakai rowCount() untuk menjawab "apakah syarat di WHERE
        // terpenuhi?". Default MySQL menghitung baris yang BERUBAH, bukan
        // yang COCOK - jadi UPDATE yang syaratnya terpenuhi tapi menulis
        // nilai yang sama persis (mis. PartiallyReceived -> PartiallyReceived
        // saat barang diterima bertahap) akan salah dibaca sebagai "syarat
        // tidak terpenuhi" dan transaksinya di-rollback tanpa alasan.
        PDO::MYSQL_ATTR_FOUND_ROWS => true,
    ]);
}
