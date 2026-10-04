<?php
/**
 * Template daftar bersama untuk modul Supplier dan Customer.
 *
 * Kedua modul memenuhi lima hal yang persis sama: field-nya sama (nama,
 * kontak, alamat, status aktif), operasinya sama (CRUD + dinonaktifkan bukan
 * dihapus, §1.3), kolom yang dicari sama, perilaku pagination/sort sama, dan
 * otorisasinya sama (Admin-only). Karena itu halamannya dulu identik
 * baris-per-baris kecuali label dan URL - 337 baris yang disalin dua kali.
 *
 * Yang dibagi HANYA lapisan tampilan. Entity, Repository, dan tabelnya
 * sengaja tetap terpisah: `purchase_orders.supplier_id` dan
 * `sales_orders.customer_id` punya foreign key ke tabel yang berbeda, dan
 * pemisahan tipe itulah yang membuat PO tidak mungkin menunjuk ke customer.
 * Lihat ADR-0008.
 *
 * Begitu salah satu dari lima kesamaan di atas tidak lagi berlaku (mis.
 * Customer butuh limit kredit, atau Supplier butuh termin pembayaran),
 * modul yang bersangkutan berhenti memakai template ini dan kembali punya
 * view sendiri - itu pemicunya, bukan "nanti kalau sempat".
 *
 * PERHATIAN saat mengubah file ini: template dijalankan lewat `require`,
 * jadi ia berbagi scope dengan `shell-start.php`. Nama-nama berikut SUDAH
 * DIPAKAI layout dan akan saling menimpa kalau dipakai lagi di sini:
 * `$item`, `$items`, `$key`, `$itemKey`, `$groupKey`, `$groupLabel`,
 * `$navGroups`, `$roleLabels`, `$currentUser`, `$activeNav`, `$pageTitle`,
 * `$csrfToken`. Karena itu daftar di bawah memakai `$records`/`$record` -
 * versi pertama template ini sempat memakai `$items`/`$item` dan baris
 * tabelnya tampil kosong karena tertimpa loop menu sidebar.
 *
 * Disediakan pemanggil:
 * @var object[] $records Daftar Supplier atau Customer pada halaman ini.
 * @var int $totalRecords Jumlah total baris (sebelum pagination).
 * @var string $entityLabel Label berhuruf besar, mis. "Supplier".
 * @var string $entityKey Bentuk huruf kecil untuk id/teks, mis. "supplier".
 * @var string $listUrl URL daftar, mis. "/suppliers".
 * @var string $navKey Key menu aktif di sidebar.
 * @var string $emptyStateContext Alur yang disebut di empty state, mis. "purchase order".
 *
 * Disediakan Controller (sama seperti view lain):
 * @var string $search
 * @var string $status
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var array{type: string, text: string}|null $statusMessage
 * @var string $sortBy
 * @var string $sortDir
 * @var string $csrfToken Disediakan shell-start.php, dipakai di setiap form POST.
 */
$pageTitle = $entityLabel;
$activeNav = $navKey;
require_once __DIR__ . '/../layout/shell-start.php';

$buildPageUrl = static function (int $targetPage) use ($search, $status, $perPage, $sortBy, $sortDir, $listUrl): string {
    $query = ['page' => $targetPage, 'per_page' => $perPage, 'sort' => $sortBy, 'dir' => $sortDir, 'status' => $status];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return $listUrl . '?' . http_build_query($query);
};

