# Laporan SonarQube

## Tool dan versi

SonarQube **Community Edition 26.9.0.129388**, dijalankan lokal lewat Docker
sesuai arahan mentor. Analisis dikirim oleh `sonar-scanner-cli`; konfigurasi
project ada di `sonar-project.properties` di root repo.

## Cara menjalankan

```bash
# 1. Jalankan server SonarQube (sekali, biarkan hidup)
docker run -d --name sonarqube-26.9 -p 9000:9000 \
  -v sq269_data:/opt/sonarqube/data \
  -v sq269_extensions:/opt/sonarqube/extensions \
  -v sq269_logs:/opt/sonarqube/logs \
  sonarqube:26.9.0.129388-community

# 2. Tunggu sampai siap (status UP, butuh 1-3 menit)
curl -s http://localhost:9000/api/system/status

# 3. Login di http://localhost:9000, buat token di
#    My Account > Security > Generate Token

# 4. Jalankan analisis dari root repo
docker run --rm -v "$(pwd):/usr/src" \
  -e SONAR_HOST_URL="http://host.docker.internal:9000" \
  -e SONAR_TOKEN="<token>" \
  sonarsource/sonar-scanner-cli

# 5. Buka hasilnya
#    http://localhost:9000/dashboard?id=inventory-order-management-system
```

> Token adalah kredensial - jangan di-commit. `sonar-project.properties`
> sengaja tidak memuat token; token dikirim lewat environment variable.

## Hasil akhir (2026-10-04, commit yang ditag `v1.0.1`)

| Metrik | Nilai |
|---|---|
| **Temuan terbuka** | **0** |
| Bugs | **0** — rating **A** |
| Vulnerabilities | **0** — rating **A** |
| Security Hotspots | **0** |
| Maintainability | rating **A** |
| Coverage | 62,5% (lapisan Service 88-100%) |
| Lines of code | 8.141 |
| Duplikasi | 9,1% |

### Catatan tentang Quality Gate bawaan

Gate default SonarQube ("Sonar way") menilai **new code** saja dan menuntut
coverage >= 80% serta duplikasi <= 3% pada baris yang baru berubah. Gate itu
dilaporkan ERROR di project ini, dan itu disampaikan apa adanya - bukan
disembunyikan dengan membuat gate longgar sendiri.

Alasannya: ambang 80% itu **tidak ada** di Project Brief maupun di Panduan
Presentation Final Project. Brief §TEST-03 meminta "PHPStan level 5+ atau
PHP_CodeSniffer, nol critical error"; panduan presentasi meminta "hasil
pemeriksaan yang memenuhi ketentuan kelulusan". Kriteria yang dipakai di sini
adalah **nol bug dan nol vulnerability dengan rating A** - dan itu terpenuhi.

## Perjalanan dari 240 temuan menjadi 0

Analisis pertama menghasilkan **240 temuan**: 88 bug, 1 vulnerability, dan
151 code smell. Semuanya ditinjau satu per satu, bukan diterima atau ditolak
massal.

### Diperbaiki di kode (89 temuan)

| Rule | Jumlah | Temuan | Tindakan |
|---|---|---|---|
| `php:S2003` | 88 | `require` sebaiknya `require_once` | Seluruh include template diubah ke `require_once`. Aman karena setiap template hanya di-include sekali per request, dan `shell-start.php` memang mendefinisikan variabel + closure sehingga double-include tidak pernah diinginkan. **Diverifikasi**: HTML 14 halaman direkam sebelum dan sesudah, nol baris berbeda; 192 test tetap lulus. |
| `php:S6353` | 1 | `[a-zA-Z0-9_]` bisa ditulis `\w` | Diperbaiki di `Router::match()`. |

Hasilnya: **bug 88 → 0**, reliability rating **B → A**.

### Ditandai false positive, dengan bukti (126 temuan)

Ketiganya berakar pada satu hal yang sama: **analisis per-file tidak bisa
melihat bahwa Controller dan template berbagi scope variabel** lewat
`require_once`.

| Rule | Jumlah | Kenapa false positive |
|---|---|---|
| `php:S1481` | 121 | "Remove this unused local variable" pada Controller. Variabelnya justru dipakai template yang di-`require_once` ke scope yang sama. Contoh terverifikasi: `DashboardController::$summary` (ditandai "tidak terpakai") dipakai **13 kali** di `views/dashboard/index.php`; `$csrfToken` dan `$loginFailed` di `AuthController` dipakai 5 kali di `views/auth/login.php`. |
| `php:S1172` | 4 | "Remove the unused function parameter" pada `renderShow()` di PO/SO Controller - akar penyebab yang sama, parameternya dikonsumsi template. |
| `Web:S6821` | 1 | "Elements with ARIA roles must use a valid role" di `views/users/index.php`. Tidak ada atribut `role` di markup itu; analyzer HTML membaca `<?= ... ?>` di posisi atribut sebagai nilai role kosong. |

### Ditinjau dan diterima dengan alasan (1 temuan)

