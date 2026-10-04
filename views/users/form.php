<?php
/**
 * @var \App\Entity\User|null $user null = create, ada isinya = edit
 * @var array{name: string, email: string, password: string, role: string} $values
 * @var array<string, string> $errors
 * @var string $csrfToken
 */
$isEdit = $user !== null;
$pageTitle = $isEdit ? 'Ubah User' : 'Tambah User';
$activeNav = 'users';
require_once __DIR__ . '/../layout/shell-start.php';
?>
            <div class="page-header">
                <div>
                    <a href="/users" class="page-header__back">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                        </svg>
                        <span>Kembali ke User</span>
                    </a>
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
            </div>

            <div class="form-card">
                <form method="post" action="<?= $isEdit ? '/users/' . $user->id : '/users' ?>" novalidate>
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="form-field">
                        <label for="name">Nama <span class="required-mark" aria-hidden="true">*</span></label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?= htmlspecialchars($values['name']) ?>"
                            maxlength="150"
                            aria-describedby="name-required"
                            required
                        >
                        <p class="form-hint form-hint--error" id="name-required" <?= isset($errors['name']) ? '' : 'hidden' ?>>
                            <?= htmlspecialchars($errors['name'] ?? 'Nama wajib diisi.') ?>
                        </p>
                    </div>

                    <div class="form-field">
                        <label for="email">Email <span class="required-mark" aria-hidden="true">*</span></label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($values['email']) ?>"
                            maxlength="190"
                            aria-describedby="email-required"
                            required
                        >
                        <p class="form-hint form-hint--error" id="email-required" <?= isset($errors['email']) ? '' : 'hidden' ?>>
                            <?= htmlspecialchars($errors['email'] ?? 'Email wajib diisi dan harus valid.') ?>
                        </p>
                    </div>

                    <div class="form-field">
                        <label for="password">Password<?= $isEdit ? ' Baru' : '' ?> <?php if (!$isEdit): ?><span class="required-mark" aria-hidden="true">*</span><?php endif; ?></label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            aria-describedby="password-required"
                            <?= $isEdit ? '' : 'required' ?>
                        >
                        <?php if ($isEdit): ?>
                            <p class="form-hint">Kosongkan jika tidak ingin mengubah password.</p>
                        <?php else: ?>
                            <p class="form-hint" <?= isset($errors['password']) ? 'hidden' : '' ?>>Minimal 8 karakter.</p>
                        <?php endif; ?>
                        <p class="form-hint form-hint--error" id="password-required" <?= isset($errors['password']) ? '' : 'hidden' ?>>
                            <?= htmlspecialchars($errors['password'] ?? 'Password wajib diisi (minimal 8 karakter).') ?>
                        </p>
                    </div>

                    <div class="form-field">
                        <label for="role">Role <span class="required-mark" aria-hidden="true">*</span></label>
                        <select id="role" name="role" aria-describedby="role-required" required>
                            <option value="">Pilih role...</option>
                            <option value="Sales" <?= $values['role'] === 'Sales' ? 'selected' : '' ?>>Sales</option>
                            <option value="WarehouseStaff" <?= $values['role'] === 'WarehouseStaff' ? 'selected' : '' ?>>Warehouse Staff</option>
                        </select>
                        <p class="form-hint form-hint--error" id="role-required" <?= isset($errors['role']) ? '' : 'hidden' ?>>
                            <?= htmlspecialchars($errors['role'] ?? 'Role wajib dipilih.') ?>
                        </p>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="/users" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
<?php require_once __DIR__ . '/../layout/shell-end.php'; ?>
