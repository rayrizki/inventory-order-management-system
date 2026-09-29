# Refactoring Log (DESIGN-03)

Tiga entri pertama terjadi di dalam satu vertical slice yang sama (Kategori) -
ditemukan dan diperbaiki sambil fitur berkembang, bukan dicari-cari di akhir
untuk memenuhi checklist. Entri 4-6 berasal dari audit menyeluruh terhadap
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
