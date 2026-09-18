<?php
/**
 * @var \App\Session\CurrentUser $currentUser Disediakan shell-start.php.
 * @var array<string, mixed> $summary Bentuknya beda per role - lihat DashboardService.
 */
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/../layout/shell-start.php';

$formatRupiah = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');

// Key array = key filter ?status= yang dipakai PurchaseOrderController/
// SalesOrderController (snake_case, LIHAT STATUS_FILTERS di kedua
// Controller) - BUKAN nilai enum PHP (PascalCase) yang dipakai sebagai key
// di $summary['...ByStatus'] (hasil countByStatus()). Dua "kamus" berbeda
// ini gampang tertukar (sudah pernah salah sekali saat menulis view ini) -
// value pertama tiap baris adalah nilai enum untuk lookup ke $summary,
// dipetakan eksplisit di sini supaya jelas keduanya memang berbeda.
$poStatusRows = [
    'draft' => ['Draft', 'Draft'],
    'ordered' => ['Ordered', 'Diajukan'],
    'partially_received' => ['PartiallyReceived', 'Sebagian Diterima'],
    'received' => ['Received', 'Diterima'],
    'cancelled' => ['Cancelled', 'Dibatalkan'],
];
$soStatusRows = [
    'draft' => ['Draft', 'Draft'],
    'pending_approval' => ['PendingApproval', 'Menunggu Persetujuan'],
    'approved' => ['Approved', 'Disetujui'],
    'fulfilled' => ['Fulfilled', 'Dipenuhi'],
    'cancelled' => ['Cancelled', 'Dibatalkan'],
];

$renderLowStockTable = static function (array $products) {
    if ($products === []) {
        echo '<p class="form-hint">Tidak ada produk di bawah reorder point saat ini.</p>';

        return;
    }
    ?>
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama</th>
                    <th>Reorder Point</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= htmlspecialchars($product->sku) ?></td>
                    <td><a href="/products/<?= $product->id ?>" class="btn-link"><?= htmlspecialchars($product->name) ?></a></td>
                    <td class="text-muted"><?= $product->reorderPoint ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};

$renderStatusTable = static function (array $counts, array $statusRows, string $listUrl) {
    ?>
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statusRows as $urlKey => [$enumValue, $label]): ?>
                <tr>
                    <td><?= htmlspecialchars($label) ?></td>
                    <td>
                        <a href="<?= htmlspecialchars($listUrl . '?status=' . urlencode($urlKey)) ?>" class="btn-link">
                            <?= $counts[$enumValue] ?? 0 ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};
?>
            <div class="page-header">
                <div>
                    <h1>Dashboard</h1>
                    <p class="page-header__meta">Halo, <?= htmlspecialchars($currentUser->name) ?></p>
                </div>
            </div>

            <?php if ($currentUser->role === \App\Entity\Role::Admin): ?>
                <div class="detail-card">
                    <h2>Ringkasan Inventori</h2>
                    <dl class="detail-grid">
                        <div>
                            <dt>Nilai Inventori (harga beli)</dt>
                            <dd><?= htmlspecialchars($formatRupiah($summary['inventoryValue'])) ?></dd>
                        </div>
                        <div>
                            <dt>Produk di Bawah Reorder Point</dt>
                            <dd>
                                <?= $summary['lowStockCount'] ?>
                                <a href="/products?stock_status=low" class="btn-link">Lihat semua</a>
                            </dd>
                        </div>
                    </dl>
                    <?php $renderLowStockTable($summary['lowStockProducts']); ?>
                </div>

                <div class="detail-card">
                    <h2>Purchase Order per Status</h2>
                    <?php $renderStatusTable($summary['purchaseOrdersByStatus'], $poStatusRows, '/purchase-orders'); ?>
                </div>

                <div class="detail-card">
                    <h2>Sales Order per Status</h2>
                    <?php $renderStatusTable($summary['salesOrdersByStatus'], $soStatusRows, '/sales-orders'); ?>
                </div>
            <?php elseif ($currentUser->role === \App\Entity\Role::Sales): ?>
                <div class="detail-card">
                    <h2>Sales Order Saya per Status</h2>
                    <p class="form-hint">Ringkasan order yang Anda buat sendiri - tidak termasuk order Sales lain.</p>
                    <?php $renderStatusTable($summary['salesOrdersByStatus'], $soStatusRows, '/sales-orders'); ?>
                </div>
            <?php else: ?>
                <div class="detail-card">
                    <h2>Antrean Gudang</h2>
                    <dl class="detail-grid">
                        <div>
                            <dt>Menunggu Goods Receipt</dt>
                            <dd>
                                <?= $summary['pendingReceiptCount'] ?>
                                <a href="/purchase-orders?status=ordered" class="btn-link">Lihat</a>
                            </dd>
                        </div>
                        <div>
                            <dt>Menunggu Goods Issue</dt>
                            <dd>
                                <?= $summary['pendingIssueCount'] ?>
                                <a href="/sales-orders?status=approved" class="btn-link">Lihat</a>
                            </dd>
                        </div>
                        <div>
                            <dt>Produk di Bawah Reorder Point</dt>
                            <dd>
                                <?= $summary['lowStockCount'] ?>
                                <a href="/products?stock_status=low" class="btn-link">Lihat semua</a>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="detail-card">
                    <h2>Produk Low-Stock</h2>
                    <?php $renderLowStockTable($summary['lowStockProducts']); ?>
                </div>
            <?php endif; ?>
<?php require __DIR__ . '/../layout/shell-end.php'; ?>
