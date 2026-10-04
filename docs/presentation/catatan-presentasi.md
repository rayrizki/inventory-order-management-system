# Catatan Presentasi — Pegangan Pribadi

Dipakai berdampingan dengan `presentasi-final-project.pptx`. Isinya tiga
bagian: **pra-presentasi** (yang harus dijalankan dan dipastikan hijau),
**naskah per slide** (apa yang diucapkan dan diklik), dan **tanya jawab**
(pertanyaan yang paling mungkin muncul beserta jawabannya).

Durasi: **10 menit presentasi + demo**, lalu **10 menit tanya jawab**.

---

# BAGIAN 1 — Pra-presentasi

## 1.1 Dijalankan H-1 (sekali, penuh)

Jalankan `docs/testing/manual-acceptance-checklist.md` dari A sampai P.
Checklist itu yang lengkap; yang di bawah ini hanya ringkasan paling kritis.

| # | Perintah / aksi | Yang diharapkan | ✓ |
|---|---|---|---|
| 1 | `docker compose down -v` lalu `docker compose up -d --build` | Kedua container naik, `db` jadi `healthy` | |
| 2 | `docker compose ps` | STATUS keduanya `Up`, db bertanda `(healthy)` | |
| 3 | Buka `http://localhost:8000/login` | Halaman login tampil | |
| 4 | `docker compose exec app vendor/bin/phpunit` | `OK (192 tests, 431 assertions)` | |
| 5 | `docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M` | `[OK] No errors` | |
| 6 | `docker compose exec app php scripts/check-low-stock.php` | 3 produk: PKN-002, OOR-001, PKK-002 | |
| 7 | Login ketiga role bergantian | Dashboard berbeda per role | |
| 8 | SonarQube jalan di `http://localhost:9000` | Dashboard project terbuka | |

> Kalau langkah 3 gagal dengan "Connection refused", tunggu 5 detik dan ulangi.
> Itu karakteristik startup yang sudah diketahui dan terdokumentasi, bukan bug —
> healthcheck `mysqladmin ping` bisa melaporkan db siap sesaat sebelum seed
> selesai dijalankan.

## 1.2 Disiapkan 15 menit sebelum sesi

- [ ] `docker compose up -d` sudah jalan, **sudah login sekali** untuk memastikan seed termuat
- [ ] **Window 1** — browser, login sebagai **Admin**
- [ ] **Window 2** — browser, login sebagai **Sales** (`sales1@iom.test`)
- [ ] **Window 3** — browser, login sebagai **Warehouse Staff** (`warehouse1@iom.test`)
- [ ] **Window 4** — tab SonarQube, dashboard project sudah terbuka
- [ ] **Terminal** — sudah di folder project, siap mengetik `phpunit`
- [ ] **Editor** — `app/Repository/MySqlProductStockRepository.php` terbuka di `decrementIfSufficient()`
- [ ] Notifikasi dimatikan, zoom browser 100%

Semua ini menghemat sekitar 2 menit yang kalau tidak disiapkan akan habis
untuk login dan mencari file.

**Akun demo** — semua password `Password123!`

| Role | Email |
|---|---|
| Admin | `admin@iom.test` |
| Sales | `sales1@iom.test`, `sales2@iom.test` |
| Warehouse Staff | `warehouse1@iom.test`, `warehouse2@iom.test` |

---

# BAGIAN 2 — Naskah per slide

## Slide 1-2 · Pembukaan — 1 menit

**Yang diucapkan** (jangan membaca slide):

> *"Inventory & Order Management System — aplikasi pencatatan stok multi-gudang
> untuk tim gudang dan sales. Masalah yang diselesaikan: memastikan angka stok
> selalu bisa dipertanggungjawabkan, termasuk saat dua proses berjalan bersamaan.*
>
> *Tiga peran dengan tanggung jawab sengaja dipisah, supaya tidak ada satu orang
> yang bisa membuat sekaligus menyetujui transaksi yang sama.*
>
> *Seluruh alur inti brief selesai dan berfungsi. Kode, arsitektur, dan keputusan
> desainnya saya kerjakan sendiri; penggunaan AI tercatat lengkap di
> `ai-usage-log.md`."*

> **Kalimat terakhir itu penting.** Panduan §5 meminta keterbukaan soal AI.
> Menyebutnya duluan jauh lebih kuat daripada menunggu ditanya.

## Slide 3 · Demo alur utama — 4 menit

Pilih **satu alur: Sales Order dari Draft sampai Fulfilled.** Alasannya ini
satu-satunya alur yang menyentuh ketiga peran sekaligus, plus stock ledger.

