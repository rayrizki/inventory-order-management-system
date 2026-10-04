<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\ProductFilter;
use App\Repository\ProductRepositoryInterface;

final class ProductService
{
    use NormalizesSearchTerm;

    public const PER_PAGE = 10;

    /** PRD-01: ukuran maksimum gambar produk yang diunggah. */
    private const MAX_IMAGE_SIZE_BYTES = 2 * 1024 * 1024;

    /**
     * Whitelist tipe gambar - key dicocokkan terhadap MIME sungguhan hasil
     * `finfo` (sniffing isi file), BUKAN terhadap ekstensi nama file atau
     * Content-Type yang dikirim browser (keduanya bisa dipalsukan). Value
     * dipakai sebagai ekstensi nama file acak yang disimpan.
     */
    private const ALLOWED_IMAGE_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly CategoryRepositoryInterface $categories,
    ) {
    }

    /**
     * @return Product[]
     */
    public function listProducts(
        ProductFilter $filter = new ProductFilter(),
        int $page = 1,
        int $perPage = self::PER_PAGE,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $perPage = max(1, $perPage);
        $offset = (max(1, $page) - 1) * $perPage;

        return $this->products->listAll($filter, $perPage, $offset, $sortBy, $sortDir);
    }

    public function countProducts(ProductFilter $filter = new ProductFilter()): int
    {
        return $this->products->countAll($filter);
    }

    public function getProductById(int $id): Product
    {
        $product = $this->products->findById($id);

        if ($product === null) {
            throw new NotFoundException();
        }

        return $product;
    }

    /**
     * Dipakai API-01 (GET /api/products/{sku}/availability) - lookup by SKU
     * dari URL, bukan id numerik.
     */
    public function getProductBySku(string $sku): Product
    {
        $product = $this->products->findBySku($sku);

        if ($product === null) {
            throw new NotFoundException();
        }

        return $product;
    }

    /**
     * @param array{sku: string, name: string, category_id: string, unit: string, buy_price: string, sell_price: string, reorder_point: string, image?: array{name: string, type: string, tmp_name: string, error: int, size: int}|null} $input
     */
    public function createProduct(array $input): Product
    {
        $data = $this->validate($input, null);
        $imagePath = $this->storeImage($data['image']);

        return $this->products->save(new Product(
            null,
            $data['sku'],
            $data['name'],
            $data['category_id'],
            $data['unit'],
            $data['buy_price'],
            $data['sell_price'],
            $data['reorder_point'],
            $imagePath,
            true,
        ));
    }

    /**
     * @param array{sku: string, name: string, category_id: string, unit: string, buy_price: string, sell_price: string, reorder_point: string, image?: array{name: string, type: string, tmp_name: string, error: int, size: int}|null} $input
     */
    public function updateProduct(int $id, array $input): Product
    {
        $existing = $this->getProductById($id);
        $data = $this->validate($input, $id);

        // Gambar baru menggantikan yang lama (dan file lama dihapus dari
        // disk); kalau tidak ada gambar baru diunggah, gambar lama
        // dipertahankan - pola yang sama dengan UserService::updateUser()
        // mempertahankan hash password lama saat field password dikosongkan.
        $newImagePath = $this->storeImage($data['image']);
        $imagePath = $newImagePath ?? $existing->imagePath;
        if ($newImagePath !== null && $existing->imagePath !== null) {
            $this->deleteImageFile($existing->imagePath);
        }

        return $this->products->save(new Product(
            $id,
            $data['sku'],
            $data['name'],
            $data['category_id'],
            $data['unit'],
            $data['buy_price'],
            $data['sell_price'],
            $data['reorder_point'],
            $imagePath,
            $existing->isActive,
        ));
    }

    public function setActive(int $id, bool $isActive): void
    {
        $this->getProductById($id);
        $this->products->setActive($id, $isActive);
    }

    /**
     * Validasi seluruh field sekaligus (bukan berhenti di error pertama) supaya
     * form bisa menampilkan semua kesalahan dalam satu kali submit (VAL-01).
     *
     * Tipe param sengaja "optional" (bukan wajib semua key ada) - $input ini
     * boundary ke $_POST/$_FILES lewat Controller::readInput(), sama seperti
     * penjelasan di PurchaseOrderService::validate().
     *
     * @param array{sku?: string, name?: string, category_id?: string, unit?: string, buy_price?: string, sell_price?: string, reorder_point?: string, image?: array{name: string, type: string, tmp_name: string, error: int, size: int}|null} $input
     * @return array{sku: string, name: string, category_id: int, unit: string, buy_price: float, sell_price: float, reorder_point: int, image: array{tmp_name: string, extension: string}|null}
     */
    private function validate(array $input, ?int $excludeId): array
    {
        $errors = [];

        [$image, $imageError] = $this->validateImage($input['image'] ?? null);
        if ($imageError !== null) {
            $errors['image'] = $imageError;
        }

        $sku = trim($input['sku']);
        $name = trim($input['name']);
        $unit = trim($input['unit']);
        $categoryIdRaw = trim($input['category_id']);
        $buyPriceRaw = trim($input['buy_price']);
        $sellPriceRaw = trim($input['sell_price']);
        $reorderPointRaw = trim($input['reorder_point']);

        if ($sku === '') {
            $errors['sku'] = 'SKU wajib diisi.';
        } else {
            $existing = $this->products->findBySku($sku);
            if ($existing !== null && $existing->id !== $excludeId) {
                $errors['sku'] = 'SKU sudah dipakai produk lain.';
            }
        }

        if ($name === '') {
            $errors['name'] = 'Nama produk wajib diisi.';
        }

        if ($unit === '') {
            $errors['unit'] = 'Unit wajib diisi.';
        }

        $categoryId = ctype_digit($categoryIdRaw) ? (int) $categoryIdRaw : null;
        if ($categoryId === null || $this->categories->findById($categoryId) === null) {
            $errors['category_id'] = 'Kategori wajib dipilih dan valid.';
        }

        $errors += $this->validateNumericFields($buyPriceRaw, $sellPriceRaw, $reorderPointRaw);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'sku' => $sku,
            'name' => $name,
            'category_id' => $categoryId,
            'unit' => $unit,
            'buy_price' => (float) $buyPriceRaw,
            'sell_price' => (float) $sellPriceRaw,
            'reorder_point' => (int) $reorderPointRaw,
            'image' => $image,
        ];
    }

    /**
     * PRD-01: harga beli, harga jual, dan reorder point harus angka >= 0.
     * Dipisah dari validate() karena ketiganya satu aturan yang sama diulang
     * tiga kali, sementara sisanya aturan per-field yang berbeda-beda -
     * memisahkannya juga menurunkan kerumitan validate() (php:S3776).
     *
     * @return array<string, string> kosong kalau ketiganya valid
     */
    private function validateNumericFields(string $buyPriceRaw, string $sellPriceRaw, string $reorderPointRaw): array
    {
        $errors = [];

        if (!is_numeric($buyPriceRaw) || (float) $buyPriceRaw < 0) {
            $errors['buy_price'] = 'Harga beli harus angka dan tidak boleh negatif.';
        }

        if (!is_numeric($sellPriceRaw) || (float) $sellPriceRaw < 0) {
            $errors['sell_price'] = 'Harga jual harus angka dan tidak boleh negatif.';
        }

        if (!ctype_digit($reorderPointRaw)) {
            $errors['reorder_point'] = 'Reorder point harus bilangan bulat dan tidak boleh negatif.';
        }

        return $errors;
    }

    /**
     * Murni logic (tidak menyentuh disk) untuk bagian error/ukuran - bisa
     * di-unit-test tanpa file sungguhan. Deteksi MIME asli (`finfo`) baru
     * dijalankan kalau tmp_name benar-benar file upload sungguhan
     * (`is_uploaded_file()`) - bagian ini cuma bisa dilalui lewat upload
     * HTTP multipart nyata, jadi diverifikasi manual (curl), bukan PHPUnit,
     * konsisten dengan pola pemisahan logic-murni vs I/O di ADR-0005.
     *
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int}|null $file
     * @return array{0: array{tmp_name: string, extension: string}|null, 1: string|null} [data gambar tervalidasi, pesan error]
     */
    private function validateImage(?array $file): array
    {
        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [null, 'Gagal mengunggah gambar (kode error: ' . $file['error'] . ').'];
        }

        if ($file['size'] > self::MAX_IMAGE_SIZE_BYTES) {
            return [null, 'Ukuran gambar maksimal 2MB.'];
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            return [null, 'Berkas gambar tidak valid.'];
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if ($mimeType === false || !isset(self::ALLOWED_IMAGE_MIME_TYPES[$mimeType])) {
            return [null, 'Format gambar harus JPEG, PNG, atau WebP.'];
        }

        return [['tmp_name' => $file['tmp_name'], 'extension' => self::ALLOWED_IMAGE_MIME_TYPES[$mimeType]], null];
    }

    /**
     * @param array{tmp_name: string, extension: string}|null $image
     */
    private function storeImage(?array $image): ?string
    {
        if ($image === null) {
            return null;
        }

        $randomName = bin2hex(random_bytes(16)) . '.' . $image['extension'];
        move_uploaded_file($image['tmp_name'], $this->uploadDir() . '/' . $randomName);

        return '/uploads/products/' . $randomName;
    }

    private function deleteImageFile(string $imagePath): void
    {
        $path = $this->uploadDir() . '/' . basename($imagePath);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function uploadDir(): string
    {
        return __DIR__ . '/../../public/uploads/products';
    }
}
