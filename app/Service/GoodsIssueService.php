<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
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
 * 2. Pengurangan stok pakai decrementIfSufficient() (bukan incrementQuantity
 *    dengan delta negatif) - method itu mengembalikan bool hasil dari
 *    `UPDATE ... WHERE quantity >= qty`, satu query atomik yang jadi
 *    mekanisme pencegahan oversell (lihat ADR-0006): dua goods issue
 *    konkuren untuk produk+gudang yang sama tidak bisa dua-duanya lolos
 *    mengurangi dari sisa stok yang sama, karena baris itu terkunci
 *    (row-level lock InnoDB) sepanjang transaksi yang sedang berjalan -
 *    yang kedua menunggu, lalu melihat quantity yang sudah diperbarui
 *    (bisa jadi tidak cukup lagi) begitu yang pertama commit.
 *
 * Guard stok di atas mencegah oversell, tapi TIDAK mencegah satu SO diproses
 * dua kali (dua request bisa sama-sama mengurangi stok yang kebetulan masih
 * cukup, lalu menulis dua baris ledger untuk order yang sama). Karena itu
 * transaksi dibuka dengan "mengklaim" SO lewat transitionStatus() bersyarat,
 * dan baris stok dikunci dengan urutan productId yang sama di setiap
 * transaksi supaya dua order multi-item tidak saling mengunci (deadlock).
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
            // Klaim SO LEBIH DULU, sebelum stok disentuh: UPDATE bersyarat ini
            // mengunci baris sales_orders sekaligus memastikan hanya SATU
            // request yang berhasil memindahkan Approved -> Fulfilled. Request
            // kedua atas SO yang sama menunggu di lock ini, lalu melihat 0 baris
            // terpengaruh begitu yang pertama commit - jadi tidak ada goods
            // issue ganda yang mengeluarkan stok dua kali untuk satu order.
            if (!$this->salesOrders->transitionStatus($salesOrderId, [SalesOrderStatus::Approved], SalesOrderStatus::Fulfilled)) {
                throw new ConflictException('SO ini sudah diproses atau statusnya berubah - muat ulang halaman lalu periksa lagi.');
            }

            foreach ($this->itemsInLockOrder($salesOrder) as $item) {
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

            $this->pdo->commit();
        } catch (Throwable $exception) {
            // inTransaction() dicek dulu: kalau commit() sendiri yang gagal,
            // transaksi sudah tidak aktif dan rollBack() akan melempar
            // exception kedua yang menutupi penyebab aslinya.
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

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

    /**
     * Baris product_stock dikunci menaik berdasarkan productId, bukan urutan
     * item diketik user. Dua SO yang memuat produk sama dengan urutan input
     * berbeda (SO#1: P2 lalu P1; SO#2: P1 lalu P2) kalau diproses bersamaan
     * akan saling menunggu kunci milik yang lain - deadlock InnoDB (error
     * 1213) yang muncul sebagai kegagalan mentah, bukan pesan yang berarti.
     * Dengan urutan kunci yang sama di seluruh transaksi, salah satu request
     * cukup menunggu lalu lanjut.
     *
     * @return SalesOrderItem[]
     */
    private function itemsInLockOrder(SalesOrder $salesOrder): array
    {
        $items = $salesOrder->items;
        usort($items, static fn (SalesOrderItem $a, SalesOrderItem $b): int => $a->productId <=> $b->productId);

        return $items;
    }
}