| Rule | Temuan | Alasan diterima |
|---|---|---|
| `php:S2092` | Cookie session tanpa flag `secure` | Flag `secure` **diset kondisional** mengikuti apakah request berjalan di HTTPS (`PhpSessionAdapter`). Demo dan penilaian berjalan di `http://localhost`, dan cookie bertanda `secure` tidak akan pernah dikirim di sana - seluruh sesi akan rusak. Pola kondisional ini tetap benar saat aplikasi dideploy di belakang HTTPS. Mitigasi lain sudah aktif: `HttpOnly`, `SameSite=Lax`, dan `session.use_strict_mode`. |

### Putaran kedua: 24 code smell sisa ikut diselesaikan (23 diperbaiki, 1 diterima)

| Rule | Jumlah | Temuan | Tindakan |
|---|---|---|---|
| `php:S1192` | 18 | Literal berulang: `"Location: "` di 8 Controller, path view form di 6 Controller, URL daftar di 3 view, `"?result=cannot_transition"` | **Diperbaiki.** `"Location: "` diangkat ke trait `SendsRedirects` - bukan sekadar konstanta, melainkan method `redirect()` bertipe `never` yang sekaligus menjamin `exit` tidak terlupa (`header()` tidak menghentikan eksekusi, jadi lupa `exit` membuat body ikut terkirim di belakang header redirect). Path view form jadi konstanta `FORM_VIEW`; URL daftar jadi variabel `$listUrl` di tiga view. |
| `php:S3776` | 3 | Cognitive complexity 42 / 40 / 17 pada `validate()` di `PurchaseOrderService`, `SalesOrderService`, `ProductService` | **Diperbaiki lewat Extract Method.** Validasi baris item dipisah dari validasi header (`validateItems()` lalu `validateItemRow()`); pada `ProductService` tiga pemeriksaan angka dipisah ke `validateNumericFields()`. Pemisahannya mengikuti batas yang memang ada di domainnya: header punya satu nilai per field, item punya N baris. |
| `php:S107` | 2 | 8 parameter pada `ProductRepositoryInterface::listAll()` dan `ProductService::listProducts()` | **Diperbaiki lewat value object `ProductFilter`.** Alasannya bukan sekadar jumlah parameter: `listAll()` dan `countAll()` WAJIB dipanggil dengan filter identik - kalau tidak, jumlah halaman pagination tidak cocok dengan isi tabel. Sebelumnya kecocokan itu cuma dijaga kebiasaan; sekarang struktural. Normalisasi kata kunci pindah ke konstruktornya sehingga tidak ada pemanggil yang bisa lupa. |
| `php:S1142` | 1 | 6 `return` pada `ProductService::validateImage()` | **Diterima dengan alasan.** Keenamnya guard clause berurutan, masing-masing satu alasan penolakan upload. Sudah dicoba dipecah dua, tapi pembagian apa pun menyisakan 4-5 `return` di salah satunya; satu-satunya cara mencapai ambang 3 adalah mengubah rangkaian `if` jadi array closure - jelas lebih sulit dibaca untuk logika linier sederhana, dan brief menilai negatif kerumitan tanpa alasan. |

## Catatan: SonarQube menutup titik buta PHPStan

`phpstan.neon` sengaja tidak menganalisis `views/` - template PHP mengandalkan
variabel dari scope pemanggil, sehingga PHPStan melaporkan setiap variabel view
sebagai undefined dan laporannya jadi tidak berguna (lihat
`static-analysis.md`). Risikonya nyata dan sempat terjadi: saat `AuthGuard`
bertambah dependency, tiga halaman balas HTTP 500 sementara PHPStan tetap
melaporkan `[OK] No errors`.

`sonar-project.properties` **memasukkan `views/`** ke `sonar.sources`, jadi
kedua tool saling melengkapi: PHPStan ketat soal tipe di `app/`, SonarQube
mencakup template dan HTML yang tidak tersentuh PHPStan.

## Yang ditunjukkan saat presentasi

1. Dashboard SonarQube: **0 bug, 0 vulnerability, rating A/A/A, 0 temuan
   terbuka**, coverage 62,5%.
2. Tab Issues difilter ke status *False Positive* - tunjukkan salah satu
   komentarnya, lalu buka `views/dashboard/index.php` dan tunjukkan `$summary`
   yang katanya "tidak terpakai" itu dipakai 13 kali. Ini bukti temuan dibaca
   satu per satu, bukan ditutup massal.
3. Kalau ditanya soal Quality Gate yang merah: jelaskan bahwa itu mengukur
   coverage *new code* >= 80%, ambang yang tidak diminta brief maupun panduan
   presentasi - dan tunjukkan tabel metrik di atas sebagai kriteria yang
   dipakai.

Verifikasi setiap perbaikan di putaran kedua: 192 test tetap lulus, PHPStan
level 6 tetap nol error, smoke test seluruh rute untuk ketiga role tanpa 500,
dan jalur redirect diuji langsung lewat HTTP (login benar/salah, CRUD, serta
cabang `try/catch` yang menghasilkan `?result=cannot_transition`).
