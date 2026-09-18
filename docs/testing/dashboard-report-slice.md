# Test Scenario & Hasil - Slice Dashboard & Laporan (DASH-01, REPORT-01)

Modul terakhir dari alur inti brief §1.1/§2 ("...Dashboard/Laporan -> Logout").
Kedua modul dibangun bersamaan karena REPORT-01 eksplisit diminta memakai
"query agregasi/rekap yang sama dengan dashboard" (§2.5) - `DashboardService`
dan `ReportService` sama-sama membaca lewat method repository yang sama
(`countByStatus()`, `listForReport()`, `sumInventoryValue()`), bukan query
mentah terpisah yang bisa saling menyimpang.

## Perintah

```
docker compose up -d --build app
docker compose exec app vendor/bin/phpunit
docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M
```

## Hasil (2026-09-18)

```
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.2.33
OK (181 tests, 397 assertions)

PHPStan level 6: [OK] No errors
```

18 test baru (12 unit + 6 integration), naik dari 163 sebelumnya.

## Unit test (tests/Unit) - tanpa DB/session sungguhan

### `DashboardServiceTest` (8 test)

| Test | Skenario |
|---|---|
| Admin: nilai inventori = SUM(quantity x buy_price) | Dihitung dari fixture stok+harga InMemory, bukan angka statis |
| Admin: jumlah + daftar produk low-stock | |
| Admin: breakdown PO per status **mencakup status dengan 0 baris** | Bukti eksplisit `countByStatus()` tidak diam-diam menghilangkan status yang kebetulan kosong |
| Admin: breakdown SO mencakup SELURUH SO lintas Sales | Beda dari ringkasan Sales yang di-scope |
| Sales: breakdown SO **di-scope ke `createdBy` sendiri** | SO milik Sales lain (`createdBy` beda) tidak ikut terhitung |
| Warehouse: antrean goods receipt = Ordered + PartiallyReceived digabung | |
| Warehouse: antrean goods issue = jumlah SO Approved | |
| Warehouse: jumlah + daftar produk low-stock | Sama dengan yang dilihat Admin |

### `ReportServiceTest` (4 test)

| Test | Skenario |
|---|---|
| Baris StockLedger diperkaya nama produk/gudang/user (bukan id mentah) | |
| Lookup yang tidak ketemu (produk/user id tidak ada) tampil "-", tidak crash | |
| PO+SO digabung satu daftar, diurutkan tanggal naik; PO tidak punya `disetujui_oleh` (selalu "-") | |
| Order di luar rentang tanggal tidak ikut muncul | |

## Integration test (tests/Integration) - MySQL asli di Docker

Seluruh assertion memakai **delta before/after** (bukan angka mutlak) karena
tabel PO/SO/product_stock sudah berisi seed nyata (30 produk, 25 order,
§7.1) - assert angka pasti akan rapuh terhadap perubahan seed di masa depan.

| Repository | Test baru | Skenario |
|---|---|---|
| `MySqlPurchaseOrderRepositoryTest` | `testCountByStatusIncludesAllStatusesAndReflectsNewRow`, `testListForReportFiltersByOrderDateRange` | `countByStatus()` selalu punya seluruh key status; `listForReport()` cuma mengembalikan baris dalam rentang `order_date` |
| `MySqlSalesOrderRepositoryTest` | `testCountByStatusIncludesAllStatusesAndScopesToCreatedBy`, `testListForReportFiltersByCreatedAtDateRange` | `countByStatus($createdBy)` benar-benar men-scope kepemilikan (§1.2); `listForReport()` filter rentang `created_at` (sargable - lihat perbaikan di bawah) |
| `MySqlStockLedgerRepositoryTest` | `testListForReportFiltersByDateRange` | `created_at` diubah paksa lewat `UPDATE` langsung (tidak bisa diset lewat `record()`) supaya filter tanggal benar-benar teruji, bukan kebetulan lolos karena dua baris dibuat "sekarang" |
| `MySqlProductRepositoryTest` | `testSumInventoryValueReflectsQuantityTimesBuyPrice` | Insert produk+stok baru, pastikan delta nilai inventori tepat `quantity x buy_price` |

