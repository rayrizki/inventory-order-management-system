<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;
use App\Entity\StockLedgerEntry;
use App\Entity\StockMovementType;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\ProductStockRepositoryInterface;
use App\Repository\PurchaseOrderRepositoryInterface;
use App\Repository\StockLedgerRepositoryInterface;
use PDO;
use Throwable;

/**
 * Dipisah dari PurchaseOrderService (bukan cuma method tambahan) karena
 * tanggung jawabnya beda: PurchaseOrderService mengelola header/status PO,
 * GoodsReceiptService mengoordinasikan tiga tabel sekaligus dalam SATU
 * transaksi (ARCH-02) - purchase_order_items.received_qty, product_stock,
 * stock_ledger. Validasi murni (computeReceiptPlan) dipisah dari eksekusi
 * (receive()) supaya bisa di-unit-test tanpa koneksi database sungguhan -
 * hanya receive() sendiri yang butuh PDO nyata (diuji lewat integration test).
 */
final class GoodsReceiptService
{
    private const REFERENCE_TYPE = 'purchase_order';

    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $purchaseOrders,
        private readonly ProductStockRepositoryInterface $stocks,
        private readonly StockLedgerRepositoryInterface $ledger,
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array<int, int> $receivedQtyByItemId itemId => qty yang diterima pada aksi ini (baris qty 0/kosong diabaikan)
     */
    public function receive(int $purchaseOrderId, array $receivedQtyByItemId, int $performedBy): PurchaseOrder
    {
        $purchaseOrder = $this->purchaseOrders->findById($purchaseOrderId);
        if ($purchaseOrder === null) {
            throw new NotFoundException();
        }

        $plan = $this->computeReceiptPlan($purchaseOrder, $receivedQtyByItemId);

        $this->pdo->beginTransaction();
        try {
            foreach ($this->planInLockOrder($plan) as $itemId => $line) {
                // Guard di WHERE, bukan cuma di computeReceiptPlan(): rencana
                // itu dihitung dari sisa qty yang dibaca SEBELUM transaksi, jadi
                // dua penerimaan konkuren atas item yang sama bisa sama-sama
                // lolos validasi. UPDATE bersyarat ini yang memastikan hanya
                // satu di antaranya benar-benar menambah received_qty.
                if (!$this->purchaseOrders->incrementItemReceivedQtyIfWithinOrdered($itemId, $line['qty'])) {
                    throw new ConflictException(
                        'Sisa qty item ini sudah berubah (kemungkinan diterima request lain) - muat ulang halaman lalu periksa lagi.'
                    );
                }

                $this->stocks->incrementQuantity($line['item']->productId, $purchaseOrder->warehouseId, $line['qty']);
                $this->ledger->record(new StockLedgerEntry(
                    null,
                    $line['item']->productId,
                    $purchaseOrder->warehouseId,
                    StockMovementType::Receipt,
                    $line['qty'],
                    self::REFERENCE_TYPE,
                    $purchaseOrderId,
                    $performedBy,
                    null,
                ));
            }

            $updated = $this->purchaseOrders->findById($purchaseOrderId);
            assert($updated !== null);
            $newStatus = $this->allItemsFullyReceived($updated) ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived;

            // Status hanya boleh maju dari dua status yang memang menerima
            // barang - kalau PO keburu dibatalkan request lain, transisi ini
            // gagal dan seluruh penerimaan ikut di-rollback, sehingga stok
            // tidak pernah bertambah untuk PO yang sudah Cancelled.
            $receivable = [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived];
            if (!$this->purchaseOrders->transitionStatus($purchaseOrderId, $receivable, $newStatus)) {
                throw new ConflictException('Status PO berubah saat diproses - muat ulang halaman lalu periksa lagi.');
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

        $result = $this->purchaseOrders->findById($purchaseOrderId);
        assert($result !== null);

        return $result;
    }

    /**
     * Murni logic (tidak menyentuh PDO/repository tulis) - bisa di-unit-test
     * langsung dengan entity PurchaseOrder buatan tangan.
     *
     * @param array<int, int> $receivedQtyByItemId
     * @return array<int, array{item: PurchaseOrderItem, qty: int}>
     */
    public function computeReceiptPlan(PurchaseOrder $purchaseOrder, array $receivedQtyByItemId): array
    {
        if (!in_array($purchaseOrder->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived], true)) {
            throw new ConflictException('PO harus berstatus Ordered atau PartiallyReceived untuk menerima barang.');
        }

        $itemsById = [];
        foreach ($purchaseOrder->items as $item) {
            $itemsById[$item->id] = $item;
        }

        $errors = [];
        $plan = [];
        $hasAnyQty = false;

        foreach ($receivedQtyByItemId as $itemId => $qtyRaw) {
            $qty = (int) $qtyRaw;
            if ($qty <= 0) {
                continue;
            }

            $hasAnyQty = true;
            $item = $itemsById[$itemId] ?? null;

            if ($item === null) {
                $errors["item_$itemId"] = 'Item tidak ditemukan pada PO ini.';
                continue;
            }

            if ($qty > $item->remainingQty()) {
                $errors["item_$itemId"] = "Qty diterima ({$qty}) melebihi sisa yang belum diterima ({$item->remainingQty()}).";
                continue;
            }

            $plan[$itemId] = ['item' => $item, 'qty' => $qty];
        }

        if (!$hasAnyQty) {
            $errors['_general'] = 'Isi minimal satu qty penerimaan.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $plan;
    }

    /**
     * Riwayat goods receipt untuk satu PO (PO-01 bukti: "isi StockLedger
     * yang dihasilkan") - dipakai halaman detail, bukan diakses Controller
     * langsung ke StockLedgerRepositoryInterface (ARCH-01).
     *
     * @return StockLedgerEntry[]
     */
    public function getReceiptHistory(int $purchaseOrderId): array
    {
        return $this->ledger->findByReference(self::REFERENCE_TYPE, $purchaseOrderId);
    }

    /**
     * Baris product_stock dikunci menaik berdasarkan productId, bukan urutan
     * item diketik user - dua PO yang memuat produk sama dengan urutan input
     * berbeda kalau diterima bersamaan bisa saling menunggu kunci milik yang
     * lain (deadlock InnoDB 1213). Kunci array (itemId) dipertahankan karena
     * dipakai sebagai argumen incrementItemReceivedQtyIfWithinOrdered().
     *
     * @param array<int, array{item: PurchaseOrderItem, qty: int}> $plan
     * @return array<int, array{item: PurchaseOrderItem, qty: int}>
     */
    private function planInLockOrder(array $plan): array
    {
        uasort($plan, static fn (array $a, array $b): int => $a['item']->productId <=> $b['item']->productId);

        return $plan;
    }

    private function allItemsFullyReceived(PurchaseOrder $purchaseOrder): bool
    {
        foreach ($purchaseOrder->items as $item) {
            if ($item->remainingQty() > 0) {
                return false;
            }
        }

        return true;
    }
}
