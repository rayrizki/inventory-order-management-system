# Refactoring Log (DESIGN-03)

Tiga entri pertama terjadi di dalam satu vertical slice yang sama (Kategori) -
ditemukan dan diperbaiki sambil fitur berkembang, bukan dicari-cari di akhir
untuk memenuhi checklist. Entri 4-10 berasal dari audit menyeluruh terhadap
brief setelah seluruh slice selesai: ketiganya memperbaiki kode lama yang
sudah berjalan, bukan fitur yang sedang dikerjakan (Boy Scout Rule).

## 1. Duplicate Code -> Extract Constant (`CategoryController::LIST_URL`)

**Smell**: string literal `'/categories'` ditulis berulang di setiap
redirect (`create()`, `update()`, `delete()`) - static analysis (SonarLint,
`php:S1192`) menandai duplikasi ini begitu redirect ketiga (untuk `delete()`)
ditambahkan.

**Teknik**: Extract Constant.

Sebelum:
```php
// create()
header('Location: /categories', true, 303);
// update()
header('Location: /categories', true, 303);
// delete()
header('Location: /categories', true, 303);
```

Sesudah:
```php
private const LIST_URL = '/categories';

// create()
header('Location: ' . self::LIST_URL . '?status=created', true, 303);
// update()
header('Location: ' . self::LIST_URL . '?status=updated', true, 303);
// delete()
header('Location: ' . self::LIST_URL . '?status=deleted', true, 303);
```

Satu sumber kebenaran untuk URL daftar Kategori - kalau rute ini pindah
suatu saat, cukup ubah satu tempat.

## 2. Primitive Obsession -> Data-driven lookup table (`STATUS_MESSAGES`)

**Smell**: fitur "tampilkan pesan setelah delete ditolak" awalnya
diimplementasikan sebagai satu flag boolean tunggal (`$deleteBlocked`) plus
teks pesan yang ditulis langsung di view. Begitu fitur diperluas untuk juga
menampilkan pesan sukses create/update/delete (masing-masing warna beda),
pola "satu flag boolean + satu pesan hardcoded" akan butuh flag baru + blok
`if` baru di Controller **dan** di view untuk setiap status baru - gejala
klasik *Shotgun Surgery* kalau diteruskan.

**Teknik**: Replace Conditional with data-driven map (tabel lookup),
dipasangkan dengan Extract Method kecil (`self::STATUS_MESSAGES[$status] ?? null`).

Sebelum:
```php
// Controller
$deleteBlocked = isset($_GET['delete_blocked']);
```
```php
<!-- View -->
<?php if ($deleteBlocked): ?>
    <p class="form-error" role="alert">
        Kategori tidak bisa dihapus karena masih dipakai oleh produk lain...
    </p>
<?php endif; ?>
```

Sesudah:
```php
// Controller
private const STATUS_MESSAGES = [
    'created' => ['type' => 'success', 'text' => 'Kategori berhasil ditambahkan.'],
    'updated' => ['type' => 'info', 'text' => 'Kategori berhasil diperbarui.'],
    'deleted' => ['type' => 'warning', 'text' => 'Kategori berhasil dihapus.'],
    'delete_blocked' => ['type' => 'error', 'text' => '...'],
];
// ...
$statusMessage = self::STATUS_MESSAGES[$_GET['status'] ?? ''] ?? null;
```
```php
<!-- View - satu blok generik untuk keempat status -->
<?php if ($statusMessage !== null): ?>
    <p class="form-<?= htmlspecialchars($statusMessage['type']) ?>" role="alert">
        <?= htmlspecialchars($statusMessage['text']) ?>
    </p>
<?php endif; ?>
```

Menambah status baru sekarang cukup satu baris di `STATUS_MESSAGES`, tidak
ada percabangan baru di Controller maupun view.

## 3. Duplicate Code -> Extract Method (`normalizeSearch()` / `filtered()`)

**Smell**: logic "kosongkan whitespace, `''` dianggap `null`" untuk parameter
`$search` awalnya ditulis ulang identik di `CategoryService::listCategories()`
dan `CategoryService::countCategories()`. Pola serupa juga terjadi di
`InMemoryCategoryRepository`, tempat filter-by-search ditulis ulang di
`listAll()` dan `countAll()`.

**Teknik**: Extract Method.

