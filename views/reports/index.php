<?php
/**
 * @var string $from Format Y-m-d.
 * @var string $to Format Y-m-d.
 */
$pageTitle = 'Laporan';
$activeNav = 'reports';
require __DIR__ . '/../layout/shell-start.php';
?>
            <div class="page-header">
                <div>
                    <h1>Laporan</h1>
                    <p class="page-header__meta">Ekspor CSV pergerakan stok dan status order dalam rentang tanggal (REPORT-01).</p>
                </div>
            </div>

            <div class="detail-card">
                <h2>Rentang Tanggal</h2>
                <form method="get" action="/reports" class="form-row" style="align-items: flex-end;">
                    <div class="form-field">
                        <label for="from">Dari Tanggal</label>
                        <input type="date" id="from" name="from" value="<?= htmlspecialchars($from) ?>">
                    </div>
                    <div class="form-field">
                        <label for="to">Sampai Tanggal</label>
                        <input type="date" id="to" name="to" value="<?= htmlspecialchars($to) ?>">
                    </div>
                    <div class="form-field">
                        <button type="submit" class="btn btn-secondary">Terapkan</button>
                    </div>
                </form>
                <p class="form-hint">Default 30 hari terakhir kalau tidak diisi. Tautan unduh di bawah mengikuti rentang yang sedang diterapkan.</p>
            </div>

            <div class="detail-card">
                <h2>Pergerakan Stok (Stock Ledger)</h2>
                <p class="form-hint">Seluruh baris StockLedger (Receipt/Issue/Adjustment) dalam rentang tanggal yang dipilih, termasuk produk, gudang, dan siapa yang memprosesnya.</p>
                <a
                    href="/reports/stock-ledger.csv?<?= http_build_query(['from' => $from, 'to' => $to]) ?>"
                    class="btn btn-primary"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    <span>Unduh CSV Pergerakan Stok</span>
                </a>
            </div>

            <div class="detail-card">
                <h2>Status Order (Purchase Order &amp; Sales Order)</h2>
                <p class="form-hint">Gabungan PO dan SO dalam rentang tanggal yang dipilih (PO berdasarkan tanggal order, SO berdasarkan tanggal dibuat), lengkap dengan status dan pihak terkait.</p>
                <a
                    href="/reports/orders.csv?<?= http_build_query(['from' => $from, 'to' => $to]) ?>"
                    class="btn btn-primary"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    <span>Unduh CSV Status Order</span>
                </a>
            </div>
<?php require __DIR__ . '/../layout/shell-end.php'; ?>
