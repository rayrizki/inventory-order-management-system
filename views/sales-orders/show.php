<?php
/**
 * @var \App\Entity\SalesOrder $salesOrder
 * @var \App\Entity\Customer $customer
 * @var \App\Entity\Warehouse $warehouse
 * @var array<int, string> $productNames productId => nama produk
 * @var \App\Entity\StockLedgerEntry[] $issueHistory
 * @var array{type: string, text: string}|null $statusMessage
 * @var string|null $issueError
 * @var string $csrfToken
 * @var \App\Session\CurrentUser $currentUser Disediakan shell-start.php.
 */
$pageTitle = $salesOrder->number();
$activeNav = 'sales-orders';
require_once __DIR__ . '/../layout/shell-start.php';

$issueError ??= null;
$formatRupiah = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');

$statusBadges = [
    'Draft' => ['badge-muted', 'Draft'],
    'PendingApproval' => ['badge-warning', 'Menunggu Persetujuan'],
    'Approved' => ['badge-info', 'Disetujui'],
    'Fulfilled' => ['badge-success', 'Dipenuhi'],
    'Cancelled' => ['badge-danger', 'Dibatalkan'],
];
[$statusClass, $statusLabel] = $statusBadges[$salesOrder->status->value];

$isOwner = $salesOrder->createdBy === $currentUser->id;
$isAdmin = $currentUser->role === \App\Entity\Role::Admin;
$isSales = $currentUser->role === \App\Entity\Role::Sales;
$isWarehouseStaff = $currentUser->role === \App\Entity\Role::WarehouseStaff;

$canSubmit = $salesOrder->status === \App\Entity\SalesOrderStatus::Draft && ($isAdmin || ($isSales && $isOwner));
$canApprove = $salesOrder->status === \App\Entity\SalesOrderStatus::PendingApproval && $isAdmin;
$canCancel = $isAdmin
    ? in_array($salesOrder->status, [\App\Entity\SalesOrderStatus::Draft, \App\Entity\SalesOrderStatus::PendingApproval, \App\Entity\SalesOrderStatus::Approved], true)
    : ($isSales && $isOwner && in_array($salesOrder->status, [\App\Entity\SalesOrderStatus::Draft, \App\Entity\SalesOrderStatus::PendingApproval], true));
$canIssue = $salesOrder->status === \App\Entity\SalesOrderStatus::Approved && ($isAdmin || $isWarehouseStaff);

$orderTotal = 0.0;
foreach ($salesOrder->items as $item) {
    $orderTotal += $item->sellPrice * $item->qty;
}
?>
            <div class="page-header">
                <div>
                    <a href="/sales-orders" class="page-header__back">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                        </svg>
                        <span>Kembali ke Sales Order</span>
                    </a>
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
                <div style="display: flex; gap: var(--space-2);">
                    <?php if ($canSubmit): ?>
                        <form method="post" action="/sales-orders/<?= $salesOrder->id ?>/submit-for-approval">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" class="btn btn-primary">Ajukan untuk Persetujuan</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canApprove): ?>
                        <form method="post" action="/sales-orders/<?= $salesOrder->id ?>/approve">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" class="btn btn-primary">Setujui SO</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canCancel): ?>
                        <form method="post" action="/sales-orders/<?= $salesOrder->id ?>/cancel" data-confirm="Batalkan <?= htmlspecialchars($salesOrder->number()) ?>? Aksi ini tidak bisa dibatalkan.">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" class="btn btn-secondary">Batalkan SO</button>
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

            <?php if ($issueError !== null): ?>
                <p class="form-error" role="alert"><?= htmlspecialchars($issueError) ?></p>
            <?php endif; ?>

            <div class="detail-card">
                <h2>Informasi Sales Order</h2>
                <dl class="detail-grid">
                    <div>
                        <dt>Customer</dt>
                        <dd><?= htmlspecialchars($customer->name) ?></dd>
                    </div>
                    <div>
                        <dt>Gudang Asal</dt>
                        <dd><?= htmlspecialchars($warehouse->name) ?></dd>
                    </div>
                    <div>
                        <dt>Dibuat</dt>
                        <dd><?= htmlspecialchars((string) $salesOrder->createdAt) ?></dd>
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
                                <th>Qty</th>
                                <th>Harga Jual</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salesOrder->items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($productNames[$item->productId] ?? '-') ?></td>
                                <td class="text-muted"><?= $item->qty ?></td>
                                <td class="text-muted"><?= htmlspecialchars($formatRupiah($item->sellPrice)) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($formatRupiah($item->sellPrice * $item->qty)) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>Total</strong></td>
                                <td><strong><?= htmlspecialchars($formatRupiah($orderTotal)) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php if ($canIssue): ?>
                    <form method="post" action="/sales-orders/<?= $salesOrder->id ?>/issue" style="margin-top: var(--space-4);">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <button type="submit" class="btn btn-primary">Proses Goods Issue</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="detail-card">
                <h2>Riwayat Stock Ledger</h2>
                <?php if ($issueHistory === []): ?>
                    <p class="form-hint">Belum ada pergerakan stok tercatat untuk SO ini.</p>
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
                                <?php foreach ($issueHistory as $entry): ?>
                                <tr>
                                    <td class="text-muted"><?= htmlspecialchars((string) $entry->createdAt) ?></td>
                                    <td><?= htmlspecialchars($productNames[$entry->productId] ?? '-') ?></td>
                                    <td><span class="badge badge-warning"><?= htmlspecialchars($entry->movementType->value) ?></span></td>
                                    <td class="text-muted">-<?= $entry->quantity ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
<?php require_once __DIR__ . '/../layout/shell-end.php'; ?>
