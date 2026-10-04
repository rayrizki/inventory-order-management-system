# Test Scenario & Hasil - Slice Sales Order & Goods Issue (SO-01, ARCH-02)

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
OK (156 tests, 327 assertions)

PHPStan level 6: [OK] No errors
```

48 test baru di slice ini (18 unit + 8 integration), naik dari 108 sebelumnya
(hitungan dari host - container menjalankan 156 total termasuk integration).

## Unit test (tests/Unit) - tanpa DB/session sungguhan

### `SalesOrderServiceTest` (13 test)

| Test | Skenario |
|---|---|
| Create SO sukses sebagai Draft dengan item | `createdBy` tercatat, `approvedBy` null |
| Customer/warehouse tidak valid ditolak sekaligus | Dua error dalam satu response |
| Item kosong ditolak | |
| Produk nonaktif ditolak | Sama seperti PO-01, tidak boleh order produk yang sudah dinonaktifkan |
| Submit Draft -> PendingApproval | |
| Submit dari status selain Draft ditolak | `ConflictException` |
| Approve PendingApproval -> Approved, approvedBy tercatat | |
| Approve dari status selain PendingApproval ditolak | |
| Cancel dari Approved berhasil; dari Fulfilled ditolak | Batas transisi cancel eksplisit diuji dua arah |
| `getSalesOrderById()` id tidak ditemukan -> `NotFoundException` | |
| **`listSalesOrders()` filter `createdBy` untuk ownership scoping** | Bukti bahwa filter kepemilikan (§1.2) benar-benar memfilter, bukan cuma parameter yang tidak dipakai |
| `listSalesOrders()` filter status | |

### `GoodsIssueServiceTest` (5 test) - cuma `assertCanIssue()`

| Test | Skenario |
|---|---|
| Status Approved diterima | |
| Status Draft/PendingApproval/Fulfilled/Cancelled ditolak (4 test terpisah) | `ConflictException` untuk tiap status non-Approved |

`issue()` sendiri (butuh transaksi PDO nyata) SENGAJA tidak diuji di sini -
lihat integration test di bawah, mengikuti pola pemisahan `computeReceiptPlan()`
vs `receive()` dari ADR-0005.

## Integration test (tests/Integration) - MySQL asli di Docker

### `MySqlSalesOrderRepositoryTest` (6 test)

| Test | Skenario |
|---|---|
| `save()` insert header+item sekaligus | |
| `findById()` memuat SO beserta item | `approvedBy` null untuk SO baru |
| `transitionStatus()` mengubah status saat syaratnya terpenuhi | |
| `transitionStatus()` MENOLAK saat status sudah berubah duluan | Ditambahkan 2026-10-04 (ADR-0007) - inilah guard yang mencegah satu SO dipenuhi dua kali |
| `approve()` mengubah status DAN `approved_by` dalam satu UPDATE | |
| `approve()` menolak percobaan kedua sehingga `approved_by` tidak tertimpa | Ditambahkan 2026-10-04 |
| `listAll()` filter search/status/**createdBy** | Termasuk bukti eksplisit: `createdBy` orang lain (id fiktif) tidak mengembalikan SO siapa pun - baris database sungguhan, bukan cuma logic PHP |
| `listAll()` tidak memuat item (hindari N+1) | |

### `GoodsIssueServiceTest` (3 test) - bukti eksplisit brief bagian 6 (TEST-02)

| Test | Skenario | Bukti apa |
|---|---|---|
| `issue()` mengurangi stok, menulis ledger, set status Fulfilled | Goods issue end-to-end lewat MySQL sungguhan, tiga tabel (`sales_orders`, `product_stock`, `stock_ledger`) konsisten |
| **`testSecondGoodsIssueRejectedWhenStockAlreadyDepletedByFirst`** | **Bukti utama ARCH-02**: dua SO Approved berbeda memperebutkan stok yang sama; goods issue pertama menghabiskan stok, goods issue KEDUA ditolak `ConflictException`, stok tidak pernah jadi negatif, status SO kedua tidak berubah - persis skenario yang diminta brief §6 |
| `issue()` rollback total kalau salah satu dari dua item stoknya tidak cukup | All-or-nothing: item pertama yang sebenarnya cukup ikut di-rollback, tidak ada ledger sama sekali, status SO tidak berubah |

## Skenario manual (Playwright, dijalankan langsung di browser lewat Docker)

Semua skenario di bawah dijalankan terhadap aplikasi yang benar-benar berjalan
(`docker compose up`), BUKAN cuma PHPUnit - membuktikan alur lintas 3 role
sungguhan lewat UI, bukan cuma lewat pemanggilan method langsung.

**Pelajaran dari insiden korupsi data USR-01 diterapkan di sini secara lebih
ketat**: setiap SO/stok yang disentuh skrip verifikasi dicatat id-nya secara
eksplisit lalu dibersihkan lewat SQL langsung setelah verifikasi selesai
(hapus `sales_order_items`/`sales_orders`/`stock_ledger` terkait, kembalikan
`product_stock.quantity` ke nilai semula) - bukan dibiarkan sebagai sampah di
data seed. Juga ditemukan (dan diperbaiki sebelum verifikasi final) bug baru
di skrip verifikasi: selector `button[type="submit"]` tanpa scope mengenai
tombol **logout** di topbar (muncul lebih dulu di DOM daripada tombol submit
form SO) - diperbaiki dengan `button:has-text("Simpan sebagai Draft")`, pola
yang sama ("selalu scope selector, jangan andalkan urutan DOM") yang
seharusnya sudah diterapkan sejak insiden USR-01.

| Skenario | Hasil |
|---|---|
| Sales membuat SO baru (customer+gudang+item) | Berhasil, status Draft |
| **Sales Dua mengakses SO milik Sales Satu lewat URL langsung** | **403** - ownership scoping (§1.2) benar-benar ditegakkan di server, bukan cuma disembunyikan di UI |
| Daftar SO milik Sales Dua tidak menampilkan SO milik Sales Satu | Filter `createdBy` bekerja di halaman index |
| Sales mengajukan SO untuk persetujuan (Draft -> PendingApproval) | Berhasil |
| **Sales tidak melihat tombol "Setujui SO" di halaman order miliknya sendiri** | Segregation of duty (§1.2: "Sales tidak dapat menyetujui order, termasuk order miliknya sendiri") - tombol tidak dirender sama sekali untuk role Sales, bukan cuma disabled |
| Admin menyetujui SO (PendingApproval -> Approved) | Berhasil, `approvedBy` tercatat |
| Warehouse Staff melihat tombol "Proses Goods Issue" pada SO Approved | Tombol tampil sesuai role |
| Warehouse Staff memproses goods issue (Approved -> Fulfilled) | Berhasil, stok produk (ELK-001 @ Gudang Pusat Jakarta) berkurang sesuai qty, satu baris ledger baru tercatat |
| **Dua SO Approved memperebutkan stok terakhir (qty=1) - goods issue kedua** | **Ditolak** dengan pesan "Stok tidak mencukupi" ditampilkan di halaman (bukan crash/500); status SO kedua tetap Disetujui, stok tetap 0 (tidak negatif) - skenario oversell yang sama dengan integration test, dibuktikan ulang lewat browser sungguhan |

## Known bugs & keterbatasan

- **Ditemukan & diperbaiki selama pengembangan (bukan bug tersisa)**: skrip
  Playwright verifikasi awal mengklik `button[type="submit"]` tanpa scope dan
  tanpa sengaja men-submit form logout di topbar alih-alih form create SO -
  murni bug skrip test (ditemukan lewat debug sebelum data seed sempat
  tersentuh sama sekali, beda dari insiden USR-01 yang baru ketahuan setelah
  data seed benar-benar rusak). Diperbaiki dengan selector `has-text()` yang
  spesifik ke tombol form yang dimaksud.
- Tidak ada input qty/harga parsial di goods issue seperti goods receipt
  PO-01 - ini disengaja (lihat class docblock `GoodsIssueService` dan
  ADR-0006), bukan keterbatasan yang belum sempat dikerjakan.
- SO tidak punya kolom `order_date` seperti PO (schema `sales_orders` cuma
  `created_at` otomatis) - sort daftar SO cuma bisa berdasar `created_at`,
  beda dari PO yang bisa disortir berdasar `order_date` yang diinput manual.
