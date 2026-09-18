# Verifikasi Seed 25 Order Gabungan (§7.1, tech-debt #6)

## Perintah

```
docker compose down -v
docker compose up --build -d
docker compose exec app php scripts/check-low-stock.php
```

## Hasil (2026-09-18)

Row count setelah clean rebuild dari volume kosong:

| Tabel | Jumlah |
|---|---|
| `suppliers` | 4 |
| `customers` | 4 |
| `purchase_orders` | 15 |
| `purchase_order_items` | 15 |
| `sales_orders` | 10 |
| `sales_order_items` | 10 |
| `stock_ledger` | 10 (7 dari PO Received/PartiallyReceived + 3 dari SO Fulfilled) |

Total order gabungan: **25** (15 PO + 10 SO), memenuhi §7.1.

Distribusi status PO: `Draft`=3, `Ordered`=4, `PartiallyReceived`=3,
`Received`=4, `Cancelled`=1.

Distribusi status SO: `Draft`=2, `PendingApproval`=2, `Approved`=2,
`Fulfilled`=3, `Cancelled`=1.

Kedua distribusi mencakup `PendingApproval` dan `Cancelled` sesuai
permintaan eksplisit §7.1 ("termasuk contoh yang PendingApproval dan
Cancelled") - bahkan status `Cancelled` ada di kedua jenis order (PO dan
SO), bukan cuma salah satu.

## Konsistensi ledger-stok (ARCH-02)

Karena status `Received`/`PartiallyReceived` (PO) dan `Fulfilled` (SO) di
aplikasi nyata SELALU dihasilkan lewat transaksi yang menulis
`stock_ledger` sekaligus menyesuaikan `product_stock`, seed data ini
menulis pasangan yang sama secara eksplisit (bukan cuma baris status tanpa
efek), supaya invarian "StockLedger tidak pernah lepas dari ProductStock"
(§1.3, kriteria Critical Failure §8.2) tetap benar walau ditulis lewat SQL
langsung saat init, bukan lewat `GoodsReceiptService`/`GoodsIssueService`.

Efek samping yang diverifikasi lewat `scripts/check-low-stock.php`:

| SKU | Sebelum order | Sesudah order | Reorder point | Status |
|---|---|---|---|---|
| ELK-002 | 8 | 33 | 15 | LOW -> NORMAL (PO Received x2) |
| OTM-002 | 2 | 12 | 5 | LOW -> NORMAL (PO Received) |
| MHB-002 | 3 | 15 | 8 | LOW -> NORMAL (PO Received) |
| PKK-001 | 1 | 6 | 5 | LOW -> NORMAL (PO PartiallyReceived) |
| FRN-001 | 5 | 9 | 8 | LOW -> NORMAL (PO PartiallyReceived) |
| BAP-002 | 0 | 15 | 8 | LOW -> NORMAL (PO Received) |
| PKN-002 | 4 | 4 | 10 | LOW -> **tetap LOW** (tidak disentuh order apa pun) |
| OOR-001 | 23 | 8 | 10 | NORMAL -> **LOW baru** (SO Fulfilled menghabiskan stok Jakarta) |
| PKK-002 | 23 | 8 | 12 | NORMAL -> **LOW baru** (SO Fulfilled menghabiskan stok Jakarta) |

Hasil akhir: **3 produk di bawah reorder point** (turun dari 7 sebelum seed
order ditambahkan), dikonfirmasi lewat eksekusi nyata `check-low-stock.php`
di atas - bukan cuma dihitung manual. Ini perubahan yang disengaja dan
realistis (bukan regresi): PO benar-benar menyelesaikan sebagian masalah
stok rendah yang ada sebelumnya, dan SO benar-benar menghabiskan sebagian
stok yang tadinya normal - persis siklus yang digambarkan brief §1.1 poin 5
("Saat stok rendah, Admin/Warehouse Staff membuat PO... ketika barang
datang, stok bertambah").

## Verifikasi lain

- Full test suite tetap **156/156 lulus** setelah seed baru (integration
  test membuat dan menghapus baris sendiri, terisolasi dari data seed).
- PHPStan level 6 tetap 0 error (murni perubahan data, tidak menyentuh
  kode aplikasi).
- Pagination `/purchase-orders` (Admin): menampilkan "15 purchase order",
  halaman 1 berisi 10 baris, halaman 2 berisi 5 baris sisanya - FIND-01
  pagination sekarang teruji dengan data order nyata, bukan cuma Produk.
- Pagination `/sales-orders`: menampilkan 10 SO dalam satu halaman (tidak
  ada halaman kedua, sesuai `ceil(10/10) = 1`) - cukup untuk membuktikan
  logic pagination bekerja, meski tidak menghasilkan halaman kedua untuk
  SO secara spesifik (PO sudah membuktikan kasus multi-halaman).
- Login 3 role setelah clean rebuild tetap berhasil (lihat
  `docs/testing/clean-rebuild-verification.md`).

## Known limitations

- `sales_orders.created_by`/`approved_by` dan `purchase_orders.created_by`
  pada seed ini memakai kombinasi user yang masuk akal (Sales untuk SO,
  Admin/Warehouse Staff untuk PO) tapi TIDAK memvariasikan seluruh 5 akun
  demo secara merata - `sales1@iom.test` dan `warehouse1@iom.test` dipakai
  lebih sering daripada `sales2@iom.test`/`warehouse2@iom.test`. Tidak
  mempengaruhi FIND-01/DASH-01 (agregasi tetap benar), dicatat sebagai
  catatan kecil bukan gap fungsional.
