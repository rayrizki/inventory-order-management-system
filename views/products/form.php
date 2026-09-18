<?php
/**
 * @var \App\Entity\Product|null $product null = create, ada isinya = edit
 * @var array{sku: string, name: string, category_id: string, unit: string, buy_price: string, sell_price: string, reorder_point: string} $values
 * @var array<string, string> $errors
 * @var \App\Entity\Category[] $categories
 * @var string $csrfToken
 */
$isEdit = $product !== null;
$pageTitle = $isEdit ? 'Ubah Produk' : 'Tambah Produk';
$activeNav = 'products';
require __DIR__ . '/../layout/shell-start.php';
?>
            <div class="page-header">
                <div>
                    <a href="/products" class="page-header__back">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                        </svg>
                        <span>Kembali ke Produk</span>
                    </a>
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
            </div>

            <form method="post" action="<?= $isEdit ? '/products/' . $product->id : '/products' ?>" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="form-card-grid">
                    <div class="form-card--section">
                        <h2>Informasi Dasar</h2>

                        <div class="form-row">
                            <div class="form-field">
                                <label for="sku">SKU <span class="required-mark" aria-hidden="true">*</span></label>
                                <input
                                    type="text"
                                    id="sku"
                                    name="sku"
                                    value="<?= htmlspecialchars($values['sku']) ?>"
                                    maxlength="50"
                                    aria-describedby="sku-required"
                                    required
                                >
                                <p class="form-hint form-hint--error" id="sku-required" <?= isset($errors['sku']) ? '' : 'hidden' ?>>
                                    <?= htmlspecialchars($errors['sku'] ?? 'SKU wajib diisi.') ?>
                                </p>
                            </div>

                            <div class="form-field">
                                <label for="name">Nama Produk <span class="required-mark" aria-hidden="true">*</span></label>
                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    value="<?= htmlspecialchars($values['name']) ?>"
                                    maxlength="200"
                                    aria-describedby="name-required"
                                    required
                                >
                                <p class="form-hint form-hint--error" id="name-required" <?= isset($errors['name']) ? '' : 'hidden' ?>>
                                    <?= htmlspecialchars($errors['name'] ?? 'Nama produk wajib diisi.') ?>
                                </p>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-field">
                                <label for="category_id">Kategori <span class="required-mark" aria-hidden="true">*</span></label>
                                <select id="category_id" name="category_id" aria-describedby="category_id-required" required>
                                    <option value="">Pilih kategori...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category->id ?>" <?= $values['category_id'] === (string) $category->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category->name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="form-hint form-hint--error" id="category_id-required" <?= isset($errors['category_id']) ? '' : 'hidden' ?>>
                                    <?= htmlspecialchars($errors['category_id'] ?? 'Kategori wajib dipilih.') ?>
                                </p>
                            </div>

                            <div class="form-field">
                                <label for="unit">Unit <span class="required-mark" aria-hidden="true">*</span></label>
                                <input
                                    type="text"
                                    id="unit"
                                    name="unit"
                                    value="<?= htmlspecialchars($values['unit']) ?>"
                                    maxlength="30"
                                    placeholder="pcs, box, kg, ..."
                                    aria-describedby="unit-required"
                                    required
                                >
                                <p class="form-hint form-hint--error" id="unit-required" <?= isset($errors['unit']) ? '' : 'hidden' ?>>
                                    <?= htmlspecialchars($errors['unit'] ?? 'Unit wajib diisi.') ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="form-card--section">
                        <h2>Harga &amp; Stok</h2>

                        <div class="form-row">
                            <div class="form-field">
                                <label for="buy_price">Harga Beli (Rp) <span class="required-mark" aria-hidden="true">*</span></label>
                                <input
                                    type="number"
                                    id="buy_price"
                                    name="buy_price"
                                    value="<?= htmlspecialchars($values['buy_price']) ?>"
                                    min="0"
                                    step="0.01"
                                    aria-describedby="buy_price-required"
                                    required
                                >
                                <p class="form-hint form-hint--error" id="buy_price-required" <?= isset($errors['buy_price']) ? '' : 'hidden' ?>>
                                    <?= htmlspecialchars($errors['buy_price'] ?? 'Harga beli wajib diisi.') ?>
                                </p>
                            </div>

                            <div class="form-field">
                                <label for="sell_price">Harga Jual (Rp) <span class="required-mark" aria-hidden="true">*</span></label>
                                <input
                                    type="number"
                                    id="sell_price"
                                    name="sell_price"
                                    value="<?= htmlspecialchars($values['sell_price']) ?>"
                                    min="0"
                                    step="0.01"
                                    aria-describedby="sell_price-required"
                                    required
                                >
                                <p class="form-hint form-hint--error" id="sell_price-required" <?= isset($errors['sell_price']) ? '' : 'hidden' ?>>
                                    <?= htmlspecialchars($errors['sell_price'] ?? 'Harga jual wajib diisi.') ?>
                                </p>
                            </div>
                        </div>

                        <div class="form-field">
                            <label for="reorder_point">Reorder Point <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="number"
                                id="reorder_point"
                                name="reorder_point"
                                value="<?= htmlspecialchars($values['reorder_point']) ?>"
                                min="0"
                                step="1"
                                aria-describedby="reorder_point-required"
                                required
                            >
                            <p class="form-hint" id="reorder_point-hint" <?= isset($errors['reorder_point']) ? 'hidden' : '' ?>>
                                Ambang batas stok rendah - dipakai untuk peringatan/laporan produk yang perlu di-restock.
                            </p>
                            <p class="form-hint form-hint--error" id="reorder_point-required" <?= isset($errors['reorder_point']) ? '' : 'hidden' ?>>
                                <?= htmlspecialchars($errors['reorder_point'] ?? 'Reorder point wajib diisi.') ?>
                            </p>
                        </div>
                    </div>

                    <div class="form-card--section">
                        <h2>Gambar Produk (Opsional)</h2>

                        <?php if ($isEdit && $product->imagePath !== null): ?>
                            <div class="form-field">
                                <img src="<?= htmlspecialchars($product->imagePath) ?>" alt="Gambar produk saat ini" style="max-width: 8rem; max-height: 8rem; object-fit: cover; border-radius: var(--radius-md); margin-bottom: var(--space-2);">
                                <p class="form-hint">Gambar saat ini. Pilih file baru di bawah untuk menggantinya.</p>
                            </div>
                        <?php endif; ?>

                        <div class="form-field">
                            <label for="image">Berkas Gambar</label>
                            <input
                                type="file"
                                id="image"
                                name="image"
                                accept="image/jpeg,image/png,image/webp"
                                aria-describedby="image-hint image-error"
                            >
                            <p class="form-hint" id="image-hint" <?= isset($errors['image']) ? 'hidden' : '' ?>>
                                Format JPEG, PNG, atau WebP. Ukuran maksimal 2MB.
                            </p>
                            <p class="form-hint form-hint--error" id="image-error" <?= isset($errors['image']) ? '' : 'hidden' ?>>
                                <?= htmlspecialchars($errors['image'] ?? '') ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: var(--space-5);">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <a href="/products" class="btn btn-secondary">Batal</a>
                </div>
            </form>
<?php require __DIR__ . '/../layout/shell-end.php'; ?>
