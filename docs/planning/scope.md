# Scope

**Catatan transparansi**: sama seperti `user-stories.md`, dokumen ini
ditulis formal setelah sebagian besar slice selesai (bukan sebelum coding
seperti idealnya), sebagai bagian dari audit kelengkapan `docs/planning/`
terhadap §7 brief. Batasan scope di bawah adalah keputusan yang memang
sudah diambil dan diikuti sejak awal proyek (bisa ditelusuri di
`ai-usage-log.md` dan `docs/quality/tech-debt.md`), direkonstruksi di sini
supaya ada satu tempat ringkas yang menjawab "apa yang dikerjakan dan apa
yang sengaja tidak dikerjakan", bukan aturan baru yang dibuat retroaktif.

## Di dalam scope (mengikuti §2 brief apa adanya)

Urutan vertical slice yang diikuti, persis seperti diminta §2 ("Selesaikan
vertical slice dahulu: login -> master data -> purchase order -> sales
order -> dashboard/laporan"):

1. **Login & Session (AUTH-01, AUTH-02)** - selesai.
2. **Master Data & Katalog** (Kategori, Gudang/WH-01, Supplier, Customer,
   Produk/PRD-01, Manajemen User/USR-01) - selesai untuk CRUD dasar +
   search/filter/sort/pagination (FIND-01 untuk Produk). Upload gambar
   Produk dan filter status stok (low/normal) SENGAJA ditunda ke akhir
   slice ini (lihat bagian "Ditunda" di bawah), sesuai §2: "Tambahkan API,
   upload gambar, dan scheduled job setelah alur transaksi inti stabil."
3. **Purchase Order & Goods Receipt (PO-01, ARCH-02)** - selesai, termasuk
   penerimaan barang penuh/sebagian dan pencatatan StockLedger transaksional.
4. **Sales Order & Goods Issue (SO-01, ARCH-02)** - selesai, termasuk
   approval workflow, segregation of duties (§1.2), dan pencegahan oversell
   pada goods issue.
5. **Dashboard & Laporan (DASH-01, REPORT-01)** - belum dikerjakan, giliran
   berikutnya persis sesuai urutan §2 (butuh data PO/SO nyata dari langkah
   3-4 supaya agregasinya bermakna).

Requirement lintas-slice yang dikerjakan sejalan dengan slice terkait
(bukan sebagai slice terpisah, tapi tetap bagian scope wajib):

- **API-01** (endpoint JSON) - selesai, dikerjakan setelah alur transaksi
  inti (PO-01/SO-01) stabil, sesuai urutan §2.
- **ERR-01** (error handling aman) - selesai.
- **VAL-01** (validasi frontend+backend) - diterapkan di setiap slice yang
  punya form (Master Data, PO, SO), bukan modul terpisah.
- **DB-01** (schema, transaksi, index) - berjalan sepanjang proyek, bukan
  satu langkah terpisah - setiap slice yang menambah tabel juga menambah
  bagian ERD/schema yang relevan.
- **JOB-01** (script terjadwal) - selesai, dikerjakan setelah PO-01/SO-01
  ada logic stok yang bisa diringkas.
- **TEST-01/02/03** (unit test, integration test, static analysis) -
  berjalan sepanjang proyek, satu atau lebih test ditambahkan di setiap
  slice, bukan ditulis sekaligus di akhir.
- **DESIGN-01/02/03** (class diagram, ADR, refactor log) - progresif per
  slice (class diagram as-built dan ADR baru ditambahkan begitu keputusan
  desain nyata diambil), bukan ditulis retroaktif tanpa dasar kode.

## Di luar scope (mengikuti §4.3 brief apa adanya)

Eksplisit tidak dikerjakan karena brief §4.3 menyatakan tidak wajib:
microservices, message queue sungguhan, cloud deployment, CI/CD,
Kubernetes, real-time notification, mobile application, cron scheduler
otomatis (JOB-01 cukup dijalankan manual), automated end-to-end test
(Playwright yang dipakai sepanjang proyek ini untuk verifikasi manual per
slice, bukan sebagai automated E2E test suite yang di-commit dan dijalankan
otomatis - skrip-skripnya bersifat sekali pakai untuk demo/verifikasi, tidak
disimpan sebagai bagian dari `tests/`).

Dilarang eksplisit oleh §4 (bukan cuma "di luar scope", tapi pelanggaran
Critical Failure kalau dipakai): framework backend (Laravel, CodeIgniter,
Symfony, Slim), ORM apa pun, DI container framework, framework frontend
(React/Vue/Angular/jQuery), framework CSS. Tidak satu pun dipakai di
codebase ini - diverifikasi lewat `composer.json` (cuma `phpstan/phpstan`
sebagai dev dependency) dan tidak ada `<script>`/`<link>` yang menunjuk ke
library UI selain Heroicons (SVG inline, dicantumkan sesuai §4 "library
icon yang dicantumkan").

## Ditunda (bukan di luar scope - masih requirement wajib, cuma dijadwal ulang)

- **Upload gambar Produk + filter status stok (PRD-01/FIND-01, tech-debt
  #5)** - ditunda sejak Produk pertama kali dibangun, sesuai §2. Sekarang
  sudah tidak diblokir dependency apa pun (PO-01 dan SO-01 sama-sama
  selesai, jadi `product_stock` sudah bergerak dari data transaksi nyata),
  sedang dikerjakan.
- **Seed 25 order gabungan PO+SO (§7.1, tech-debt #6)** - ditunda sampai
  SO-01 selesai supaya variasi status (termasuk PendingApproval dan
  Cancelled) bisa mencakup kedua jenis order sekaligus, bukan cuma PO.
  Sedang dikerjakan.
- **Dashboard (DASH-01) & Laporan CSV (REPORT-01)** - lihat urutan slice di
  atas, giliran berikutnya.

## Fitur Bonus (§4.4) - tidak dikerjakan

Brief eksplisit: "Bonus tidak dapat menutup requirement wajib yang tidak
berfungsi" dan "dinilai setelah seluruh requirement wajib stabil." Karena
DASH-01/REPORT-01 dan beberapa item tech-debt masih terbuka, seluruh fitur
bonus (notifikasi email simulasi, audit trail master data, dashboard
grafik, integration test tambahan di luar minimum) sengaja tidak disentuh
sampai requirement wajib benar-benar stabil semua.
