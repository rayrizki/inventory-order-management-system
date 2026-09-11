# Inventory & Order Management System

Aplikasi web inventory & order management, 3 peran (Admin, Sales, Warehouse Staff), multi-gudang. Dibangun untuk Final Project Intermediate Programmer, PT Neuronworks Indonesia - lihat `docs/planning/` untuk brief lengkap.

**Teknologi**: PHP 8.2+ Native (Controller/Service/Repository, tanpa framework/ORM), Vanilla JS, MySQL 8, Docker Compose, PHPUnit.

## Status

Slice yang sudah selesai: **Login & Logout (AUTH-01, AUTH-02)**. Slice lain (master data, purchase order, sales order, dashboard/laporan) masih dalam pengerjaan bertahap - lihat `docs/quality/tech-debt.md` untuk keterbatasan yang disadari saat ini.

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
