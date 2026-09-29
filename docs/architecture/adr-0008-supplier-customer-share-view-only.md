# ADR-0008: Supplier dan Customer berbagi lapisan tampilan saja, bukan tipe dan tabelnya

## Context

Audit terhadap brief menemukan bahwa modul Supplier dan Customer adalah
salinan satu sama lain. Diukur dengan mengganti kata "Supplier" jadi
"Customer" lalu membandingkan baris per baris, hasilnya:

| Lapisan | Baris (per modul) | Baris berbeda |
|---|---|---|
| Entity | 17 | 0 |
| Repository interface | 36 | 0 |
| MySQL repository | 151 | 0 |
| In-memory repository | 103 | 0 |
| Service | 101 | 0 |
| Controller | 166 | 0 |
| View `index.php` | 337 | 2 |
| View `form.php` | 60 | 0 |

Sekitar 971 baris tergandakan, dan satu-satunya perbedaan sejati ada di dua
baris teks empty state ("...untuk mulai mencatat purchase order" vs "...sales
order").

Riwayat git menegaskan sifatnya: keenam file `app/` untuk kedua modul hanya
pernah disentuh oleh **tiga commit yang sama persis** - tidak pernah ada
perubahan yang hanya mengenai salah satunya. Jadi keduanya belum pernah
divergen, tetapi setiap perubahan memang harus dikerjakan dua kali.

Penyebab kesamaan itu bukan "entity-nya kebetulan sama", melainkan lima
requirement yang kebetulan identik:

1. Field-nya sama: nama, kontak, alamat, status aktif (§1.3).
2. Operasinya sama: CRUD, dinonaktifkan bukan dihapus (§1.3).
3. Kolom yang dicari sama (tiga kolom).
4. Perilaku pagination/sort/filter status sama (FIND-01).
5. Otorisasinya sama: Admin-only (§1.2).

## Decision

**Yang dibagi: lapisan tampilan.** `views/shared/supplier-customer-list.php`
dan `views/shared/supplier-customer-form.php` menampung seluruh markup;
`views/suppliers/*` dan `views/customers/*` tinggal jadi adapter ~15 baris
yang mengisi label, URL, key menu, dan konteks empty state.

**Yang TIDAK dibagi: Entity, Repository interface, dan tabel database.**
Di situlah letak jaminan yang membuat pemisahan ini bernilai:

```sql
purchase_orders.supplier_id  REFERENCES suppliers (id)
sales_orders.customer_id     REFERENCES customers (id)
```

Selama tabelnya terpisah, database sendiri yang menjamin Purchase Order tidak
akan pernah menunjuk ke customer, dan Sales Order tidak akan pernah menunjuk
ke supplier. Digabung jadi satu tabel `parties` dengan kolom `type`, jaminan
itu hilang dan yang tersisa hanya disiplin kode. Hal yang sama berlaku di
level tipe: `PurchaseOrderService` menerima `SupplierRepositoryInterface` dan
`SalesOrderService` menerima `CustomerRepositoryInterface`, sehingga salah
pasang ketahuan sebelum program jalan.

**Service, Controller, dan Repository sengaja dibiarkan terduplikasi.** Bisa
saja dibagi lewat abstract base class, tapi biayanya nyata: nama tabel harus
disisipkan ke string SQL (bukan pelanggaran - brief melarang penggabungan
**input user**, sedangkan nama tabel berasal dari method milik kelas - tapi
jadi beban penjelasan), tipe kembalian jadi kabur karena PHP tidak punya
generic, dan duplikasi ditukar dengan pewarisan yang butuh banyak abstract
method kecil untuk label/URL/tabel. Untuk kode CRUD yang stabil dan sudah
melewati tiga kali perubahan tanpa satu pun terlewat, biaya itu tidak
sepadan.

**Pemicu pembubaran.** Begitu salah satu dari lima kesamaan di atas tidak
lagi berlaku - contoh nyata yang paling mungkin: Customer butuh limit kredit,
atau Supplier butuh termin pembayaran dan lead time - modul yang bersangkutan
berhenti memakai template bersama dan kembali punya view sendiri. Ini dicatat
supaya keputusannya punya titik akhir yang jelas, bukan "nanti kalau sempat".

## Consequences

**Yang didapat.** Duplikasi terbesar (397 baris markup, bagian yang paling
sering disunting saat memperbaiki tampilan) hilang; total view turun dari 794
menjadi 508 baris. Perbaikan tampilan cukup dilakukan sekali. Jaminan
foreign key dan pemisahan tipe tetap utuh.

**Harganya.** Untuk memahami halaman Supplier, pembaca harus membuka dua
file: adapter-nya dan template bersamanya. Itu indirection nyata, diterima
karena adapter-nya sangat tipis dan namanya menyebut eksplisit kedua modul
yang berbagi.

**Jebakan yang ditemukan saat mengerjakannya, dicatat supaya tidak terulang.**
Template di-`require` sehingga berbagi scope dengan `shell-start.php`. Versi
pertama memakai `$items`/`$item` untuk daftar - nama yang sudah dipakai
`shell-start.php` untuk loop menu sidebar - sehingga baris tabel tampil
kosong meski tidak ada error apa pun. Diganti jadi `$records`/`$record`, dan
daftar nama yang sudah "dipesan" layout dicantumkan di docblock template.
Ini juga menegaskan catatan di `docs/quality/static-analysis.md`: `views/`
tidak dianalisis PHPStan, jadi kesalahan semacam ini hanya bisa ditangkap
dengan menjalankan aplikasinya.

**Bukti tidak ada perubahan perilaku.** HTML hasil render untuk delapan
halaman (daftar Supplier & Customer, modal tambah, form ubah, pencarian
dengan sort + per_page, filter status) direkam sebelum dan sesudah refaktor,
lalu dibandingkan: **nol baris berbeda**. Aksi POST diuji terpisah (toggle
aktif/nonaktif dua kali sampai kembali ke nilai semula). 192 test tetap
lulus, PHPStan level 6 tetap bersih.
