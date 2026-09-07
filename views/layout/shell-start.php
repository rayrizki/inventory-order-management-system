<?php
/**
 * Partial pembuka layout aplikasi (topbar + sidebar).
 * Dipasangkan dengan shell-end.php di tiap halaman yang butuh sidebar.
 *
 * Variabel yang bisa diisi pemanggil sebelum require:
 * @var string $pageTitle Judul halaman, tampil di <title> dan topbar.
 * @var string $activeNav Key menu yang sedang aktif (lihat $navItems).
 */

$pageTitle = $pageTitle ?? 'Aplikasi';
$activeNav = $activeNav ?? '';

// Sementara: seluruh menu Admin ditampilkan ke siapa saja, karena belum
// ada session/role sungguhan (lihat docs/quality/tech-debt.md #1).
// Nanti daftar ini difilter per role begitu AuthService/session siap.
$navItems = [
    'dashboard' => ['label' => 'Dashboard', 'href' => '/dashboard'],
    'products' => ['label' => 'Produk', 'href' => '/products'],
    'categories' => ['label' => 'Kategori', 'href' => '/categories'],
    'warehouses' => ['label' => 'Gudang', 'href' => '/warehouses'],
    'suppliers' => ['label' => 'Supplier', 'href' => '/suppliers'],
    'customers' => ['label' => 'Customer', 'href' => '/customers'],
    'purchase-orders' => ['label' => 'Purchase Order', 'href' => '/purchase-orders'],
    'sales-orders' => ['label' => 'Sales Order', 'href' => '/sales-orders'],
    'reports' => ['label' => 'Laporan', 'href' => '/reports'],
    'users' => ['label' => 'User', 'href' => '/users'],
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
                <span aria-hidden="true">&#9776;</span>
            </button>
            <p class="app-topbar__title"><?= htmlspecialchars($pageTitle) ?></p>
        </header>

        <div class="app-body">
            <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>

            <nav class="app-sidebar" id="app-sidebar" aria-label="Navigasi utama">
                <p class="app-sidebar__brand">IOM System</p>
                <ul class="app-sidebar__list">
                    <?php foreach ($navItems as $key => $item): ?>
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
            </nav>

            <main class="app-content" id="main-content">
