<?php
/**
 * @var \App\Entity\Supplier|null $supplier null = create, ada isinya = edit
 * @var array{name: string, contact: string, address: string} $values
 * @var array<string, string> $errors
 * @var string $csrfToken
 */
$isEdit = $supplier !== null;
$pageTitle = $isEdit ? 'Ubah Supplier' : 'Tambah Supplier';
$activeNav = 'suppliers';
require __DIR__ . '/../layout/shell-start.php';
?>
            <div class="page-header">
                <div>
                    <a href="/suppliers" class="page-header__back">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                        </svg>
                        <span>Kembali ke Supplier</span>
                    </a>
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
            </div>

            <div class="form-card">
                <form method="post" action="<?= $isEdit ? '/suppliers/' . $supplier->id : '/suppliers' ?>" novalidate>
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
                            <?= htmlspecialchars($errors['name'] ?? 'Nama supplier wajib diisi.') ?>
                        </p>
                    </div>

                    <div class="form-field">
                        <label for="contact">Kontak</label>
                        <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($values['contact']) ?>" maxlength="150">
                    </div>

                    <div class="form-field">
                        <label for="address">Alamat</label>
                        <textarea id="address" name="address" rows="2" maxlength="255"><?= htmlspecialchars($values['address']) ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="/suppliers" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
<?php require __DIR__ . '/../layout/shell-end.php'; ?>