Sebelum (di `CategoryService`, ditulis dua kali):
```php
public function listCategories(?string $search = null, ...): array
{
    $search = trim((string) $search);
    return $this->categories->listAll($search === '' ? null : $search, ...);
}

public function countCategories(?string $search = null): int
{
    $search = trim((string) $search);
    return $this->categories->countAll($search === '' ? null : $search);
}
```

Sesudah:
```php
public function listCategories(?string $search = null, ...): array
{
    return $this->categories->listAll($this->normalizeSearch($search), ...);
}

public function countCategories(?string $search = null): int
{
    return $this->categories->countAll($this->normalizeSearch($search));
}

private function normalizeSearch(?string $search): ?string
{
    $search = trim((string) $search);
    return $search === '' ? null : $search;
}
```

`InMemoryCategoryRepository` mengikuti pola sama: filter-by-search
diekstrak jadi `private function filtered(?string $search): array`, dipakai
ulang oleh `listAll()` (di-`array_slice` untuk pagination) dan `countAll()`
(cukup `count()`).

## Audit SRP

**Kelas**: `public/assets/js/list-controls.js` (draf awal).

Saat fitur hapus kategori pertama kali dibuat, konfirmasi "yakin ingin
menghapus?" ditangani dengan `window.confirm()` bawaan browser, dan logic-nya
ditaruh di `list-controls.js` - file yang sama yang menangani auto-submit
select "baris per halaman". Begitu user minta konfirmasi ini diganti jadi
modal custom (bukan `window.confirm()`) - dengan state (`pendingForm`),
elemen dialog bersama (`#confirm-dialog`), dan siklus buka/tutup sendiri -
`list-controls.js` mulai memikul dua tanggung jawab yang tidak berhubungan:
*kontrol widget list* (pagination, per-page) dan *konfirmasi aksi
destruktif* (state modal, dialog lifecycle).

**Cara dipecah**: logic konfirmasi diekstrak penuh ke file baru
`public/assets/js/confirm-dialog.js`, dipasang sebagai `<script>` terpisah
di `views/layout/shell-end.php`. `list-controls.js` sekarang cuma berisi
satu tanggung jawab (`data-auto-submit`); `confirm-dialog.js` berisi
tanggung jawab satunya (`data-confirm` + `#confirm-dialog`). Konvensi
`data-*` yang dipakai keduanya tetap kompatibel dengan `modal-form.js`
(dialog create/edit) tanpa saling tumpang tindih.

## 4. Duplicate Code + Primitive Obsession -> Extract Method ke Entity (`PurchaseOrder::number()` / `SalesOrder::number()`)

**Smell**: nomor order yang dilihat user (`PO-000012`) dirakit ulang dengan
`str_pad()` di empat view (`purchase-orders/index.php`, `purchase-orders/show.php`,
`sales-orders/index.php`, `sales-orders/show.php`) dan sekali lagi di
`ReportService`. Format yang sama disalin lima kali, dan id-nya diperlakukan
sebagai angka mentah yang formatnya jadi urusan setiap pemanggil.

Akibat nyatanya bukan sekadar duplikasi: pencarian FIND-01 mencocokkan id
mentah (`CAST(po.id AS CHAR) LIKE ...`), sehingga mengetik nomor **persis
seperti yang tampil di layar** justru tidak menemukan apa pun. Tampilan dan
pencarian memakai dua definisi "nomor order" yang berbeda tanpa ada yang
menyadari.

**Teknik**: Extract Method (dipindah ke Entity, bersama konstanta formatnya).

Sebelum:
```php
// views/purchase-orders/index.php
<td>PO-<?= str_pad((string) $purchaseOrder->id, 6, '0', STR_PAD_LEFT) ?></td>

// views/purchase-orders/show.php
$pageTitle = 'PO-' . str_pad((string) $purchaseOrder->id, 6, '0', STR_PAD_LEFT);

// app/Service/ReportService.php
'nomor' => 'PO-' . str_pad((string) $po->id, 6, '0', STR_PAD_LEFT),
```

Sesudah:
```php
// app/Entity/PurchaseOrder.php
public const NUMBER_PREFIX = 'PO-';
public const NUMBER_DIGITS = 6;

public function number(): string
{
    return self::NUMBER_PREFIX . str_pad((string) $this->id, self::NUMBER_DIGITS, '0', STR_PAD_LEFT);
}

// pemanggil
<td><?= htmlspecialchars($purchaseOrder->number()) ?></td>
$pageTitle = $purchaseOrder->number();
'nomor' => $po->number(),
```

