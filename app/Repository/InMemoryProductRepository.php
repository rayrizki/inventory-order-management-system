<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;

final class InMemoryProductRepository implements ProductRepositoryInterface
{
    /** @var array<int, Product> */
    private array $products = [];

    private int $nextId = 1;

    /**
     * @param Product[] $products
     */
    public function __construct(array $products = [])
    {
        foreach ($products as $product) {
            $this->products[$product->id] = $product;
            $this->nextId = max($this->nextId, $product->id + 1);
        }
    }

    public function findById(int $id): ?Product
    {
        return $this->products[$id] ?? null;
    }

    public function findBySku(string $sku): ?Product
    {
        foreach ($this->products as $product) {
            if (strcasecmp($product->sku, $sku) === 0) {
                return $product;
            }
        }

        return null;
    }

    public function save(Product $product): Product
    {
        if ($product->id === null) {
            $product = new Product(
                $this->nextId++,
                $product->sku,
                $product->name,
                $product->categoryId,
                $product->unit,
                $product->buyPrice,
                $product->sellPrice,
                $product->reorderPoint,
                $product->imagePath,
                $product->isActive,
            );
        }

        $this->products[$product->id] = $product;

        return $product;
    }

    public function listAll(
        ?string $search = null,
        ?int $categoryId = null,
        ?bool $isActive = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $products = $this->filtered($search, $categoryId, $isActive);

        usort($products, static function (Product $a, Product $b) use ($sortBy, $sortDir): int {
            $valueA = $sortBy === 'sku' ? $a->sku : $a->name;
            $valueB = $sortBy === 'sku' ? $b->sku : $b->name;
            $result = strcasecmp($valueA, $valueB);

            return $sortDir === 'desc' ? -$result : $result;
        });

        return array_slice($products, $offset, $limit);
    }

    public function countAll(?string $search = null, ?int $categoryId = null, ?bool $isActive = null): int
    {
        return count($this->filtered($search, $categoryId, $isActive));
    }

    public function setActive(int $id, bool $isActive): void
    {
        $product = $this->products[$id] ?? null;

        if ($product !== null) {
            $this->products[$id] = new Product(
                $product->id,
                $product->sku,
                $product->name,
                $product->categoryId,
                $product->unit,
                $product->buyPrice,
                $product->sellPrice,
                $product->reorderPoint,
                $product->imagePath,
                $isActive,
            );
        }
    }

    /**
     * @return Product[]
     */
    private function filtered(?string $search, ?int $categoryId, ?bool $isActive): array
    {
        $products = array_values($this->products);

        if ($search !== null && $search !== '') {
            $products = array_values(array_filter(
                $products,
                static fn (Product $product): bool => stripos($product->sku, $search) !== false
                    || stripos($product->name, $search) !== false,
            ));
        }

        if ($categoryId !== null) {
            $products = array_values(array_filter(
                $products,
                static fn (Product $product): bool => $product->categoryId === $categoryId,
            ));
        }

        if ($isActive !== null) {
            $products = array_values(array_filter(
                $products,
                static fn (Product $product): bool => $product->isActive === $isActive,
            ));
        }

        return $products;
    }
}
