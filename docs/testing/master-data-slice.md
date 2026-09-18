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

- Selama pengembangan modul Produk, script Playwright verifikasi sempat salah klik tombol "Keluar" (logout) alih-alih tombol submit form karena selector `button[type=submit]` tidak di-scope - ini bug pada script test, bukan pada aplikasi (root cause dikonfirmasi lewat logging request/response: `POST /logout` yang tercatat, bukan `POST /products`). Sudah diperbaiki di script, tidak berdampak pada kode aplikasi.

## Update 2026-09-18: PRD-01 upload gambar produk + FIND-01 filter status stok (tech-debt #5)

Kedua item di atas sempat ditunda (baris sudah dihapus dari "Known bugs &
keterbatasan" karena sudah selesai) - dikerjakan sekarang setelah PO-01 dan
SO-01 sama-sama selesai. Full test suite naik ke **163 test** (+7 dari 156
sebelum SO-01 seed data, minus nol regresi).

### Keputusan desain

- **Lokasi penyimpanan file**: `public/uploads/products/` (bukan
  `storage/uploads/` yang sempat direncanakan di `.gitignore` scaffold
  awal, tapi tidak pernah dipakai kode apa pun). `public/` satu-satunya
  docroot yang dilayani `php -S -t public` (lihat Dockerfile) - gambar
  produk memang bukan aset sensitif yang butuh access-control tambahan,
  jadi disimpan langsung di sana supaya bisa diakses via URL tanpa perlu
  Controller streaming khusus (lebih sederhana, sesuai peringatan brief
  soal over-engineering).
- **Validasi tipe file lewat MIME sniffing asli** (`finfo_file()` membaca
  isi file), BUKAN ekstensi nama file atau `Content-Type` yang dikirim
  browser (keduanya bisa dipalsukan) - dibuktikan lewat percobaan nyata
  mengunggah file teks polos berekstensi `.jpg`, ditolak dengan benar.
- **`is_uploaded_file()`** dicek sebelum `finfo_file()` - guard supaya
  Service tidak bisa dipaksa membaca file arbitrer di server lewat
  `tmp_name` yang dipalsukan (bukan hasil upload HTTP sungguhan).
- **Nama file acak** (`bin2hex(random_bytes(16))` + ekstensi dari MIME
  tervalidasi, bukan dari nama file asli) - sesuai ketentuan PRD-01 "tidak
  dapat ditebak".
- **Gambar lama dihapus dari disk saat diganti** gambar baru (update) -
  dibuktikan lewat pengujian nyata: file lama benar-benar hilang dari
  `public/uploads/products/`, file baru muncul.
- **Filter status stok (FIND-01)** diimplementasikan lewat `LEFT JOIN
  product_stock` + `GROUP BY` + `HAVING` di `MySqlProductRepository` -
  produk tanpa baris `product_stock` sama sekali dianggap stok 0 (LOW),
  bukan dikecualikan dari hasil (`COALESCE(SUM(...), 0)`).

### Bug ditemukan & diperbaiki selama verifikasi (bukan tersisa)

**MySQL menolak `HAVING` yang mereferensikan kolom non-agregat yang tidak
ada di `GROUP BY`, bahkan kalau functionally dependent ke primary key** -
`SELECT p.id ... GROUP BY p.id HAVING ... < p.reorder_point` gagal dengan
`Unknown column 'p.reorder_point' in 'having clause'` (error 1054),
padahal varian yang sama TAPI dengan `p.reorder_point` ditambahkan ke
SELECT list (persis bentuk query `listAll()`) berhasil normal - MySQL's
functional-dependency exception (mengizinkan kolom non-agregat yang
functionally dependent ke primary key GROUP BY) ternyata CUMA berlaku
untuk SELECT list, bukan HAVING. `countAll()` (subquery derived table yang
cuma `SELECT p.id`, tanpa `reorder_point`) kena bug ini; `listAll()`
kebetulan tidak kena karena SELECT list-nya memang sudah menyertakan
`reorder_point` untuk `hydrate()`. Ditemukan lewat verifikasi curl end-to-
end (500 Internal Server Error saat membuka `/products?stock_status=low`),
BUKAN oleh integration test PHPUnit awal (`testListAllFiltersByStockStatus`
cuma memanggil `listAll()`, tidak pernah memanggil `countAll()` - gap
inilah yang membuat bug lolos dari suite test sebelum verifikasi manual).
Diperbaiki dengan membungkus `p.reorder_point` dalam `MAX()` di
`buildFilter()` (aman - functionally single-valued per grup, cuma
memenuhi syarat sintaks HAVING). Test diperkuat dengan assertion eksplisit
`countAll()` supaya regresi ini tidak bisa lolos lagi tanpa terdeteksi.

### Verifikasi manual (curl multipart, browser sungguhan setelah rebuild)

| Skenario | Hasil |
|---|---|
| Upload PNG valid saat create | Berhasil; file tersimpan dengan nama acak, `image_path` tersimpan di DB, bisa diakses langsung via URL (200, `Content-Type: image/png`) |
| Upload file teks berekstensi `.jpg` (MIME asli bukan gambar) | Ditolak - "Format gambar harus JPEG, PNG, atau WebP." Produk TIDAK dibuat sama sekali |
| Upload file > 2MB | Ditolak - "Ukuran gambar maksimal 2MB." (setelah `upload_max_filesize`/`post_max_size` php.ini dinaikkan ke 5M/6M supaya validasi APLIKASI yang menampilkan pesan, bukan pesan generik PHP ini "kode error: 1") |
| Ganti gambar saat update | Berhasil; file lama terhapus dari disk, file baru tersimpan, `image_path` di DB ter-update |
| Filter `?stock_status=low` | Menampilkan produk dengan total stok (lintas gudang) < reorder_point, termasuk produk tanpa baris `product_stock` sama sekali |
| Filter `?stock_status=normal` | Mengecualikan seluruh produk low-stock dengan benar |

Data uji (3 percobaan produk `TEST-IMG-*`, termasuk 1 yang berhasil dibuat)
dan file gambar yang ter-upload dibersihkan setelah verifikasi selesai.
