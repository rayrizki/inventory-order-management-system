# Tech-Debt Register

| # | Keterbatasan / jalan pintas | Alasan | Perbaikan ideal |
|---|---|---|---|
| 1 | `public/assets/js/login.js` mengecek kredensial langsung di client dengan nilai dummy (`admin`/`admin`), bukan memanggil server. | Docker, MySQL, PDO, dan `AuthService` belum dibangun. Dibutuhkan tampilan error login yang bisa didemokan sekarang, sebelum backend siap. | Ganti dengan `POST /login` sungguhan ke `AuthController` yang memanggil `AuthService::authenticate()` (`password_hash`/`password_verify` ke tabel `users`), pesan gagal dikirim lewat session flash + Post/Redirect/Get (AUTH-01). Setelah itu, logic pengecekan benar/salah kredensial di `login.js` harus dihapus total - JS di sisi client hanya boleh tersisa untuk validasi "field kosong" (VAL-01 tetap mewajibkan backend sebagai sumber kebenaran). |