| Menit | Window | Aksi | Yang diucapkan |
|---|---|---|---|
| 1:00 | Sales | Buat SO, 1 item, ajukan | *"Sales hanya bisa membuat order miliknya sendiri."* |
| 1:45 | Sales | Tunjuk **tidak ada tombol Setujui** | *"Sales tidak bisa menyetujui order manapun — termasuk miliknya. Ditegakkan di server, bukan disembunyikan di UI."* |
| 2:15 | Admin | Setujui SO | Status jadi **Approved** |
| 2:45 | Admin | Buka detail produk, **sebutkan angka stoknya** | *"Stok sekarang sekian."* |
| 3:00 | Warehouse | Proses goods issue | Status jadi **Fulfilled** |
| 3:30 | Warehouse | Refresh detail produk | *"Stok berkurang — dan setiap pergerakan tercatat di stock ledger."* |
| 3:50 | Warehouse | Tunjuk baris ledger di detail SO | |
| 4:15 | Warehouse | Coba goods issue qty melebihi stok | *"Ditolak, dan stok tidak jadi negatif."* |

**Sisakan ~30 detik sebagai bantalan.** Kalau waktu mepet, **buang langkah
4:15** — itu justru bagus ditanyakan di sesi tanya jawab.

## Slide 4 · Arsitektur berlapis — 1 menit

Buka editor, telusuri satu alur dari Controller ke Repository. **Jangan membaca
kode baris demi baris** — panduan §5 eksplisit meminta sampling.

> *"Controller menangani HTTP, Service menyimpan aturan bisnis, Repository
> mengakses data. Repository selalu interface — ada dua implementasi, MySQL asli
> dan in-memory yang dipakai unit test. Itu yang membuat 131 unit test bisa jalan
> tanpa database sama sekali."*

## Slide 5 · ARCH-02 — 1,5 menit ← **PALING DINILAI, JANGAN BURU-BURU**

Buka `MySqlProductStockRepository::decrementIfSufficient()`.

> *"Ini jantungnya. Pengecekan stok dan pengurangannya digabung jadi satu
> statement SQL atomik — `UPDATE ... WHERE quantity >= qty`. Dua goods issue
> bersamaan tidak bisa dua-duanya lolos, karena InnoDB mengunci barisnya.*
>
> *Tapi saat audit, ternyata guard ini belum cukup. Dia menjaga baris stok —
> bukan order-nya. Kalau stoknya kebetulan masih cukup, dua request untuk SO yang
> sama tetap bisa lolos dua-duanya dan mengeluarkan stok dua kali.*
>
> *Perbaikannya: transisi status ikut dibuat bersyarat. Status asal ikut di
> klausa WHERE, dan goods issue mengklaim SO sebagai statement pertama transaksi.
> Alasannya saya tulis di ADR-0007."*

## Slide 6 · Docker & keamanan — 30 detik

Buka `compose.yaml` sekilas.

> *"Dua service, aplikasi dan MySQL, dengan healthcheck supaya app baru start
> setelah database benar-benar siap. Konfigurasi lewat environment variable,
> `.env` tidak pernah masuk repo. Password di-hash, semua query pakai prepared
> statement, CSRF dicek terpusat di Router."*

## Slide 7 · Bukti kualitas — 1 menit

**Terminal:** `docker compose exec app vendor/bin/phpunit`

> *"192 test — 131 unit tanpa database, 61 integration terhadap MySQL sungguhan.
> Tanpa database, integration test gagal keras; tidak ada yang di-skip diam-diam."*

**Browser:** dashboard SonarQube.

> *"Nol bug, nol vulnerability, rating A. Analisis pertama menghasilkan 240
> temuan. Semuanya saya baca satu per satu: 112 diperbaiki di kode, 126 false
> positive, 2 diterima dengan alasan tertulis."*

Lalu **buka satu false positive** dan buktikan:

> *"Ini contohnya — SonarQube bilang `$summary` di DashboardController tidak
> terpakai. Padahal dipakai 13 kali di `views/dashboard/index.php`. Analisis
> per-file tidak bisa melihat Controller dan template berbagi scope."*

## Slide 8 · Refleksi — 1 menit

> *"Kendala terbesar: memastikan stok tidak bisa oversell. Solusinya satu
> statement atomik — dan saat audit ternyata itu belum menjaga ordernya.*
>
> *Keterbatasan yang saya sadari dan catat jujur di tech-debt register: Supplier
> dan Customer masih berbagi struktur yang sama persis, dan coverage Controller
> masih rendah karena belum ada controller test.*
>
> *Prioritas berikutnya: menambah test di lapisan Controller, lalu memisahkan
> Supplier dan Customer begitu keduanya benar-benar berbeda."*

> **Menyebut keterbatasan sendiri lebih dulu selalu lebih kuat** daripada
> menunggu ditemukan asesor.

---

## Kalau waktu mepet, buang berurutan