Repository memakai konstanta yang sama (`LPAD(po.id, :number_digits, '0')`)
untuk mencocokkan nomor ber-padding, dan Service membuang awalan `PO-` yang
diketik user. Tampilan, laporan, dan pencarian sekarang tidak bisa lagi
berbeda karena ketiganya membaca satu definisi.

## 5. Feature Envy + pelanggaran SRP -> Move Responsibility ke Controller (`views/layout/shell-start.php`)

**Smell**: partial layout merakit sendiri infrastrukturnya untuk mendapatkan
user yang sedang login - sebuah view yang melakukan wiring dan menjalankan
ulang pemeriksaan otorisasi. Persis definisi Feature Envy: view lebih sibuk
mengurus milik lapisan lain daripada tugasnya sendiri, dan melanggar ARCH-01
("business logic tidak boleh bergantung langsung pada session").

Ini juga yang membuat kesalahan jadi mahal: saat `AuthGuard` bertambah satu
dependency, tiga halaman langsung 500 dan **PHPStan tidak menangkapnya**,
karena `views/` memang di luar `paths` di `phpstan.neon`.

**Teknik**: Move Responsibility (yang memakai, bukan yang menampilkan, yang
menyediakan data).

Sebelum:
```php
// views/layout/shell-start.php
$shellSession = new \App\Session\PhpSessionAdapter();
$currentUser = (new \App\Session\AuthGuard($shellSession))->requireLogin();
```

Sesudah:
```php
// views/layout/shell-start.php
/** @var \App\Session\CurrentUser $currentUser Hasil AuthGuard::requireLogin(). */
assert(isset($currentUser), 'shell-start.php butuh $currentUser dari Controller');

// app/Controller/PurchaseOrderController.php (pola yang sama di seluruh Controller)
$currentUser = $this->guard->requireLogin();
$this->guard->requireRole($currentUser, self::ALLOWED_ROLES);
```

Controller memang sudah memanggil `requireLogin()` lebih dulu; sebelumnya
hasilnya dibuang begitu saja lalu dicari ulang oleh view. Dua helper
`renderShow()` di PO/SO ikut menerimanya sebagai parameter.

## 6. Duplicate Code -> Extract Trait (`NormalizesSearchTerm`)

**Smell**: delapan Service menulis ulang method privat yang identik
byte-per-byte untuk merapikan kata kunci pencarian. Entri refactor
sebelumnya sempat mencatat ekstraksi serupa, tapi hanya di dalam satu kelas -
duplikasi lintas kelas tetap dibiarkan sampai audit ini.

**Teknik**: Extract Trait (bukan Extract Class + constructor injection -
aturannya stateless dan tanpa dependency, jadi menambah objek beserta
wiring-nya di delapan Service hanya menambah upacara).

Sebelum (diulang di 8 file):
```php
private function normalizeSearch(?string $search): ?string
{
    $search = trim((string) $search);

    return $search === '' ? null : $search;
}
```

Sesudah:
```php
// app/Service/NormalizesSearchTerm.php
trait NormalizesSearchTerm
{
    private function normalizeSearch(?string $search): ?string
    {
        $search = trim((string) $search);

        return $search === '' ? null : $search;
    }
}

// app/Service/CategoryService.php
final class CategoryService
{
    use NormalizesSearchTerm;

// app/Service/PurchaseOrderService.php - punya aturan tambahan, jadi di-alias
final class PurchaseOrderService
{
    use NormalizesSearchTerm {
        normalizeSearch as private normalizeSearchTerm;
    }

    private function normalizeSearch(?string $search): ?string
    {
        $search = trim((string) $search);
        $prefix = PurchaseOrder::NUMBER_PREFIX;

        if (stripos($search, $prefix) === 0) {
            $search = substr($search, strlen($prefix));
        }

        return $this->normalizeSearchTerm($search);
    }
```

Perilaku tidak berubah (192 test tetap lulus, pencarian nomor order, nama
supplier, dan nama produk diuji manual). Commit: `refactor: extract the
duplicated search-term normaliser into a trait`.

