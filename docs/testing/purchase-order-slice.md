# Test Scenario & Hasil - Slice Purchase Order & Goods Receipt (PO-01, ARCH-02)

## Perintah

```
docker compose up -d --build app
docker compose exec app vendor/bin/phpunit
```

## Hasil (2026-09-18)

```
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.2.33
OK (109 tests, 233 assertions)
```

28 test baru di slice ini (18 unit + 10 integration), naik dari 81 sebelumnya.

## Unit test (tests/Unit) - tanpa DB/session sungguhan

| File | Jumlah | Skenario utama |
|---|---|---|
| `PurchaseOrderServiceTest` | 11 | Create PO valid sebagai Draft; supplier/gudang tidak valid ditolak sekaligus (error per-field); item kosong ditolak; baris item invalid menghasilkan error dengan key `items.{index}.{field}`; format tanggal invalid ditolak; `markOrdered` Draft->Ordered; `markOrdered` ditolak kalau bukan Draft (`ConflictException`); `cancel` ditolak dari status Received; `cancel` berhasil dari Ordered; `getPurchaseOrderById` -> `NotFoundException` untuk id tidak ada; `listPurchaseOrders` filter by status |
| `GoodsReceiptServiceTest` | 7 | `computeReceiptPlan()` murni - ditolak saat status Draft/Cancelled (`ConflictException`); diterima saat status Ordered/PartiallyReceived; ditolak kalau qty melebihi sisa (`ValidationException`); ditolak kalau item id tidak dikenal; ditolak kalau semua baris qty 0; baris qty 0 diabaikan tanpa error (bukan baris invalid) |

Catatan desain: `computeReceiptPlan()` diuji dengan entity `PurchaseOrder` buatan tangan tanpa PDO sama sekali (lihat ADR-0005) - membuktikan validasi goods receipt benar-benar independen dari database, sesuai ARCH-01.

## Integration test (tests/Integration) - MySQL asli di Docker

| File | Jumlah | Skenario utama |
|---|---|---|
| `MySqlPurchaseOrderRepositoryTest` | 9 | `save()` insert header+item sekaligus; `findById()` memuat item; `transitionStatus()` mengubah status **dan** menolak transisi yang syaratnya sudah basi; `incrementItemReceivedQtyIfWithinOrdered()` terakumulasi (bukan menimpa) **dan** menolak penerimaan yang melebihi qty dipesan; `listAll()` filter search (nomor PO **dan** nama supplier) serta status |

> Diperbarui 2026-10-04: dua method yang semula tanpa syarat (`updateStatus()`,
> `incrementItemReceivedQty()`) diganti versi bersyarat setelah audit
> menemukan celah balapan - lihat ADR-0007. Jumlah test di kelas ini naik dari
> 5 menjadi 9 karena tiap guard baru dibuktikan dengan test penolakannya.
| `MySqlStockLedgerRepositoryTest` | 2 | `record()` insert dan dapat id; `findByReference()` hanya mengembalikan entry milik reference yang diminta, terurut sesuai waktu insert |
| `GoodsReceiptServiceTest` (integration) | 3 | **Bukti PO-01 eksplisit**: penerimaan sebagian (4 dari 10) menambah `product_stock` dan menulis 1 baris `stock_ledger`, status jadi `PartiallyReceived`; penerimaan sisa (6 lagi) mengakumulasi stok jadi 10 (bukan menimpa) dan status jadi `Received`, dengan 2 baris ledger terpisah (satu per aksi); penerimaan qty melebihi sisa ditolak DAN tidak menulis apa pun ke `product_stock`/`stock_ledger`/status PO (bukan cuma ditolak, tapi juga tidak meninggalkan efek samping parsial) |

## Skenario manual (Playwright + curl, didemokan sepanjang pengembangan)

| Skenario | Hasil |
|---|---|
| Create PO dengan 2 item (baris kedua ditambah lewat tombol "+ Tambah Item", JS murni) | Berhasil sebagai Draft; penomoran `name`/`id` baris dinamis benar (`items[1][...]`, `item-product-1`, dst) |
| Ajukan ke Supplier (Draft -> Ordered) | Berhasil, banner info tampil |
| Goods receipt sebagian (4 dari 10 pada satu item) | Status jadi "Sebagian Diterima", banner info |
| Goods receipt sisa (6 lagi) | Status jadi "Diterima", form goods receipt hilang dari halaman detail, riwayat Stock Ledger menampilkan 3 baris (2 aksi item pertama + 1 aksi item kedua) |
| Batalkan PO (dari status Draft, lewat modal konfirmasi custom) | Berhasil, banner warning, badge "Dibatalkan" |
| Create PO dengan seluruh baris item kosong | Ditolak dengan pesan error, form di-render ulang tanpa redirect (input header tetap terisi) |
| Akses `/purchase-orders` sebagai Sales | 403 (curl + Playwright); item sidebar "Purchase Order" juga tidak tampil sama sekali untuk Sales |
| Akses `/purchase-orders` dan `/purchase-orders/create` sebagai Warehouse Staff | 200 - kedua aksi diizinkan (§1.2: Warehouse Staff boleh membuat/memproses PO) |
| Overflow horizontal di viewport 375px pada form create (item rows) | 0px (diverifikasi terprogram lewat `document.documentElement.scrollWidth - clientWidth`) setelah perbaikan `min-width: 0` |

## Known bugs & keterbatasan

- **Ditemukan & diperbaiki selama slice ini**: `.form-row` dipakai di form Produk sejak awal tapi tidak pernah didefinisikan CSS-nya - field selalu bertumpuk satu kolom meski markup-nya sudah dikelompokkan berpasangan. Baru ketahuan saat form PO (yang juga memakai `.form-row`) dibandingkan dengan form Produk.
- **Ditemukan & diperbaiki**: tombol "X" (hapus pencarian) pada daftar Produk dan PO memakai URL builder yang sama dengan pagination, yang selalu menambahkan kembali `?q=` selama `$search` tidak kosong (dan tombol X cuma tampil saat `$search` tidak kosong) - akibatnya pencarian tidak pernah benar-benar hilang saat diklik.
- **Ditemukan & diperbaiki**: baris item PO awalnya berupa `<table>` yang kolom Produk-nya melebar mengisi sisa lebar (default table layout), menyisakan jarak kosong besar sebelum kolom Qty/Harga, dan overflow horizontal di 375px karena `<select>` tidak bisa menyusut di bawah lebar teks option terpanjangnya (`min-width: auto` bawaan flex item). Diganti jadi baris flex proporsional dengan `min-width: 0`.
- Seed data belum punya Purchase Order permanen (§7.1 minta "25 order gabungan agar pagination dapat diuji") - dicatat di `docs/quality/tech-debt.md` #6, akan diisi setelah Sales Order selesai supaya seed-nya sekaligus mencakup kombinasi PO+SO.
- Goods receipt saat ini tidak diuji dengan skenario CONCURRENT sungguhan (dua request paralel) - brief secara eksplisit menyatakan ini tidak wajib ("simulasi thread/paralel sungguhan tidak wajib"). Karena goods receipt bersifat aditif (menambah stok), tidak ada risiko oversell yang perlu dibuktikan lewat concurrency test seperti goods issue (SO-01) nanti.
