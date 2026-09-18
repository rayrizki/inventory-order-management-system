# ADR-0005: Satu transaksi PDO untuk goods receipt, dipisah dari validasi murni

## Context

PO-01 mensyaratkan "Goods receipt menambah ProductStock dan menulis baris
StockLedger bertipe Receipt dalam satu transaksi" (ARCH-02: "Perubahan
ProductStock dan penulisan StockLedger terjadi dalam satu transaksi
(`beginTransaction`/`commit`/`rollBack`)"). Satu aksi goods receipt
menyentuh TIGA tabel sekaligus: `purchase_order_items.received_qty`,
`product_stock.quantity`, dan `stock_ledger` (insert baris baru) - kalau
salah satu gagal di tengah jalan tanpa transaksi, ketiga tabel itu bisa
tidak sinkron (mis. stok bertambah tapi ledger tidak tercatat, melanggar
"keputusan data" §1.3 bahwa setiap perubahan stok harus bisa ditelusuri ke
satu baris ledger).

Masalah kedua: ARCH-01 mewajibkan business logic bisa diuji tanpa koneksi
database sungguhan ("Test business logic ... dapat dijalankan tanpa koneksi
database sungguhan"). Tapi validasi goods receipt (status PO harus
Ordered/PartiallyReceived, qty diterima tidak boleh melebihi sisa per item)
secara alami "menempel" di method yang sama dengan penulisan transaksional -
kalau ditulis sebagai satu method besar, method itu jadi tidak bisa
di-unit-test tanpa PDO nyata.

## Decision

`GoodsReceiptService::receive()` dipecah jadi dua bagian dengan tanggung
jawab berbeda:

1. **`computeReceiptPlan(PurchaseOrder $po, array $receivedQtyByItemId): array`**
   - Method publik, murni logic (tidak menyentuh PDO/repository tulis sama
     sekali). Menerima entity `PurchaseOrder` yang sudah di-load (termasuk
     item-nya) dan input qty mentah, mengembalikan "rencana" tervalidasi
     (item + qty yang valid untuk diproses) atau melempar
     `ConflictException` (status PO salah) / `ValidationException` (qty
     melebihi sisa, item tidak ditemukan, atau semua baris kosong).
   - Diuji lewat `tests/Unit/Service/GoodsReceiptServiceTest.php` dengan
     entity `PurchaseOrder` buatan tangan - tidak butuh database sama sekali.

2. **`receive(int $purchaseOrderId, array $receivedQtyByItemId, int $performedBy): PurchaseOrder`**
   - Memanggil `computeReceiptPlan()` dulu (validasi selesai *sebelum*
     transaksi dibuka - kalau invalid, tidak ada `beginTransaction()` yang
     perlu di-rollback sama sekali).
   - Baru setelah valid: `$this->pdo->beginTransaction()`, lalu untuk tiap
     item dalam rencana - `incrementItemReceivedQty()`,
     `ProductStockRepositoryInterface::incrementQuantity()` (upsert atomik
     `INSERT ... ON DUPLICATE KEY UPDATE`), `StockLedgerRepositoryInterface::record()`
     - baca ulang status PO (apakah semua item sudah `remainingQty() === 0`)
     untuk menentukan status baru (`Received`/`PartiallyReceived`), lalu
     `commit()`. Exception apa pun di tengah jalan -> `rollBack()` lalu
     dilempar ulang.
   - Diuji lewat `tests/Integration/Service/GoodsReceiptServiceTest.php`
     dengan MySQL sungguhan di Docker (TEST-02) - membuktikan tiga tabel
     benar-benar konsisten setelah satu aksi, termasuk skenario penerimaan
     ditolak (qty melebihi sisa) yang **tidak menulis apa pun** ke ketiga
     tabel itu (bukan cuma ditolak, tapi juga tidak meninggalkan efek
     samping parsial).

`GoodsReceiptService` menerima `PDO $pdo` langsung lewat constructor (bukan
lewat `PurchaseOrderRepositoryInterface`) - satu-satunya Service di
codebase ini yang melakukannya. Alasannya: transaksi di sini melintasi TIGA
repository (`PurchaseOrderRepositoryInterface`, `ProductStockRepositoryInterface`,
`StockLedgerRepositoryInterface`) yang masing-masing dipakai lewat interface-nya
sendiri (tetap menghormati Dependency Inversion di boundary repository,
ARCH-01) - PDO di sini murni jadi *unit of work boundary* untuk
mengoordinasikan transaksi lintas repository, bukan dipakai untuk menulis
query mentah menggantikan repository manapun.

## Consequences

**Nilai tambah nyata:**
- Validasi (bagian yang paling sering berubah/di-test) bisa di-unit-test
  cepat tanpa Docker; hanya orkestrasi transaksi yang butuh integration test
  - memisahkan biaya testing sesuai risikonya masing-masing.
- Rollback otomatis mencegah kelas bug "stok bertambah tapi ledger kosong"
  yang akan sangat sulit dilacak kalau baru ketahuan belakangan (data
  historis tidak konsisten, tidak ada cara memperbaikinya tanpa audit
  manual).
- Pola ini jadi template siap pakai untuk SO-01 (`GoodsIssueService`)
  nanti - bedanya cuma arah quantity (kurang, bukan tambah) dan kebutuhan
  baru: mencegah oversell lewat `decrementIfSufficient()` (`UPDATE ...
  WHERE quantity >= qty`, mengecek jumlah baris yang berubah) alih-alih
  `incrementQuantity()` yang tidak perlu guard karena penambahan stok tidak
  punya risiko jadi negatif.

**Trade-off yang disadari:**
- `GoodsReceiptService` menerima `PDO` langsung - sedikit menyimpang dari
  pola constructor Service lain di codebase ini yang cuma menerima
  RepositoryInterface. Ini disengaja (dijelaskan di atas) dan dicatat di
  sini supaya tidak terlihat seperti kelalaian saat kode dibaca ulang.
- Constructor `GoodsReceiptService` jadi punya 4 dependency (3 interface +
  1 PDO) - lebih banyak dari Service manapun sebelumnya. Trade-off yang
  diterima karena mencerminkan kompleksitas nyata aksi ini (menyentuh 3
  tabel sekaligus), bukan tanda kelas melanggar SRP - tanggung jawabnya
  tetap satu: "mengeksekusi satu goods receipt secara atomik".
