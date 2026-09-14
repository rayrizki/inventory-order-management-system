<?php
/**
 * @var \App\Entity\Category|null $category null = create, ada isinya = edit
 * @var array{name: string, description: string} $values
 * @var array<string, string> $errors
 */
$isEdit = $category !== null;
$pageTitle = $isEdit ? 'Ubah Kategori' : 'Tambah Kategori';
$activeNav = 'categories';
require __DIR__ . '/../layout/shell-start.php';
?>
            <div class="page-header">
                <div>
                    <a href="/categories" class="page-header__back">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                        </svg>
                        <span>Kembali ke Kategori</span>
                    </a>
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
            </div>

            <div class="form-card">
                <form method="post" action="<?= $isEdit ? '/categories/' . $category->id : '/categories' ?>" novalidate>
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
                            <?= htmlspecialchars($errors['name'] ?? 'Nama kategori wajib diisi.') ?>
                        </p>
                    </div>

                    <div class="form-field">
                        <label for="description">Deskripsi</label>
                        <textarea id="description" name="description" rows="3"><?= htmlspecialchars($values['description']) ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="/categories" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
<?php require __DIR__ . '/../layout/shell-end.php'; ?>
