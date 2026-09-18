# Static Analysis Report (TEST-03)

## Tool

[PHPStan](https://phpstan.org/) 1.12.34, dipasang sebagai dev dependency
(`composer require --dev phpstan/phpstan`). Konfigurasi di `phpstan.neon`
(root proyek) - menganalisis `app/`, `public/`, `config/`, dan `tests/`,
mengecualikan `vendor/`.

## Perintah

```
docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M
```

(atau `php vendor/bin/phpstan analyse` langsung dari host kalau PHP CLI
tersedia lokal - `memory_limit` CLI host biasanya sudah lebih longgar dari
128M default container, jadi flag itu opsional di situ).

`--memory-limit` dibutuhkan di dalam container karena `php.ini` bawaan
image `php:8.2-cli` membatasi 128M - cukup untuk menjalankan aplikasi,
tapi PHPStan (menganalisis seluruh `app/`+`tests/` sekaligus di beberapa
worker paralel) butuh lebih banyak. Ini bukan masalah aplikasi, murni
kebutuhan tool analisis saat development.

## Hasil (2026-09-18)

**Level 6 - 0 critical error.**

Brief mensyaratkan minimum level 5; proyek ini menjalankan level 6 setelah
audit menunjukkan hanya 8 error tersisa di level itu (dibanding 64 di level
7 dan 202 di level 8 - lompatan yang jauh lebih besar, tidak realistis
dikejar tanpa mengorbankan waktu untuk fitur inti yang belum selesai).

## Perjalanan menuju 0 error

| Level | Error awal | Tindakan |
|---|---|---|
| 5 | 12 | Semua error bertipe sama: PHPStan menganggap `$input['key'] ?? 'default'` mubazir karena PHPDoc `@param` di `PurchaseOrderService::validate()` dan `UserService::validate()` mendeklarasikan seluruh key array sebagai wajib ada. **Ini bukan bug** - kedua method itu jadi boundary ke `$_POST` lewat `Controller::readInput()`, yang secara runtime tidak dijamin selengkap PHPDoc-nya (mis. field hilang dari form yang dimodifikasi manual). Diperbaiki dengan mengubah anotasi jadi *optional keys* (`array{name?: string, ...}`) - lebih jujur secara semantik, dan mempertahankan `??` sebagai pertahanan yang memang disengaja, bukan menghapusnya. |
| 6 | 8 (setelah perbaikan di atas) | Semua error bertipe "no value type specified in iterable type array" - beberapa method (`renderShow()`, `makePurchaseOrder()` test helper, tiga `validInput()` test helper) punya parameter/return `array` tanpa generic type. Diperbaiki dengan menambah `@param`/`@return` yang lebih spesifik (`array<string, string>`, `PurchaseOrderItem[]`, `array<string, mixed>`). Murni anotasi dokumentasi - tidak mengubah perilaku runtime, dikonfirmasi lewat full test suite tetap hijau sesudahnya. |

## Warning yang tersisa (bukan critical error)

Tidak ada. `[OK] No errors` di level 6.

## Kenapa tidak PHP_CodeSniffer juga

Brief meminta "PHPStan (level 5+) **atau** PHP_CodeSniffer (PSR-12)" - salah
satu, bukan keduanya. PHPStan dipilih karena menangkap kesalahan tipe/logic
(potensi bug nyata), sementara PHPCS murni soal gaya penulisan kode - untuk
proyek native tanpa framework/generator kode, risiko bug tipe data
(terutama di boundary array dari `$_POST`, seperti yang ditemukan di atas)
lebih relevan diperiksa otomatis daripada soal spasi/indentasi.
