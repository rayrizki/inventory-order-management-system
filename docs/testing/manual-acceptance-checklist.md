# Skenario Uji Manual Sebelum Submission

Checklist ini dijalankan **sekali penuh dari folder bersih** sebelum tag
release dibuat (§5.1 "Uji sebelum submission" dan checklist §10). Urutannya
sengaja mengikuti agenda demo §8.1 supaya sekalian jadi latihan demo.

Isi kolom **Hasil** saat menjalankan. Kalau ada yang tidak sesuai, catat di
bagian "Temuan" di bawah - jangan diperbaiki diam-diam tanpa dicatat.

**Akun demo** (semua password: `Password123!`)

| Role | Email |
|---|---|
| Admin | `admin@iom.test` |
| Sales | `sales1@iom.test`, `sales2@iom.test` |
| Warehouse Staff | `warehouse1@iom.test`, `warehouse2@iom.test` |

**Angka acuan dari seed** (kalau berbeda, berarti data sudah tercemar -
ulangi dari langkah A1): 30 produk, 25 order (15 PO + 10 SO), 2 gudang,
3 produk di bawah reorder point (`OOR-001`, `PKK-002`, `PKN-002`).

---

## A. Docker dari kondisi bersih

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| A1 | `docker compose down -v` lalu `docker compose up --build -d` | Kedua container naik, `db` jadi `healthy` | |
| A2 | Tunggu ~15 detik, buka `http://localhost:8000/login` | Halaman login tampil | |
| A3 | Kalau A2 gagal dengan "Connection refused", tunggu 5 detik dan ulangi | Berhasil di percobaan kedua | |

> Catatan: A3 adalah karakteristik startup yang sudah diketahui dan
> terdokumentasi (`clean-rebuild-verification.md`) - healthcheck `mysqladmin
> ping` bisa melaporkan `db` siap sesaat sebelum seed selesai dijalankan.
> Kalau terjadi saat demo, jelaskan itu, jangan panik.

---

## B. Login, session, logout (AUTH-01, AUTH-02)

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| B1 | Login `admin@iom.test` | Masuk ke dashboard Admin | |
| B2 | Logout, login `sales1@iom.test` | Dashboard Sales (isinya beda dari Admin) | |
| B3 | Logout, login `warehouse1@iom.test` | Dashboard Warehouse Staff | |
| B4 | Login dengan email yang tidak ada | Pesan **"Email atau password salah"** - tidak menyebut bagian mana yang salah | |
| B5 | Login dengan email benar, password salah | Pesan yang **sama persis** dengan B4 | |
| B6 | Logout, lalu buka `http://localhost:8000/products` langsung | Dialihkan ke `/login` | |
| B7 | Login, lalu tekan tombol Back browser ke halaman login | Tidak bisa masuk tanpa login ulang | |

### B8. Pencabutan akses langsung berlaku

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| B8a | Login sebagai `sales1@iom.test` di satu browser, biarkan terbuka | Bisa membuka `/sales-orders` | |
| B8b | Di browser lain, login Admin → menu User → nonaktifkan `Sales Satu` | Status jadi Nonaktif | |
| B8c | Kembali ke browser pertama, muat ulang `/sales-orders` | **Dialihkan ke `/login`** - tanpa perlu logout dulu | |
| B8d | Aktifkan kembali `Sales Satu` lewat Admin | Bisa login lagi | |

### B9. Atribut cookie session (§4.2)

Buka DevTools → Application → Cookies → `PHPSESSID`.

| # | Yang diperiksa | Yang diharapkan | Hasil |
|---|---|---|---|
| B9 | Kolom HttpOnly dan SameSite | `HttpOnly` tercentang, `SameSite` = `Lax` | |

---

## C. Segregation of duties (§1.2) - diuji di server, bukan cuma UI

Untuk setiap baris: login sebagai role tersebut, lalu **ketik URL-nya
langsung di address bar** (jangan lewat menu - tujuannya membuktikan server
yang menolak, bukan menu yang disembunyikan).

| # | Role | URL | Yang diharapkan | Hasil |
|---|---|---|---|---|
| C1 | Sales | `/users` | 403 | |
| C2 | Warehouse Staff | `/users` | 403 | |
| C3 | Sales | `/categories` | 403 | |
| C4 | Sales | `/purchase-orders` | 403 | |
| C5 | Warehouse Staff | `/sales-orders/create` | 403 | |
| C6 | Sales | `/products` | **200** (Sales boleh lihat katalog) | |
| C7 | Warehouse Staff | `/products` | **200** (boleh lihat produk & stok) | |
| C8 | Sales | `/products/create` | 403 | |

