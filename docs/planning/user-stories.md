# User Stories

**Catatan transparansi**: dokumen ini seharusnya ditulis sebelum coding
dimulai (§7 submission package mensyaratkan `docs/planning/` berisi user
story, bukan cuma ERD dan class diagram initial). File ini baru ditulis
formal setelah slice Purchase Order dan Sales Order selesai, sebagai bagian
dari audit kelengkapan dokumentasi brief §7 - bukan disembunyikan sebagai
seolah-olah ditulis di awal. Isinya tetap merekonstruksi niat asli di balik
tiap fitur (§1.1 alur utama, §1.2 tabel peran) yang memang jadi acuan sejak
awal pengerjaan, dicek konsisten terhadap kode yang sudah jadi.

## Admin

- Sebagai Admin, saya ingin login dan melihat dashboard ringkasan seluruh
  data, supaya saya punya gambaran kondisi inventori dan order tanpa harus
  membuka tiap halaman satu per satu. (AUTH-01, DASH-01)
- Sebagai Admin, saya ingin menambah/mengubah/menonaktifkan akun Sales dan
  Warehouse Staff, supaya saya bisa mengatur siapa saja yang punya akses ke
  sistem tanpa perlu pendaftaran mandiri. (USR-01)
- Sebagai Admin, saya ingin mengelola master data (kategori, gudang,
  supplier, customer, produk), supaya seluruh transaksi PO/SO punya
  referensi data yang valid dan konsisten. (PRD-01, WH-01)
- Sebagai Admin, saya ingin membuat Purchase Order ke supplier saat stok
  rendah, supaya stok bisa diisi ulang sebelum benar-benar habis. (PO-01)
- Sebagai Admin, saya ingin meninjau dan menyetujui/menolak Sales Order yang
  diajukan Sales, supaya tidak ada satu orang yang bisa membuat sekaligus
  menyetujui order-nya sendiri (segregation of duties, §1.2). (SO-01)
- Sebagai Admin, saya ingin mengunduh laporan CSV pergerakan stok dan status
  order dalam rentang tanggal tertentu, supaya saya bisa membawa datanya ke
  luar aplikasi untuk keperluan lain (rapat, audit). (REPORT-01)

## Sales

- Sebagai Sales, saya ingin melihat katalog produk beserta stok yang
  tersedia, supaya saya tahu apa yang bisa ditawarkan ke customer sebelum
  membuat order. (§1.2: Sales "hanya melihat katalog")
- Sebagai Sales, saya ingin membuat Sales Order berdasarkan katalog dan
  stok yang tersedia lalu mengajukannya untuk persetujuan, supaya proses
  penjualan tercatat resmi dan bisa ditindaklanjuti gudang. (SO-01)
- Sebagai Sales, saya ingin melihat daftar order milik saya sendiri beserta
  statusnya, supaya saya tahu order mana yang masih menunggu persetujuan,
  sudah disetujui, atau sudah selesai - tanpa melihat order milik Sales
  lain. (SO-01, §1.2 ownership scoping)
- Sebagai Sales, saya *tidak* ingin (dan memang tidak boleh) menyetujui
  order milik saya sendiri, supaya tidak ada penyalahgunaan wewenang -
  ini justru requirement negatif yang sengaja ditegakkan di server, bukan
  cuma disembunyikan di UI. (§1.2, SO-01)

## Warehouse Staff

- Sebagai Warehouse Staff, saya ingin melihat data produk & stok per
  gudang, supaya saya tahu produk mana yang perlu diisi ulang. (§1.2, WH-01)
- Sebagai Warehouse Staff, saya ingin mengusulkan Purchase Order saat stok
  rendah, supaya proses pengadaan bisa dimulai tanpa harus menunggu Admin
  menyadarinya sendiri. (§1.2, PO-01)
- Sebagai Warehouse Staff, saya ingin mencatat goods receipt (penuh atau
  sebagian) saat barang dari supplier datang, supaya stok bertambah sesuai
  jumlah yang benar-benar diterima, dengan riwayat yang bisa ditelusuri.
  (PO-01, ARCH-02)
- Sebagai Warehouse Staff, saya ingin memproses goods issue untuk Sales
  Order yang sudah Approved, supaya barang keluar gudang sesuai order yang
  sah, dan sistem menolak permintaan itu kalau stok sebenarnya tidak cukup
  (bukan malah membuat stok jadi negatif). (SO-01, ARCH-02)

## Lintas peran

- Sebagai pengguna aplikasi (peran apa pun), saya ingin melihat pesan error
  yang aman dan jelas ketika saya tidak berhak mengakses sesuatu atau data
  yang saya cari tidak ada, supaya saya tahu harus berbuat apa selanjutnya
  tanpa melihat detail teknis internal aplikasi (stack trace, query SQL).
  (ERR-01)
- Sebagai pengguna aplikasi, saya ingin bisa mencari, memfilter, mengurutkan,
  dan berpindah halaman pada daftar produk/PO/SO, supaya saya bisa
  menemukan data yang saya cari dengan cepat meski datanya banyak. (FIND-01)
- Sebagai pengguna aplikasi di perangkat mobile, saya ingin seluruh halaman
  utama tetap bisa dipakai di layar sempit (360px), supaya saya tidak
  terpaksa memakai laptop hanya untuk mengecek status order. (UI-01)