$buildSortUrl = static function (string $column) use ($search, $status, $perPage, $sortBy, $sortDir, $listUrl): string {
    $nextDir = ($sortBy === $column && $sortDir === 'asc') ? 'desc' : 'asc';
    $query = ['page' => 1, 'per_page' => $perPage, 'sort' => $column, 'dir' => $nextDir, 'status' => $status];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return $listUrl . '?' . http_build_query($query);
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
                    <h1><?= htmlspecialchars($entityLabel) ?></h1>
                    <?php if ($totalRecords > 0): ?>
                        <p class="page-header__meta"><?= $totalRecords ?> <?= htmlspecialchars($entityKey) ?></p>
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
                <form method="get" action="<?= htmlspecialchars($listUrl) ?>" class="search-box">
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <label for="<?= htmlspecialchars($entityKey) ?>-search" class="sr-only">Cari <?= htmlspecialchars($entityKey) ?></label>
                    <input
                        type="search"
                        id="<?= htmlspecialchars($entityKey) ?>-search"
                        name="q"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Cari nama, kontak, atau alamat..."
                        class="search-box__input"
                        autocomplete="off"
                    >
                    <?php if ($search !== ''): ?>
                        <a href="<?= htmlspecialchars($listUrl) ?>" class="search-box__clear" aria-label="Hapus pencarian">
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

                <form method="get" action="<?= htmlspecialchars($listUrl) ?>" class="status-filter">
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <label for="<?= htmlspecialchars($entityKey) ?>-status" class="sr-only">Filter status</label>
                    <select id="<?= htmlspecialchars($entityKey) ?>-status" name="status" data-auto-submit>
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </form>
                </div>

                <a href="<?= htmlspecialchars($listUrl) ?>/create" class="btn btn-primary" data-modal-target="#<?= htmlspecialchars($entityKey) ?>-create-dialog">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Tambah <?= htmlspecialchars($entityLabel) ?></span>
                </a>
            </div>

            <?php if ($records === [] && ($search !== '' || $status !== 'all')): ?>
                <div class="empty-state">
                    <p>Tidak ada <?= htmlspecialchars($entityKey) ?> yang cocok dengan filter saat ini.</p>
                    <a href="<?= htmlspecialchars($listUrl) ?>" class="btn-link">Hapus filter</a>
                </div>
            <?php elseif ($records === []): ?>
                <div class="empty-state">
                    <p>Belum ada <?= htmlspecialchars($entityKey) ?>. Tambahkan <?= htmlspecialchars($entityKey) ?> pertama untuk mulai mencatat <?= htmlspecialchars($emptyStateContext) ?>.</p>
                    <a href="<?= htmlspecialchars($listUrl) ?>/create" class="btn btn-primary" data-modal-target="#<?= htmlspecialchars($entityKey) ?>-create-dialog">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        <span>Tambah <?= htmlspecialchars($entityLabel) ?></span>
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
                                    <a href="<?= htmlspecialchars($buildSortUrl('contact')) ?>" class="data-table__sort">
                                        Kontak
                                        <?php if ($sortBy === 'contact'): ?>
                                            <span aria-hidden="true"><?= $sortIcon($sortDir) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Alamat</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?= htmlspecialchars($record->name) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($record->contact ?? '-') ?></td>
                                <td class="text-muted"><?= htmlspecialchars($record->address ?? '-') ?></td>
                                <td>
                                    <?php if ($record->isActive): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="data-table__actions">
                                    <div class="data-table__actions-group">
                                        <a
                                            href="<?= htmlspecialchars($listUrl) ?>/<?= $record->id ?>/edit"
                                            class="btn-link"
                                            data-modal-target="#<?= htmlspecialchars($entityKey) ?>-edit-dialog"
                                            data-form-action="<?= htmlspecialchars($listUrl) ?>/<?= $record->id ?>"
                                            data-name="<?= htmlspecialchars($record->name) ?>"
                                            data-contact="<?= htmlspecialchars($record->contact ?? '') ?>"
                                            data-address="<?= htmlspecialchars($record->address ?? '') ?>"
                                        >Ubah</a>
                                        <form
                                            method="post"
                                            action="<?= htmlspecialchars($listUrl) ?>/<?= $record->id ?>/toggle-active"
                                            data-confirm="<?= $record->isActive ? 'Nonaktifkan' : 'Aktifkan' ?> <?= htmlspecialchars($entityKey) ?> &quot;<?= htmlspecialchars($record->name, ENT_QUOTES) ?>&quot;?"
                                        >
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <button type="submit" class="btn-link<?= $record->isActive ? ' btn-link--danger' : '' ?>">
                                                <?= $record->isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
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
                    <form method="get" action="<?= htmlspecialchars($listUrl) ?>" class="per-page-select">
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

            <dialog id="<?= htmlspecialchars($entityKey) ?>-create-dialog" class="modal" aria-labelledby="<?= htmlspecialchars($entityKey) ?>-create-title">
                <div class="modal__header">
                    <h2 id="<?= htmlspecialchars($entityKey) ?>-create-title">Tambah <?= htmlspecialchars($entityLabel) ?></h2>
                    <button type="button" class="modal__close" data-modal-close aria-label="Tutup">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <form method="post" action="<?= htmlspecialchars($listUrl) ?>" novalidate>
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div class="form-field">
                            <label for="create-<?= htmlspecialchars($entityKey) ?>-name">Nama <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="create-<?= htmlspecialchars($entityKey) ?>-name"
                                name="name"
                                maxlength="150"
                                data-field="name"
                                aria-describedby="create-<?= htmlspecialchars($entityKey) ?>-name-required"
                                required
                            >
                            <p class="form-hint form-hint--error" id="create-<?= htmlspecialchars($entityKey) ?>-name-required" hidden>Nama <?= htmlspecialchars($entityKey) ?> wajib diisi.</p>
                        </div>
                        <div class="form-field">
                            <label for="create-<?= htmlspecialchars($entityKey) ?>-contact">Kontak</label>
                            <input type="text" id="create-<?= htmlspecialchars($entityKey) ?>-contact" name="contact" maxlength="150" data-field="contact">
                        </div>
                        <div class="form-field">
                            <label for="create-<?= htmlspecialchars($entityKey) ?>-address">Alamat</label>
                            <textarea id="create-<?= htmlspecialchars($entityKey) ?>-address" name="address" rows="2" maxlength="255" data-field="address"></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                        </div>
                    </form>
                </div>
            </dialog>

            <dialog id="<?= htmlspecialchars($entityKey) ?>-edit-dialog" class="modal" aria-labelledby="<?= htmlspecialchars($entityKey) ?>-edit-title">
                <div class="modal__header">
                    <h2 id="<?= htmlspecialchars($entityKey) ?>-edit-title">Ubah <?= htmlspecialchars($entityLabel) ?></h2>
                    <button type="button" class="modal__close" data-modal-close aria-label="Tutup">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <form method="post" action="<?= htmlspecialchars($listUrl) ?>" novalidate>
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div class="form-field">
                            <label for="edit-<?= htmlspecialchars($entityKey) ?>-name">Nama <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="edit-<?= htmlspecialchars($entityKey) ?>-name"
                                name="name"
                                maxlength="150"
                                data-field="name"
                                aria-describedby="edit-<?= htmlspecialchars($entityKey) ?>-name-required"
                                required
                            >
                            <p class="form-hint form-hint--error" id="edit-<?= htmlspecialchars($entityKey) ?>-name-required" hidden>Nama <?= htmlspecialchars($entityKey) ?> wajib diisi.</p>
                        </div>
                        <div class="form-field">
                            <label for="edit-<?= htmlspecialchars($entityKey) ?>-contact">Kontak</label>
                            <input type="text" id="edit-<?= htmlspecialchars($entityKey) ?>-contact" name="contact" maxlength="150" data-field="contact">
                        </div>
                        <div class="form-field">
                            <label for="edit-<?= htmlspecialchars($entityKey) ?>-address">Alamat</label>
                            <textarea id="edit-<?= htmlspecialchars($entityKey) ?>-address" name="address" rows="2" maxlength="255" data-field="address"></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                        </div>
                    </form>
                </div>
            </dialog>
<?php require_once __DIR__ . '/../layout/shell-end.php'; ?>