### C9. Sales tidak bisa approve - termasuk order miliknya sendiri

Ini yang paling sering diuji penguji.

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| C9a | Login Sales, buat SO baru, ajukan (jadi PendingApproval) | Status PendingApproval | |
| C9b | Di halaman detail SO itu, cari tombol Setujui | **Tidak ada** tombolnya | |
| C9c | Buka Network tab, salin request approve dari sesi Admin, atau kirim POST manual ke `/sales-orders/{id}/approve` sebagai Sales | **403** | |

### C10. Sales tidak bisa melihat order Sales lain

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| C10a | Login `sales1`, catat id salah satu SO miliknya | | |
| C10b | Login `sales2`, buka `/sales-orders/{id-milik-sales1}` | **403** | |
| C10c | Daftar `/sales-orders` sebagai `sales2` | Hanya order milik `sales2` | |

---

## D. Master data & katalog (PRD-01, WH-01)

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| D1 | Admin → Produk | 30 produk, paginasi 10 per halaman (3 halaman) | |
| D2 | Tambah produk dengan SKU yang sudah ada | Ditolak, pesan SKU sudah dipakai, data tidak tersimpan | |
| D3 | Tambah produk dengan reorder point `-5` | Ditolak sebelum submit (pesan muncul di bawah field) | |
| D4 | Tambah produk, upload file **.txt** yang di-rename jadi `.jpg` | Ditolak - tipe file divalidasi dari isinya, bukan ekstensi | |
| D5 | Tambah produk, upload gambar >2MB | Ditolak dengan pesan ukuran | |
| D6 | Tambah produk dengan gambar valid | Tersimpan; cek nama file di `public/uploads/products/` - **acak**, bukan nama asli | |
| D7 | Buka detail produk apa pun | Tampil total stok **dan** rincian per gudang (2 gudang) | |
| D8 | Nonaktifkan sebuah produk | Jadi Nonaktif, **tidak hilang** dari daftar | |

---

## E. Search, filter, sort, pagination (FIND-01)

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| E1 | Produk → cari `Kabel` | Hanya produk yang namanya cocok | |
| E2 | Produk → filter Status Stok = **Stok Rendah** | Tepat **3 produk**: `OOR-001`, `PKK-002`, `PKN-002`, masing-masing ber-badge merah | |
| E3 | Dari E2, pindah ke halaman 2 (kalau ada) lalu kembali | Filter **tetap aktif**, tidak reset | |
| E4 | Produk → filter kategori + pencarian sekaligus | Keduanya berlaku bersamaan | |
| E5 | Produk → klik header kolom Nama dua kali | Urutan naik lalu turun, ikon panah berubah | |
| E6 | PO → ketik `PO-000001` di pencarian (persis seperti yang tampil di tabel) | **Ketemu** | |
| E7 | PO → ketik `000001` | Ketemu juga | |
| E8 | SO → ketik `SO-000001` | Ketemu | |
| E9 | PO → cari nama supplier, mis. `Sumber` | Muncul beberapa PO dari supplier itu | |
| E10 | PO → filter status `Draft`, lalu pindah halaman | Filter bertahan | |

---

## F. Purchase Order & goods receipt (PO-01)

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| F1 | Admin → buat PO baru, supplier + gudang + **2 item** (pakai "+ Tambah Item") | Tersimpan sebagai **Draft** | |
| F2 | Simpan PO tanpa satu pun item | Ditolak, isian yang sudah diketik **tidak hilang** | |
| F3 | Ajukan ke Supplier | Status jadi **Ordered** | |
| F4 | Catat stok awal salah satu produk di PO itu (lewat halaman detail produk) | | |
| F5 | Goods receipt **sebagian** (mis. 3 dari 10) | Status jadi **PartiallyReceived**, sisa qty tercatat | |
| F6 | Cek stok produk tadi | Bertambah **tepat 3** | |
| F7 | Goods receipt **sisanya** | Status jadi **Received** | |
| F8 | Coba terima **lebih** dari sisa qty | **Ditolak** dengan pesan, stok tidak berubah | |
| F9 | Lihat riwayat penerimaan di halaman detail PO | Ada baris ledger untuk **setiap** aksi penerimaan | |
| F10 | Login Warehouse Staff → buka detail PO berstatus Draft | Tombol **"Ajukan ke Supplier"** dan **"Batalkan PO"** tidak ada | |
| F11 | Sebagai Warehouse Staff, POST langsung ke `/purchase-orders/{id}/mark-ordered` | **403** | |

---

