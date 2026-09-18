<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ProductService;
use App\Service\StockService;
use App\Session\AuthGuard;

/**
 * API-01: satu endpoint JSON, terpisah dari halaman HTML biasa. Alurnya
 * sama seperti dijelaskan di docs/planning/class-diagram-initial.md:
 * resolve SKU -> Product lewat ProductService::getProductBySku()
 * (NotFoundException -> 404 kalau SKU tidak ada), baru StockService yang
 * SAMA dipakai halaman HTML biasa (WH-01) - bukan logic duplikat.
 */
final class ProductAvailabilityApiController
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly StockService $stockService,
        private readonly AuthGuard $guard,
    ) {
    }

    public function availability(string $sku): void
    {
        // Autentikasi diperiksa sama seperti halaman biasa (AuthGuard) -
        // bedanya cuma format response 401-nya, ditangani terpusat di
        // public/index.php (JSON untuk /api/*, bukan redirect ke /login).
        $this->guard->requireLogin();

        $product = $this->productService->getProductBySku($sku);
        $stock = $this->stockService->getStockSummary($product->id);

        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'sku' => $product->sku,
            'name' => $product->name,
            'total_quantity' => $stock['total'],
            'warehouses' => array_map(
                static fn (array $line): array => [
                    'warehouse_id' => $line['warehouseId'],
                    'warehouse_name' => $line['warehouseName'],
                    'quantity' => $line['quantity'],
                ],
                $stock['warehouses'],
            ),
        ]);
    }
}
