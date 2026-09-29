<?php
/**
 * Partial pembuka layout aplikasi (topbar + sidebar).
 * Dipasangkan dengan shell-end.php di tiap halaman yang butuh sidebar.
 *
 * Variabel yang HARUS sudah diisi Controller sebelum require:
 * @var \App\Session\CurrentUser $currentUser Hasil AuthGuard::requireLogin().
 *
 * Variabel opsional:
 * @var string $pageTitle Judul halaman, tampil di tag <title> browser.
 * @var string $activeNav Key menu yang sedang aktif (lihat $navGroups).
 */

$pageTitle = $pageTitle ?? 'Aplikasi';
$activeNav = $activeNav ?? '';

// $currentUser datang dari Controller yang sudah memanggil requireLogin().
// Sebelumnya file ini merakit sendiri PhpSessionAdapter + AuthGuard untuk
// mendapatkannya - view jadi ikut melakukan wiring infrastruktur dan
// pemeriksaan otorisasi, dua hal yang bukan tanggung jawabnya (ARCH-01).
assert(isset($currentUser), 'shell-start.php butuh $currentUser dari Controller');

// Dipakai tiap form POST di halaman ini (lihat Router::dispatch()) - satu
// token per session, bukan dibuat ulang tiap kali file ini di-require.
$csrfToken = (new \App\Session\CsrfToken(new \App\Session\PhpSessionAdapter()))->get();

$roleLabels = [
    'Admin' => 'Admin',
    'Sales' => 'Sales',
    'WarehouseStaff' => 'Warehouse Staff',
];
$currentUserRoleLabel = $roleLabels[$currentUser->role->value] ?? $currentUser->role->value;

$navGroups = [
    'General' => [
        'dashboard' => ['label' => 'Dashboard', 'href' => '/dashboard'],
    ],
    'Katalog' => [
        'products' => ['label' => 'Produk', 'href' => '/products'],
    ],
    'Master Data' => [
        'categories' => ['label' => 'Kategori', 'href' => '/categories'],
        'warehouses' => ['label' => 'Gudang', 'href' => '/warehouses'],
        'suppliers' => ['label' => 'Supplier', 'href' => '/suppliers'],
        'customers' => ['label' => 'Customer', 'href' => '/customers'],
    ],
    'Transaksi' => [
        'purchase-orders' => ['label' => 'Purchase Order', 'href' => '/purchase-orders', 'roles' => [\App\Entity\Role::Admin, \App\Entity\Role::WarehouseStaff]],
        'sales-orders' => ['label' => 'Sales Order', 'href' => '/sales-orders'],
    ],
    'Laporan' => [
        'reports' => ['label' => 'Laporan', 'href' => '/reports', 'roles' => [\App\Entity\Role::Admin]],
    ],
    'Administrasi' => [
        'users' => ['label' => 'User', 'href' => '/users'],
    ],
];

// 'Katalog' (Produk) sengaja dipisah dari 'Master Data': §1.2 memberi Sales
// "hanya melihat katalog" dan Warehouse Staff "hanya melihat produk & stok"
// untuk PRODUK secara spesifik, sedangkan Kategori/Gudang/Supplier/Customer
// tidak disebutkan boleh dilihat role lain sama sekali - jadi hanya grup
// 'Master Data' dan 'Administrasi' (kelola user, murni Admin) yang
// disembunyikan dari role selain Admin (tech-debt #2).
if ($currentUser->role !== \App\Entity\Role::Admin) {
    unset($navGroups['Master Data'], $navGroups['Administrasi']);
}

// Filter per-item lewat key 'roles' (kalau ada) - dipakai untuk item yang
// modulnya sudah nyata dibangun dan aksesnya tidak seragam se-grup, mis.
// Purchase Order (Admin+Warehouse Staff, Sales sama sekali tidak boleh -
// beda dari Sales Order yang nanti (SO-01) justru boleh diakses Sales).
// Item tanpa 'roles' tetap tampil ke semua role yang login (default lama,
// dipertahankan untuk modul yang belum dibangun - tech-debt #2).
foreach ($navGroups as $groupKey => $items) {
    foreach ($items as $itemKey => $item) {
        if (isset($item['roles']) && !in_array($currentUser->role, $item['roles'], true)) {
            unset($navGroups[$groupKey][$itemKey]);
        }
    }
    if ($navGroups[$groupKey] === []) {
        unset($navGroups[$groupKey]);
    }
}
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
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
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
