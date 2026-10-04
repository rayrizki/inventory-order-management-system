<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Category;
use App\Entity\Product;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\InMemoryCategoryRepository;
use App\Repository\InMemoryProductRepository;
use App\Repository\ProductFilter;
use App\Service\ProductService;
use PHPUnit\Framework\TestCase;

final class ProductServiceTest extends TestCase
{
    private function makeService(?InMemoryProductRepository $products = null, ?InMemoryCategoryRepository $categories = null): ProductService
    {
        $categories ??= new InMemoryCategoryRepository([new Category(1, 'Elektronik', null)]);

        return new ProductService($products ?? new InMemoryProductRepository(), $categories);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'SKU-001',
            'name' => 'Kabel HDMI 2m',
            'category_id' => '1',
            'unit' => 'pcs',
            'buy_price' => '25000',
            'sell_price' => '40000',
            'reorder_point' => '10',
        ], $overrides);
    }

    public function testCreateProductSucceedsWithValidInput(): void
    {
        $service = $this->makeService();

        $product = $service->createProduct($this->validInput());

        self::assertNotNull($product->id);
        self::assertSame('SKU-001', $product->sku);
        self::assertSame(1, $product->categoryId);
        self::assertSame(25000.0, $product->buyPrice);
        self::assertTrue($product->isActive);
    }

    public function testCreateProductRejectsEmptyRequiredFields(): void
    {
        $service = $this->makeService();

        try {
            $service->createProduct($this->validInput(['sku' => '', 'name' => '', 'unit' => '']));
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            self::assertArrayHasKey('sku', $errors);
            self::assertArrayHasKey('name', $errors);
            self::assertArrayHasKey('unit', $errors);
        }
    }

    public function testCreateProductRejectsUnknownCategory(): void
    {
        $service = $this->makeService();

        $this->expectException(ValidationException::class);

        $service->createProduct($this->validInput(['category_id' => '999']));
    }

    public function testCreateProductRejectsNegativePrices(): void
    {
        $service = $this->makeService();

        try {
            $service->createProduct($this->validInput(['buy_price' => '-100', 'sell_price' => '-1']));
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            self::assertArrayHasKey('buy_price', $errors);
            self::assertArrayHasKey('sell_price', $errors);
        }
    }

    public function testCreateProductRejectsNonNumericReorderPoint(): void
    {
        $service = $this->makeService();

        $this->expectException(ValidationException::class);

        $service->createProduct($this->validInput(['reorder_point' => 'banyak']));
    }

    public function testCreateProductRejectsDuplicateSku(): void
    {
        $categories = new InMemoryCategoryRepository([new Category(1, 'Elektronik', null)]);
        $products = new InMemoryProductRepository([
            new Product(1, 'SKU-001', 'Produk Lama', 1, 'pcs', 10000, 15000, 5, null, true),
        ]);
        $service = $this->makeService($products, $categories);

        $this->expectException(ValidationException::class);

        $service->createProduct($this->validInput(['sku' => 'SKU-001']));
    }

    public function testUpdateProductAllowsKeepingOwnSku(): void
    {
        $categories = new InMemoryCategoryRepository([new Category(1, 'Elektronik', null)]);
        $products = new InMemoryProductRepository([
            new Product(1, 'SKU-001', 'Produk Lama', 1, 'pcs', 10000, 15000, 5, null, true),
        ]);
        $service = $this->makeService($products, $categories);

        $updated = $service->updateProduct(1, $this->validInput(['name' => 'Produk Baru']));

        self::assertSame('Produk Baru', $updated->name);
        self::assertSame('SKU-001', $updated->sku);
    }

    public function testUpdateProductThrowsNotFoundForUnknownId(): void
    {
        $service = $this->makeService();

        $this->expectException(NotFoundException::class);

        $service->updateProduct(999, $this->validInput());
    }

    public function testUpdateProductPreservesActiveStatus(): void
    {
        $categories = new InMemoryCategoryRepository([new Category(1, 'Elektronik', null)]);
        $products = new InMemoryProductRepository([
            new Product(1, 'SKU-001', 'Produk Lama', 1, 'pcs', 10000, 15000, 5, null, false),
        ]);
        $service = $this->makeService($products, $categories);

        $updated = $service->updateProduct(1, $this->validInput());

        self::assertFalse($updated->isActive, 'update tidak boleh diam-diam mengaktifkan kembali produk yang nonaktif');
    }

    public function testSetActiveTogglesStatus(): void
    {
        $service = $this->makeService();
        $created = $service->createProduct($this->validInput());

        $service->setActive($created->id, false);

        self::assertFalse($service->getProductById($created->id)->isActive);
    }

    public function testListProductsFiltersByCategory(): void
    {
        $categories = new InMemoryCategoryRepository([
            new Category(1, 'Elektronik', null),
            new Category(2, 'Alat Tulis', null),
        ]);
        $products = new InMemoryProductRepository([
            new Product(1, 'SKU-001', 'Kabel HDMI', 1, 'pcs', 10000, 15000, 5, null, true),
            new Product(2, 'SKU-002', 'Pulpen', 2, 'pcs', 2000, 3000, 20, null, true),
        ]);
        $service = $this->makeService($products, $categories);

        $result = $service->listProducts(new ProductFilter(categoryId: 2));

        self::assertCount(1, $result);
        self::assertSame('Pulpen', $result[0]->name);
    }

    public function testGetProductBySkuReturnsMatchingProduct(): void
    {
        $products = new InMemoryProductRepository([
            new Product(1, 'SKU-001', 'Kabel HDMI', 1, 'pcs', 10000, 15000, 5, null, true),
        ]);
        $service = $this->makeService($products);

        $product = $service->getProductBySku('SKU-001');

        self::assertSame(1, $product->id);
    }

    public function testGetProductBySkuThrowsNotFoundForUnknownSku(): void
    {
        $service = $this->makeService();

        $this->expectException(NotFoundException::class);

        $service->getProductBySku('SKU-TIDAK-ADA');
    }

    public function testListProductsFiltersByStockStatus(): void
    {
        $categories = new InMemoryCategoryRepository([new Category(1, 'Elektronik', null)]);
        $products = new InMemoryProductRepository(
            [
                new Product(1, 'SKU-001', 'Stok Rendah', 1, 'pcs', 10000, 15000, 10, null, true),
                new Product(2, 'SKU-002', 'Stok Normal', 1, 'pcs', 10000, 15000, 10, null, true),
            ],
            totalStockByProductId: [1 => 5, 2 => 20],
        );
        $service = $this->makeService($products, $categories);

        $low = $service->listProducts(new ProductFilter(stockStatus: 'low'));
        $normal = $service->listProducts(new ProductFilter(stockStatus: 'normal'));

        self::assertCount(1, $low);
        self::assertSame('Stok Rendah', $low[0]->name);
        self::assertCount(1, $normal);
        self::assertSame('Stok Normal', $normal[0]->name);
    }

    public function testCreateProductWithoutImageLeavesImagePathNull(): void
    {
        $service = $this->makeService();

        $product = $service->createProduct($this->validInput());

        self::assertNull($product->imagePath);
    }

    public function testCreateProductTreatsNoFileUploadErrorAsNoImage(): void
    {
        $service = $this->makeService();

        $product = $service->createProduct($this->validInput([
            'image' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
        ]));

        self::assertNull($product->imagePath);
    }

    public function testCreateProductRejectsFailedUpload(): void
    {
        $service = $this->makeService();

        try {
            $service->createProduct($this->validInput([
                'image' => ['name' => 'x.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/tmp/x', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 100],
            ]));
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('image', $exception->errors());
        }
    }

    public function testCreateProductRejectsImageExceedingMaxSize(): void
    {
        $service = $this->makeService();

        try {
            $service->createProduct($this->validInput([
                'image' => ['name' => 'x.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/tmp/x', 'error' => UPLOAD_ERR_OK, 'size' => 3 * 1024 * 1024],
            ]));
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertSame('Ukuran gambar maksimal 2MB.', $exception->errors()['image']);
        }
    }

    public function testCreateProductRejectsUploadNotRegisteredByPhp(): void
    {
        // tmp_name yang bukan hasil upload HTTP sungguhan (mis. path buatan
        // sendiri) ditolak is_uploaded_file() - guard keamanan supaya
        // Service tidak bisa dipaksa membaca file arbitrer di server lewat
        // tmp_name yang dipalsukan.
        $service = $this->makeService();

        try {
            $service->createProduct($this->validInput([
                'image' => ['name' => 'x.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/etc/passwd', 'error' => UPLOAD_ERR_OK, 'size' => 100],
            ]));
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertSame('Berkas gambar tidak valid.', $exception->errors()['image']);
        }
    }
}
