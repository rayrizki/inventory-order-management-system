<?php
/**
 * @var \App\Entity\SalesOrder[] $salesOrders
 * @var array<int, string> $customerNames id => nama customer, untuk lookup tanpa N+1 query
 * @var string $search
 * @var string $statusKey
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var int $totalSalesOrders
 * @var array{type: string, text: string}|null $statusMessage
 * @var string $sortDir
 * @var string $csrfToken Disediakan shell-start.php, dipakai di setiap form POST.
 * @var \App\Session\CurrentUser $currentUser Disediakan shell-start.php.
 */
$pageTitle = 'Sales Order';
$activeNav = 'sales-orders';
require __DIR__ . '/../layout/shell-start.php';

$canCreate = $currentUser->role === \App\Entity\Role::Admin || $currentUser->role === \App\Entity\Role::Sales;

$buildPageUrl = static function (int $targetPage) use ($search, $statusKey, $perPage, $sortDir): string {
    $query = ['page' => $targetPage, 'per_page' => $perPage, 'dir' => $sortDir, 'status' => $statusKey];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return '/sales-orders?' . http_build_query($query);
};

$buildClearSearchUrl = static function () use ($statusKey, $perPage, $sortDir): string {
    $query = ['page' => 1, 'per_page' => $perPage, 'dir' => $sortDir, 'status' => $statusKey];

    return '/sales-orders?' . http_build_query($query);
};

$buildSortUrl = static function () use ($search, $statusKey, $perPage, $sortDir): string {
    $nextDir = $sortDir === 'asc' ? 'desc' : 'asc';
    $query = ['page' => 1, 'per_page' => $perPage, 'dir' => $nextDir, 'status' => $statusKey];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return '/sales-orders?' . http_build_query($query);
};

$sortIcon = static function (string $dir): string {
    $d = $dir === 'asc' ? 'm4.5 15.75 7.5-7.5 7.5 7.5' : 'm19.5 8.25-7.5 7.5-7.5-7.5';

    return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="' . $d . '"/></svg>';
};

$statusBadge = static function (\App\Entity\SalesOrderStatus $status): string {
    $labels = [
        'Draft' => ['badge-muted', 'Draft'],
        'PendingApproval' => ['badge-warning', 'Menunggu Persetujuan'],
        'Approved' => ['badge-info', 'Disetujui'],
        'Fulfilled' => ['badge-success', 'Dipenuhi'],
        'Cancelled' => ['badge-danger', 'Dibatalkan'],
    ];
    [$class, $label] = $labels[$status->value];

    return '<span class="badge ' . $class . '">' . htmlspecialchars($label) . '</span>';
};
?>
            <div class="page-header">
                <div>
                    <h1>Sales Order</h1>
                    <?php if ($totalSalesOrders > 0): ?>
                        <p class="page-header__meta"><?= $totalSalesOrders ?> sales order</p>
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
                <form method="get" action="/sales-orders" class="search-box">
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusKey) ?>">
                    <label for="so-search" class="sr-only">Cari sales order</label>
                    <input
                        type="search"
                        id="so-search"
                        name="q"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Cari nomor SO atau nama customer..."
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

                <form method="get" action="/sales-orders" class="status-filter">
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <label for="so-status" class="sr-only">Filter status</label>
                    <select id="so-status" name="status" data-auto-submit>
                        <option value="all" <?= $statusKey === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="draft" <?= $statusKey === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="pending_approval" <?= $statusKey === 'pending_approval' ? 'selected' : '' ?>>Menunggu Persetujuan</option>
                        <option value="approved" <?= $statusKey === 'approved' ? 'selected' : '' ?>>Disetujui</option>
                        <option value="fulfilled" <?= $statusKey === 'fulfilled' ? 'selected' : '' ?>>Dipenuhi</option>
                        <option value="cancelled" <?= $statusKey === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                </form>
                </div>

                <?php if ($canCreate): ?>
                    <a href="/sales-orders/create" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        <span>Buat Sales Order</span>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($salesOrders === [] && ($search !== '' || $statusKey !== 'all')): ?>
                <div class="empty-state">
                    <p>Tidak ada sales order yang cocok dengan filter saat ini.</p>
                    <a href="/sales-orders" class="btn-link">Hapus filter</a>
                </div>
            <?php elseif ($salesOrders === []): ?>
                <div class="empty-state">
                    <p>Belum ada sales order<?= $canCreate ? '. Buat SO pertama untuk mencatat penjualan ke customer.' : '.' ?></p>
                    <?php if ($canCreate): ?>
                        <a href="/sales-orders/create" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                            </svg>
                            <span>Buat Sales Order</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nomor SO</th>
                                <th>Customer</th>
                                <th>
                                    <a href="<?= htmlspecialchars($buildSortUrl()) ?>" class="data-table__sort">
                                        Dibuat
                                        <span aria-hidden="true"><?= $sortIcon($sortDir) ?></span>
                                    </a>
                                </th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salesOrders as $salesOrder): ?>
                            <tr>
                                <td><?= htmlspecialchars($salesOrder->number()) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($customerNames[$salesOrder->customerId] ?? '-') ?></td>
                                <td class="text-muted"><?= htmlspecialchars((string) $salesOrder->createdAt) ?></td>
                                <td><?= $statusBadge($salesOrder->status) ?></td>
                                <td class="data-table__actions">
                                    <div class="data-table__actions-group">
                                        <a href="/sales-orders/<?= $salesOrder->id ?>" class="btn-link">Detail</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-bar">
                    <form method="get" action="/sales-orders" class="per-page-select">
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
