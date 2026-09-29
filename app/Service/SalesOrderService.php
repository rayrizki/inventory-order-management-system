<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\SalesOrderStatus;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\CustomerRepositoryInterface;
use App\Repository\ProductRepositoryInterface;
use App\Repository\SalesOrderRepositoryInterface;
use App\Repository\WarehouseRepositoryInterface;

/**
 * Aturan kepemilikan (Sales cuma boleh lihat/ajukan/batalkan order miliknya
 * sendiri) SENGAJA tidak ada di sini - itu authorization concern, bukan
 * business rule, jadi ditegakkan di Controller lewat AuthGuard + perbandingan
 * createdBy langsung terhadap CurrentUser, bukan diteruskan ke Service
 * sebagai parameter Role. Service ini cuma tahu status mana yang valid
 * untuk transisi mana, terlepas dari siapa yang memintanya.
 */
final class SalesOrderService
{
    public const PER_PAGE = 10;

    /**
     * Dipakai saat transisi status bersyarat gagal: status sudah diubah
     * request lain di antara baca dan tulis, jadi yang perlu dilakukan user
     * adalah memuat ulang, bukan mengulang aksi yang sama.
     */
    private const STATUS_CHANGED_CONCURRENTLY = 'Status SO berubah saat diproses - muat ulang halaman lalu coba lagi.';

