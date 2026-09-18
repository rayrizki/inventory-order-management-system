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
| `MySqlSalesOrderRepositoryTest` | `testCountByStatusIncludesAllStatusesAndScopesToCreatedBy`, `testListForReportFiltersByCreatedAtDateRange` | `countByStatus($createdBy)` benar-benar men-scope kepemilikan (§1.2); `listForReport()` filter `DATE(created_at)` |
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
