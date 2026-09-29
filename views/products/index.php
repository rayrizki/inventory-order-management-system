<?php
/**
 * @var \App\Entity\Product[] $products
 * @var array<int, string> $categoryNames id => nama kategori, untuk lookup tanpa N+1 query
 * @var \App\Entity\Category[] $categories daftar lengkap untuk dropdown filter
 * @var string $search
 * @var int|null $categoryId
 * @var string $status
 * @var string $stockStatus
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var int $totalProducts
 * @var array{type: string, text: string}|null $statusMessage
 * @var string $sortBy
 * @var string $sortDir
 * @var string $csrfToken Disediakan shell-start.php, dipakai di setiap form POST.
 * @var \App\Session\CurrentUser $currentUser Disediakan shell-start.php.
 */
$isAdmin = $currentUser->role === \App\Entity\Role::Admin;
$pageTitle = 'Produk';
$activeNav = 'products';
require __DIR__ . '/../layout/shell-start.php';

$buildPageUrl = static function (int $targetPage) use ($search, $categoryId, $status, $stockStatus, $perPage, $sortBy, $sortDir): string {
    $query = ['page' => $targetPage, 'per_page' => $perPage, 'sort' => $sortBy, 'dir' => $sortDir, 'status' => $status, 'stock_status' => $stockStatus];
    if ($search !== '') {
        $query['q'] = $search;
    }
    if ($categoryId !== null) {
        $query['category_id'] = $categoryId;
    }

    return '/products?' . http_build_query($query);
};

// Beda dari buildPageUrl(1): tombol X di search box harus menghapus q,
// bukan cuma reset ke halaman 1 - kalau pakai buildPageUrl(1) di sini,
// q ikut ditambahkan lagi karena $search masih terisi (tombol X cuma
// tampil saat $search !== ''), jadi pencariannya tidak pernah benar-benar
// hilang.
$buildClearSearchUrl = static function () use ($categoryId, $status, $stockStatus, $perPage, $sortBy, $sortDir): string {
    $query = ['page' => 1, 'per_page' => $perPage, 'sort' => $sortBy, 'dir' => $sortDir, 'status' => $status, 'stock_status' => $stockStatus];
    if ($categoryId !== null) {
        $query['category_id'] = $categoryId;
    }

    return '/products?' . http_build_query($query);
};

$buildSortUrl = static function (string $column) use ($search, $categoryId, $status, $stockStatus, $perPage, $sortBy, $sortDir): string {
    $nextDir = ($sortBy === $column && $sortDir === 'asc') ? 'desc' : 'asc';
    $query = ['page' => 1, 'per_page' => $perPage, 'sort' => $column, 'dir' => $nextDir, 'status' => $status, 'stock_status' => $stockStatus];
    if ($search !== '') {
        $query['q'] = $search;
    }
    if ($categoryId !== null) {
        $query['category_id'] = $categoryId;
    }

    return '/products?' . http_build_query($query);
};

// Heroicons chevron-up/chevron-down (24x24), ditampilkan mengecil jadi 12px
// sebagai penanda arah sort di header kolom yang sedang aktif.
$sortIcon = static function (string $dir): string {
    $d = $dir === 'asc' ? 'm4.5 15.75 7.5-7.5 7.5 7.5' : 'm19.5 8.25-7.5 7.5-7.5-7.5';

    return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="' . $d . '"/></svg>';
};

