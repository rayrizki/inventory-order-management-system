<?php
/**
 * @var \App\Entity\Product $product
 * @var string $categoryName
 * @var array{total: int, warehouses: list<array{warehouseId: int, warehouseName: string, quantity: int}>} $stock
 * @var \App\Session\CurrentUser $currentUser Disediakan shell-start.php.
 */
$pageTitle = $product->name;
$activeNav = 'products';
require __DIR__ . '/../layout/shell-start.php';

$formatRupiah = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$isLowStock = $stock['total'] < $product->reorderPoint;
?>
            <div class="page-header">
                <div>
                    <a href="/products" class="page-header__back">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                        </svg>
                        <span>Kembali ke Produk</span>
                    </a>
                    <h1><?= htmlspecialchars($product->name) ?></h1>
                    <p class="page-header__meta">SKU <?= htmlspecialchars($product->sku) ?></p>
                </div>
                <?php if ($currentUser->role === \App\Entity\Role::Admin): ?>
                    <a href="/products/<?= $product->id ?>/edit" class="btn btn-primary">Ubah Produk</a>
                <?php endif; ?>
            </div>

            <div class="detail-card">
                <h2>Informasi Produk</h2>
                <dl class="detail-grid">
                    <div>
                        <dt>Kategori</dt>
                        <dd><?= htmlspecialchars($categoryName) ?></dd>
                    </div>
                    <div>
                        <dt>Unit</dt>
                        <dd><?= htmlspecialchars($product->unit) ?></dd>
                    </div>
                    <div>
                        <dt>Harga Beli</dt>
                        <dd><?= htmlspecialchars($formatRupiah($product->buyPrice)) ?></dd>
                    </div>
                    <div>
                        <dt>Harga Jual</dt>
                        <dd><?= htmlspecialchars($formatRupiah($product->sellPrice)) ?></dd>
                    </div>
                    <div>
                        <dt>Reorder Point</dt>
                        <dd><?= $product->reorderPoint ?></dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd>
                            <?php if ($product->isActive): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-muted">Nonaktif</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="detail-card">
                <h2>Stok per Gudang</h2>
                <dl class="detail-grid">
                    <div>
                        <dt>Total Stok (seluruh gudang aktif)</dt>
                        <dd>
                            <?= $stock['total'] ?>
                            <?php if ($isLowStock): ?>
                                <span class="badge badge-warning">Stok Rendah</span>
                            <?php else: ?>
                                <span class="badge badge-success">Normal</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>

                <?php if ($stock['warehouses'] === []): ?>
                    <p class="form-hint" style="margin-top: var(--space-4);">Belum ada gudang aktif untuk menampilkan rincian stok.</p>
                <?php else: ?>
                    <div class="data-table-wrap" style="margin-top: var(--space-4);">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Gudang</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stock['warehouses'] as $line): ?>
                                <tr>
                                    <td><?= htmlspecialchars($line['warehouseName']) ?></td>
                                    <td><?= $line['quantity'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
<?php require __DIR__ . '/../layout/shell-end.php'; ?>
