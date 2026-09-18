# Critique Exercise (DESIGN-04)

## Status: menunggu cuplikan kode dari assessor

Brief §3.2 (DESIGN-04) menyatakan: "Assessor menyediakan satu cuplikan kode
yang sengaja bermasalah (mis. satu Service yang menangani validasi,
penyimpanan, dan pengiriman notifikasi sekaligus)." Isi kritik yang
sebenarnya - smell apa yang ada, prinsip SOLID apa yang dilanggar, dan
bagaimana seharusnya direfaktor - baru bisa ditulis setelah cuplikan itu
diberikan, bukan sebelumnya (menulis cuplikan sendiri lalu mengkritik
sendiri tidak menguji hal yang sama seperti yang dimaksud brief: kemampuan
membaca dan mengevaluasi kode ORANG LAIN secara langsung).

File ini dibuat sekarang (bukan ditunda sampai hari defense) supaya
strukturnya sudah siap diisi begitu cuplikan diterima, dan supaya tidak ada
kesan item ini terlewat dari checklist §7. Diisi lengkap saat technical
defense, sesuai catatan resmi FAQ brief #5: "Analisis tertulis yang
menyebutkan smell, prinsip yang dilanggar, dan arah perbaikan sudah cukup -
implementasi perbaikan tidak wajib."

## Format yang akan diisi

```markdown
## Cuplikan kode

(tempel cuplikan dari assessor apa adanya)

## Smell yang teridentifikasi

- ...

## Prinsip SOLID/Clean Code yang dilanggar

- ...

## Arah perbaikan

- ...
```

## Latihan mandiri (sambil menunggu) - contoh dari kode sendiri

Sebagai latihan (bukan pengganti exercise yang sebenarnya), berikut contoh
kritik terhadap draf awal kode proyek ini sendiri yang sempat melanggar SRP
sebelum diperbaiki - polanya identik dengan yang dimaksud DESIGN-04:

**Kode**: draf awal `public/assets/js/list-controls.js` (lihat
`docs/quality/refactor-log.md` bagian "Audit SRP" untuk kode
sebelum/sesudah lengkap).

**Smell**: file ini awalnya memikul dua tanggung jawab tidak berhubungan -
*kontrol widget list* (auto-submit dropdown "baris per halaman") dan
*konfirmasi aksi destruktif* (state modal, dialog lifecycle untuk tombol
hapus/nonaktifkan). Kedua tanggung jawab itu berubah karena alasan yang
berbeda (satu berubah kalau kontrol pagination berubah, satu berubah kalau
UX konfirmasi berubah) - indikator klasik pelanggaran SRP.

**Prinsip yang dilanggar**: Single Responsibility Principle - "a class
should have only one reason to change." File ini punya dua alasan berubah.

**Arah perbaikan**: diekstrak jadi dua file terpisah
(`list-controls.js` dan `confirm-dialog.js`), masing-masing satu tanggung
jawab, dihubungkan lewat konvensi `data-*` attribute yang tetap kompatibel
tanpa saling tahu detail internal satu sama lain. Sudah diimplementasikan
(bukan cuma dianalisis) - lihat commit yang direferensikan di
`refactor-log.md`.