## G. Sales Order & goods issue (SO-01)

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| G1 | Sales → buat SO Draft, lalu ajukan | Status **PendingApproval** | |
| G2 | Admin → setujui SO itu | Status **Approved** | |
| G3 | Catat stok produk di SO itu | | |
| G4 | Warehouse Staff → proses goods issue | Status **Fulfilled** | |
| G5 | Cek stok | **Berkurang** sesuai qty SO | |
| G6 | Cek riwayat di detail SO | Ada baris ledger bertipe **Issue** | |
| G7 | Buat SO dengan qty **melebihi stok tersedia**, setujui, lalu goods issue | **Ditolak**, stok **tidak jadi negatif**, status tetap Approved | |
| G8 | Buat SO 2 item, item kedua stoknya tidak cukup, lalu goods issue | **Seluruhnya** dibatalkan - item pertama pun tidak berkurang | |
| G9 | Batalkan SO yang masih Draft | Status **Cancelled** | |
| G10 | Coba batalkan SO yang sudah Fulfilled | **Ditolak** | |

---

## H. Concurrency / ARCH-02 (paling ditekankan brief)

Skenario terkontrol, tidak perlu thread sungguhan.

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| H1 | Siapkan satu SO Approved. Buka halaman detail-nya di **dua tab** browser | Kedua tab menampilkan tombol goods issue | |
| H2 | Proses goods issue di tab pertama | Berhasil, status Fulfilled | |
| H3 | **Tanpa me-refresh**, tekan goods issue di tab kedua | **Ditolak** dengan pesan status berubah | |
| H4 | Cek stok produknya | Berkurang **hanya sekali** | |
| H5 | Cek riwayat ledger SO itu | **Hanya satu** baris Issue | |
| H6 | Ulangi pola yang sama untuk goods receipt PO (dua tab, terima qty yang sama) | Yang kedua ditolak, `received_qty` tidak melebihi qty pesanan | |

> Kalau ditanya "kenapa guard stok saja tidak cukup?" - jawabannya: guard
> `decrementIfSufficient()` menjaga **baris stok**, bukan order-nya. Di H3
> stoknya bisa saja masih cukup (jatah order lain), jadi tanpa klaim status
> bersyarat, goods issue kedua akan lolos dan mengeluarkan stok dua kali
> untuk satu order. Lihat ADR-0007.

---

## I. Dashboard (DASH-01)

| # | Role | Yang diharapkan | Hasil |
|---|---|---|---|
| I1 | Admin | Nilai inventori, jumlah produk di bawah reorder point, jumlah PO+SO per status | |
| I2 | Sales | Ringkasan **SO miliknya sendiri** per status (bukan semua SO) | |
| I3 | Warehouse Staff | Antrean goods receipt/issue + produk low-stock | |
| I4 | Bandingkan angka "produk di bawah reorder point" di dashboard Admin dengan hasil E2 | **Sama** (3) | |
| I5 | Proses satu goods issue, lalu muat ulang dashboard | Angkanya **berubah** - bukti dihitung dari query, bukan statis | |

---

## J. Laporan CSV (REPORT-01)

| # | Role | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|---|
| J1 | Admin | Buka `/reports` | Dua kartu unduhan tersedia | |
| J2 | Admin | Unduh CSV pergerakan stok | File terunduh, isinya baris ledger | |
| J3 | Admin | Ubah rentang tanggal, unduh lagi | Jumlah baris **berbeda** dari J2 | |
| J4 | Sales | Buka `/reports` | Hanya kartu **laporan order** | |
| J5 | Sales | Unduh CSV order | Isinya **hanya SO miliknya**, tidak ada baris Purchase Order | |
| J6 | Sales | Ketik URL `/reports/stock-ledger.csv` langsung | **403** | |
| J7 | Warehouse Staff | Buka `/reports` | Hanya kartu **laporan stok** | |
| J8 | Warehouse Staff | Ketik URL `/reports/orders.csv` langsung | **403** | |

### J9. CSV injection

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| J9a | Admin → ubah nama satu produk jadi `=1+1` | Tersimpan | |
| J9b | Proses satu goods receipt/issue untuk produk itu supaya masuk ledger | | |
| J9c | Unduh CSV pergerakan stok, buka di Excel/LibreOffice | Sel tampil sebagai **teks** `=1+1`, **bukan** hasil hitungan `2` | |
| J9d | Kembalikan nama produk ke semula | | |

---