$formatRupiah = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
?>
            <div class="page-header">
                <div>
                    <h1>Produk</h1>
                    <?php if ($totalProducts > 0): ?>
                        <p class="page-header__meta"><?= $totalProducts ?> produk</p>
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
                <form method="get" action="/products" class="search-box">
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <input type="hidden" name="stock_status" value="<?= htmlspecialchars($stockStatus) ?>">
                    <?php if ($categoryId !== null): ?>
                        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                    <?php endif; ?>
                    <label for="product-search" class="sr-only">Cari produk</label>
                    <input
                        type="search"
                        id="product-search"
                        name="q"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Cari nama atau SKU produk..."
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

                <form method="get" action="/products" class="status-filter">
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <input type="hidden" name="stock_status" value="<?= htmlspecialchars($stockStatus) ?>">
                    <label for="product-category" class="sr-only">Filter kategori</label>
                    <select id="product-category" name="category_id" data-auto-submit>
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category->id ?>" <?= $categoryId === $category->id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <form method="get" action="/products" class="status-filter">
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                    <?php if ($categoryId !== null): ?>
                        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                    <?php endif; ?>
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <input type="hidden" name="stock_status" value="<?= htmlspecialchars($stockStatus) ?>">
                    <label for="product-status" class="sr-only">Filter status</label>
                    <select id="product-status" name="status" data-auto-submit>
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </form>

                <form method="get" action="/products" class="status-filter">
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                    <?php if ($categoryId !== null): ?>
                        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                    <?php endif; ?>
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <label for="product-stock-status" class="sr-only">Filter status stok</label>
                    <select id="product-stock-status" name="stock_status" data-auto-submit>
                        <option value="all" <?= $stockStatus === 'all' ? 'selected' : '' ?>>Semua Stok</option>
                        <option value="low" <?= $stockStatus === 'low' ? 'selected' : '' ?>>Stok Rendah</option>
                        <option value="normal" <?= $stockStatus === 'normal' ? 'selected' : '' ?>>Stok Normal</option>
                    </select>
                </form>
                </div>

                <?php if ($isAdmin): ?>
                    <a href="/products/create" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        <span>Tambah Produk</span>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($products === [] && ($search !== '' || $categoryId !== null || $status !== 'all' || $stockStatus !== 'all')): ?>
                <div class="empty-state">
                    <p>Tidak ada produk yang cocok dengan filter saat ini.</p>
                    <a href="/products" class="btn-link">Hapus filter</a>
                </div>
            <?php elseif ($products === []): ?>
                <div class="empty-state">
                    <p>Belum ada produk<?= $isAdmin ? '. Tambahkan produk pertama untuk mulai mengelola katalog dan stok.' : '.' ?></p>
                    <?php if ($isAdmin): ?>
                        <a href="/products/create" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                            </svg>
                            <span>Tambah Produk</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Gambar</th>
                                <th>
                                    <a href="<?= htmlspecialchars($buildSortUrl('sku')) ?>" class="data-table__sort">
                                        SKU
                                        <?php if ($sortBy === 'sku'): ?>
                                            <span aria-hidden="true"><?= $sortIcon($sortDir) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?= htmlspecialchars($buildSortUrl('name')) ?>" class="data-table__sort">
                                        Nama
                                        <?php if ($sortBy === 'name'): ?>
                                            <span aria-hidden="true"><?= $sortIcon($sortDir) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Kategori</th>
                                <th>Unit</th>
                                <th>Harga Beli</th>
                                <th>Harga Jual</th>
                                <th>Reorder Point</th>
                                <th>Stok</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <?php if ($product->imagePath !== null): ?>
                                        <img src="<?= htmlspecialchars($product->imagePath) ?>" alt="" style="width: 2.5rem; height: 2.5rem; object-fit: cover; border-radius: var(--radius-md);">
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($product->sku) ?></td>
                                <td><?= htmlspecialchars($product->name) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($categoryNames[$product->categoryId] ?? '-') ?></td>
                                <td class="text-muted"><?= htmlspecialchars($product->unit) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($formatRupiah($product->buyPrice)) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($formatRupiah($product->sellPrice)) ?></td>
                                <td class="text-muted"><?= $product->reorderPoint ?></td>
                                <?php
                                    $stokTotal = $stockTotals[$product->id] ?? 0;
                                    $stokRendah = $stokTotal < $product->reorderPoint;
                                ?>
                                <td>
                                    <?= $stokTotal ?>
                                    <?php if ($stokRendah): ?>
                                        <span class="badge badge-warning">Stok Rendah</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product->isActive): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="data-table__actions">
                                    <div class="data-table__actions-group">
                                        <a href="/products/<?= $product->id ?>" class="btn-link">Detail</a>
                                        <?php if ($isAdmin): ?>
                                            <a href="/products/<?= $product->id ?>/edit" class="btn-link">Ubah</a>
                                            <form
                                                method="post"
                                                action="/products/<?= $product->id ?>/toggle-active"
                                                data-confirm="<?= $product->isActive ? 'Nonaktifkan' : 'Aktifkan' ?> produk &quot;<?= htmlspecialchars($product->name, ENT_QUOTES) ?>&quot;?"
                                            >
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <button type="submit" class="btn-link<?= $product->isActive ? ' btn-link--danger' : '' ?>">
                                                    <?= $product->isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-bar">
                    <form method="get" action="/products" class="per-page-select">
                        <?php if ($search !== ''): ?>
                            <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                        <?php endif; ?>
                        <?php if ($categoryId !== null): ?>
                            <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                        <?php endif; ?>
                        <input type="hidden" name="page" value="1">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                        <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                        <input type="hidden" name="stock_status" value="<?= htmlspecialchars($stockStatus) ?>">
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
