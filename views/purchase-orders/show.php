<?php
/**
 * @var \App\Entity\PurchaseOrder $purchaseOrder
 * @var \App\Entity\Supplier $supplier
 * @var \App\Entity\Warehouse $warehouse
 * @var array<int, string> $productNames productId => nama produk
 * @var \App\Entity\StockLedgerEntry[] $receiptHistory
 * @var array{type: string, text: string}|null $statusMessage
 * @var array<string, string> $receiptErrors
 * @var string $csrfToken
 */
$pageTitle = $purchaseOrder->number();
$activeNav = 'purchase-orders';
require_once __DIR__ . '/../layout/shell-start.php';

$receiptErrors ??= [];
$formatRupiah = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');

$statusBadges = [
    'Draft' => ['badge-muted', 'Draft'],
    'Ordered' => ['badge-info', 'Ordered'],
    'PartiallyReceived' => ['badge-warning', 'Sebagian Diterima'],
    'Received' => ['badge-success', 'Diterima'],
    'Cancelled' => ['badge-danger', 'Dibatalkan'],
];
[$statusClass, $statusLabel] = $statusBadges[$purchaseOrder->status->value];

// Mengajukan PO ke supplier dan membatalkannya adalah keputusan komersial,
// bukan bagian dari "mengusulkan" yang §1.2 berikan ke Warehouse Staff -
// server sudah membatasinya ke Admin (PurchaseOrderController::COMMIT_ROLES),
// tombolnya ikut disembunyikan supaya tidak menawarkan aksi yang pasti 403.
$isAdmin = $currentUser->role === \App\Entity\Role::Admin;
$canCancel = $isAdmin && in_array($purchaseOrder->status, [\App\Entity\PurchaseOrderStatus::Draft, \App\Entity\PurchaseOrderStatus::Ordered, \App\Entity\PurchaseOrderStatus::PartiallyReceived], true);
$canMarkOrdered = $isAdmin && $purchaseOrder->status === \App\Entity\PurchaseOrderStatus::Draft;
$canReceive = in_array($purchaseOrder->status, [\App\Entity\PurchaseOrderStatus::Ordered, \App\Entity\PurchaseOrderStatus::PartiallyReceived], true);
?>
            <div class="page-header">
                <div>
                    <a href="/purchase-orders" class="page-header__back">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                        </svg>
                        <span>Kembali ke Purchase Order</span>
                    </a>
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
                <div style="display: flex; gap: var(--space-2);">
                    <?php if ($canMarkOrdered): ?>
                        <form method="post" action="/purchase-orders/<?= $purchaseOrder->id ?>/mark-ordered">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" class="btn btn-primary">Ajukan ke Supplier</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canCancel): ?>
                        <form method="post" action="/purchase-orders/<?= $purchaseOrder->id ?>/cancel" data-confirm="Batalkan <?= htmlspecialchars($purchaseOrder->number()) ?>? Aksi ini tidak bisa dibatalkan.">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" class="btn btn-secondary">Batalkan PO</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($statusMessage !== null): ?>
                <p class="form-<?= htmlspecialchars($statusMessage['type']) ?>" role="alert">
                    <span><?= htmlspecialchars($statusMessage['text']) ?></span>
                    <button type="button" class="alert__close" data-dismiss-alert aria-label="Tutup pesan">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </p>
            <?php endif; ?>

            <?php if (isset($receiptErrors['_general'])): ?>
                <p class="form-error" role="alert"><?= htmlspecialchars($receiptErrors['_general']) ?></p>
            <?php endif; ?>

            <div class="detail-card">
                <h2>Informasi Purchase Order</h2>
                <dl class="detail-grid">
                    <div>
                        <dt>Supplier</dt>
                        <dd><?= htmlspecialchars($supplier->name) ?></dd>
                    </div>
                    <div>
                        <dt>Gudang Tujuan</dt>
                        <dd><?= htmlspecialchars($warehouse->name) ?></dd>
                    </div>
                    <div>
                        <dt>Tanggal Order</dt>
                        <dd><?= htmlspecialchars($purchaseOrder->orderDate) ?></dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span></dd>
                    </div>
                </dl>
            </div>

            <div class="detail-card">
                <h2>Item Pesanan</h2>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Qty Dipesan</th>
                                <th>Harga Beli</th>
                                <th>Subtotal</th>
                                <th>Diterima</th>
                                <th>Sisa</th>
                                <?php if ($canReceive): ?>
                                    <th>Terima Sekarang</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchaseOrder->items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($productNames[$item->productId] ?? '-') ?></td>
                                <td class="text-muted"><?= $item->qty ?></td>
                                <td class="text-muted"><?= htmlspecialchars($formatRupiah($item->buyPrice)) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($formatRupiah($item->buyPrice * $item->qty)) ?></td>
                                <td class="text-muted"><?= $item->receivedQty ?></td>
                                <td class="text-muted"><?= $item->remainingQty() ?></td>
                                <?php if ($canReceive): ?>
                                    <td>
                                        <?php if ($item->remainingQty() > 0): ?>
                                            <label for="receive-item-<?= $item->id ?>" class="sr-only">Qty diterima untuk item ini</label>
                                            <input
                                                type="number"
                                                id="receive-item-<?= $item->id ?>"
                                                name="received_qty[<?= $item->id ?>]"
                                                form="goods-receipt-form"
                                                min="0"
                                                max="<?= $item->remainingQty() ?>"
                                                step="1"
                                                style="width: 6rem;"
                                            >
                                            <?php if (isset($receiptErrors["item_{$item->id}"])): ?>
                                                <p class="form-hint form-hint--error"><?= htmlspecialchars($receiptErrors["item_{$item->id}"]) ?></p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Selesai</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($canReceive): ?>
                    <form id="goods-receipt-form" method="post" action="/purchase-orders/<?= $purchaseOrder->id ?>/receive" style="margin-top: var(--space-4);">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <button type="submit" class="btn btn-primary">Catat Penerimaan Barang</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="detail-card">
                <h2>Riwayat Stock Ledger</h2>
                <?php if ($receiptHistory === []): ?>
                    <p class="form-hint">Belum ada pergerakan stok tercatat untuk PO ini.</p>
                <?php else: ?>
                    <div class="data-table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Produk</th>
                                    <th>Tipe</th>
                                    <th>Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receiptHistory as $entry): ?>
                                <tr>
                                    <td class="text-muted"><?= htmlspecialchars((string) $entry->createdAt) ?></td>
                                    <td><?= htmlspecialchars($productNames[$entry->productId] ?? '-') ?></td>
                                    <td><span class="badge badge-success"><?= htmlspecialchars($entry->movementType->value) ?></span></td>
                                    <td class="text-muted">+<?= $entry->quantity ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
<?php require_once __DIR__ . '/../layout/shell-end.php'; ?>
