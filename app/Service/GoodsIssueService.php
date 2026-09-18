<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderStatus;
use App\Entity\StockLedgerEntry;
use App\Entity\StockMovementType;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\ProductStockRepositoryInterface;
use App\Repository\SalesOrderRepositoryInterface;
use App\Repository\StockLedgerRepositoryInterface;
use PDO;
use Throwable;

/**
 * ARCH-02: berbeda dari GoodsReceiptService (PO-01) dalam dua hal penting.
 *
 * 1. Semua item diproses SEKALIGUS dengan qty penuh dari SO - tidak ada
 *    input qty parsial dari user seperti goods receipt (skema
 *    sales_order_items tidak punya kolom penerimaan sebagian). Kalau SATU
 *    saja item stoknya tidak cukup, SELURUH transaksi di-rollback - tidak
 *    ada goods issue separuh jalan.
 * 2. Penguranganstok pakai decrementIfSufficient() (bukan incrementQuantity
 *    dengan delta negatif) - method itu mengembalikan bool hasil dari
 *    `UPDATE ... WHERE quantity >= qty`, satu query atomik yang jadi
 *    mekanisme pencegahan oversell (lihat ADR-0006): dua goods issue
 *    konkuren untuk produk+gudang yang sama tidak bisa dua-duanya lolos
 *    mengurangi dari sisa stok yang sama, karena baris itu terkunci
 *    (row-level lock InnoDB) sepanjang transaksi yang sedang berjalan -
 *    yang kedua menunggu, lalu melihat quantity yang sudah diperbarui
 *    (bisa jadi tidak cukup lagi) begitu yang pertama commit.
 */
final class GoodsIssueService
{
    private const REFERENCE_TYPE = 'sales_order';

    public function __construct(
        private readonly SalesOrderRepositoryInterface $salesOrders,
        private readonly ProductStockRepositoryInterface $stocks,
        private readonly StockLedgerRepositoryInterface $ledger,
        private readonly PDO $pdo,
    ) {
    }

    public function issue(int $salesOrderId, int $performedBy): SalesOrder
    {
        $salesOrder = $this->salesOrders->findById($salesOrderId);
        if ($salesOrder === null) {
            throw new NotFoundException();
        }

        $this->assertCanIssue($salesOrder);

        $this->pdo->beginTransaction();
        try {
            foreach ($salesOrder->items as $item) {
                $sufficient = $this->stocks->decrementIfSufficient($item->productId, $salesOrder->warehouseId, $item->qty);

                if (!$sufficient) {
                    throw new ConflictException(sprintf(
                        'Stok tidak mencukupi untuk salah satu item (produk id %d, butuh %d).',
                        $item->productId,
                        $item->qty,
                    ));
                }

                $this->ledger->record(new StockLedgerEntry(
                    null,
                    $item->productId,
                    $salesOrder->warehouseId,
                    StockMovementType::Issue,
                    $item->qty,
                    self::REFERENCE_TYPE,
                    $salesOrderId,
                    $performedBy,
                    null,
                ));
            }

            $this->salesOrders->updateStatus($salesOrderId, SalesOrderStatus::Fulfilled);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $updated = $this->salesOrders->findById($salesOrderId);
        assert($updated !== null);

        return $updated;
    }

    /**
     * Murni logic status (tidak menyentuh PDO) - bisa di-unit-test langsung
     * dengan entity SalesOrder buatan tangan.
     */
    public function assertCanIssue(SalesOrder $salesOrder): void
    {
        if ($salesOrder->status !== SalesOrderStatus::Approved) {
            throw new ConflictException('SO harus berstatus Approved untuk diproses goods issue.');
        }
    }

    /**
     * @return StockLedgerEntry[]
     */
    public function getIssueHistory(int $salesOrderId): array
    {
        return $this->ledger->findByReference(self::REFERENCE_TYPE, $salesOrderId);
    }
}
