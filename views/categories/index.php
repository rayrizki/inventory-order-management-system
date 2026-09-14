<?php
/**
 * @var \App\Entity\Category[] $categories
 * @var string $search
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var int $totalCategories
 * @var array{type: string, text: string}|null $statusMessage
 * @var string $sortBy
 * @var string $sortDir
 */
$pageTitle = 'Kategori';
$activeNav = 'categories';
require __DIR__ . '/../layout/shell-start.php';

$buildPageUrl = static function (int $targetPage) use ($search, $perPage, $sortBy, $sortDir): string {
    $query = ['page' => $targetPage, 'per_page' => $perPage, 'sort' => $sortBy, 'dir' => $sortDir];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return '/categories?' . http_build_query($query);
};

$buildSortUrl = static function (string $column) use ($search, $perPage, $sortBy, $sortDir): string {
    $nextDir = ($sortBy === $column && $sortDir === 'asc') ? 'desc' : 'asc';
    $query = ['page' => 1, 'per_page' => $perPage, 'sort' => $column, 'dir' => $nextDir];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return '/categories?' . http_build_query($query);
};

// Heroicons chevron-up/chevron-down (24x24), ditampilkan mengecil jadi 12px
// sebagai penanda arah sort di header kolom yang sedang aktif.
$sortIcon = static function (string $dir): string {
    $d = $dir === 'asc' ? 'm4.5 15.75 7.5-7.5 7.5 7.5' : 'm19.5 8.25-7.5 7.5-7.5-7.5';

    return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="' . $d . '"/></svg>';
};
?>
            <div class="page-header">
                <div>
                    <h1>Kategori</h1>
                    <?php if ($totalCategories > 0): ?>
                        <p class="page-header__meta"><?= $totalCategories ?> kategori</p>
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
                <form method="get" action="/categories" class="search-box">
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <label for="category-search" class="sr-only">Cari kategori</label>
                    <input
                        type="search"
                        id="category-search"
                        name="q"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Cari nama kategori..."
                        class="search-box__input"
                        autocomplete="off"
                    >
                    <?php if ($search !== ''): ?>
                        <a href="/categories" class="search-box__clear" aria-label="Hapus pencarian">
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
                <a href="/categories/create" class="btn btn-primary" data-modal-target="#category-create-dialog">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Tambah Kategori</span>
                </a>
            </div>

            <?php if ($categories === [] && $search !== ''): ?>
                <div class="empty-state">
                    <p>Tidak ada kategori yang cocok dengan pencarian "<?= htmlspecialchars($search) ?>".</p>
                    <a href="/categories" class="btn-link">Hapus pencarian</a>
                </div>
            <?php elseif ($categories === []): ?>
                <div class="empty-state">
                    <p>Belum ada kategori. Tambahkan kategori pertama untuk mulai mengelompokkan produk.</p>
                    <a href="/categories/create" class="btn btn-primary" data-modal-target="#category-create-dialog">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Tambah Kategori</span>
                </a>
                </div>
            <?php else: ?>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="<?= htmlspecialchars($buildSortUrl('name')) ?>" class="data-table__sort">
                                        Nama
                                        <?php if ($sortBy === 'name'): ?>
                                            <span aria-hidden="true"><?= $sortIcon($sortDir) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?= htmlspecialchars($buildSortUrl('description')) ?>" class="data-table__sort">
                                        Deskripsi
                                        <?php if ($sortBy === 'description'): ?>
                                            <span aria-hidden="true"><?= $sortIcon($sortDir) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?= htmlspecialchars($category->name) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($category->description ?? '-') ?></td>
                                <td class="data-table__actions">
                                    <a
                                        href="/categories/<?= $category->id ?>/edit"
                                        class="btn-link"
                                        data-modal-target="#category-edit-dialog"
                                        data-form-action="/categories/<?= $category->id ?>"
                                        data-name="<?= htmlspecialchars($category->name) ?>"
                                        data-description="<?= htmlspecialchars($category->description ?? '') ?>"
                                    >Ubah</a>
                                    <form
                                        method="post"
                                        action="/categories/<?= $category->id ?>/delete"
                                        data-confirm="Hapus kategori &quot;<?= htmlspecialchars($category->name, ENT_QUOTES) ?>&quot;? Tindakan ini tidak bisa dibatalkan."
                                    >
                                        <button type="submit" class="btn-link btn-link--danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-bar">
                    <form method="get" action="/categories" class="per-page-select">
                        <?php if ($search !== ''): ?>
                            <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                        <?php endif; ?>
                        <input type="hidden" name="page" value="1">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                        <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
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

            <dialog id="category-create-dialog" class="modal" aria-labelledby="category-create-title">
                <div class="modal__header">
                    <h2 id="category-create-title">Tambah Kategori</h2>
                    <button type="button" class="modal__close" data-modal-close aria-label="Tutup">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <form method="post" action="/categories" novalidate>
                        <div class="form-field">
                            <label for="create-name">Nama <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="create-name"
                                name="name"
                                maxlength="150"
                                data-field="name"
                                aria-describedby="create-name-required"
                                required
                            >
                            <p class="form-hint form-hint--error" id="create-name-required" hidden>Nama kategori wajib diisi.</p>
                        </div>
                        <div class="form-field">
                            <label for="create-description">Deskripsi</label>
                            <textarea id="create-description" name="description" rows="3" data-field="description"></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                        </div>
                    </form>
                </div>
            </dialog>

            <dialog id="category-edit-dialog" class="modal" aria-labelledby="category-edit-title">
                <div class="modal__header">
                    <h2 id="category-edit-title">Ubah Kategori</h2>
                    <button type="button" class="modal__close" data-modal-close aria-label="Tutup">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <form method="post" action="/categories" novalidate>
                        <div class="form-field">
                            <label for="edit-name">Nama <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="edit-name"
                                name="name"
                                maxlength="150"
                                data-field="name"
                                aria-describedby="edit-name-required"
                                required
                            >
                            <p class="form-hint form-hint--error" id="edit-name-required" hidden>Nama kategori wajib diisi.</p>
                        </div>
                        <div class="form-field">
                            <label for="edit-description">Deskripsi</label>
                            <textarea id="edit-description" name="description" rows="3" data-field="description"></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                        </div>
                    </form>
                </div>
            </dialog>
<?php require __DIR__ . '/../layout/shell-end.php'; ?>
