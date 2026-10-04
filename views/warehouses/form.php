<?php
/**
 * @var \App\Entity\Warehouse|null $warehouse null = create, ada isinya = edit
 * @var array{name: string, location: string} $values
 * @var array<string, string> $errors
 * @var string $csrfToken
 */
$isEdit = $warehouse !== null;
$pageTitle = $isEdit ? 'Ubah Gudang' : 'Tambah Gudang';
$activeNav = 'warehouses';
require_once __DIR__ . '/../layout/shell-start.php';
?>
            <div class="page-header">
                <div>
                    <a href="/warehouses" class="page-header__back">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                        </svg>
                        <span>Kembali ke Gudang</span>
                    </a>
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
            </div>

            <div class="form-card">
                <form method="post" action="<?= $isEdit ? '/warehouses/' . $warehouse->id : '/warehouses' ?>" novalidate>
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
                            <?= htmlspecialchars($errors['name'] ?? 'Nama gudang wajib diisi.') ?>
                        </p>
                    </div>

                    <div class="form-field">
                        <label for="location">Lokasi</label>
                        <input type="text" id="location" name="location" value="<?= htmlspecialchars($values['location']) ?>" maxlength="255">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="/warehouses" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
<?php require_once __DIR__ . '/../layout/shell-end.php'; ?>
