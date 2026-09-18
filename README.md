# Inventory & Order Management System

Aplikasi web inventory & order management, 3 peran (Admin, Sales, Warehouse Staff), multi-gudang. Dibangun untuk Final Project Intermediate Programmer, PT Neuronworks Indonesia - lihat `docs/planning/` untuk brief lengkap.

**Teknologi**: PHP 8.2+ Native (Controller/Service/Repository, tanpa framework/ORM), Vanilla JS, MySQL 8, Docker Compose, PHPUnit.

**Icon**: [Heroicons](https://heroicons.com/) (MIT License, oleh Tailwind Labs) - dipakai sebagai markup SVG inline yang di-copy langsung ke view PHP (bukan lewat CDN/webfont/JS runtime), supaya aplikasi tetap jalan penuh offline setelah `docker compose up --build`.

## Status

Slice yang sudah selesai:

- **Login & Logout (AUTH-01, AUTH-02)**.
- **Master Data & Katalog**: Kategori, Gudang (WH-01), Supplier, Customer, Produk (PRD-01 CRUD dasar + halaman detail dengan rincian stok per gudang). Search/filter/sort/pagination (FIND-01) sudah diterapkan di kelima modul. Akses baca Produk terbuka untuk ketiga role (Admin/Sales/Warehouse Staff) sesuai §1.2; modul Master Data lain dan seluruh aksi mutasi tetap Admin-only, ditegakkan di server.
- **Purchase Order & Goods Receipt (PO-01, ARCH-02)**: create PO (Draft) dengan banyak item, ajukan ke supplier (Ordered), goods receipt penuh/sebagian dalam satu transaksi PDO yang menambah `product_stock` dan menulis `stock_ledger` (lihat ADR-0005), status otomatis PartiallyReceived/Received. Akses Admin + Warehouse Staff saja; Sales mendapat 403 dan item sidebar-nya disembunyikan.
- **Manajemen User (USR-01)**: Admin dapat menambah/melihat/mengubah/menonaktifkan akun Sales dan Warehouse Staff (email unik, password di-hash). Modul ini Admin-only sepenuhnya termasuk untuk baca - Sales/Warehouse Staff mendapat 403 dan tidak melihat halamannya sama sekali.
- **Penanganan error (ERR-01)**: exception tak terduga (bug kode, koneksi database putus) tidak lagi menampilkan stack trace ke user - dicatat ke log server, ditampilkan sebagai 500 generik.
- **Endpoint JSON API (API-01)**: `GET /api/products/{sku}/availability` - stok per gudang dalam format JSON, autentikasi sama seperti halaman biasa, kode status 200/401/404 yang tepat.
- **Static analysis (TEST-03)**: PHPStan level 6, 0 error. Lihat `docs/quality/static-analysis.md`.
- **Script terjadwal (JOB-01)**: `scripts/check-low-stock.php` - ringkasan produk di bawah reorder point, dijalankan manual lewat `docker compose exec app php scripts/check-low-stock.php`.
- **Sales Order & Goods Issue (SO-01, ARCH-02)**: create SO (Draft) dengan banyak item, ajukan untuk persetujuan (PendingApproval), Admin menyetujui (Approved), Warehouse Staff memproses goods issue dalam satu transaksi PDO yang mengurangi `product_stock` secara atomik (`decrementIfSufficient()`, mencegah oversell - lihat ADR-0006) dan menulis `stock_ledger`, status otomatis Fulfilled. Segregation of duty §1.2 ditegakkan penuh: Sales cuma boleh membuat/mengajukan/membatalkan order **miliknya sendiri** dan tidak pernah bisa approve (termasuk order sendiri); Admin akses penuh; Warehouse Staff cuma memproses goods issue pada SO Approved. Goods issue bersifat all-or-nothing (beda dari goods receipt PO yang boleh parsial) - satu item stok tidak cukup membatalkan seluruh transaksi.

- **Seed 25 order gabungan (§7.1)**: 15 Purchase Order + 10 Sales Order dengan variasi status lengkap (termasuk PendingApproval dan Cancelled di kedua jenis order), plus 4 supplier dan 4 customer. Status Received/PartiallyReceived (PO) dan Fulfilled (SO) menulis `stock_ledger` dan menyesuaikan `product_stock` secara konsisten - lihat `docs/testing/seed-data-verification.md`.

Dashboard (DASH-01) dan Laporan CSV (REPORT-01) belum dikerjakan - keduanya butuh data order (ringkasan status SO, order pending) yang baru lengkap setelah Sales Order ada, dan brief sendiri menempatkan "Dashboard/Laporan" setelah "Sales Order" di alur inti (§1.1); ini yang selanjutnya dikerjakan. Upload gambar Produk dan filter status stok low/normal (FIND-01, tech-debt #5) sudah tidak diblokir dependency apa pun (PO-01 dan SO-01 keduanya selesai) tapi belum dikerjakan. Lihat `docs/quality/tech-debt.md` untuk daftar lengkap keterbatasan yang disadari saat ini, `docs/planning/backlog.md` untuk checklist status seluruh ID requirement brief, dan `docs/testing/` untuk hasil test tiap slice.

## Instalasi (Docker)

```bash
cp .env.example .env
docker compose up --build -d
```

Aplikasi berjalan di http://localhost:8000, database MySQL otomatis dibuat dari kondisi kosong lewat `database/schema-and-seed.sql` saat container pertama kali dijalankan (`docker-entrypoint-initdb.d`).

## Akun demo

Password sama untuk semua akun: **`Password123!`**

| Role | Email |
|---|---|
| Admin | `admin@iom.test` |
| Sales | `sales1@iom.test`, `sales2@iom.test` |
| Warehouse Staff | `warehouse1@iom.test`, `warehouse2@iom.test` |

## Menjalankan test

```bash
docker compose exec app vendor/bin/phpunit
```

Menjalankan unit test (`tests/Unit`, tanpa DB) dan integration test (`tests/Integration`, terhadap MySQL asli di container `db`) sekaligus.

## Static analysis

```bash
docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M
```

PHPStan level 6, 0 error - lihat `docs/quality/static-analysis.md` untuk detail.

## Struktur proyek

```
app/Controller/   Controller (HTTP/routing)
app/Service/      Business logic
app/Repository/   Akses data - interface + implementasi MySQL & in-memory (fake, untuk unit test)
app/Entity/       Domain model
app/Session/      Session abstraction, AuthGuard, CurrentUser
app/Exception/    Exception untuk ERR-01 (401/403)
views/            Template (PHP native, tanpa template engine)
config/           Loader environment & koneksi PDO
database/         schema-and-seed.sql
tests/Unit/       Test terisolasi (fake repository)
tests/Integration/ Test terhadap MySQL sungguhan
docs/             Planning, architecture (ADR, class diagram), quality (refactor log, tech-debt), testing
```

## Known limitations

Lihat `docs/quality/tech-debt.md` untuk daftar lengkap dan alasannya (jujur dicatat, bukan disembunyikan).
