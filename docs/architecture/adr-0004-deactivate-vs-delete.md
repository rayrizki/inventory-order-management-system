# ADR-0004: Kategori di-hard-delete, Gudang dinonaktifkan - dua pola berbeda yang disengaja

## Context

Kategori dan Gudang adalah dua entity Master Data pertama yang dibangun,
dengan struktur CRUD yang sangat mirip (nama + satu field tambahan,
dikelola Admin lewat modal create/edit yang sama polanya). Godaan alaminya
adalah menyalin pola hapus Kategori (`delete()` + pengecekan `isInUse()`)
apa adanya ke Gudang, supaya kedua modul benar-benar seragam.

Brief §1.3 (tabel field minimum) memberi sinyal berbeda untuk dua entity
ini:

| Entity | Field minimum |
|---|---|
| Category | Nama, deskripsi |
| Warehouse | Nama, lokasi, **status aktif** |

Category tidak diberi kolom status sama sekali; Warehouse (juga Product,
Supplier, Customer) eksplisit diberi "status aktif". §1.3 juga menyatakan
"Produk, supplier, dan customer dinonaktifkan, bukan dihapus permanen" -
Warehouse tidak disebut eksplisit di kalimat itu, tapi strukturnya
(kolom `is_active` yang sama) menunjukkan dia dimaksudkan mengikuti pola
yang sama, bukan pola Category.

## Decision

Dua pola delete yang berbeda, sesuai bentuk data masing-masing:

- **Category**: `delete()` permanen di `CategoryRepositoryInterface`, dijaga
  `isInUse()` (cek ke tabel `products`) supaya tidak menghapus kategori
  yang masih dipakai - kalau masih dipakai, `CategoryService::deleteCategory()`
  melempar `ConflictException`.
- **Warehouse**: `setActive(int id, bool isActive)` di
  `WarehouseRepositoryInterface`, tidak ada `delete()`/`isInUse()` sama
  sekali. Toggle status tidak pernah memeriksa pemakaian karena baris
  gudangnya tetap ada - tidak ada risiko merusak referensi.

## Consequences

**Nilai tambah nyata:**
- Pola masing-masing sesuai kapasitas skema-nya sendiri - Category memang
  tidak punya kolom untuk "nonaktif", jadi memaksanya ikut pola Warehouse
  berarti menambah migrasi skema yang tidak diminta brief.
- Warehouse akan dirujuk banyak tabel transaksi ke depan (`ProductStock`,
  `PurchaseOrder.warehouse_id`, `SalesOrder.warehouse_id`) - menonaktifkan
  menghindari seluruh kelas masalah integritas referensial tanpa perlu
  logic pengecekan tambahan seperti `isInUse()` Category. Menghapus
  permanen gudang yang riwayat stok/order-nya masih menunjuk ke situ akan
  merusak data historis yang harus tetap bisa ditelusuri.
- Dua `RepositoryInterface` yang bentuknya berbeda justru lebih jujur
  merepresentasikan bahwa keduanya memang perilaku bisnis yang berbeda -
  memaksakan method yang identik (`delete()`/`isInUse()` di keduanya) akan
  jadi abstraksi palsu (kedua interface terlihat sama padahal aturan
  bisnisnya tidak).

**Trade-off yang disadari:**
- Dua modul Master Data yang terlihat mirip di UI (form 2 field, list
  dengan search/sort/pagination) punya kontrak Repository yang tidak
  seragam - siapa pun yang membaca kode harus tahu bedanya per-entity,
  bukan bisa mengasumsikan satu pola berlaku untuk semua Master Data.
  Mitigasi: didokumentasikan eksplisit di sini dan di
  `docs/architecture/class-diagram-as-built.md` (Diagram C vs Diagram D),
  bukan dibiarkan jadi kejutan saat kode dibaca.
- Keputusan ini perlu ditinjau ulang saat Product/Supplier/Customer
  dikerjakan - kalau ternyata salah satu dari mereka butuh delete permanen
  juga (mis. data uji yang belum pernah dipakai sama sekali), pola mana
  yang dipakai harus diputuskan sadar, bukan ikut-ikutan salah satu tanpa
  alasan.