## Bug ditemukan & diperbaiki saat menulis view Dashboard (bukan lolos ke user)

Draf pertama `views/dashboard/index.php` membangun URL link status
(`/purchase-orders?status=...`, `/sales-orders?status=...`) memakai NILAI
ENUM PHP asli (`"PartiallyReceived"`, `"PendingApproval"` - PascalCase, key
dari hasil `countByStatus()`) padahal `PurchaseOrderController`/
`SalesOrderController` mengharapkan key snake_case dari `STATUS_FILTERS`
masing-masing (`"partially_received"`, `"pending_approval"`) untuk
`?status=`. Ditemukan sendiri sebelum verifikasi curl (bukan lewat bug
report) - diperbaiki dengan memetakan eksplisit `urlKey => [enumValue,
label]` di view, dengan komentar yang menjelaskan dua "kamus" berbeda itu
supaya tidak tertukar lagi kalau modul serupa ditambah di masa depan.
Diverifikasi ulang lewat curl: klik status link `pending_approval`
benar-benar mengembalikan 200 dan daftar SO yang difilter dengan benar,
bukan 404 atau daftar tak terfilter.

## Skenario manual (curl, terhadap seed data nyata setelah SO-01+tech-debt #6)

| Skenario | Hasil |
|---|---|
| Dashboard Admin | Nilai inventori **Rp 38.075.000** - dicocokkan manual lewat `SELECT SUM(quantity*buy_price)...` langsung ke MySQL, angka identik |
| Dashboard Sales (`sales1@iom.test`) | Breakdown SO Draft=1/PendingApproval=1/Approved=1/Fulfilled=2/Cancelled=0 - dicocokkan manual lewat query `WHERE created_by=...`, identik. **Tidak menampilkan "Nilai Inventori"** (bagian itu memang cuma ada di ringkasan Admin) |
| Dashboard Warehouse Staff | "Menunggu Goods Receipt"=7 (4 Ordered + 3 PartiallyReceived), "Menunggu Goods Issue"=2 (SO Approved) - keduanya dicocokkan manual, identik |
| Akses `/reports` sebagai Sales/Warehouse Staff | **403** keduanya (brief §1.2: hanya Admin boleh unduh laporan) |
| Akses `/reports` sebagai Admin | 200, form rentang tanggal ter-prefill default 30 hari terakhir |
| Item sidebar "Laporan" | Tampil untuk Admin, **tersembunyi** untuk Sales/Warehouse Staff |
| Unduh `stock-ledger.csv` rentang penuh (2026-08-01 s.d. 2026-09-18) | 10 baris data (+1 header) - **cocok persis** dengan 10 baris `stock_ledger` hasil seed 25 order |
| Unduh `stock-ledger.csv` rentang sempit (2026-08-01 s.d. 2026-08-10) | 3 baris data - **lebih sedikit**, membuktikan filter tanggal benar-benar bekerja (bukti brief: "rentang tanggal berbeda") |
| Unduh `orders.csv` rentang penuh | 25 baris data (+1 header) - **cocok persis** dengan 25 order seed, PO dan SO tercampur terurut tanggal, nama supplier/customer/user terisi benar (bukan id mentah) |

## Perbaikan sargability query tanggal (ditemukan saat audit untuk presentasi SQL)

`MySqlSalesOrderRepository::listForReport()` dan
`MySqlStockLedgerRepository::listForReport()` awalnya menulis
`WHERE DATE(created_at) BETWEEN :from_date AND :to_date` - predikat
**non-sargable** (modul SQL Ch1-2/11: membungkus kolom dalam fungsi
mencegah MySQL memakai index apa pun pada kolom itu, karena fungsi harus
dievaluasi ulang per baris, bukan dibandingkan langsung terhadap B-tree
index). Diperbaiki jadi `created_at >= :from_date AND created_at <
DATE_ADD(:to_date, INTERVAL 1 DAY)` - batas atas eksklusif memastikan
seluruh baris PADA tanggal `:to_date` (jam berapa pun) tetap ikut, tanpa
perlu membungkus kolom sama sekali. `MySqlPurchaseOrderRepository::listForReport()`
tidak kena masalah ini karena `order_date` sudah bertipe `DATE` murni
(bukan `DATETIME`), jadi `BETWEEN` polos sudah sargable dari awal.

