# ADR-0007: Transisi status order pakai UPDATE bersyarat, bukan baca-lalu-tulis

## Context

ADR-0006 menutup oversell dengan menggabungkan cek dan pengurangan stok jadi
satu statement atomik. Guard itu menjaga **baris stok**, dan hanya itu. Saat
seluruh alur PO/SO diaudit ulang terhadap brief, ketahuan bahwa **order-nya
sendiri tidak dijaga apa-apa**: setiap perubahan status ditulis dengan

```sql
UPDATE sales_orders SET status = :status WHERE id = :id
```

setelah status dibaca di Service, di luar transaksi. Polanya persis
cek-lalu-tulis yang ADR-0006 tolak untuk stok, hanya saja di tabel yang
berbeda. Tiga akibat nyata, semuanya bisa terjadi tanpa stok pernah negatif:

1. **Satu SO dipenuhi dua kali.** Admin dan Warehouse Staff sama-sama menekan
   "Proses Goods Issue" untuk SO yang sama. Keduanya membaca status
   `Approved`, keduanya lolos `assertCanIssue()`, keduanya mengurangi stok
   (yang kebetulan masih cukup - stok itu jatah order lain) dan keduanya
   menulis baris ledger `Issue`. Hasilnya: satu order 10 unit mengeluarkan 20
   unit, dan StockLedger mencatat dua kali untuk referensi order yang sama.
   Guard ADR-0006 tidak menolak apa pun di sini karena stoknya memang cukup.
2. **Satu item PO diterima melebihi yang dipesan.** `received_qty = received_qty
   + :delta` juga ditulis tanpa syarat, sementara validasi "tidak boleh
   melebihi sisa" dihitung dari angka yang dibaca sebelum transaksi. Dua
   penerimaan 5 unit atas sisa 5 unit sama-sama lolos: `received_qty` jadi 10
   pada baris yang cuma dipesan 5, dan stok bertambah 10.
3. **Order dibatalkan padahal barangnya sudah bergerak.** `cancel()` menulis
   `Cancelled` tanpa syarat. Kalau berjalan bersamaan dengan goods issue, SO
   berakhir `Cancelled` sementara stok sudah keluar dan ledger sudah mencatat
   `Issue` - ProductStock dan SalesOrder saling bertentangan, persis kondisi
   yang brief sebut sebagai critical failure.

Ketiganya tidak akan tertangkap oleh test berurutan biasa, karena pemeriksaan
status di Service memang menolak percobaan kedua **kalau dijalankan setelah
yang pertama selesai**. Yang bocor adalah jeda antara baca dan tulis.

## Decision

Semua penulisan status dan `received_qty` diubah jadi **write bersyarat yang
melaporkan hasilnya**, mengikuti bentuk yang sudah dipakai
`decrementIfSufficient()` - syarat ikut di `WHERE`, keputusan diambil oleh
database, bukan oleh PHP:

```sql
-- transitionStatus(id, expected[], next)
UPDATE sales_orders SET status = :next
WHERE id = :id AND status IN (:expected_0, :expected_1, ...)

-- incrementItemReceivedQtyIfWithinOrdered(itemId, delta)
UPDATE purchase_order_items SET received_qty = received_qty + :delta
WHERE id = :id AND received_qty + :delta_check <= qty
```

Keduanya mengembalikan `rowCount() > 0`. Service tetap membaca status lebih
dulu - itu yang memberi pesan error yang enak dibaca untuk kasus biasa - tapi
yang menentukan boleh atau tidaknya adalah hasil `bool` tadi; `false` berarti
ada request lain yang mendahului, dan seluruh transaksi di-rollback.

`GoodsIssueService` menempatkan transisi `Approved -> Fulfilled` sebagai
**statement pertama** dalam transaksi, sebelum stok disentuh. Request kedua
atas SO yang sama akan menunggu di row lock baris `sales_orders` itu, lalu
melihat 0 baris cocok begitu yang pertama commit - jadi ia berhenti sebelum
sempat mengeluarkan stok, bukan setelah.

Dua keputusan pendukung:

- **`PDO::MYSQL_ATTR_FOUND_ROWS` diaktifkan.** Default MySQL membuat
  `rowCount()` menghitung baris yang *berubah*, bukan yang *cocok*. Tanpa
  opsi ini, transisi yang syaratnya terpenuhi tapi menulis nilai yang sama
  (`PartiallyReceived -> PartiallyReceived` saat barang diterima bertahap)
  terbaca sebagai "syarat gagal" dan penerimaan yang sah ikut di-rollback.
  Kelima pemakaian `rowCount()` di codebase ini semuanya bertanya "apakah
  syarat saya cocok?", dan itu yang dijawab FOUND_ROWS.
- **Baris stok dikunci menaik berdasarkan `productId`.** Sebelumnya item
  diproses menurut urutan diketik user, sehingga dua order yang memuat produk
  sama dengan urutan berbeda bisa saling menunggu kunci milik yang lain
  (deadlock InnoDB 1213) dan muncul sebagai HTTP 500. Urutan kunci yang sama
  di setiap transaksi membuat salah satu request cukup menunggu lalu lanjut.

Sebagai pagar terakhir, `purchase_order_items` mendapat
`CHECK (received_qty <= qty)`, sejajar dengan `CHECK (quantity >= 0)` pada
`product_stock`: guard utama tetap di `WHERE`, constraint ini menjaga kalau
ada jalur lain (perbaikan data manual, migrasi) yang mencoba melewatinya.

## Consequences

**Yang didapat.** Tidak ada lagi jalur yang bisa memenuhi satu order dua kali,
menerima lebih banyak dari yang dipesan, atau membatalkan order yang stoknya
sudah bergerak - tanpa menambah lapisan, lock manual, atau kolom versi.
Mekanismenya satu bentuk yang sama di stok maupun status, jadi hanya ada satu
hal untuk dijelaskan saat defense.

**Harganya.** Interface repository jadi sedikit lebih ramai: pemanggil wajib
menyebut status asal yang ia harapkan, dan wajib memeriksa nilai balik. Itu
disengaja - tidak ada lagi varian "tulis saja tanpa syarat" yang bisa dipakai
tanpa sadar. Konsekuensinya test yang memakai repository sebagai fixture ikut
menyebut status asalnya, yang justru membuat maksud test lebih jelas.

`FOUND_ROWS` berlaku untuk seluruh koneksi, bukan per query. Saat ini tidak
ada kode yang butuh arti "baris berubah", dan semua pemakaian `rowCount()`
sudah diperiksa satu per satu; kalau nanti ada kebutuhan itu, ia harus
membandingkan nilai sebelum/sesudah sendiri, bukan mengandalkan `rowCount()`.

**Bukti.** `testConcurrentIssueOfSameSalesOrderIsRejectedForTheStaleReader`
meniru balapan lewat dua koneksi PDO terpisah: koneksi kedua membaca SO
selagi masih `Approved`, koneksi pertama menyelesaikan goods issue sampai
commit, lalu koneksi kedua mencoba mengklaim berdasarkan bacaan basi itu dan
ditolak. Test ini dipastikan benar-benar gagal ketika guard-nya dilepas -
bukan sekadar hijau. Dilengkapi test repository untuk transisi basi, untuk
penerimaan yang melebihi pesanan, dan untuk penerimaan bertahap yang statusnya
tidak berubah.
