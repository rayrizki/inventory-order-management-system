<?php
/**
 * @var array{supplier_id: string, warehouse_id: string, order_date: string, items: array<int, array{product_id: string, qty: string, buy_price: string}>} $values
 * @var array<string, string> $errors
 * @var \App\Entity\Supplier[] $suppliers
 * @var \App\Entity\Warehouse[] $warehouses
 * @var \App\Entity\Product[] $products
 * @var string $csrfToken
 */
$pageTitle = 'Buat Purchase Order';
$activeNav = 'purchase-orders';
require_once __DIR__ . '/../layout/shell-start.php';

// Minimal 1 baris ditampilkan meski belum ada item terisi - baris pertama
// jadi "template kosong" yang bisa langsung diisi user.
$rows = $values['items'] !== [] ? $values['items'] : [['product_id' => '', 'qty' => '', 'buy_price' => '']];
?>
            <div class="page-header">
                <div>
                    <a href="/purchase-orders" class="page-header__back">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                        </svg>
                        <span>Kembali ke Purchase Order</span>
                    </a>
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
            </div>

            <?php if (isset($errors['items']) || isset($errors['_general'])): ?>
                <p class="form-error" role="alert">
                    <?= htmlspecialchars($errors['items'] ?? $errors['_general']) ?>
                </p>
            <?php endif; ?>

            <form method="post" action="/purchase-orders" novalidate>
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="form-card--section" style="max-width: 64rem; margin-bottom: var(--space-5);">
                    <h2>Informasi PO</h2>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="supplier_id">Supplier <span class="required-mark" aria-hidden="true">*</span></label>
                            <select id="supplier_id" name="supplier_id" aria-describedby="supplier_id-required" required>
                                <option value="">Pilih supplier...</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier->id ?>" <?= $values['supplier_id'] === (string) $supplier->id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($supplier->name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="form-hint form-hint--error" id="supplier_id-required" <?= isset($errors['supplier_id']) ? '' : 'hidden' ?>>
                                <?= htmlspecialchars($errors['supplier_id'] ?? 'Supplier wajib dipilih.') ?>
                            </p>
                        </div>

                        <div class="form-field">
                            <label for="warehouse_id">Gudang Tujuan <span class="required-mark" aria-hidden="true">*</span></label>
                            <select id="warehouse_id" name="warehouse_id" aria-describedby="warehouse_id-required" required>
                                <option value="">Pilih gudang...</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?= $warehouse->id ?>" <?= $values['warehouse_id'] === (string) $warehouse->id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($warehouse->name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="form-hint form-hint--error" id="warehouse_id-required" <?= isset($errors['warehouse_id']) ? '' : 'hidden' ?>>
                                <?= htmlspecialchars($errors['warehouse_id'] ?? 'Gudang tujuan wajib dipilih.') ?>
                            </p>
                        </div>

                        <div class="form-field">
                            <label for="order_date">Tanggal Order <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="date"
                                id="order_date"
                                name="order_date"
                                value="<?= htmlspecialchars($values['order_date']) ?>"
                                aria-describedby="order_date-required"
                                required
                            >
                            <p class="form-hint form-hint--error" id="order_date-required" <?= isset($errors['order_date']) ? '' : 'hidden' ?>>
                                <?= htmlspecialchars($errors['order_date'] ?? 'Tanggal order wajib diisi.') ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="form-card--section" style="max-width: 64rem;">
                    <h2>Item Pesanan</h2>

                    <div class="item-rows" id="po-items-body">
                        <?php foreach ($rows as $index => $row): ?>
                        <div class="item-row po-item-row">
                            <div class="form-field item-row__product">
                                <label for="item-product-<?= $index ?>">Produk</label>
                                <select id="item-product-<?= $index ?>" name="items[<?= $index ?>][product_id]">
                                    <option value="">Pilih produk...</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= $product->id ?>" <?= $row['product_id'] === (string) $product->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($product->sku . ' - ' . $product->name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors["items.$index.product_id"])): ?>
                                    <p class="form-hint form-hint--error"><?= htmlspecialchars($errors["items.$index.product_id"]) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="form-field item-row__qty">
                                <label for="item-qty-<?= $index ?>">Qty</label>
                                <input type="number" id="item-qty-<?= $index ?>" name="items[<?= $index ?>][qty]" value="<?= htmlspecialchars($row['qty']) ?>" min="1" step="1">
                                <?php if (isset($errors["items.$index.qty"])): ?>
                                    <p class="form-hint form-hint--error"><?= htmlspecialchars($errors["items.$index.qty"]) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="form-field item-row__price">
                                <label for="item-price-<?= $index ?>">Harga Beli (Rp)</label>
                                <input type="number" id="item-price-<?= $index ?>" name="items[<?= $index ?>][buy_price]" value="<?= htmlspecialchars($row['buy_price']) ?>" min="0" step="0.01">
                                <?php if (isset($errors["items.$index.buy_price"])): ?>
                                    <p class="form-hint form-hint--error"><?= htmlspecialchars($errors["items.$index.buy_price"]) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="item-row__remove">
                                <button type="button" class="btn-link btn-link--danger po-item-remove">Hapus</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <p style="margin-top: var(--space-3); margin-bottom: 0;">
                        <button type="button" id="po-add-item" class="btn btn-secondary">+ Tambah Item</button>
                    </p>
                </div>

                <div class="form-actions" style="margin-top: var(--space-5);">
                    <button type="submit" class="btn btn-primary">Simpan sebagai Draft</button>
                    <a href="/purchase-orders" class="btn btn-secondary">Batal</a>
                </div>
            </form>

            <template id="po-item-template">
                <div class="item-row po-item-row">
                    <div class="form-field item-row__product">
                        <label for="item-product-__INDEX__">Produk</label>
                        <select id="item-product-__INDEX__" name="items[__INDEX__][product_id]">
                            <option value="">Pilih produk...</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product->id ?>"><?= htmlspecialchars($product->sku . ' - ' . $product->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field item-row__qty">
                        <label for="item-qty-__INDEX__">Qty</label>
                        <input type="number" id="item-qty-__INDEX__" name="items[__INDEX__][qty]" min="1" step="1">
                    </div>
                    <div class="form-field item-row__price">
                        <label for="item-price-__INDEX__">Harga Beli (Rp)</label>
                        <input type="number" id="item-price-__INDEX__" name="items[__INDEX__][buy_price]" min="0" step="0.01">
                    </div>
                    <div class="item-row__remove">
                        <button type="button" class="btn-link btn-link--danger po-item-remove">Hapus</button>
                    </div>
                </div>
            </template>
<?php require_once __DIR__ . '/../layout/shell-end.php'; ?>
