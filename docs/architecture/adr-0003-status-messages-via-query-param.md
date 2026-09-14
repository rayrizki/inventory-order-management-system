# ADR-0003: Pesan hasil aksi (sukses/gagal) lewat query param `?status=`, bukan session flash

## Context

Setelah create/update/delete Kategori berhasil (atau delete ditolak karena
kategori masih dipakai produk lain), halaman daftar perlu menampilkan pesan
hasil aksi itu ke user. Pola paling umum untuk kasus ini adalah *session
flash*: simpan pesan di session sebelum redirect, baca dan hapus lagi saat
render halaman berikutnya.

Search, sort, dan pagination pada halaman yang sama sudah lebih dulu
didesain sepenuhnya lewat query string (`?q=`, `?sort=`, `?page=`,
`?per_page=`) - state halaman daftar dapat dibaca ulang dari URL kapan pun,
tanpa bergantung ke session sama sekali.

## Decision

Pesan status juga dikodekan sebagai query param (`?status=created`,
`?status=updated`, `?status=deleted`, `?status=delete_blocked`), dipetakan
ke teks + warna lewat satu tabel terpusat (`CategoryController::
STATUS_MESSAGES`), bukan lewat `SessionInterface::set()`/`get()`.

## Consequences

**Nilai tambah nyata:**
- Konsisten dengan pola state-di-URL yang sudah dipakai search/sort/
  pagination di halaman yang sama - satu mental model, bukan dua (sebagian
  state di URL, sebagian di session).
- Tidak ada risiko klasik "lupa `session->remove()` setelah dibaca" yang
  membuat flash message muncul berulang di halaman berikutnya secara tidak
  sengaja.
- Bisa dites end-to-end lewat request biasa (`curl`/Playwright ke URL
  dengan `?status=...`) tanpa perlu menyiapkan session state lebih dulu -
  memudahkan verifikasi.

**Trade-off yang disadari:**
- URL redirect jadi sedikit "kotor" (`?status=deleted` terlihat di address
  bar) - ditangani dengan tombol tutup pada banner yang membersihkan
  `?status=` dari URL lewat `history.replaceState()` begitu user
  menutupnya, supaya refresh halaman tidak memunculkan pesan yang sama
  lagi.
- Kalau nanti ada redirect chain lebih dari satu hop, query param ini perlu
  sengaja diteruskan di setiap hop - berbeda dari session flash yang
  otomatis "ikut" user tanpa perlu diteruskan manual. Belum jadi masalah
  nyata karena alur create/update/delete Kategori masing-masing cuma satu
  kali redirect.

Diverifikasi lewat Playwright: keempat nilai `status` menampilkan warna dan
teks yang benar (hijau/biru/kuning/merah), tombol tutup menghilangkan
banner dan membersihkan `?status=` dari URL, dan reload halaman setelah
ditutup tidak memunculkan pesan itu lagi.
