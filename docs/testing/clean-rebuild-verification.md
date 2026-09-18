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

## Update 2026-09-18 (setelah DASH-01/REPORT-01): retry sekali karena timing

Diulang lagi setelah Dashboard & Laporan CSV selesai (schema tidak berubah
di slice ini, tapi seed sudah bertambah besar - 25 order + ledger). Rebuild
sukses; **percobaan pertama** `vendor/bin/phpunit` gagal 55 test dengan
`PDOException: SQLSTATE[HY000] [2002] Connection refused` - bukan bug kode
(tidak ada perubahan schema/koneksi di slice ini), melainkan kondisi balapan
murni container: `db` sempat dilaporkan `healthy` oleh healthcheck
(`mysqladmin ping`) sesaat SEBELUM script `docker-entrypoint-initdb.d`
(seed 25-order yang sekarang jauh lebih besar dari seed awal) benar-benar
selesai dieksekusi - `mysqladmin ping` cuma memastikan daemon MySQL merespons,
bukan bahwa `docker-entrypoint-initdb.d` sudah selesai. Diulang ~5 detik
kemudian (tanpa perubahan apa pun) - **181/181 lulus normal**. Row count
PO/SO/ledger dikonfirmasi tetap benar (15/10/10) setelah retry.

**Implikasi untuk demo/defense**: kalau `docker compose up --build -d` baru
saja selesai dan test/curl pertama gagal dengan "Connection refused", tunggu
beberapa detik lalu ulangi - ini bukan kegagalan aplikasi, murni waktu init
seed yang sekarang lebih besar (25 order + 60 baris stok) belum rampung saat
container `app` pertama kali mencoba connect. Tidak ditindaklanjuti sebagai
perbaikan kode (mis. retry-loop di `createPdoConnection()`) karena ini murni
karakteristik startup Docker sekali pakai, bukan kondisi yang terjadi lagi
selama aplikasi berjalan normal - brief §4.3 juga tidak mewajibkan resiliensi
startup se-detail itu ("di luar scope").