    public function __construct(
        private readonly SalesOrderRepositoryInterface $salesOrders,
        private readonly CustomerRepositoryInterface $customers,
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    /**
     * @return SalesOrder[]
     */
    public function listSalesOrders(
        ?string $search = null,
        ?SalesOrderStatus $status = null,
        ?int $createdBy = null,
        int $page = 1,
        int $perPage = self::PER_PAGE,
        string $sortDir = 'desc',
    ): array {
        $perPage = max(1, $perPage);
        $offset = (max(1, $page) - 1) * $perPage;

        return $this->salesOrders->listAll($this->normalizeSearch($search), $status, $createdBy, $perPage, $offset, 'created_at', $sortDir);
    }

    public function countSalesOrders(?string $search = null, ?SalesOrderStatus $status = null, ?int $createdBy = null): int
    {
        return $this->salesOrders->countAll($this->normalizeSearch($search), $status, $createdBy);
    }

    public function getSalesOrderById(int $id): SalesOrder
    {
        $salesOrder = $this->salesOrders->findById($id);

        if ($salesOrder === null) {
            throw new NotFoundException();
        }

        return $salesOrder;
    }

    /**
     * @param array{customer_id?: string, warehouse_id?: string, items?: array<int, array{product_id?: string, qty?: string, sell_price?: string}>} $input
     */
    public function createSalesOrder(array $input, int $createdBy): SalesOrder
    {
        $data = $this->validate($input);

        return $this->salesOrders->save(new SalesOrder(
            null,
            $data['customer_id'],
            $data['warehouse_id'],
            SalesOrderStatus::Draft,
            $createdBy,
            null,
            null,
            $data['items'],
        ));
    }

    /**
     * Draft -> PendingApproval: SO diajukan untuk ditinjau Admin.
     */
    public function submitForApproval(int $id): SalesOrder
    {
        $salesOrder = $this->getSalesOrderById($id);

        if ($salesOrder->status !== SalesOrderStatus::Draft) {
            throw new ConflictException('Hanya SO berstatus Draft yang bisa diajukan untuk persetujuan.');
        }

        // Pengecekan di atas memberi pesan yang jelas untuk kasus biasa; guard
        // di bawah menutup jeda antara baca dan tulis (status bisa berubah oleh
        // request lain di antara keduanya).
        if (!$this->salesOrders->transitionStatus($id, [SalesOrderStatus::Draft], SalesOrderStatus::PendingApproval)) {
            throw new ConflictException(self::STATUS_CHANGED_CONCURRENTLY);
        }

        return $this->getSalesOrderById($id);
    }

    /**
     * PendingApproval -> Approved. Segregation of duty (Sales tidak boleh
     * menyetujui, termasuk order miliknya sendiri) ditegakkan di
     * SalesOrderController lewat requireRole([Admin]) - bukan di sini.
     */
    public function approve(int $id, int $approvedBy): SalesOrder
    {
        $salesOrder = $this->getSalesOrderById($id);

        if ($salesOrder->status !== SalesOrderStatus::PendingApproval) {
            throw new ConflictException('Hanya SO berstatus PendingApproval yang bisa disetujui.');
        }

        if (!$this->salesOrders->approve($id, $approvedBy)) {
            throw new ConflictException(self::STATUS_CHANGED_CONCURRENTLY);
        }

        return $this->getSalesOrderById($id);
    }

    /**
     * Boleh dibatalkan di tahap manapun sebelum Fulfilled. Siapa yang boleh
     * memanggil ini untuk SO tertentu (Admin selalu; Sales cuma order
     * miliknya sendiri dan cuma sebelum Approved) diperiksa di Controller.
     */
    public function cancel(int $id): SalesOrder
    {
        $salesOrder = $this->getSalesOrderById($id);

        $cancellable = [SalesOrderStatus::Draft, SalesOrderStatus::PendingApproval, SalesOrderStatus::Approved];

        if (!in_array($salesOrder->status, $cancellable, true)) {
            throw new ConflictException('SO yang sudah dipenuhi atau sudah dibatalkan tidak bisa dibatalkan lagi.');
        }

        // Guard penting khusus di sini: tanpa syarat status di WHERE, cancel
        // yang berjalan bersamaan dengan goods issue bisa menandai SO
        // Cancelled PADAHAL stoknya sudah keluar dan ledger sudah menulis
        // baris Issue - ProductStock dan SalesOrder jadi saling bertentangan.
        if (!$this->salesOrders->transitionStatus($id, $cancellable, SalesOrderStatus::Cancelled)) {
            throw new ConflictException(self::STATUS_CHANGED_CONCURRENTLY);
        }

        return $this->getSalesOrderById($id);
    }

    /**
     * Tipe param sengaja "optional" (bukan wajib semua key ada) - $input ini
     * boundary ke $_POST lewat Controller::readInput(), yang secara runtime
     * tidak dijamin lengkap sekuat PHPDoc-nya - fallback `??` di bawah bukan
     * kode mati (lihat penjelasan sama di PurchaseOrderService::validate()).
     *
     * @param array{customer_id?: string, warehouse_id?: string, items?: array<int, array{product_id?: string, qty?: string, sell_price?: string}>} $input
     * @return array{customer_id: int, warehouse_id: int, items: SalesOrderItem[]}
     */
    private function validate(array $input): array
    {
        $errors = [];

        $customerIdRaw = trim((string) ($input['customer_id'] ?? ''));
        $warehouseIdRaw = trim((string) ($input['warehouse_id'] ?? ''));
        $itemsInput = is_array($input['items'] ?? null) ? array_values($input['items']) : [];

        $customerId = ctype_digit($customerIdRaw) ? (int) $customerIdRaw : null;
        if ($customerId === null || $this->customers->findById($customerId) === null) {
            $errors['customer_id'] = 'Customer wajib dipilih dan valid.';
        }

        $warehouseId = ctype_digit($warehouseIdRaw) ? (int) $warehouseIdRaw : null;
        if ($warehouseId === null || $this->warehouses->findById($warehouseId) === null) {
            $errors['warehouse_id'] = 'Gudang asal wajib dipilih dan valid.';
        }

        $items = [];
        if ($itemsInput === []) {
            $errors['items'] = 'SO harus memiliki minimal satu item.';
        } else {
            foreach ($itemsInput as $index => $itemInput) {
                $productIdRaw = trim((string) ($itemInput['product_id'] ?? ''));
                $qtyRaw = trim((string) ($itemInput['qty'] ?? ''));
                $sellPriceRaw = trim((string) ($itemInput['sell_price'] ?? ''));

                if ($productIdRaw === '' && $qtyRaw === '' && $sellPriceRaw === '') {
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

                if (!is_numeric($sellPriceRaw) || (float) $sellPriceRaw < 0) {
                    $errors["items.$index.sell_price"] = 'Harga jual harus angka dan tidak boleh negatif.';
                    $rowValid = false;
                }

                if ($rowValid) {
                    $items[] = new SalesOrderItem(null, null, $productId, (int) $qtyRaw, (float) $sellPriceRaw);
                }
            }

            if ($items === [] && !isset($errors['items'])) {
                $errors['items'] = 'SO harus memiliki minimal satu item yang valid.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'customer_id' => $customerId,
            'warehouse_id' => $warehouseId,
            'items' => $items,
        ];
    }

    private function normalizeSearch(?string $search): ?string
    {
        $search = trim((string) $search);

        return $search === '' ? null : $search;
    }
}
