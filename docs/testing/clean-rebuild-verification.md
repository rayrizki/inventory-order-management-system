# Verifikasi Clean Rebuild (§5.1 "Uji sebelum submission", Critical Failure #1)

Brief §5.1 mewajibkan aplikasi bisa dibangun dari kondisi bersih lewat
`docker compose up --build`, dan §8.2 menyebut "Aplikasi atau database tidak
dapat dijalankan lewat Docker setelah prosedur setup yang wajar" sebagai
Critical Failure. Setelah SO-01 menambah dua tabel baru (`sales_orders`,
`sales_order_items`) ke schema, verifikasi ini dijalankan ulang dari nol -
bukan cuma diasumsikan masih benar dari verifikasi PO-01 sebelumnya.

## Prosedur

```bash
docker compose down -v   # hapus container + volume database sepenuhnya
docker compose up --build -d
```

## Hasil (2026-09-18)

- Build image sukses (cache composer install terpakai, `COPY . .` mengambil
  kode terbaru termasuk seluruh modul SO-01).
- Volume `db-data` dan network dibuat ulang dari nol; container `db` mencapai
  status `healthy` sebelum `app` start (`depends_on: condition:
  service_healthy` di `compose.yaml` bekerja sesuai rancangan).
- Schema + seed (`database/schema-and-seed.sql`, dijalankan otomatis oleh
  image `mysql:8.0` lewat `docker-entrypoint-initdb.d`) berhasil membuat
  **12 tabel** dari kosong: `users`, `categories`, `warehouses`, `products`,
  `product_stock`, `suppliers`, `customers`, `purchase_orders`,
  `purchase_order_items`, `sales_orders`, `sales_order_items`,
  `stock_ledger`.
- Jumlah baris seed sesuai rancangan: `users`=5, `categories`=15,
  `warehouses`=2, `products`=30, `product_stock`=60. `suppliers`/`customers`
  masih 0 baris - **bukan bug**, seed memang belum pernah mengisi kedua
  tabel ini (lihat tech-debt #6, sedang dikerjakan bersamaan dengan seed 25
  order gabungan yang butuh referensi supplier/customer).
- `GET /login` -> 200 tanpa error.
- Login end-to-end untuk ketiga role (`admin@iom.test`, `sales1@iom.test`,
  `warehouse1@iom.test`) via curl (termasuk ekstraksi token CSRF dari halaman
  login, bukan tanpa token) -> `GET /dashboard` setelah login = 200 untuk
  ketiganya.
- Full test suite: **156/156 lulus** (unit + integration, integration
  menyentuh MySQL container yang baru dibuat, bukan container lama).
- PHPStan level 6: **0 error**.

## Kesimpulan

Aplikasi dan database terbukti bisa dijalankan dari kondisi bersih setelah
penambahan modul SO-01 - tidak ada regresi pada prosedur setup Docker.
Verifikasi ini sebaiknya diulang lagi sekali lagi tepat sebelum release/tag
final (§10 checklist: "Aplikasi dan database dapat dijalankan lewat Docker
dari folder bersih"), khususnya setelah seed 25 order gabungan (tech-debt
#6) ditambahkan.
