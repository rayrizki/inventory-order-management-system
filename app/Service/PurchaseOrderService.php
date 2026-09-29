<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\ProductRepositoryInterface;
use App\Repository\PurchaseOrderRepositoryInterface;
use App\Repository\SupplierRepositoryInterface;
use App\Repository\WarehouseRepositoryInterface;
use DateTime;

final class PurchaseOrderService
{
    public const PER_PAGE = 10;

    /**
     * Dipakai saat transisi status bersyarat gagal: status sudah diubah
     * request lain di antara baca dan tulis, jadi yang perlu dilakukan user
     * adalah memuat ulang, bukan mengulang aksi yang sama.
     */
    private const STATUS_CHANGED_CONCURRENTLY = 'Status PO berubah saat diproses - muat ulang halaman lalu coba lagi.';

    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $purchaseOrders,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    /**
     * @return PurchaseOrder[]
     */
    public function listPurchaseOrders(
        ?string $search = null,
        ?PurchaseOrderStatus $status = null,
        int $page = 1,
        int $perPage = self::PER_PAGE,
        string $sortDir = 'desc',
    ): array {
        $perPage = max(1, $perPage);
        $offset = (max(1, $page) - 1) * $perPage;

        return $this->purchaseOrders->listAll($this->normalizeSearch($search), $status, $perPage, $offset, 'order_date', $sortDir);
    }

    public function countPurchaseOrders(?string $search = null, ?PurchaseOrderStatus $status = null): int
    {
        return $this->purchaseOrders->countAll($this->normalizeSearch($search), $status);
    }

    public function getPurchaseOrderById(int $id): PurchaseOrder
    {
        $purchaseOrder = $this->purchaseOrders->findById($id);

        if ($purchaseOrder === null) {
            throw new NotFoundException();
        }

        return $purchaseOrder;
    }

    /**
     * @param array{supplier_id: string, warehouse_id: string, order_date: string, items: array<int, array{product_id: string, qty: string, buy_price: string}>} $input
     */
    public function createPurchaseOrder(array $input, int $createdBy): PurchaseOrder
    {
        $data = $this->validate($input);

        return $this->purchaseOrders->save(new PurchaseOrder(
            null,
            $data['supplier_id'],
            $data['warehouse_id'],
            PurchaseOrderStatus::Draft,
            $data['order_date'],
            $createdBy,
            $data['items'],
        ));
    }

    /**
     * Draft -> Ordered: PO diajukan ke supplier, siap menerima goods receipt.
     */
    public function markOrdered(int $id): PurchaseOrder
    {
        $purchaseOrder = $this->getPurchaseOrderById($id);

        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            throw new ConflictException('Hanya PO berstatus Draft yang bisa diajukan ke supplier.');
        }

        // Pengecekan di atas memberi pesan yang jelas untuk kasus biasa; guard
        // di bawah menutup jeda antara baca dan tulis.
        if (!$this->purchaseOrders->transitionStatus($id, [PurchaseOrderStatus::Draft], PurchaseOrderStatus::Ordered)) {
            throw new ConflictException(self::STATUS_CHANGED_CONCURRENTLY);
        }

