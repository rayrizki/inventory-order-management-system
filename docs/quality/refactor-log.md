# Refactoring Log (DESIGN-03)

Tiga entri di bawah semuanya terjadi di dalam satu vertical slice yang sama
(Kategori) - ditemukan dan diperbaiki sambil fitur berkembang, bukan
dicari-cari di akhir untuk memenuhi checklist.

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
