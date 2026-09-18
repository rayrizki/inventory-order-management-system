# Test Scenario & Hasil - Slice Manajemen User (USR-01)

## Perintah

```
docker compose up -d --build app
docker compose exec app vendor/bin/phpunit
```

## Hasil (2026-09-18)

```
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.2.33
OK (127 tests, 268 assertions)
```

18 test baru di slice ini (14 unit + 4 integration), naik dari 109 sebelumnya.

## Unit test (tests/Unit) - tanpa DB/session sungguhan

| Test | Skenario |
|---|---|
| Create sukses, password ter-hash | Password TIDAK disimpan plaintext; `verifyPassword()` cocok dengan input asli |
| Field wajib kosong ditolak sekaligus | name/email/password/role semua muncul sebagai error dalam satu response |
| Format email invalid ditolak | `filter_var(..., FILTER_VALIDATE_EMAIL)` |
| Email duplikat ditolak | Termasuk saat `excludeId` (update) mengizinkan user mempertahankan email-nya sendiri |
| Password < 8 karakter ditolak | |
| **Role `Admin` ditolak** | Satu-satunya Service yang membatasi NILAI enum, bukan cuma format - form ini tidak boleh dipakai membuat/mengubah akun jadi Admin |
| Role tidak dikenal ditolak | `Role::tryFrom()` gagal -> error, bukan exception tak tertangani |
| Update boleh pertahankan email sendiri | |
| Update dengan password kosong mempertahankan hash lama | Bukan menimpa dengan hash dari string kosong |
| Update dengan password baru mengganti hash | |
| Update tidak diam-diam mengaktifkan user nonaktif | |
| Update id tidak ditemukan -> `NotFoundException` | |
| `setActive()` toggle status | |
| `listUsers()` filter status aktif | |

## Integration test (tests/Integration) - MySQL asli di Docker

| Test | Skenario |
|---|---|
| `save()` insert baru, default aktif | |
| `save()` update tidak menyentuh `is_active` | Toggle status dan ganti field lain tidak saling mempengaruhi |
| `setActive()` toggle status | |
| `listAll()` filter search **email** (bukan cuma name) dan status aktif | |

## Skenario manual (Playwright, didemokan sepanjang pengembangan)

| Skenario | Hasil |
|---|---|
| Create user (modal, role Sales) | Berhasil, baris baru muncul di tabel |
| Create user dengan email yang sudah dipakai | Ditolak; server me-render ulang **halaman form penuh** (bukan modal) dengan pesan error - konsisten dengan pola fallback validasi Category/Warehouse/Supplier/Customer, bukan render ulang index dengan modal (percobaan pertama salah meniru pola ini, diperbaiki sebelum commit) |
| Edit user (prefill nama/email/role lewat `data-*`) | Prefill role benar; update berhasil |
| Nonaktifkan user (modal konfirmasi custom) | Berhasil |
| Akses `/users` sebagai Sales dan Warehouse Staff | 403 di server; item sidebar "User" juga tidak tampil ke keduanya |

## Known bugs & keterbatasan

- **Ditemukan & diperbaiki sebelum commit**: skrip Playwright verifikasi awal memakai selector `a:has-text("Ubah")`/`button:has-text("Nonaktifkan")` tanpa di-scope ke baris user test - mengenai baris user SEED ASLI (`warehouse1@iom.test` jadi nonaktif, `warehouse2@iom.test` namanya tertimpa). Ini murni bug skrip test (bukan bug aplikasi - `UserService::updateUser()`/`setActive()` bekerja benar terhadap id yang dikirim, cuma id yang salah yang terkirim karena klik salah baris). Kedua baris seed dipulihkan ke nilai semula sebelum commit; skrip diperbaiki dengan mempersempit lewat search sebelum klik aksi apa pun.
- Role `Admin` tidak bisa dibuat/diubah lewat UI ini secara sengaja (lihat tech-debt #3 dan Diagram H) - kalau pernah dibutuhkan akun Admin tambahan, itu tetap operasi database/seed manual, bukan lewat form.
- USR-01 tidak meminta FIND-01 (search/filter/pagination) secara eksplisit untuk User, tapi tetap diterapkan mengikuti pola Master Data lain untuk konsistensi UI - jumlah user realistis (seed 5) membuat pagination-nya belum benar-benar teruji dengan data > 1 halaman, tapi logic-nya identik dengan Supplier/Customer yang sudah teruji.
