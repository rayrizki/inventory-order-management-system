# Inventory & Order Management System

Aplikasi web inventory & order management, 3 peran (Admin, Sales, Warehouse Staff), multi-gudang. Dibangun untuk Final Project Intermediate Programmer, PT Neuronworks Indonesia - lihat `docs/planning/` untuk brief lengkap.

**Teknologi**: PHP 8.2+ Native (Controller/Service/Repository, tanpa framework/ORM), Vanilla JS, MySQL 8, Docker Compose, PHPUnit.

**Icon**: [Heroicons](https://heroicons.com/) (MIT License, oleh Tailwind Labs) - dipakai sebagai markup SVG inline yang di-copy langsung ke view PHP (bukan lewat CDN/webfont/JS runtime), supaya aplikasi tetap jalan penuh offline setelah `docker compose up --build`.

## Status

Slice yang sudah selesai:

- **Login & Logout (AUTH-01, AUTH-02)**.
- **Master Data & Katalog**: Kategori, Gudang (WH-01), Supplier, Customer, Produk (PRD-01 CRUD dasar + halaman detail dengan rincian stok per gudang). Search/filter/sort/pagination (FIND-01) sudah diterapkan di kelima modul. Akses baca Produk terbuka untuk ketiga role (Admin/Sales/Warehouse Staff) sesuai §1.2; modul Master Data lain dan seluruh aksi mutasi tetap Admin-only, ditegakkan di server.
- **Purchase Order & Goods Receipt (PO-01, ARCH-02)**: create PO (Draft) dengan banyak item, ajukan ke supplier (Ordered), goods receipt penuh/sebagian dalam satu transaksi PDO yang menambah `product_stock` dan menulis `stock_ledger` (lihat ADR-0005), status otomatis PartiallyReceived/Received. Akses Admin + Warehouse Staff saja; Sales mendapat 403 dan item sidebar-nya disembunyikan.

Slice berikutnya (Sales Order, dashboard/laporan) belum dikerjakan. Upload gambar Produk dan filter status stok low/normal (FIND-01) sengaja ditunda mengikuti urutan pembangunan brief §2 ("alur transaksi inti dulu, baru upload gambar"). Seed data belum menyertakan Purchase Order/Sales Order (§7.1 minta 25 order gabungan) - ditunda sampai Sales Order selesai supaya variasi statusnya lengkap sekaligus. Lihat `docs/quality/tech-debt.md` untuk daftar lengkap keterbatasan yang disadari saat ini, dan `docs/testing/` untuk hasil test tiap slice.

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
