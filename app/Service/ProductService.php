<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\ProductRepositoryInterface;

final class ProductService
{
    public const PER_PAGE = 10;

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly CategoryRepositoryInterface $categories,
    ) {
    }

    /**
     * @return Product[]
     */
    public function listProducts(
        ?string $search = null,
        ?int $categoryId = null,
        ?bool $isActive = null,
        int $page = 1,
        int $perPage = self::PER_PAGE,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $perPage = max(1, $perPage);
        $offset = (max(1, $page) - 1) * $perPage;

        return $this->products->listAll($this->normalizeSearch($search), $categoryId, $isActive, $perPage, $offset, $sortBy, $sortDir);
    }

    public function countProducts(?string $search = null, ?int $categoryId = null, ?bool $isActive = null): int
    {
        return $this->products->countAll($this->normalizeSearch($search), $categoryId, $isActive);
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
     * @param array{sku: string, name: string, category_id: string, unit: string, buy_price: string, sell_price: string, reorder_point: string} $input
     */
    public function createProduct(array $input): Product
    {
        $data = $this->validate($input, null);

        return $this->products->save(new Product(
            null,
            $data['sku'],
            $data['name'],
            $data['category_id'],
            $data['unit'],
            $data['buy_price'],
            $data['sell_price'],
            $data['reorder_point'],
            null,
            true,
        ));
    }

    /**
     * @param array{sku: string, name: string, category_id: string, unit: string, buy_price: string, sell_price: string, reorder_point: string} $input
     */
    public function updateProduct(int $id, array $input): Product
    {
        $existing = $this->getProductById($id);
        $data = $this->validate($input, $id);

        return $this->products->save(new Product(
            $id,
            $data['sku'],
            $data['name'],
            $data['category_id'],
            $data['unit'],
            $data['buy_price'],
            $data['sell_price'],
            $data['reorder_point'],
            $existing->imagePath,
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
     * @param array{sku: string, name: string, category_id: string, unit: string, buy_price: string, sell_price: string, reorder_point: string} $input
     * @return array{sku: string, name: string, category_id: int, unit: string, buy_price: float, sell_price: float, reorder_point: int}
     */
    private function validate(array $input, ?int $excludeId): array
    {
        $errors = [];

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

        if (!is_numeric($buyPriceRaw) || (float) $buyPriceRaw < 0) {
            $errors['buy_price'] = 'Harga beli harus angka dan tidak boleh negatif.';
        }

        if (!is_numeric($sellPriceRaw) || (float) $sellPriceRaw < 0) {
            $errors['sell_price'] = 'Harga jual harus angka dan tidak boleh negatif.';
        }

        if (!ctype_digit($reorderPointRaw)) {
            $errors['reorder_point'] = 'Reorder point harus bilangan bulat dan tidak boleh negatif.';
        }

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
        ];
    }

    private function normalizeSearch(?string $search): ?string
    {
        $search = trim((string) $search);

        return $search === '' ? null : $search;
    }
}
