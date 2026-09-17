# Test Scenario & Hasil - Slice Master Data & Katalog (PRD-01, WH-01, FIND-01)

Mencakup lima modul yang dibangun berurutan: Kategori, Gudang (WH-01),
Supplier, Customer, dan Produk (PRD-01 CRUD dasar + WH-01 tampilan stok).
Kelimanya dijalankan lewat satu perintah yang sama - tidak ada suite
terpisah per modul.

## Perintah

```
docker compose up -d --build app
docker compose exec app vendor/bin/phpunit
```

## Hasil (2026-09-17)

```
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.2.33
OK (81 tests, 169 assertions)
```

56 unit test (tanpa DB/session sungguhan) + 25 integration test (MySQL asli
di Docker, tiap test membuat dan menghapus baris miliknya sendiri di
`tearDown()` supaya tidak mencemari data demo §7.1).

## Unit test (tests/Unit) - per modul

| Modul | File | Jumlah | Skenario utama |
|---|---|---|---|
| Kategori | `CategoryServiceTest` | 8 | Create valid; nama kosong ditolak; whitespace di-trim; update tidak ditemukan -> `NotFoundException`; delete ditolak (`ConflictException`) saat kategori masih dipakai produk lain; delete berhasil saat tidak dipakai; list/pagination |
| Gudang | `WarehouseServiceTest` | 8 | Create default aktif; lokasi kosong jadi `null`; nama kosong ditolak; update preserve status aktif; `setActive` toggle; `setActive` id tidak ditemukan; filter status pada list |
| Supplier | `SupplierServiceTest` | 8 | Sama pola dengan Gudang, plus field `contact`/`address` di-trim jadi `null` saat kosong |
| Customer | `CustomerServiceTest` | 8 | Identik struktural dengan Supplier (lihat Diagram E, class-diagram-as-built.md) |
| Produk | `ProductServiceTest` | 11 | Create valid; field wajib kosong -> error per-field terkumpul sekaligus (bukan berhenti di error pertama); `category_id` yang tidak ada -> ditolak (validasi FK, bukan cuma format angka); harga beli/jual negatif -> dua error field sekaligus; reorder point non-numerik ditolak; SKU duplikat ditolak; update boleh mempertahankan SKU sendiri (`excludeId`); update preserve status aktif; filter by kategori |
| Stok (WH-01) | `StockServiceTest` | 4 | Gudang tanpa baris `product_stock` dianggap quantity 0 (bukan error); total menjumlahkan lintas gudang; gudang nonaktif tidak ikut dihitung; hanya baris milik `product_id` yang diminta yang dikembalikan |

## Integration test (tests/Integration) - MySQL asli di Docker

| Modul | File | Jumlah | Skenario utama |
|---|---|---|---|
| Kategori | `MySqlCategoryRepositoryTest` | 3 | Insert baru; `isInUse()` mendeteksi kategori yang dipakai produk; search+sort+pagination lewat query nyata |
| Gudang | `MySqlWarehouseRepositoryTest` | 4 | Insert default aktif; update tidak menyentuh `is_active`; `setActive` toggle; search 2 kolom (name/location) tidak salah gara-gara native-prepares placeholder reuse |
| Supplier | `MySqlSupplierRepositoryTest` | 4 | Sama pola Gudang, plus search 3 kolom (name/contact/address) |
| Customer | `MySqlCustomerRepositoryTest` | 4 | Identik struktural dengan Supplier |
| Produk | `MySqlProductRepositoryTest` | 5 | Insert default aktif; `findBySku` cocok/tidak cocok; update tidak menyentuh `is_active`/`image_path`; `setActive` toggle; `listAll` search cocok di kolom SKU (bukan cuma nama), filter kategori, filter status aktif |
| Stok (WH-01) | `MySqlProductStockRepositoryTest` | 2 | `findByProduct` mengembalikan baris milik produk itu saja (butuh baris Product+Warehouse nyata karena FK `NOT NULL`); mengembalikan array kosong (bukan error) saat belum ada baris stok sama sekali |

## Skenario manual (Playwright + curl, didemokan sepanjang pengembangan)

| Skenario | Modul | Hasil |
|---|---|---|
| Create/edit/nonaktifkan lewat modal (`<dialog>`) | Kategori, Gudang, Supplier, Customer | Berhasil; banner sukses/info/warning sesuai aksi |
| Create/edit lewat form halaman penuh (bukan modal, field lebih banyak) | Produk | Berhasil; validasi SKU duplikat tampil sebagai error per-field, bukan crash |
| Search nama/SKU, filter kategori, sort SKU/nama naik-turun, pagination lintas 2+ halaman | Produk | Semua benar; filter tetap aktif saat pindah halaman (FIND-01) |
| Halaman detail Produk menampilkan total + rincian stok per gudang aktif, badge "Stok Rendah" saat total < reorder point | Produk (WH-01) | Benar; dicocokkan manual lewat query SQL langsung sebagai ground truth |
| Toggle aktif/nonaktif lewat `confirm-dialog` (bukan `window.confirm()`) | Semua 5 modul | Berhasil; status di badge tabel berubah sesuai |
| Akses langsung sebagai role Sales/Warehouse Staff | Kategori/Gudang/Supplier/Customer (`GET /categories`, dst) | 403 (Admin-only, konsisten §1.2) |
| Akses `GET /products` dan `GET /products/{id}` sebagai Sales/Warehouse Staff | Produk | 200 - kedua role boleh baca katalog/stok (§1.2), tombol Tambah/Ubah/Nonaktifkan tersembunyi di view |
| Akses `GET /products/create` dan POST mutasi Produk sebagai Sales/Warehouse Staff | Produk | 403 - mutasi tetap Admin-only di server (`AuthGuard::requireRole`), bukan cuma disembunyikan di UI |
| POST tanpa `_csrf_token` / token salah ke semua form mutasi | Semua 5 modul | 403 (`Router::dispatch()`, satu titik enforcement) |

## Known bugs & keterbatasan

- Upload gambar Produk (PRD-01) dan filter status stok low/normal (FIND-01) belum diimplementasikan - ditunda sengaja mengikuti urutan pembangunan brief §2 ("tambahkan upload gambar setelah alur transaksi inti stabil"). Lihat `docs/quality/tech-debt.md` #5.
- Selama pengembangan modul Produk, script Playwright verifikasi sempat salah klik tombol "Keluar" (logout) alih-alih tombol submit form karena selector `button[type=submit]` tidak di-scope - ini bug pada script test, bukan pada aplikasi (root cause dikonfirmasi lewat logging request/response: `POST /logout` yang tercatat, bukan `POST /products`). Sudah diperbaiki di script, tidak berdampak pada kode aplikasi.