## K. API JSON (API-01)

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| K1 | Login, buka `/api/products/ELK-001/availability` | JSON stok per gudang, status 200 | |
| K2 | Logout, buka URL yang sama | **401 JSON**, bukan halaman HTML login | |
| K3 | Login, buka `/api/products/SKU-TIDAK-ADA/availability` | **404 JSON** | |
| K4 | Cek header response di DevTools | `Content-Type: application/json` | |

---

## L. Validasi (VAL-01)

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| L1 | Form produk → kosongkan Nama, submit | Ditolak di browser, pesan muncul di bawah field | |
| L2 | Form produk → harga beli `-100`, submit | Ditolak **sebelum** request terkirim (cek Network tab: tidak ada POST) | |
| L3 | Form produk → harga beli `abc` | Ditolak | |
| L4 | Matikan JavaScript di browser, ulangi L2 | Tetap ditolak, kali ini oleh server | |
| L5 | Form PO → qty `0` | Ditolak | |
| L6 | Setelah validasi gagal, periksa field lain | Isian yang sudah diketik **masih ada** | |

---

## M. Error handling (ERR-01)

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| M1 | Buka `/halaman-tidak-ada` | 404 | |
| M2 | Buka `/products/999999` | 404 | |
| M3 | Sales buka `/users` | 403 | |
| M4 | `docker compose stop db`, lalu buka halaman apa pun | **"500 Internal Server Error"** polos - **tidak ada** stack trace, path file, atau pesan SQL | |
| M5 | `docker compose logs app` | Detail error lengkap **ada di log** | |
| M6 | `docker compose start db`, tunggu healthy, muat ulang | Normal kembali | |

---

## N. Responsive & usability (UI-01)

DevTools → Toggle device toolbar → atur lebar **360px**.

| # | Halaman | Yang diharapkan | Hasil |
|---|---|---|---|
| N1 | Login | Tidak terpotong, tombol terjangkau | |
| N2 | Dashboard | Sidebar jadi off-canvas, ada tombol toggle | |
| N3 | Daftar Produk | Tabel bisa digeser horizontal **di dalam kontainernya** - halaman tidak ikut geser | |
| N4 | Detail PO | Terbaca, tidak tumpang tindih | |
| N5 | Form produk | Field menumpuk satu kolom, label terlihat | |
| N6 | Tekan Tab di form mana pun | **Focus ring terlihat** di setiap field | |
| N7 | Ulangi N1-N5 di lebar desktop | Normal | |

---

## O. Script terjadwal (JOB-01)

| # | Langkah | Yang diharapkan | Hasil |
|---|---|---|---|
| O1 | `docker compose exec app php scripts/check-low-stock.php` | Ringkasan produk di bawah reorder point | |
| O2 | Bandingkan dengan hasil E2 | **Sama** (3 produk) | |

---

## P. Test & static analysis

| # | Perintah | Yang diharapkan | Hasil |
|---|---|---|---|
| P1 | `docker compose exec app vendor/bin/phpunit` | **OK (192 tests)**, tidak ada skip | |
| P2 | `docker compose exec app vendor/bin/phpunit --testsuite Unit` | 131 test, cepat (~3 detik) | |
| P3 | `docker compose exec app vendor/bin/phpunit --testsuite Integration` | 61 test | |
| P4 | `docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M` | `[OK] No errors` | |

---

## Q. Kesiapan defense (§8.2 - auto-fail kalau tidak bisa menjelaskan)

Bukan uji aplikasi, tapi uji kesiapan Anda. Jawab **tanpa membuka dokumen**:

| # | Pertanyaan | Bisa dijawab? |
|---|---|---|
| Q1 | Kenapa Repository dibuat interface, bukan langsung PDO di Controller? (ADR-0001) | |
| Q2 | Bagaimana oversell dicegah, dan kenapa cek-lalu-tulis tidak aman? (ADR-0006) | |
| Q3 | Kenapa guard stok saja tidak cukup - apa yang masih bisa bocor? (ADR-0007) | |
| Q4 | Kenapa Supplier dan Customer tidak digabung jadi satu tabel? (ADR-0008) | |
| Q5 | Tunjukkan satu kelas di class diagram, lalu telusuri ke file kodenya | |
| Q6 | Sebutkan satu refactor yang Anda lakukan beserta smell-nya | |

---

## Temuan

Catat di sini apa pun yang tidak sesuai harapan. Kolom "Tindakan" diisi
"diperbaiki (commit X)" atau "dicatat sebagai known limitation".

| # | Langkah | Yang terjadi | Tindakan |
|---|---|---|---|
| | | | |

---

## Tanda tangan

| Dijalankan pada | Oleh | Versi / commit |
|---|---|---|
| | | |
