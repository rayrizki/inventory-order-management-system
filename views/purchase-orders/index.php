<?php
/**
 * @var \App\Entity\PurchaseOrder[] $purchaseOrders
 * @var array<int, string> $supplierNames id => nama supplier, untuk lookup tanpa N+1 query
 * @var string $search
 * @var string $statusKey
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var int $totalPurchaseOrders
 * @var array{type: string, text: string}|null $statusMessage
 * @var string $sortDir
 * @var string $csrfToken Disediakan shell-start.php, dipakai di setiap form POST.
 */
$pageTitle = 'Purchase Order';
$activeNav = 'purchase-orders';
require __DIR__ . '/../layout/shell-start.php';

$buildPageUrl = static function (int $targetPage) use ($search, $statusKey, $perPage, $sortDir): string {
    $query = ['page' => $targetPage, 'per_page' => $perPage, 'dir' => $sortDir, 'status' => $statusKey];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return '/purchase-orders?' . http_build_query($query);
};

// Beda dari buildPageUrl(1): tombol X di search box harus menghapus q,
// bukan cuma reset ke halaman 1 - kalau pakai buildPageUrl(1) di sini, q
// ikut ditambahkan lagi karena $search masih terisi (tombol X cuma tampil
// saat $search !== ''), jadi pencariannya tidak pernah benar-benar hilang.
$buildClearSearchUrl = static function () use ($statusKey, $perPage, $sortDir): string {
    $query = ['page' => 1, 'per_page' => $perPage, 'dir' => $sortDir, 'status' => $statusKey];

    return '/purchase-orders?' . http_build_query($query);
};

$buildSortUrl = static function () use ($search, $statusKey, $perPage, $sortDir): string {
    $nextDir = $sortDir === 'asc' ? 'desc' : 'asc';
    $query = ['page' => 1, 'per_page' => $perPage, 'dir' => $nextDir, 'status' => $statusKey];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return '/purchase-orders?' . http_build_query($query);
};

// Heroicons chevron-up/chevron-down (24x24), ditampilkan mengecil jadi 12px.
$sortIcon = static function (string $dir): string {
    $d = $dir === 'asc' ? 'm4.5 15.75 7.5-7.5 7.5 7.5' : 'm19.5 8.25-7.5 7.5-7.5-7.5';

    return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="' . $d . '"/></svg>';
};