        return $this->getPurchaseOrderById($id);
    }

    /**
     * Boleh dibatalkan di tahap manapun sebelum diterima penuh (Received) -
     * PO yang sudah Received tidak masuk akal dibatalkan lagi.
     */
    public function cancel(int $id): PurchaseOrder
    {
        $purchaseOrder = $this->getPurchaseOrderById($id);

        $cancellable = [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived];

        if (!in_array($purchaseOrder->status, $cancellable, true)) {
            throw new ConflictException('PO yang sudah diterima penuh atau sudah dibatalkan tidak bisa dibatalkan lagi.');
        }

        // Guard penting khusus di sini: cancel yang berjalan bersamaan dengan
        // goods receipt bisa menandai PO Cancelled padahal barangnya sudah
        // masuk stok dan ledger sudah menulis baris Receipt.
        if (!$this->purchaseOrders->transitionStatus($id, $cancellable, PurchaseOrderStatus::Cancelled)) {
            throw new ConflictException(self::STATUS_CHANGED_CONCURRENTLY);
        }

        return $this->getPurchaseOrderById($id);
    }

    /**
     * Validasi seluruh field header DAN seluruh baris item sekaligus (VAL-01) -
     * error per-item diberi key "items.{index}.{field}" supaya view bisa
     * menandai baris yang salah secara spesifik, bukan cuma pesan generik.
     *
     * Tipe param sengaja "optional" (bukan wajib semua key ada) - $input ini
     * boundary ke $_POST lewat Controller::readInput(), yang secara runtime
     * tidak dijamin lengkap sekuat PHPDoc-nya (mis. field hilang dari form
     * yang dimodifikasi manual) - fallback `??` di bawah bukan kode mati.
     *
     * @param array{supplier_id?: string, warehouse_id?: string, order_date?: string, items?: array<int, array{product_id?: string, qty?: string, buy_price?: string}>} $input
     * @return array{supplier_id: int, warehouse_id: int, order_date: string, items: PurchaseOrderItem[]}
     */
    private function validate(array $input): array
    {
        $errors = [];

        $supplierIdRaw = trim((string) ($input['supplier_id'] ?? ''));
        $warehouseIdRaw = trim((string) ($input['warehouse_id'] ?? ''));
        $orderDate = trim((string) ($input['order_date'] ?? ''));
        $itemsInput = is_array($input['items'] ?? null) ? array_values($input['items']) : [];

        $supplierId = ctype_digit($supplierIdRaw) ? (int) $supplierIdRaw : null;
        if ($supplierId === null || $this->suppliers->findById($supplierId) === null) {
            $errors['supplier_id'] = 'Supplier wajib dipilih dan valid.';
        }

        $warehouseId = ctype_digit($warehouseIdRaw) ? (int) $warehouseIdRaw : null;
        if ($warehouseId === null || $this->warehouses->findById($warehouseId) === null) {
            $errors['warehouse_id'] = 'Gudang tujuan wajib dipilih dan valid.';
        }

        if ($orderDate === '' || !$this->isValidDate($orderDate)) {
            $errors['order_date'] = 'Tanggal order wajib diisi dengan format yang valid.';
        }

        $items = [];
        if ($itemsInput === []) {
            $errors['items'] = 'PO harus memiliki minimal satu item.';
        } else {
            foreach ($itemsInput as $index => $itemInput) {
                $productIdRaw = trim((string) ($itemInput['product_id'] ?? ''));
                $qtyRaw = trim((string) ($itemInput['qty'] ?? ''));
                $buyPriceRaw = trim((string) ($itemInput['buy_price'] ?? ''));

                // Baris kosong sepenuhnya dilewati, bukan error - form boleh
                // menyisakan baris kosong ekstra tanpa memaksa user menghapusnya.
                if ($productIdRaw === '' && $qtyRaw === '' && $buyPriceRaw === '') {
                    continue;
                }

                $productId = ctype_digit($productIdRaw) ? (int) $productIdRaw : null;
                $product = $productId !== null ? $this->products->findById($productId) : null;
                $rowValid = true;

                if ($product === null || !$product->isActive) {
                    $errors["items.$index.product_id"] = 'Produk wajib dipilih dan aktif.';
                    $rowValid = false;
                }

                if (!ctype_digit($qtyRaw) || (int) $qtyRaw <= 0) {
                    $errors["items.$index.qty"] = 'Qty harus bilangan bulat positif.';
                    $rowValid = false;
                }

                if (!is_numeric($buyPriceRaw) || (float) $buyPriceRaw < 0) {
                    $errors["items.$index.buy_price"] = 'Harga beli harus angka dan tidak boleh negatif.';
                    $rowValid = false;
                }

                if ($rowValid) {
                    $items[] = new PurchaseOrderItem(null, null, $productId, (int) $qtyRaw, (float) $buyPriceRaw, 0);
                }
            }

            if ($items === [] && !isset($errors['items'])) {
                $errors['items'] = 'PO harus memiliki minimal satu item yang valid.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'supplier_id' => $supplierId,
            'warehouse_id' => $warehouseId,
            'order_date' => $orderDate,
            'items' => $items,
        ];
    }

    private function isValidDate(string $date): bool
    {
        $parsed = DateTime::createFromFormat('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function normalizeSearch(?string $search): ?string
    {
        $search = trim((string) $search);

        return $search === '' ? null : $search;
    }
}
