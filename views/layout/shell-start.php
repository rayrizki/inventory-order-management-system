<?php
/**
 * Partial pembuka layout aplikasi (topbar + sidebar).
 * Dipasangkan dengan shell-end.php di tiap halaman yang butuh sidebar.
 *
 * Variabel yang bisa diisi pemanggil sebelum require:
 * @var string $pageTitle Judul halaman, tampil di tag <title> browser.
 * @var string $activeNav Key menu yang sedang aktif (lihat $navGroups).
 */

$pageTitle = $pageTitle ?? 'Aplikasi';
$activeNav = $activeNav ?? '';

// Controller pemanggil sudah memastikan user login (AuthGuard::requireLogin())
// sebelum require file ini - dipanggil ulang di sini murni untuk kebutuhan
// tampilan (nama + role di sidebar), bukan pengecekan otorisasi baru.
$currentUser = (new \App\Session\AuthGuard(new \App\Session\PhpSessionAdapter()))->requireLogin();

$roleLabels = [
    'Admin' => 'Admin',
    'Sales' => 'Sales',
    'WarehouseStaff' => 'Warehouse Staff',
];
$currentUserRoleLabel = $roleLabels[$currentUser->role->value] ?? $currentUser->role->value;

// Sementara: seluruh menu Admin ditampilkan ke siapa saja, karena belum
// ada session/role sungguhan (lihat docs/quality/tech-debt.md #1).
// Nanti daftar ini difilter per role begitu AuthService/session siap.
$navGroups = [
    'General' => [
        'dashboard' => ['label' => 'Dashboard', 'href' => '/dashboard'],
    ],
    'Master Data' => [
        'products' => ['label' => 'Produk', 'href' => '/products'],
        'categories' => ['label' => 'Kategori', 'href' => '/categories'],
        'warehouses' => ['label' => 'Gudang', 'href' => '/warehouses'],
        'suppliers' => ['label' => 'Supplier', 'href' => '/suppliers'],
        'customers' => ['label' => 'Customer', 'href' => '/customers'],
    ],
    'Transaksi' => [
        'purchase-orders' => ['label' => 'Purchase Order', 'href' => '/purchase-orders'],
        'sales-orders' => ['label' => 'Sales Order', 'href' => '/sales-orders'],
    ],
    'Laporan' => [
        'reports' => ['label' => 'Laporan', 'href' => '/reports'],
    ],
    'Administrasi' => [
        'users' => ['label' => 'User', 'href' => '/users'],
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Inventory &amp; Order Management System</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/app-shell.css">
</head>
<body>
    <div class="app-shell">
        <header class="app-topbar">
            <button
                type="button"
                class="sidebar-toggle"
                id="sidebar-toggle"
                aria-expanded="false"
                aria-controls="app-sidebar"
            >
                <span class="sr-only">Buka menu navigasi</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                </svg>
            </button>
            <p class="app-topbar__title">IOM System</p>
            <form method="post" action="/logout" class="app-topbar__logout">
                <button type="submit" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                    </svg>
                    <span>Keluar</span>
                </button>
            </form>
        </header>

        <div class="app-body">
            <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>

            <nav class="app-sidebar" id="app-sidebar" aria-label="Navigasi utama">
                <div class="app-sidebar__scroll">
                    <?php foreach ($navGroups as $groupLabel => $items): ?>
                    <div class="app-sidebar__group">
                        <p class="app-sidebar__group-label"><?= htmlspecialchars($groupLabel) ?></p>
                        <ul class="app-sidebar__list">
                            <?php foreach ($items as $key => $item): ?>
                            <li>
                                <a
                                    href="<?= htmlspecialchars($item['href']) ?>"
                                    class="app-sidebar__link<?= $activeNav === $key ? ' is-active' : '' ?>"
                                    <?= $activeNav === $key ? 'aria-current="page"' : '' ?>
                                >
                                    <?= htmlspecialchars($item['label']) ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="app-sidebar__user">
                    <div class="app-sidebar__user-avatar" aria-hidden="true">
                        <?= htmlspecialchars(mb_strtoupper(mb_substr($currentUser->name, 0, 1))) ?>
                    </div>
                    <div class="app-sidebar__user-info">
                        <p class="app-sidebar__user-name"><?= htmlspecialchars($currentUser->name) ?></p>
                        <p class="app-sidebar__user-role"><?= htmlspecialchars($currentUserRoleLabel) ?></p>
                    </div>
                </div>
            </nav>

            <main class="app-content" id="main-content">
