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
| PRD-01 | Produk, kategori, reorder point | **Sebagian** | CRUD dasar + validasi selesai (`docs/testing/master-data-slice.md`); upload gambar produk belum - tech-debt #5, sedang dikerjakan |
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
| FIND-01 | Search/filter/sort/pagination + seed 30 produk & 25 order | **Sebagian** | Search/filter/sort/pagination selesai untuk Produk, PO, SO; seed 25 order gabungan PO+SO selesai (tech-debt #6, `docs/testing/seed-data-verification.md`) - PO kini teruji 2 halaman pagination dengan data nyata; filter status stok (low/normal) Produk masih belum (tech-debt #5) |
| DASH-01 | Dashboard per role dari query agregasi | Belum | Giliran berikutnya sesuai urutan §2 |
| REPORT-01 | Laporan CSV (stock ledger + status order) | Belum | Menyusul setelah DASH-01 |

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
| DESIGN-01 | Class diagram initial & as-built | Selesai | `docs/planning/class-diagram-initial.md`, `docs/architecture/class-diagram-as-built.md` (9 diagram, A-I, terus diperbarui per slice) |
| DESIGN-02 | 2-3 ADR | Selesai (melebihi minimum) | 6 ADR di `docs/architecture/adr-*.md` |
| DESIGN-03 | Refactor log (3 entri) + audit SRP + tech-debt register + 1 commit refactor | Selesai | `docs/quality/refactor-log.md`, `docs/quality/tech-debt.md`, commit `3848279` |
| DESIGN-04 | Critique exercise (cuplikan kode dari assessor) | Belum - menunggu assessor | Isinya baru bisa ditulis setelah assessor memberi cuplikan kode saat defense; `docs/quality/critique.md` belum dibuat sebagai placeholder |

## 3.3 Testing

| ID | Deskripsi singkat | Status | Bukti / catatan |
|---|---|---|---|
| TEST-01 | Unit test terisolasi (min 6 test, 3 area) | Selesai (melebihi minimum) | 156 test total, jauh dari 6 minimum, mencakup lebih dari 3 area logic |
| TEST-02 | Integration test MySQL (min 3) | Selesai (melebihi minimum) | Termasuk bukti eksplisit oversell-prevention SO-01 |
| TEST-03 | Static analysis (PHPStan level 5+) | Selesai | PHPStan level 6, 0 error - `docs/quality/static-analysis.md` |

## Ringkasan status saat ini (2026-09-18)

- **Selesai penuh**: 18 dari 24 item.
- **Sebagian** (gap eksplisit, sedang/akan dikerjakan): PRD-01 (upload
  gambar belum), VIEW-01 (screenshot belum), FIND-01 (filter status stok
  Produk belum - seed 25 order sudah selesai), UI-01 (screenshot belum) -
  lihat `docs/quality/tech-debt.md` #5.
- **Belum dikerjakan**: DASH-01, REPORT-01 (giliran berikutnya sesuai
  urutan §2), DESIGN-04 (menunggu assessor, di luar kendali jadwal peserta).
