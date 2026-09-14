# ADR-0002: Modal create/edit/konfirmasi pakai elemen `<dialog>` native, bukan library JS atau modal buatan sendiri

## Context

Form Kategori (create/edit) cuma 2 field, dan konfirmasi hapus cuma butuh
"ya/tidak" - keduanya terlalu kecil untuk pindah halaman penuh, tapi tetap
butuh backdrop, fokus terkunci di dalam dialog, dan bisa ditutup lewat
tombol/Escape supaya terasa seperti komponen UI sungguhan, bukan sekadar
`<div>` yang disembunyikan-tampilkan.

Tiga opsi yang dipertimbangkan:
1. Modal buatan sendiri dari nol (`<div>` + CSS + JS manual untuk focus-trap,
   klik-di-luar-untuk-tutup, `aria-modal`, dsb).
2. Library modal JS (mis. lewat CDN) - **tidak diperbolehkan**: brief §4
   secara eksplisit melarang library/JS framework di luar Vanilla JS, dan
   izin "library icon" tidak berlaku untuk komponen UI/interaksi.
3. Elemen `<dialog>` HTML5 native (`showModal()`/`close()`).

## Decision

Pakai `<dialog>` native untuk ketiga modal (create Kategori, edit Kategori,
konfirmasi hapus). Trigger-nya tetap `<a href="/categories/create">`/
`<a href="/categories/{id}/edit">` sungguhan (bukan `<button>` tanpa href) -
`modal-form.js` (Vanilla JS, IIFE, tanpa dependency) mencegat klik lewat
`preventDefault()` dan memanggil `showModal()` sebagai gantinya. Route
halaman penuh (`CategoryController::showCreateForm()`/`showEditForm()`,
`views/categories/form.php`) **tetap dipertahankan utuh**, bukan dihapus -
kalau script gagal dimuat, link tetap berfungsi normal sebagai navigasi
halaman penuh (progressive enhancement).

## Consequences

**Nilai tambah nyata:**
- Focus-trap, `Escape` untuk menutup, dan `::backdrop` didapat gratis dari
  browser - tidak perlu menulis ulang logic aksesibilitas modal yang rawan
  bug (mis. lupa mengembalikan fokus setelah ditutup).
- Karena fallback halaman penuh tetap ada, aplikasi tidak bergantung pada JS
  untuk fungsi inti (create/edit/delete kategori tetap bisa dilakukan tanpa
  JS sama sekali) - selaras dengan VAL-01 (backend tetap sumber kebenaran)
  dan tidak menambah risiko kalau browser penilai mematikan JS saat demo.
- Satu dialog konfirmasi (`#confirm-dialog`) dipakai ulang untuk seluruh
  aplikasi (didefinisikan sekali di `views/layout/shell-end.php`), bukan
  digandakan per halaman.

**Trade-off yang disadari:**
- Dukungan `<dialog>` di browser baseline sekitar 2022 ke atas (Chrome/
  Firefox/Safari modern) - bukan masalah untuk konteks penilaian ini, tapi
  dicatat sebagai batasan kalau suatu saat perlu dukungan browser lama.
- Styling default `<dialog>` (termasuk `::backdrop`) perlu di-override
  manual di CSS (`.modal`, `.modal::backdrop`) supaya konsisten dengan
  design token aplikasi (`--color-*`, `--radius`, `--shadow-sm`) - sedikit
  boilerplate CSS tambahan dibanding kalau modal sudah datang dengan style
  dari sebuah library.

Diverifikasi lewat Playwright (browser sungguhan): dialog terbuka/tertutup
lewat tombol X/Batal/Escape, field edit ter-prefill benar dari atribut
`data-*` pada trigger yang diklik, dan submit lewat modal tetap
menghasilkan redirect + perubahan data yang sama seperti lewat halaman
penuh.
