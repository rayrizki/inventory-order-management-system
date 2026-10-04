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

## Hasil akhir (2026-10-04, commit yang ditag `v1.0.0`)

| Metrik | Nilai |
|---|---|
| **Quality Gate** | **Passed** |
| Bugs | **0** (rating A) |
| Vulnerabilities | 0 terbuka (1 ditinjau & diterima - lihat di bawah) |
| Security Hotspots | 0 |
| Maintainability rating | **A** |
| Code smells terbuka | 24 |
| Lines of code | 8.103 |
| Duplikasi | 8,3% |

## Perjalanan dari 240 temuan menjadi 24

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

### Terbuka dan sengaja tidak diperbaiki (24 temuan)

Semuanya minor, dan dipertahankan karena memperbaikinya tidak menghasilkan
kode yang lebih baik - hanya angka yang lebih rapi.

| Rule | Jumlah | Temuan | Alasan |
|---|---|---|---|
| `php:S1192` | 18 | Literal berulang: `"Location: "` di 8 Controller, path view form di 7 Controller, URL daftar di 3 view | Perbaikannya nyata (ekstrak konstanta/helper), tapi untuk literal `"Location: "` solusi yang benar bukan sekadar konstanta melainkan helper redirect yang juga menjamin `exit` tidak terlupa. Itu mengubah alur kontrol di 10 Controller - perubahan yang tidak sepadan risikonya beberapa hari sebelum submission. Dicatat sebagai pekerjaan berikutnya. |
| `php:S3776` | 3 | Cognitive complexity 17/40/42 pada `validate()` di `ProductService`, `SalesOrderService`, `PurchaseOrderService` | Ketiganya memvalidasi header + seluruh baris item sekaligus dan mengumpulkan **semua** error dalam satu kali jalan, karena VAL-01 meminta input yang sudah diisi dipertahankan - jadi tidak bisa berhenti di error pertama. Percabangannya banyak tapi linier dan sejenis; memecahnya hanya memindahkan percabangan. Sudah tercatat di `tech-debt.md` #16. |
| `php:S107` | 2 | 8 parameter pada `ProductRepositoryInterface::listAll()` dan `ProductService::listProducts()` | Delapan parameter itu adalah dimensi filter FIND-01 yang memang diminta brief (search, kategori, status aktif, status stok, limit, offset, sortBy, sortDir). Membungkusnya jadi object kriteria adalah perbaikan yang sah, tapi menambah satu kelas yang belum menyelesaikan masalah nyata. |
| `php:S1142` | 1 | 6 `return` pada `ProductService::validateImage()` | Tiap `return` adalah satu kondisi penolakan upload yang berbeda (tidak ada file, error upload, ukuran, MIME, dst). Guard clause berurutan lebih mudah dibaca daripada satu jalur bercabang. |

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

1. Dashboard SonarQube: Quality Gate **Passed**, Bugs 0, rating A.
2. Tab Issues difilter ke status *False Positive* - tunjukkan salah satu
   komentarnya, lalu buka `views/dashboard/index.php` dan tunjukkan `$summary`
   yang katanya "tidak terpakai" itu dipakai 13 kali.
3. 24 temuan terbuka beserta alasannya di tabel atas.
