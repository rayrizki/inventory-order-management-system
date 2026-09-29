# Backlog

**Catatan transparansi**: ditulis formal setelah sebagian besar slice
selesai (lihat catatan yang sama di `user-stories.md`/`scope.md`) - fungsi
dokumen ini sekarang adalah checklist audit terhadap seluruh ID requirement
brief §2-§3, bukan backlog perencanaan di depan. Diperbarui setiap kali
status sebuah item berubah.

Legenda: **Selesai** = ketentuan minimum brief terpenuhi dan sudah
diverifikasi. **Sebagian** = sebagian ketentuan minimum terpenuhi, sisanya
tercatat eksplisit sebagai gap. **Belum** = belum dikerjakan sama sekali.

## 2.1 Autentikasi & User

| ID | Deskripsi singkat | Status | Bukti / catatan |
|---|---|---|---|
| AUTH-01 | Login & session sesuai role | Selesai | `docs/testing/auth-slice.md` |
| AUTH-02 | Logout, session dihapus | Selesai | `docs/testing/auth-slice.md` |
| USR-01 | Admin CRUD akun Sales/Warehouse Staff | Selesai | `docs/testing/user-management-slice.md` |

## 2.2 Master Data

| ID | Deskripsi singkat | Status | Bukti / catatan |
|---|---|---|---|
| PRD-01 | Produk, kategori, reorder point | Selesai | CRUD lengkap + upload gambar (MIME sniffing, nama acak, maks 2MB) - `docs/testing/master-data-slice.md` (tech-debt #5) |
| WH-01 | Gudang & stok multi-lokasi | Selesai | `docs/testing/master-data-slice.md` |

## 2.3 Purchase Order

| ID | Deskripsi singkat | Status | Bukti / catatan |
|---|---|---|---|
| PO-01 | PO + goods receipt (penuh/sebagian) | Selesai | `docs/testing/purchase-order-slice.md`, ADR-0005 |

## 2.4 Sales Order

| ID | Deskripsi singkat | Status | Bukti / catatan |
|---|---|---|---|
| SO-01 | SO, approval, goods issue | Selesai | `docs/testing/sales-order-slice.md`, ADR-0006 |

## 2.5 Daftar, Pencarian, Dashboard & Laporan

| ID | Deskripsi singkat | Status | Bukti / catatan |
|---|---|---|---|
| VIEW-01 | List, detail, empty state | **Sebagian** | Empty state & detail page fungsional di semua modul (diverifikasi Playwright/curl per slice); **belum ada file screenshot tersimpan** sebagai bukti eksplisit yang diminta brief |
| FIND-01 | Search/filter/sort/pagination + seed 30 produk & 25 order | Selesai | Search/filter/sort/pagination selesai untuk Produk, PO, SO termasuk filter status stok low/normal (tech-debt #5); seed 25 order gabungan PO+SO selesai (tech-debt #6, `docs/testing/seed-data-verification.md`) - PO kini teruji 2 halaman pagination dengan data nyata |
| DASH-01 | Dashboard per role dari query agregasi | Selesai | `docs/testing/dashboard-report-slice.md` - nilai inventori/low-stock/PO+SO per status (Admin), SO milik sendiri per status (Sales), antrean goods receipt/issue + low-stock (Warehouse Staff) |
| REPORT-01 | Laporan CSV (stock ledger + status order) | Selesai | `docs/testing/dashboard-report-slice.md` - dua CSV (pergerakan stok, status order gabungan PO+SO), Admin-only, diverifikasi dengan 2 rentang tanggal berbeda |

## 2.6 API

| ID | Deskripsi singkat | Status | Bukti / catatan |
|---|---|---|---|
| API-01 | Endpoint JSON `/api/products/{sku}/availability` | Selesai | Diverifikasi curl: 200/401/404 |

## 2.7 Validation, Error, UI, Database

| ID | Deskripsi singkat | Status | Bukti / catatan |
|---|---|---|---|
| VAL-01 | Validasi frontend+backend, field wajib/enum/FK/angka | Selesai | Tersebar per slice di `docs/testing/*.md` |
| ERR-01 | Login redirect, 403, 404, stack trace tidak bocor | Selesai | `docs/quality/tech-debt.md` #7 |
| UI-01 | Responsive 360px + desktop, label, focus state | **Sebagian** | Diverifikasi fungsional (Playwright mengukur `scrollWidth`/`clientWidth`) untuk sebagian halaman (PO); belum menyeluruh ke semua "empat halaman utama" dan **belum ada file screenshot tersimpan** |
| DB-01 | Schema relasional, transaksi, index, PDO prepared statement | Selesai | `docs/planning/erd.md`, `database/schema-and-seed.sql` |
| JOB-01 | Script terjadwal check-low-stock | Selesai | `scripts/check-low-stock.php`, dijalankan manual dan diverifikasi |

## 3.1 Layered Architecture & Concurrency

| ID | Deskripsi singkat | Status | Bukti / catatan |
|---|---|---|---|
| ARCH-01 | Controller/Service/Repository, DIP, constructor injection | Selesai | Setiap modul; `InMemory*`/`MySql*` per repository |
| ARCH-02 | Transaksi + concurrency-safe stock (anti-oversell) | Selesai | ADR-0005 (goods receipt), ADR-0006 (goods issue oversell prevention), dibuktikan integration test MySQL |

## 3.2 Class Diagram, ADR, Refactor Log

| ID | Deskripsi singkat | Status | Bukti / catatan |
|---|---|---|---|
| DESIGN-01 | Class diagram initial & as-built | Selesai | `docs/planning/class-diagram-initial.md`, `docs/architecture/class-diagram-as-built.md` (10 diagram, A-J, terus diperbarui per slice) |
| DESIGN-02 | 2-3 ADR | Selesai (melebihi minimum) | 6 ADR di `docs/architecture/adr-*.md` |
| DESIGN-03 | Refactor log (3 entri) + audit SRP + tech-debt register + 1 commit refactor | Selesai | `docs/quality/refactor-log.md`, `docs/quality/tech-debt.md`, commit `3848279` |
| DESIGN-04 | Critique exercise (cuplikan kode dari assessor) | Sebagian - menunggu cuplikan assessor | `docs/quality/critique.md` sudah ada berisi self-critique `list-controls.js` (smell, prinsip SOLID yang dilanggar, arah refactor); bagian untuk cuplikan assessor baru bisa diisi saat defense |

## 3.3 Testing

| ID | Deskripsi singkat | Status | Bukti / catatan |
|---|---|---|---|
| TEST-01 | Unit test terisolasi (min 6 test, 3 area) | Selesai (melebihi minimum) | 181 test total, jauh dari 6 minimum, mencakup lebih dari 3 area logic |
| TEST-02 | Integration test MySQL (min 3) | Selesai (melebihi minimum) | Termasuk bukti eksplisit oversell-prevention SO-01 |
| TEST-03 | Static analysis (PHPStan level 5+) | Selesai | PHPStan level 6, 0 error - `docs/quality/static-analysis.md` |

## Ringkasan status saat ini (2026-09-18)

- **Selesai penuh**: 22 dari 24 item - seluruh alur inti brief §1.1
  ("Login -> Master Data -> Purchase Order -> Sales Order -> Stock Ledger
  -> Dashboard/Laporan -> Logout") sudah dibangun dan berfungsi.
- **Sebagian** (gap eksplisit, evidence visual belum dikumpulkan - bukan
  fungsionalitas yang belum jalan): VIEW-01, UI-01 (keduanya butuh
  screenshot; akan dikumpulkan dalam satu ronde verifikasi visual
  menyeluruh sebelum release final).
- **Belum dikerjakan**: DESIGN-04 (menunggu assessor memberi cuplikan kode
  saat defense, di luar kendali jadwal peserta).