1. Uji stok tidak cukup di demo (menit 4:15)
2. Slide 6 Docker & keamanan — ringkas jadi satu kalimat
3. Contoh false positive SonarQube — cukup sebut angkanya

**Jangan pernah dibuang: Slide 5 (ARCH-02).** Itu inti penilaian level Intermediate.

---

# BAGIAN 3 — Tanya jawab (10 menit)

## Pertanyaan yang hampir pasti muncul

**"Kenapa guard stok saja tidak cukup?"**
> Guard `decrementIfSufficient()` menjaga baris `product_stock`, bukan order-nya.
> Kalau stoknya kebetulan masih cukup — misalnya jatah order lain — dua request
> untuk SO yang sama akan lolos dua-duanya, mengeluarkan stok dua kali dan
> menulis dua baris ledger untuk satu order. Karena itu transaksi goods issue
> dibuka dengan mengklaim SO lewat `transitionStatus()` bersyarat. (ADR-0007)

**"Quality Gate SonarQube-nya merah, kenapa?"**
> Gate default mengukur **new code** dan menuntut coverage ≥80% pada baris yang
> baru berubah. Ambang itu tidak ada di Project Brief maupun di Panduan
> Presentation Final Project. Kriteria yang saya pakai: nol bug, nol
> vulnerability, rating A — dan itu terpenuhi. Semua angkanya saya tulis apa
> adanya di `docs/quality/sonarqube.md`, termasuk kenapa gate-nya merah.

**"Kenapa Supplier dan Customer tidak digabung?"**
> `purchase_orders.supplier_id` dan `sales_orders.customer_id` punya foreign key
> ke tabel yang berbeda — itu yang menjamin PO tidak mungkin menunjuk ke
> customer. Digabung jadi satu tabel dengan kolom `type`, jaminan itu hilang dan
> yang tersisa hanya disiplin kode. Lapisan tampilannya memang sudah saya bagi;
> Entity dan Repository sengaja tetap terpisah. (ADR-0008)

**"Bagaimana Anda memastikan ini benar-benar jalan dari nol?"**
> `docker compose down -v` lalu `up --build`, seed termuat otomatis, 192 test
> lulus. Justru di situ saya menemukan satu bug nyata: script cron JOB-01 masih
> memanggil parameter lama setelah refactor, dan tidak tertangkap test maupun
> PHPStan karena folder `scripts/` belum masuk cakupan analisis. Sudah diperbaiki
> dan cakupannya ditambah. Tercatat di tech-debt #20.

**"Mana bukti Sales tidak bisa approve?"**
> Dua lapis: tombolnya tidak dirender, dan kalau endpoint-nya dipanggil langsung
> tetap 403. Bisa saya tunjukkan sekarang — ketik URL-nya di address bar.

**"Penggunaan AI-nya sejauh apa?"**
> Tercatat di `ai-usage-log.md`, 47 entri, lengkap dengan tujuan, ringkasan
> prompt, output yang dipakai maupun ditolak, dan bukti verifikasinya. Ada
> beberapa entri di mana saran AI justru saya koreksi — misalnya saat perbaikan
> race condition awalnya mengandung bug `rowCount()` yang baru ketahuan karena
> saya sengaja melumpuhkan guard-nya untuk menguji apakah test-nya benar-benar
> bisa gagal.

## Kalau diminta melakukan perubahan kecil (safe-refactor)

Siapkan satu kandidat dari sekarang. Pilih Service yang sudah punya unit test,
lakukan Extract Method kecil, lalu jalankan:

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit
```

131 test, ~3 detik, tanpa database. Itu yang membuat demo refactor aman
dilakukan di depan asesor.

## Kalau aplikasi gagal jalan

Waktu tetap berjalan (panduan §5). Yang dilakukan:

1. Katakan apa yang Anda lihat dan dugaan penyebabnya — asesor menilai cara
   mendiagnosis, bukan kesempurnaan
2. `docker compose logs app` untuk menunjukkan log aslinya
3. Lanjut ke bukti statis: kode, test yang sudah pernah hijau, dokumen

---

# Tiga hal yang jangan sampai salah ucap

1. **Jangan bilang "upload image ke Docker"** — Dockerfile adalah resep yang
   dibaca `docker compose build` secara lokal. Tidak ada registry yang dituju.
2. **Jangan sebut "Model"** — di arsitektur ini: Entity → Repository → Service →
   Controller → View. "Model" terdengar seperti MVC framework, padahal framework
   justru dilarang di brief ini.
3. **Jangan mengklaim coverage 80%** — angkanya 62,5%, dan itu tidak masalah
   karena bukan kriteria yang diminta. Menyebut angka yang benar jauh lebih aman
   daripada ketahuan membesar-besarkan.