Dibuktikan lewat `EXPLAIN` langsung (bukan cuma diklaim benar):

```sql
-- Query yang dirancang untuk idx_ledger_product_warehouse_date
-- (product_id, warehouse_id, created_at):
EXPLAIN SELECT * FROM stock_ledger
WHERE product_id = 35 AND warehouse_id = 1
  AND created_at >= '2026-08-01' AND created_at < '2026-09-19';
-- type=range, key_len=13 (KETIGA kolom index terpakai, termasuk created_at)

EXPLAIN SELECT * FROM stock_ledger
WHERE product_id = 35 AND warehouse_id = 1
  AND DATE(created_at) BETWEEN '2026-08-01' AND '2026-09-18';
-- type=ref, key_len=8 (cuma DUA kolom pertama terpakai - created_at
-- dibungkus DATE() sehingga index tidak bisa dipakai untuk bagian range-nya,
-- MySQL harus scan semua baris yang cocok product_id+warehouse_id lalu
-- evaluasi DATE() satu-satu)
```

Pada `listForReport()` sendiri (query tanpa filter `product_id`/`warehouse_id`,
cuma rentang tanggal), `EXPLAIN` tetap menunjukkan `type=ALL` (full table
scan) SEBELUM maupun SESUDAH perbaikan - **disengaja, bukan gagal
diperbaiki**: `created_at` bukan kolom terdepan di index manapun
(`idx_ledger_product_warehouse_date`/`idx_so_status_date` keduanya
menaruh `created_at` di posisi terakhir sesuai left-prefix rule, karena
pola akses yang jauh lebih sering adalah "riwayat SATU produk+gudang" atau
"filter status", bukan "seluruh tabel per rentang tanggal tanpa filter
lain" - REPORT-01 satu-satunya pemakai pola query itu). Menambah index
khusus `created_at` sendiri untuk satu query laporan yang jarang dipanggil
bukan trade-off yang sepadan pada skala data demo ini (modul SQL Ch1-2:
"setiap index punya biaya tulis/storage - jangan index semua kolom").
Yang tetap terbukti nyata dari perbaikan ini: kolom `filtered` di
`EXPLAIN` (estimasi selektivitas optimizer) naik dari 100.00 (optimizer
tidak bisa menaksir apa-apa karena kolomnya dibungkus fungsi) menjadi
11.11 (optimizer bisa menaksir proporsi baris yang benar-benar cocok) -
statistik yang lebih akurat ini tetap bernilai untuk query planning
lanjutan (mis. join order) meski belum ada index yang terpakai langsung.

Full test suite tetap 181/181 dan hasil CSV (jumlah baris, isi) identik
sebelum/sesudah perbaikan - dikonfirmasi lewat curl ulang terhadap seed
data yang sama.

## Known bugs & keterbatasan

- Tidak ada preview agregat di halaman `/reports` sebelum mengunduh (mis.
  "akan mengekspor 10 baris") - dianggap tidak esensial (brief cuma minta
  "Bukti: File CSV hasil ekspor dengan rentang tanggal berbeda", bukan
  preview UI) dan bisa ditambah sebagai polish kalau diminta.
- Validasi format tanggal `?from=`/`?to=` di `ReportController::parseDate()`
  longgar (`DateTimeImmutable::createFromFormat()` tanpa memeriksa
  `getLastErrors()`) - input yang benar-benar tidak valid jatuh ke default
  30 hari terakhir, bukan pesan error. Diterima karena ini endpoint
  Admin-only untuk laporan internal (bukan boundary VAL-01 yang
  menyimpan data), risiko salah pakai rendah.