## 7. Duplicate Code -> Extract Template (`views/shared/supplier-customer-list.php` dan `-form.php`)

**Smell**: seluruh halaman Supplier dan Customer adalah salinan satu sama
lain. Diukur dengan mengganti kata "Supplier" jadi "Customer" lalu
membandingkan: `index.php` 337 baris dengan **2 baris berbeda**, `form.php`
60 baris dengan **0 baris berbeda**. Dua baris yang berbeda itu pun hanya
teks empty state ("...mulai mencatat purchase order" vs "...sales order").

Riwayat git menunjukkan biayanya nyata: keenam file kedua modul hanya pernah
disentuh oleh tiga commit yang sama persis - tidak pernah ada perubahan yang
hanya mengenai salah satunya, artinya setiap perbaikan memang dikerjakan dua
kali.

**Teknik**: Extract Template (bukan Extract Class - yang diangkat markup,
bukan perilaku), dengan modul asal menjadi adapter tipis berisi konfigurasi.

Sebelum (dua file 337 baris yang isinya sama):
```php
// views/suppliers/index.php
$pageTitle = 'Supplier';
$activeNav = 'suppliers';
require __DIR__ . '/../layout/shell-start.php';
// ... 330 baris markup ...
<h1>Supplier</h1>
<p class="page-header__meta"><?= $totalSuppliers ?> supplier</p>
<form method="get" action="/suppliers" class="search-box">
// ... dst, disalin lagi di views/customers/index.php dengan kata diganti
```

Sesudah:
```php
// views/suppliers/index.php - seluruh isinya
$records = $suppliers;
$totalRecords = $totalSuppliers;
$entityLabel = 'Supplier';
$entityKey = 'supplier';
$listUrl = '/suppliers';
$navKey = 'suppliers';
$emptyStateContext = 'purchase order';

require __DIR__ . '/../shared/supplier-customer-list.php';
```

Total view turun dari 794 menjadi 508 baris. **Hanya lapisan tampilan yang
dibagi** - Entity, Repository, dan tabelnya tetap terpisah karena di situlah
foreign key `purchase_orders.supplier_id` vs `sales_orders.customer_id`
menjamin PO tidak mungkin menunjuk ke customer. Alasan lengkap beserta
pemicu kapan pembagian ini harus dibubarkan: ADR-0008.

**Jebakan yang ditemukan saat mengerjakannya**: template di-`require`
sehingga berbagi scope dengan `shell-start.php`. Versi pertama memakai
`$items`/`$item` - nama yang sudah dipakai layout untuk loop menu sidebar -
sehingga seluruh baris tabel tampil kosong tanpa satu pun pesan error.
Ketahuan karena HTML hasil render dibandingkan dengan rekaman sebelum
refaktor, bukan karena test atau static analysis (lihat tech-debt #17:
`views/` memang di luar cakupan PHPStan). Diganti jadi `$records`/`$record`,
dan daftar nama yang sudah dipesan layout dicantumkan di docblock template.

**Bukti tidak ada perubahan perilaku**: HTML delapan halaman (daftar kedua
modul, modal tambah, form ubah, pencarian dengan sort + per_page, filter
status) direkam sebelum dan sesudah, dibandingkan baris per baris - **nol
perbedaan**. Aksi POST diuji terpisah (toggle aktif/nonaktif dua kali sampai
kembali ke nilai semula). 192 test tetap lulus.

## 8. Duplicate Code + risiko lupa `exit` -> Extract Trait (`SendsRedirects`)

**Smell**: pasangan `header('Location: ...', true, 303); exit;` ditulis 44
kali di sembilan Controller; literal `"Location: "` sendiri berulang 4-9 kali
per file (SonarQube `php:S1192`).

Yang membuatnya lebih dari sekadar duplikasi: `header()` **tidak**
menghentikan eksekusi. Lupa menulis `exit` setelahnya membuat kode di
bawahnya tetap berjalan dan body response ikut terkirim di belakang header
redirect - bug yang tidak terlihat sampai ada yang memeriksa response mentah.

**Teknik**: Extract Trait, dengan method bertipe `never`.

Sebelum (diulang 44 kali):
```php
header('Location: ' . self::LIST_URL . '?status=created', true, 303);
exit;
```

