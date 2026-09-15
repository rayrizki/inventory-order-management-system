<?php
/**
 * @var \App\Entity\Warehouse[] $warehouses
 * @var string $search
 * @var string $status
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var int $totalWarehouses
 * @var array{type: string, text: string}|null $statusMessage
 * @var string $sortBy
 * @var string $sortDir
 * @var string $csrfToken Disediakan shell-start.php, dipakai di setiap form POST.
 */
$pageTitle = 'Gudang';
$activeNav = 'warehouses';
require __DIR__ . '/../layout/shell-start.php';

$buildPageUrl = static function (int $targetPage) use ($search, $status, $perPage, $sortBy, $sortDir): string {
    $query = ['page' => $targetPage, 'per_page' => $perPage, 'sort' => $sortBy, 'dir' => $sortDir, 'status' => $status];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return '/warehouses?' . http_build_query($query);
};

$buildSortUrl = static function (string $column) use ($search, $status, $perPage, $sortBy, $sortDir): string {
    $nextDir = ($sortBy === $column && $sortDir === 'asc') ? 'desc' : 'asc';
    $query = ['page' => 1, 'per_page' => $perPage, 'sort' => $column, 'dir' => $nextDir, 'status' => $status];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return '/warehouses?' . http_build_query($query);
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
                    <h1>Gudang</h1>
                    <?php if ($totalWarehouses > 0): ?>
                        <p class="page-header__meta"><?= $totalWarehouses ?> gudang</p>
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
                <form method="get" action="/warehouses" class="search-box">
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <label for="warehouse-search" class="sr-only">Cari gudang</label>
                    <input
                        type="search"
                        id="warehouse-search"
                        name="q"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Cari nama atau lokasi gudang..."
                        class="search-box__input"
                        autocomplete="off"
                    >
                    <?php if ($search !== ''): ?>
                        <a href="/warehouses" class="search-box__clear" aria-label="Hapus pencarian">
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

                <form method="get" action="/warehouses" class="status-filter">
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <label for="warehouse-status" class="sr-only">Filter status</label>
                    <select id="warehouse-status" name="status" data-auto-submit>
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </form>
                </div>

                <a href="/warehouses/create" class="btn btn-primary" data-modal-target="#warehouse-create-dialog">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Tambah Gudang</span>
                </a>
            </div>

            <?php if ($warehouses === [] && ($search !== '' || $status !== 'all')): ?>
                <div class="empty-state">
                    <p>Tidak ada gudang yang cocok dengan filter saat ini.</p>
                    <a href="/warehouses" class="btn-link">Hapus filter</a>
                </div>
            <?php elseif ($warehouses === []): ?>
                <div class="empty-state">
                    <p>Belum ada gudang. Tambahkan gudang pertama untuk mulai mencatat stok per lokasi.</p>
                    <a href="/warehouses/create" class="btn btn-primary" data-modal-target="#warehouse-create-dialog">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        <span>Tambah Gudang</span>
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
                                    <a href="<?= htmlspecialchars($buildSortUrl('location')) ?>" class="data-table__sort">
                                        Lokasi
                                        <?php if ($sortBy === 'location'): ?>
                                            <span aria-hidden="true"><?= $sortIcon($sortDir) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($warehouses as $warehouse): ?>
                            <tr>
                                <td><?= htmlspecialchars($warehouse->name) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($warehouse->location ?? '-') ?></td>
                                <td>
                                    <?php if ($warehouse->isActive): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="data-table__actions">
                                    <div class="data-table__actions-group">
                                        <a
                                            href="/warehouses/<?= $warehouse->id ?>/edit"
                                            class="btn-link"
                                            data-modal-target="#warehouse-edit-dialog"
                                            data-form-action="/warehouses/<?= $warehouse->id ?>"
                                            data-name="<?= htmlspecialchars($warehouse->name) ?>"
                                            data-location="<?= htmlspecialchars($warehouse->location ?? '') ?>"
                                        >Ubah</a>
                                        <form
                                            method="post"
                                            action="/warehouses/<?= $warehouse->id ?>/toggle-active"
                                            data-confirm="<?= $warehouse->isActive ? 'Nonaktifkan' : 'Aktifkan' ?> gudang &quot;<?= htmlspecialchars($warehouse->name, ENT_QUOTES) ?>&quot;?"
                                        >
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <button type="submit" class="btn-link<?= $warehouse->isActive ? ' btn-link--danger' : '' ?>">
                                                <?= $warehouse->isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-bar">
                    <form method="get" action="/warehouses" class="per-page-select">
                        <?php if ($search !== ''): ?>
                            <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                        <?php endif; ?>
                        <input type="hidden" name="page" value="1">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                        <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
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

            <dialog id="warehouse-create-dialog" class="modal" aria-labelledby="warehouse-create-title">
                <div class="modal__header">
                    <h2 id="warehouse-create-title">Tambah Gudang</h2>
                    <button type="button" class="modal__close" data-modal-close aria-label="Tutup">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <form method="post" action="/warehouses" novalidate>
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div class="form-field">
                            <label for="create-warehouse-name">Nama <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="create-warehouse-name"
                                name="name"
                                maxlength="150"
                                data-field="name"
                                aria-describedby="create-warehouse-name-required"
                                required
                            >
                            <p class="form-hint form-hint--error" id="create-warehouse-name-required" hidden>Nama gudang wajib diisi.</p>
                        </div>
                        <div class="form-field">
                            <label for="create-warehouse-location">Lokasi</label>
                            <input type="text" id="create-warehouse-location" name="location" maxlength="255" data-field="location">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                        </div>
                    </form>
                </div>
            </dialog>

            <dialog id="warehouse-edit-dialog" class="modal" aria-labelledby="warehouse-edit-title">
                <div class="modal__header">
                    <h2 id="warehouse-edit-title">Ubah Gudang</h2>
                    <button type="button" class="modal__close" data-modal-close aria-label="Tutup">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <form method="post" action="/warehouses" novalidate>
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div class="form-field">
                            <label for="edit-warehouse-name">Nama <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="edit-warehouse-name"
                                name="name"
                                maxlength="150"
                                data-field="name"
                                aria-describedby="edit-warehouse-name-required"
                                required
                            >
                            <p class="form-hint form-hint--error" id="edit-warehouse-name-required" hidden>Nama gudang wajib diisi.</p>
                        </div>
                        <div class="form-field">
                            <label for="edit-warehouse-location">Lokasi</label>
                            <input type="text" id="edit-warehouse-location" name="location" maxlength="255" data-field="location">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                        </div>
                    </form>
                </div>
            </dialog>
<?php require __DIR__ . '/../layout/shell-end.php'; ?>