$statusBadge = static function (\App\Entity\PurchaseOrderStatus $status): string {
    $labels = [
        'Draft' => ['badge-muted', 'Draft'],
        'Ordered' => ['badge-info', 'Ordered'],
        'PartiallyReceived' => ['badge-warning', 'Sebagian Diterima'],
        'Received' => ['badge-success', 'Diterima'],
        'Cancelled' => ['badge-danger', 'Dibatalkan'],
    ];
    [$class, $label] = $labels[$status->value];

    return '<span class="badge ' . $class . '">' . htmlspecialchars($label) . '</span>';
};
?>
            <div class="page-header">
                <div>
                    <h1>Purchase Order</h1>
                    <?php if ($totalPurchaseOrders > 0): ?>
                        <p class="page-header__meta"><?= $totalPurchaseOrders ?> purchase order</p>
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

            <div class="list-toolbar">
                <div class="list-toolbar__filters">
                <form method="get" action="/purchase-orders" class="search-box">
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusKey) ?>">
                    <label for="po-search" class="sr-only">Cari purchase order</label>
                    <input
                        type="search"
                        id="po-search"
                        name="q"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Cari nomor PO atau nama supplier..."
                        class="search-box__input"
                        autocomplete="off"
                    >
                    <?php if ($search !== ''): ?>
                        <a href="<?= htmlspecialchars($buildClearSearchUrl()) ?>" class="search-box__clear" aria-label="Hapus pencarian">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="search-box__submit" aria-label="Cari">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </button>
                </form>

                <form method="get" action="/purchase-orders" class="status-filter">
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <label for="po-status" class="sr-only">Filter status</label>
                    <select id="po-status" name="status" data-auto-submit>
                        <option value="all" <?= $statusKey === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="draft" <?= $statusKey === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="ordered" <?= $statusKey === 'ordered' ? 'selected' : '' ?>>Ordered</option>
                        <option value="partially_received" <?= $statusKey === 'partially_received' ? 'selected' : '' ?>>Sebagian Diterima</option>
                        <option value="received" <?= $statusKey === 'received' ? 'selected' : '' ?>>Diterima</option>
                        <option value="cancelled" <?= $statusKey === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                </form>
                </div>

                <a href="/purchase-orders/create" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Buat Purchase Order</span>
                </a>
            </div>

            <?php if ($purchaseOrders === [] && ($search !== '' || $statusKey !== 'all')): ?>
                <div class="empty-state">
                    <p>Tidak ada purchase order yang cocok dengan filter saat ini.</p>
                    <a href="/purchase-orders" class="btn-link">Hapus filter</a>
                </div>
            <?php elseif ($purchaseOrders === []): ?>
                <div class="empty-state">
                    <p>Belum ada purchase order. Buat PO pertama untuk memesan barang ke supplier.</p>
                    <a href="/purchase-orders/create" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        <span>Buat Purchase Order</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nomor PO</th>
                                <th>Supplier</th>
                                <th>
                                    <a href="<?= htmlspecialchars($buildSortUrl()) ?>" class="data-table__sort">
                                        Tanggal Order
                                        <span aria-hidden="true"><?= $sortIcon($sortDir) ?></span>
                                    </a>
                                </th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchaseOrders as $purchaseOrder): ?>
                            <tr>
                                <td>PO-<?= str_pad((string) $purchaseOrder->id, 6, '0', STR_PAD_LEFT) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($supplierNames[$purchaseOrder->supplierId] ?? '-') ?></td>
                                <td class="text-muted"><?= htmlspecialchars($purchaseOrder->orderDate) ?></td>
                                <td><?= $statusBadge($purchaseOrder->status) ?></td>
                                <td class="data-table__actions">
                                    <div class="data-table__actions-group">
                                        <a href="/purchase-orders/<?= $purchaseOrder->id ?>" class="btn-link">Detail</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-bar">
                    <form method="get" action="/purchase-orders" class="per-page-select">
                        <?php if ($search !== ''): ?>
                            <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                        <?php endif; ?>
                        <input type="hidden" name="page" value="1">
                        <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($statusKey) ?>">
                        <label for="per-page">Baris per halaman</label>
                        <select id="per-page" name="per_page" data-auto-submit>
                            <?php foreach ([5, 10, 25, 50, 100] as $option): ?>
                                <option value="<?= $option ?>" <?= $option === $perPage ? 'selected' : '' ?>><?= $option ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <?php if ($totalPages > 1): ?>
                        <nav class="pagination" aria-label="Navigasi halaman">
                            <a
                                href="<?= htmlspecialchars($buildPageUrl(max(1, $page - 1))) ?>"
                                class="pagination__link"
                                aria-label="Halaman sebelumnya"
                                <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                            >
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                                </svg>
                            </a>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a
                                    href="<?= htmlspecialchars($buildPageUrl($i)) ?>"
                                    class="pagination__link<?= $i === $page ? ' is-active' : '' ?>"
                                    <?= $i === $page ? 'aria-current="page"' : '' ?>
                                ><?= $i ?></a>
                            <?php endfor; ?>
                            <a
                                href="<?= htmlspecialchars($buildPageUrl(min($totalPages, $page + 1))) ?>"
                                class="pagination__link"
                                aria-label="Halaman berikutnya"
                                <?= $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                            >
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                </svg>
                            </a>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
<?php require __DIR__ . '/../layout/shell-end.php'; ?>
