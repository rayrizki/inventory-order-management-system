# Test Scenario & Hasil - Slice Login (AUTH-01, AUTH-02)

## Perintah

```
docker compose up --build -d
docker compose exec app vendor/bin/phpunit
```

## Hasil (2026-09-08)

```
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.2.33
............                                                      12 / 12 (100%)
OK (12 tests, 18 assertions)
```

## Unit test (tests/Unit) - tanpa DB/session sungguhan

| Area | Test | Skenario |
|---|---|---|
| `AuthService` | `AuthServiceTest` (5 case) | Login benar; password salah; email tidak terdaftar; user nonaktif (`is_active=0`); password kosong |
| `AuthGuard` | `AuthGuardTest` (4 case) | Akses tanpa session -> `UnauthenticatedException`; session valid -> `CurrentUser` terbentuk benar; role diizinkan -> lolos; role tidak diizinkan -> `ForbiddenException` |

## Integration test (tests/Integration) - MySQL asli di Docker

| Test | Skenario |
|---|---|
| `MySqlUserRepositoryTest::testFindByEmailReturnsSeededAdmin` | Ambil user `admin@iom.test` dari seed, verifikasi `password_verify` benar untuk `Password123!` dan salah untuk password lain |
| `MySqlUserRepositoryTest::testFindByEmailReturnsNullForUnknownEmail` | Email tidak terdaftar mengembalikan `null`, bukan error |
| `MySqlUserRepositoryTest::testFindByIdReturnsSameUserAsFindByEmail` | `findById` dan `findByEmail` mengembalikan data konsisten |

## Skenario manual (curl, didemokan di percakapan pengembangan)

| Skenario | Hasil |
|---|---|
| `GET /dashboard` tanpa login | Redirect 303 ke `/login` (AuthGuard) |
| `POST /login` dengan password salah | Redirect 303 ke `/login`, pesan "Email atau password salah" tampil sekali (flash), hilang setelah reload berikutnya |
| `POST /login` dengan kredensial benar (`admin@iom.test` / `Password123!`) | Redirect 303 ke `/dashboard`, session ID diperbarui (`regenerateId`) |
| `GET /dashboard` dengan session valid | Menampilkan "Selamat datang, Admin" |
| Login sebagai `sales1@iom.test` | Redirect ke `/dashboard`, menampilkan "Selamat datang, Sales" |
| Login sebagai `warehouse1@iom.test` | Redirect ke `/dashboard`, menampilkan "Selamat datang, WarehouseStaff" |
| `POST /logout` (lewat tombol "Keluar" di topbar) | Session dihapus, `GET /dashboard` sesudahnya redirect lagi ke `/login` |

## Bug ditemukan & diperbaiki selama pengembangan

Tombol "Keluar" sempat berada di luar tag `<form>` (form-nya kosong) akibat reorder tidak sengaja di `views/layout/shell-start.php` - tombol `type="submit"` tanpa form pembungkus tidak melakukan apa-apa saat diklik. Ditemukan lewat pengecekan manual, diperbaiki dengan memindahkan tombol kembali ke dalam `<form method="post" action="/logout">`, diverifikasi ulang lewat curl POST /logout dan re-run test suite (tetap 12/12).

## Known bugs / keterbatasan

Lihat `docs/quality/tech-debt.md` (#2 menu sidebar belum difilter per role, #3 `UserRepositoryInterface` belum lengkap - keduanya disengaja, bukan bug).
