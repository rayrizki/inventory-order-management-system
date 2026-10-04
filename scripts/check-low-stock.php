<?php

declare(strict_types=1);

/**
 * JOB-01: script mandiri, terpisah dari siklus request web (mencerminkan
 * proses yang di dunia nyata biasanya berjalan lewat cron). Menghasilkan
 * ringkasan produk aktif yang stoknya (dijumlahkan lintas gudang aktif) di
 * bawah reorder_point-nya - sumber data SAMA dengan StockService yang
 * dipakai halaman detail Produk (WH-01) dan API-01, bukan query terpisah.
 *
 * Dijalankan manual lewat:
 *   docker compose exec app php scripts/check-low-stock.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use App\Repository\MySqlCategoryRepository;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlProductStockRepository;
use App\Repository\MySqlWarehouseRepository;
use App\Service\ProductService;
use App\Service\StockService;

$pdo = createPdoConnection();

$categoryRepository = new MySqlCategoryRepository($pdo);
$productRepository = new MySqlProductRepository($pdo);
$productService = new ProductService($productRepository, $categoryRepository);

$warehouseRepository = new MySqlWarehouseRepository($pdo);
$productStockRepository = new MySqlProductStockRepository($pdo);
$stockService = new StockService($productStockRepository, $warehouseRepository);

// Semua produk aktif dianggap "semua" - jauh di atas jumlah produk realistis
// (sama seperti pola dropdown di Controller, konsisten dengan konvensi lain
// di codebase ini untuk kebutuhan "ambil semua" tanpa method baru).
const ALL_PRODUCTS_LIMIT = 10000;

$products = $productService->listProducts(isActive: true, perPage: ALL_PRODUCTS_LIMIT);

$lowStock = [];
foreach ($products as $product) {
    $summary = $stockService->getStockSummary($product->id);

    if ($summary['total'] < $product->reorderPoint) {
        $lowStock[] = [
            'sku' => $product->sku,
            'name' => $product->name,
            'total' => $summary['total'],
            'reorder_point' => $product->reorderPoint,
        ];
    }
}

echo "=== Ringkasan Produk di Bawah Reorder Point ===\n";
echo 'Dijalankan: ' . date('Y-m-d H:i:s') . "\n";
echo 'Total produk aktif diperiksa: ' . count($products) . "\n";
echo 'Produk di bawah reorder point: ' . count($lowStock) . "\n\n";

if ($lowStock === []) {
    echo "Tidak ada produk yang stoknya di bawah reorder point.\n";
    exit(0);
}

printf("%-12s %-40s %8s %8s\n", 'SKU', 'Nama', 'Stok', 'Reorder');
echo str_repeat('-', 72) . "\n";
foreach ($lowStock as $row) {
    printf("%-12s %-40s %8d %8d\n", $row['sku'], $row['name'], $row['total'], $row['reorder_point']);
}

exit(0);
