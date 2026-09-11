# ADR-0001: Repository pattern dengan dua implementasi, bukan PDO langsung di Controller

## Context

ARCH-01 mewajibkan business logic (Service) tidak boleh bergantung langsung pada PDO. Alternatif yang lebih sederhana adalah menaruh query SQL langsung di Controller atau Service - lebih sedikit file, lebih cepat ditulis di awal.

Modul training PHP (Bab 11.3) memberi kriteria eksplisit soal kapan Repository pattern *layak* dipakai: "Repository yang cuma passthrough tanpa nilai tambah itu anti-pattern; yang beneran menyediakan abstraksi koleksi/persistensi itu valid." Ini jadi pertanyaan yang harus dijawab sebelum memakai pattern ini di sini.

## Decision

`AuthService` menerima `UserRepositoryInterface` lewat constructor (bukan `PDO` langsung), dengan dua implementasi:

- `MySqlUserRepository` - query PDO asli ke tabel `users`, dipakai production & integration test.
- `InMemoryUserRepository` - array biasa di memory, dipakai unit test.

`AuthService::authenticate()` hanya bergantung pada interface, sehingga bisa diuji (`tests/Unit/Service/AuthServiceTest.php`) tanpa koneksi database sama sekali.

## Consequences

**Nilai tambah nyata (bukan cuma passthrough):**
- `MySqlUserRepository::hydrate()` menerjemahkan baris SQL (string `role`) menjadi `Role` enum PHP dan objek `User` yang type-safe - ini logic konversi asli, bukan sekadar meneruskan hasil `$pdo->query()` mentah.
- Unit test `AuthService` (5 test case: sukses, password salah, email tak ditemukan, user nonaktif, password kosong) berjalan dalam hitungan milidetik tanpa Docker/MySQL menyala - terbukti dari `docker compose exec app vendor/bin/phpunit` yang memisahkan testsuite Unit dan Integration.
- Kalau nanti sumber data user berubah (mis. ditambah cache, atau tabel dipecah), `AuthService` dan test unit-nya tidak perlu diubah sama sekali - cuma `MySqlUserRepository` yang tersentuh.

**Trade-off yang disadari:**
- Ada biaya menulis dua file (interface + dua implementasi) untuk satu tabel yang query-nya sebenarnya sederhana (`SELECT ... WHERE email = ?`). Untuk kasus se-simpel ini, manfaatnya baru terasa di sisi testability, bukan di sisi menghindari duplikasi query.
- Interface `UserRepositoryInterface` sengaja **belum** menyertakan `save()`/`listPaginated()` (berbeda dari class diagram initial) - method itu ditunda sampai slice manajemen User (USR-01) benar-benar membutuhkannya, supaya tidak menulis method yang belum ada pemanggilnya (YAGNI). Perbedaan ini akan dicatat saat class diagram as-built dibuat.
