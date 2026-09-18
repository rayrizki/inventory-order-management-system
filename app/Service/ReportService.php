<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CustomerRepositoryInterface;
use App\Repository\ProductRepositoryInterface;
use App\Repository\PurchaseOrderRepositoryInterface;
use App\Repository\SalesOrderRepositoryInterface;
use App\Repository\StockLedgerRepositoryInterface;
use App\Repository\SupplierRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Repository\WarehouseRepositoryInterface;

/**
 * REPORT-01: dua laporan CSV, dibangun dari method repository YANG SAMA
 * dipakai DASH-01 (`countByStatus()`, dan repository dasar yang sama untuk
 * membaca baris StockLedger/PO/SO) - bukan query mentah terpisah yang bisa
 * menyimpang dari angka dashboard. Service ini murni menyusun baris siap-
 * ekspor (array asosiatif per baris); penulisan CSV sungguhan (fputcsv,
 * header Content-Type) tetap tanggung jawab Controller (ARCH-01 - Service
 * tidak menyentuh output/superglobal).
 */
final class ReportService
{
    /** Dropdown/lookup map dianggap "semua" - jauh di atas jumlah data realistis. */
    private const LOOKUP_LIMIT = 10000;

    public function __construct(
        private readonly StockLedgerRepositoryInterface $ledger,
        private readonly PurchaseOrderRepositoryInterface $purchaseOrders,
        private readonly SalesOrderRepositoryInterface $salesOrders,
        private readonly ProductRepositoryInterface $products,
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly CustomerRepositoryInterface $customers,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    /**
     * @return list<array{tanggal: string, sku: string, produk: string, gudang: string, tipe_pergerakan: string, quantity: int, referensi: string, dilakukan_oleh: string}>
     */
    public function getStockLedgerReport(string $fromDate, string $toDate): array
    {
        $productNames = $this->productLookup();
        $warehouseNames = $this->warehouseLookup();
        $userNames = $this->userLookup();

        $rows = [];
        foreach ($this->ledger->listForReport($fromDate, $toDate) as $entry) {
            $rows[] = [
                'tanggal' => (string) $entry->createdAt,
                'sku' => $productNames[$entry->productId]['sku'] ?? '-',
                'produk' => $productNames[$entry->productId]['name'] ?? '-',
                'gudang' => $warehouseNames[$entry->warehouseId] ?? '-',
                'tipe_pergerakan' => $entry->movementType->value,
                'quantity' => $entry->quantity,
                'referensi' => $entry->referenceType . ' #' . $entry->referenceId,
                'dilakukan_oleh' => $userNames[$entry->performedBy] ?? '-',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{tipe: string, nomor: string, tanggal: string, pihak_terkait: string, status: string, dibuat_oleh: string, disetujui_oleh: string}>
     */
    public function getOrdersReport(string $fromDate, string $toDate): array
    {
        $supplierNames = $this->supplierLookup();
        $customerNames = $this->customerLookup();
        $userNames = $this->userLookup();

        $rows = [];
        foreach ($this->purchaseOrders->listForReport($fromDate, $toDate) as $po) {
            $rows[] = [
                'tipe' => 'Purchase Order',
                'nomor' => 'PO-' . str_pad((string) $po->id, 6, '0', STR_PAD_LEFT),
                'tanggal' => $po->orderDate,
                'pihak_terkait' => $supplierNames[$po->supplierId] ?? '-',
                'status' => $po->status->value,
                'dibuat_oleh' => $userNames[$po->createdBy] ?? '-',
                'disetujui_oleh' => '-',
            ];
        }

        foreach ($this->salesOrders->listForReport($fromDate, $toDate) as $so) {
            $rows[] = [
                'tipe' => 'Sales Order',
                'nomor' => 'SO-' . str_pad((string) $so->id, 6, '0', STR_PAD_LEFT),
                'tanggal' => (string) $so->createdAt,
                'pihak_terkait' => $customerNames[$so->customerId] ?? '-',
                'status' => $so->status->value,
                'dibuat_oleh' => $userNames[$so->createdBy] ?? '-',
                'disetujui_oleh' => $so->approvedBy !== null ? ($userNames[$so->approvedBy] ?? '-') : '-',
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $a['tanggal'] <=> $b['tanggal']);

        return $rows;
    }

    /**
     * @return array<int, array{sku: string, name: string}>
     */
    private function productLookup(): array
    {
        $map = [];
        foreach ($this->products->listAll(limit: self::LOOKUP_LIMIT) as $product) {
            $map[$product->id] = ['sku' => $product->sku, 'name' => $product->name];
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function warehouseLookup(): array
    {
        $map = [];
        foreach ($this->warehouses->listAll(limit: self::LOOKUP_LIMIT) as $warehouse) {
            $map[$warehouse->id] = $warehouse->name;
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function supplierLookup(): array
    {
        $map = [];
        foreach ($this->suppliers->listAll(limit: self::LOOKUP_LIMIT) as $supplier) {
            $map[$supplier->id] = $supplier->name;
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function customerLookup(): array
    {
        $map = [];
        foreach ($this->customers->listAll(limit: self::LOOKUP_LIMIT) as $customer) {
            $map[$customer->id] = $customer->name;
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function userLookup(): array
    {
        $map = [];
        foreach ($this->users->listAll(limit: self::LOOKUP_LIMIT) as $user) {
            $map[$user->id] = $user->name;
        }

        return $map;
    }
}
