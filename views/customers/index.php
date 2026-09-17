<?php
/**
 * @var \App\Entity\Customer[] $customers
 * @var string $search
 * @var string $status
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var int $totalCustomers
 * @var array{type: string, text: string}|null $statusMessage
 * @var string $sortBy
 * @var string $sortDir
 * @var string $csrfToken Disediakan shell-start.php, dipakai di setiap form POST.
 */
$pageTitle = 'Customer';
$activeNav = 'customers';
require __DIR__ . '/../layout/shell-start.php';

$buildPageUrl = static function (int $targetPage) use ($search, $status, $perPage, $sortBy, $sortDir): string {
    $query = ['page' => $targetPage, 'per_page' => $perPage, 'sort' => $sortBy, 'dir' => $sortDir, 'status' => $status];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return '/customers?' . http_build_query($query);
};

$buildSortUrl = static function (string $column) use ($search, $status, $perPage, $sortBy, $sortDir): string {
    $nextDir = ($sortBy === $column && $sortDir === 'asc') ? 'desc' : 'asc';
    $query = ['page' => 1, 'per_page' => $perPage, 'sort' => $column, 'dir' => $nextDir, 'status' => $status];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return '/customers?' . http_build_query($query);
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
                    <h1>Customer</h1>
                    <?php if ($totalCustomers > 0): ?>
                        <p class="page-header__meta"><?= $totalCustomers ?> customer</p>
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
                <form method="get" action="/customers" class="search-box">
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <label for="customer-search" class="sr-only">Cari customer</label>
                    <input
                        type="search"
                        id="customer-search"
                        name="q"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Cari nama, kontak, atau alamat..."
                        class="search-box__input"
                        autocomplete="off"
                    >
                    <?php if ($search !== ''): ?>
                        <a href="/customers" class="search-box__clear" aria-label="Hapus pencarian">
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

                <form method="get" action="/customers" class="status-filter">
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <label for="customer-status" class="sr-only">Filter status</label>
                    <select id="customer-status" name="status" data-auto-submit>
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </form>
                </div>

                <a href="/customers/create" class="btn btn-primary" data-modal-target="#customer-create-dialog">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Tambah Customer</span>
                </a>
            </div>

            <?php if ($customers === [] && ($search !== '' || $status !== 'all')): ?>
                <div class="empty-state">
                    <p>Tidak ada customer yang cocok dengan filter saat ini.</p>
                    <a href="/customers" class="btn-link">Hapus filter</a>
                </div>
            <?php elseif ($customers === []): ?>
                <div class="empty-state">
                    <p>Belum ada customer. Tambahkan customer pertama untuk mulai mencatat sales order.</p>
                    <a href="/customers/create" class="btn btn-primary" data-modal-target="#customer-create-dialog">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        <span>Tambah Customer</span>
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
                            <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?= htmlspecialchars($customer->name) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($customer->contact ?? '-') ?></td>
                                <td class="text-muted"><?= htmlspecialchars($customer->address ?? '-') ?></td>
                                <td>
                                    <?php if ($customer->isActive): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="data-table__actions">
                                    <div class="data-table__actions-group">
                                        <a
                                            href="/customers/<?= $customer->id ?>/edit"
                                            class="btn-link"
                                            data-modal-target="#customer-edit-dialog"
                                            data-form-action="/customers/<?= $customer->id ?>"
                                            data-name="<?= htmlspecialchars($customer->name) ?>"
                                            data-contact="<?= htmlspecialchars($customer->contact ?? '') ?>"
                                            data-address="<?= htmlspecialchars($customer->address ?? '') ?>"
                                        >Ubah</a>
                                        <form
                                            method="post"
                                            action="/customers/<?= $customer->id ?>/toggle-active"
                                            data-confirm="<?= $customer->isActive ? 'Nonaktifkan' : 'Aktifkan' ?> customer &quot;<?= htmlspecialchars($customer->name, ENT_QUOTES) ?>&quot;?"
                                        >
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <button type="submit" class="btn-link<?= $customer->isActive ? ' btn-link--danger' : '' ?>">
                                                <?= $customer->isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
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
                    <form method="get" action="/customers" class="per-page-select">
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

            <dialog id="customer-create-dialog" class="modal" aria-labelledby="customer-create-title">
                <div class="modal__header">
                    <h2 id="customer-create-title">Tambah Customer</h2>
                    <button type="button" class="modal__close" data-modal-close aria-label="Tutup">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <form method="post" action="/customers" novalidate>
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div class="form-field">
                            <label for="create-customer-name">Nama <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="create-customer-name"
                                name="name"
                                maxlength="150"
                                data-field="name"
                                aria-describedby="create-customer-name-required"
                                required
                            >
                            <p class="form-hint form-hint--error" id="create-customer-name-required" hidden>Nama customer wajib diisi.</p>
                        </div>
                        <div class="form-field">
                            <label for="create-customer-contact">Kontak</label>
                            <input type="text" id="create-customer-contact" name="contact" maxlength="150" data-field="contact">
                        </div>
                        <div class="form-field">
                            <label for="create-customer-address">Alamat</label>
                            <textarea id="create-customer-address" name="address" rows="2" maxlength="255" data-field="address"></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                        </div>
                    </form>
                </div>
            </dialog>

            <dialog id="customer-edit-dialog" class="modal" aria-labelledby="customer-edit-title">
                <div class="modal__header">
                    <h2 id="customer-edit-title">Ubah Customer</h2>
                    <button type="button" class="modal__close" data-modal-close aria-label="Tutup">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <form method="post" action="/customers" novalidate>
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div class="form-field">
                            <label for="edit-customer-name">Nama <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="edit-customer-name"
                                name="name"
                                maxlength="150"
                                data-field="name"
                                aria-describedby="edit-customer-name-required"
                                required
                            >
                            <p class="form-hint form-hint--error" id="edit-customer-name-required" hidden>Nama customer wajib diisi.</p>
                        </div>
                        <div class="form-field">
                            <label for="edit-customer-contact">Kontak</label>
                            <input type="text" id="edit-customer-contact" name="contact" maxlength="150" data-field="contact">
                        </div>
                        <div class="form-field">
                            <label for="edit-customer-address">Alamat</label>
                            <textarea id="edit-customer-address" name="address" rows="2" maxlength="255" data-field="address"></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                        </div>
                    </form>
                </div>
            </dialog>
<?php require __DIR__ . '/../layout/shell-end.php'; ?>