Sesudah:
```php
// app/Controller/SendsRedirects.php
trait SendsRedirects
{
    private function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}

// pemanggil
$this->redirect(self::LIST_URL . '?status=created');
```

Tipe `never` membuat kelalaian itu tidak mungkin lagi, dan PHPStan ikut
memverifikasi tidak ada kode tak terjangkau sesudahnya. Blok `try/catch` yang
dulu menaruh satu `exit;` bersama di luar catch kini tiap cabangnya
mengembalikan sendiri - perilakunya sama, alurnya lebih jelas.

Diverifikasi lewat HTTP, bukan hanya test: login benar (303 ke `/dashboard`),
login salah (303 ke `/login`), CRUD (`?status=created`), dan cabang
`try/catch` (`?result=cannot_transition`).

## 9. Long Method -> Extract Method (`validate()` di PurchaseOrderService, SalesOrderService, ProductService)

**Smell**: cognitive complexity 42, 40, dan 17 (ambang 15, SonarQube
`php:S3776`). Ketiganya menggabungkan dua hal berbeda dalam satu method -
validasi header (satu nilai per field) dan validasi baris item (N baris yang
masing-masing punya aturan sendiri).

**Teknik**: Extract Method, dua tingkat, mengikuti batas yang memang ada di
domainnya.

Sebelum (PurchaseOrderService, ~65 baris dalam satu method):
```php
private function validate(array $input): array
{
    // ... validasi supplier, gudang, tanggal ...
    $items = [];
    if ($itemsInput === []) {
        $errors['items'] = '...';
    } else {
        foreach ($itemsInput as $index => $itemInput) {
            // ... 3 pemeriksaan per baris, flag $rowValid ...
        }
        if ($items === [] && !isset($errors['items'])) { ... }
    }
    // ...
}
```

Sesudah:
```php
private function validate(array $input): array
{
    // ... validasi header saja ...
    [$items, $itemErrors] = $this->validateItems($itemsInput);
    $errors += $itemErrors;
    // ...
}

private function validateItems(array $itemsInput): array   // loop + rekap
private function validateItemRow(int $index, mixed $itemInput): array  // satu baris
```

`validateItemRow()` mengembalikan `[item|null, errors]` sehingga flag
`$rowValid` hilang - baris yang valid mengembalikan itemnya, yang tidak
mengembalikan kumpulan errornya. Pengumpulan SELURUH error tetap
dipertahankan (VAL-01: input yang sudah diisi tidak boleh hilang), jadi
perilakunya tidak berubah - dibuktikan lewat HTTP dengan submit yang header
dan itemnya sama-sama salah: kelima pesan error muncul bersamaan.

## 10. Long Parameter List -> Introduce Parameter Object (`ProductFilter`)

**Smell**: `ProductRepositoryInterface::listAll()` dan
`ProductService::listProducts()` sama-sama punya 8 parameter (SonarQube
`php:S107`).

Tapi jumlah parameter bukan masalah sebenarnya. Masalahnya: `listAll()` dan
`countAll()` **wajib** dipanggil dengan empat nilai filter yang identik -
kalau berbeda, jumlah halaman pagination tidak cocok dengan baris yang
benar-benar tampil. Kecocokan itu sebelumnya hanya dijaga kebiasaan, karena
keempat nilainya dikirim terpisah ke dua method berbeda.

**Teknik**: Introduce Parameter Object.

Sebelum:
```php
$totalProducts = $this->productService->countProducts($search, $categoryId, $isActive, $stockStatusFilter);
$products = $this->productService->listProducts($search, $categoryId, $isActive, $stockStatusFilter, $page, $perPage, $sortBy, $sortDir);
```

Sesudah:
```php
$filter = new ProductFilter($search, $categoryId, $isActive, $stockStatusFilter);

$totalProducts = $this->productService->countProducts($filter);
$products = $this->productService->listProducts($filter, $page, $perPage, $sortBy, $sortDir);
```

Hanya dimensi PENYARINGAN yang dibungkus; limit/offset/sort tetap parameter
tersendiri karena itu urusan penyajian - `countAll()` butuh filternya tapi
tidak pernah butuh pagination. Normalisasi kata kunci (trim, string kosong
jadi null) pindah ke konstruktor `ProductFilter`, sehingga tidak ada pemanggil
yang bisa lupa melakukannya.
