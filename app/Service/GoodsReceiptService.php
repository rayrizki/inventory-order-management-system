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
            foreach ($plan as $itemId => $line) {
                $this->purchaseOrders->incrementItemReceivedQty($itemId, $line['qty']);
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
            $newStatus = $this->allItemsFullyReceived($updated) ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived;
            $this->purchaseOrders->updateStatus($purchaseOrderId, $newStatus);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->purchaseOrders->findById($purchaseOrderId);
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
