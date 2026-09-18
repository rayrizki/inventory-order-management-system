<?php
/**
 * @var \App\Entity\User[] $users
 * @var string $search
 * @var string $status
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var int $totalUsers
 * @var array{type: string, text: string}|null $statusMessage
 * @var string $sortBy
 * @var string $sortDir
 * @var string $csrfToken Disediakan shell-start.php, dipakai di setiap form POST.
 */
$pageTitle = 'User';
$activeNav = 'users';
require __DIR__ . '/../layout/shell-start.php';

$roleLabels = ['Admin' => 'Admin', 'Sales' => 'Sales', 'WarehouseStaff' => 'Warehouse Staff'];

$buildPageUrl = static function (int $targetPage) use ($search, $status, $perPage, $sortBy, $sortDir): string {
    $query = ['page' => $targetPage, 'per_page' => $perPage, 'sort' => $sortBy, 'dir' => $sortDir, 'status' => $status];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return '/users?' . http_build_query($query);
};

$buildSortUrl = static function (string $column) use ($search, $status, $perPage, $sortBy, $sortDir): string {
    $nextDir = ($sortBy === $column && $sortDir === 'asc') ? 'desc' : 'asc';
    $query = ['page' => 1, 'per_page' => $perPage, 'sort' => $column, 'dir' => $nextDir, 'status' => $status];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return '/users?' . http_build_query($query);
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
                    <h1>User</h1>
                    <?php if ($totalUsers > 0): ?>
                        <p class="page-header__meta"><?= $totalUsers ?> user</p>
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
                <form method="get" action="/users" class="search-box">
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <label for="user-search" class="sr-only">Cari user</label>
                    <input
                        type="search"
                        id="user-search"
                        name="q"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Cari nama atau email..."
                        class="search-box__input"
                        autocomplete="off"
                    >
                    <?php if ($search !== ''): ?>
                        <a href="/users" class="search-box__clear" aria-label="Hapus pencarian">
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

                <form method="get" action="/users" class="status-filter">
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <label for="user-status" class="sr-only">Filter status</label>
                    <select id="user-status" name="status" data-auto-submit>
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </form>
                </div>

                <a href="/users/create" class="btn btn-primary" data-modal-target="#user-create-dialog">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Tambah User</span>
                </a>
            </div>

            <?php if ($users === [] && ($search !== '' || $status !== 'all')): ?>
                <div class="empty-state">
                    <p>Tidak ada user yang cocok dengan filter saat ini.</p>
                    <a href="/users" class="btn-link">Hapus filter</a>
                </div>
            <?php elseif ($users === []): ?>
                <div class="empty-state">
                    <p>Belum ada user Sales/Warehouse Staff. Tambahkan user pertama untuk memberi akses ke tim.</p>
                    <a href="/users/create" class="btn btn-primary" data-modal-target="#user-create-dialog">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        <span>Tambah User</span>
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
                                    <a href="<?= htmlspecialchars($buildSortUrl('email')) ?>" class="data-table__sort">
                                        Email
                                        <?php if ($sortBy === 'email'): ?>
                                            <span aria-hidden="true"><?= $sortIcon($sortDir) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user->name) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($user->email) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($roleLabels[$user->role->value] ?? $user->role->value) ?></td>
                                <td>
                                    <?php if ($user->isActive): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="data-table__actions">
                                    <div class="data-table__actions-group">
                                        <?php if ($user->role !== \App\Entity\Role::Admin): ?>
                                            <a
                                                href="/users/<?= $user->id ?>/edit"
                                                class="btn-link"
                                                data-modal-target="#user-edit-dialog"
                                                data-form-action="/users/<?= $user->id ?>"
                                                data-name="<?= htmlspecialchars($user->name) ?>"
                                                data-email="<?= htmlspecialchars($user->email) ?>"
                                                data-role="<?= htmlspecialchars($user->role->value) ?>"
                                            >Ubah</a>
                                            <form
                                                method="post"
                                                action="/users/<?= $user->id ?>/toggle-active"
                                                data-confirm="<?= $user->isActive ? 'Nonaktifkan' : 'Aktifkan' ?> user &quot;<?= htmlspecialchars($user->name, ENT_QUOTES) ?>&quot;?"
                                            >
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <button type="submit" class="btn-link<?= $user->isActive ? ' btn-link--danger' : '' ?>">
                                                    <?= $user->isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-bar">
                    <form method="get" action="/users" class="per-page-select">
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

            <dialog id="user-create-dialog" class="modal" aria-labelledby="user-create-title">
                <div class="modal__header">
                    <h2 id="user-create-title">Tambah User</h2>
                    <button type="button" class="modal__close" data-modal-close aria-label="Tutup">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <form method="post" action="/users" novalidate>
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div class="form-field">
                            <label for="create-user-name">Nama <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="text" id="create-user-name" name="name" maxlength="150" data-field="name" aria-describedby="create-user-name-required" required>
                            <p class="form-hint form-hint--error" id="create-user-name-required" hidden>Nama wajib diisi.</p>
                        </div>
                        <div class="form-field">
                            <label for="create-user-email">Email <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="email" id="create-user-email" name="email" maxlength="190" data-field="email" aria-describedby="create-user-email-required" required>
                            <p class="form-hint form-hint--error" id="create-user-email-required" hidden>Email wajib diisi dan harus valid.</p>
                        </div>
                        <div class="form-field">
                            <label for="create-user-password">Password <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="password" id="create-user-password" name="password" minlength="8" data-field="password" aria-describedby="create-user-password-required" required>
                            <p class="form-hint" id="create-user-password-hint">Minimal 8 karakter.</p>
                            <p class="form-hint form-hint--error" id="create-user-password-required" hidden>Password wajib diisi (minimal 8 karakter).</p>
                        </div>
                        <div class="form-field">
                            <label for="create-user-role">Role <span class="required-mark" aria-hidden="true">*</span></label>
                            <select id="create-user-role" name="role" data-field="role" aria-describedby="create-user-role-required" required>
                                <option value="">Pilih role...</option>
                                <option value="Sales">Sales</option>
                                <option value="WarehouseStaff">Warehouse Staff</option>
                            </select>
                            <p class="form-hint form-hint--error" id="create-user-role-required" hidden>Role wajib dipilih.</p>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                        </div>
                    </form>
                </div>
            </dialog>

            <dialog id="user-edit-dialog" class="modal" aria-labelledby="user-edit-title">
                <div class="modal__header">
                    <h2 id="user-edit-title">Ubah User</h2>
                    <button type="button" class="modal__close" data-modal-close aria-label="Tutup">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <form method="post" action="/users" novalidate>
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div class="form-field">
                            <label for="edit-user-name">Nama <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="text" id="edit-user-name" name="name" maxlength="150" data-field="name" aria-describedby="edit-user-name-required" required>
                            <p class="form-hint form-hint--error" id="edit-user-name-required" hidden>Nama wajib diisi.</p>
                        </div>
                        <div class="form-field">
                            <label for="edit-user-email">Email <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="email" id="edit-user-email" name="email" maxlength="190" data-field="email" aria-describedby="edit-user-email-required" required>
                            <p class="form-hint form-hint--error" id="edit-user-email-required" hidden>Email wajib diisi dan harus valid.</p>
                        </div>
                        <div class="form-field">
                            <label for="edit-user-password">Password Baru</label>
                            <input type="password" id="edit-user-password" name="password" minlength="8" data-field="password">
                            <p class="form-hint">Kosongkan jika tidak ingin mengubah password.</p>
                        </div>
                        <div class="form-field">
                            <label for="edit-user-role">Role <span class="required-mark" aria-hidden="true">*</span></label>
                            <select id="edit-user-role" name="role" data-field="role" aria-describedby="edit-user-role-required" required>
                                <option value="">Pilih role...</option>
                                <option value="Sales">Sales</option>
                                <option value="WarehouseStaff">Warehouse Staff</option>
                            </select>
                            <p class="form-hint form-hint--error" id="edit-user-role-required" hidden>Role wajib dipilih.</p>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                        </div>
                    </form>
                </div>
            </dialog>
<?php require __DIR__ . '/../layout/shell-end.php'; ?>
