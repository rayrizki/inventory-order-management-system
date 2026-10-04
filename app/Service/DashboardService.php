<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PurchaseOrderStatus;
use App\Entity\SalesOrderStatus;
use App\Repository\ProductFilter;
use App\Repository\ProductRepositoryInterface;
use App\Repository\PurchaseOrderRepositoryInterface;
use App\Repository\SalesOrderRepositoryInterface;

/**
 * DASH-01: satu Service, tiga bentuk ringkasan berbeda per role (bukan tiga
 * Service terpisah) - ketiganya menyusun ulang HASIL agregasi yang sama
 * (countByStatus() PO/SO, jumlah produk low-stock) dengan sudut pandang
 * berbeda, bukan logic bisnis yang benar-benar berbeda. Otorisasi "role
 * mana lihat ringkasan mana" tetap keputusan Controller (AuthGuard), bukan
 * Service - sama seperti Service lain di codebase ini.
 */
final class DashboardService
{
    /** Produk low-stock ditampilkan sebagai daftar pendek di dashboard, bukan seluruh hasil. */
    private const LOW_STOCK_PREVIEW_LIMIT = 10;

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly PurchaseOrderRepositoryInterface $purchaseOrders,
        private readonly SalesOrderRepositoryInterface $salesOrders,
    ) {
    }

    /**
     * @return array{inventoryValue: float, lowStockCount: int, lowStockProducts: \App\Entity\Product[], purchaseOrdersByStatus: array<string, int>, salesOrdersByStatus: array<string, int>}
     */
    public function getAdminSummary(): array
    {
        return [
            'inventoryValue' => $this->products->sumInventoryValue(),
            'lowStockCount' => $this->products->countAll(new ProductFilter(stockStatus: 'low')),
            'lowStockProducts' => $this->products->listAll(new ProductFilter(stockStatus: 'low'), limit: self::LOW_STOCK_PREVIEW_LIMIT),
            'purchaseOrdersByStatus' => $this->purchaseOrders->countByStatus(),
            'salesOrdersByStatus' => $this->salesOrders->countByStatus(),
        ];
    }

    /**
     * @return array{salesOrdersByStatus: array<string, int>}
     */
    public function getSalesSummary(int $salesUserId): array
    {
        return [
            'salesOrdersByStatus' => $this->salesOrders->countByStatus($salesUserId),
        ];
    }

    /**
     * @return array{pendingReceiptCount: int, pendingIssueCount: int, lowStockCount: int, lowStockProducts: \App\Entity\Product[]}
     */
    public function getWarehouseSummary(): array
    {
        $purchaseOrdersByStatus = $this->purchaseOrders->countByStatus();
        $salesOrdersByStatus = $this->salesOrders->countByStatus();

        return [
            // "Antrean goods receipt" - PO yang sudah diajukan ke supplier
            // tapi barangnya belum (atau belum seluruhnya) diterima.
            'pendingReceiptCount' => $purchaseOrdersByStatus[PurchaseOrderStatus::Ordered->value]
                + $purchaseOrdersByStatus[PurchaseOrderStatus::PartiallyReceived->value],
            // "Antrean goods issue" - SO yang sudah Approved, menunggu diproses.
            'pendingIssueCount' => $salesOrdersByStatus[SalesOrderStatus::Approved->value],
            'lowStockCount' => $this->products->countAll(new ProductFilter(stockStatus: 'low')),
            'lowStockProducts' => $this->products->listAll(new ProductFilter(stockStatus: 'low'), limit: self::LOW_STOCK_PREVIEW_LIMIT),
        ];
    }
}
