# ADR-0006: Pencegahan oversell lewat UPDATE atomik, bukan cek-lalu-tulis

## Context

SO-01 membalikkan arah pergerakan stok dari PO-01: goods issue MENGURANGI
`product_stock.quantity`, bukan menambah. Berbeda dari goods receipt
(penambahan tidak pernah punya risiko jadi negatif, jadi `incrementQuantity()`
cukup dengan upsert polos), pengurangan stok punya risiko nyata: dua goods
issue untuk kombinasi produk+gudang yang sama, dijalankan hampir bersamaan
oleh dua request PHP-FPM/worker yang berbeda, bisa lolos berdua kalau
pengecekan "stok cukup?" dan penulisan "kurangi stok" adalah dua langkah
terpisah (baca quantity, bandingkan di PHP, baru UPDATE) - keduanya bisa
membaca quantity yang sama SEBELUM salah satu commit, sehingga total yang
dikurangi melebihi stok yang benar-benar ada (oversell). Ini persis skenario
yang diminta dibuktikan brief bagian 6 (TEST-02): "goods issue kedua ditolak
ketika stok sudah habis oleh goods issue pertama".

ARCH-02 mensyaratkan pencegahan ini menyatu dengan transaksi yang sama yang
menulis StockLedger - tidak boleh ada jendela waktu antara "cek stok" dan
"tulis pengurangan" yang bisa disisipi request lain.

## Decision

Pengecekan DAN pengurangan digabung jadi **satu statement SQL atomik**:

```sql
UPDATE product_stock SET quantity = quantity - :qty
WHERE product_id = :product_id AND warehouse_id = :warehouse_id AND quantity >= :qty_check
```

(`:qty` dan `:qty_check` adalah placeholder terpisah untuk nilai yang sama -
native prepared statement PDO, `PDO::ATTR_EMULATE_PREPARES => false`, tidak
mengizinkan satu named placeholder dipakai di lebih dari satu posisi.)

Diekspos lewat `ProductStockRepositoryInterface::decrementIfSufficient(int
$productId, int $warehouseId, int $qty): bool` - mengembalikan `true` kalau
ADA baris yang berubah (`rowCount() > 0`, berarti stok cukup) atau `false`
kalau tidak (stok tidak cukup, ATAU baris belum pernah ada sama sekali - qty
yang diminta pasti > 0 sehingga baris dengan quantity 0/tidak ada tidak akan
pernah mencukupi kondisi `quantity >= qty`).

**Kenapa ini mencegah race condition tanpa lock eksplisit tambahan:**
`UPDATE` di MySQL/InnoDB mengambil row-level exclusive lock atas baris yang
cocok dengan `WHERE` SEBELUM mengevaluasi apakah baris itu benar-benar
di-update - dua transaksi konkuren yang menyasar baris `product_stock` yang
sama (product_id+warehouse_id sama, primary/unique key kombinasi itu) tidak
bisa dua-duanya memegang lock secara bersamaan. Transaksi kedua menunggu
transaksi pertama commit/rollback, baru mengevaluasi `WHERE ... quantity >=
:qty_check` terhadap quantity yang SUDAH diperbarui oleh transaksi pertama -
kalau ternyata sudah tidak cukup, `rowCount()` jadi 0, `false` dikembalikan,
tanpa pernah menuliskan quantity negatif.

`GoodsIssueService::issue()` memanggil `decrementIfSufficient()` untuk
SETIAP item dalam satu transaksi PDO (`beginTransaction`/`commit`/`rollBack`,
mengikuti pola ADR-0005). Kalau satu saja item mengembalikan `false`,
`ConflictException` dilempar dan SELURUH transaksi di-rollback - termasuk
item lain yang sebenarnya stoknya cukup (all-or-nothing, lihat penjelasan di
`GoodsIssueService`'s class docblock: `sales_order_items` tidak punya kolom
penerimaan sebagian seperti PO, jadi tidak ada goods issue separuh jalan).

Method ini SENGAJA dibuat terpisah dari `incrementQuantity()` (bukan
memanggilnya dengan delta negatif) - guard "tidak boleh oversell" jadi
eksplisit di level nama method dan level query, bukan mengandalkan CHECK
constraint database gagal secara diam-diam sebagai satu-satunya pertahanan,
dan bukan risiko lupa menambahkan guard kalau suatu saat
`incrementQuantity()` dipakai ulang di tempat lain untuk kasus pengurangan.

## Consequences

**Nilai tambah nyata:**
- Race condition oversell dicegah oleh mekanisme locking InnoDB bawaan,
  bukan oleh `SELECT ... FOR UPDATE` eksplisit terpisah yang butuh dua
  round-trip query dan lebih mudah salah urutan penguncian. Satu statement,
  satu round-trip.
- Dibuktikan dengan integration test MySQL sungguhan
  (`tests/Integration/Service/GoodsIssueServiceTest::testSecondGoodsIssueRejectedWhenStockAlreadyDepletedByFirst`)
  yang secara eksplisit menjalankan dua goods issue berurutan atas SO
  terpisah yang memperebutkan stok sama, dan diverifikasi ulang lewat
  Playwright E2E langsung di browser (skenario yang sama, lewat UI
  sungguhan) - bukan cuma diklaim benar dari membaca kode.
- Simetris dengan `incrementQuantity()` (ADR-0005) - keduanya method
  repository yang mengekspos SATU operasi atomik yang sesuai dengan arah
  pergerakan stoknya masing-masing, bukan satu method generik "ubah
  quantity" yang menyembunyikan perbedaan risiko antara menambah dan
  mengurangi.

**Trade-off yang disadari:**
- Kegagalan `decrementIfSufficient()` tidak membedakan "stok tidak cukup"
  dari "baris product_stock belum pernah ada sama sekali" - keduanya
  mengembalikan `false` yang sama. Diterima karena secara bisnis kedua
  kondisi itu punya akibat yang identik ("tidak ada cukup stok untuk
  dikurangi") dan pesan error ke user (`ConflictException`) sudah cukup
  jelas tanpa perlu membedakan penyebabnya.
- Dua placeholder (`:qty` dan `:qty_check`) untuk nilai yang sama sedikit
  mengurangi keterbacaan query dibanding SQL biasa yang mengizinkan reuse
  placeholder - konsekuensi langsung dari `PDO::ATTR_EMULATE_PREPARES =>
  false` (native prepares) yang sudah jadi keputusan arsitektur sejak awal
  proyek demi keamanan (mencegah SQL injection lewat query yang benar-benar
  di-parse server MySQL, bukan disimulasikan client-side).
