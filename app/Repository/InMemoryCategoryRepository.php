<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;

final class InMemoryCategoryRepository implements CategoryRepositoryInterface
{
    /** @var array<int, Category> */
    private array $categories = [];

    private int $nextId = 1;

    /** @var array<int, true> id kategori yang dianggap masih dipakai produk, untuk test isInUse() */
    private array $inUseIds = [];

    /**
     * @param Category[] $categories
     * @param int[] $inUseIds id kategori yang dianggap masih dipakai produk (untuk simulasi di unit test)
     */
    public function __construct(array $categories = [], array $inUseIds = [])
    {
        foreach ($categories as $category) {
            $this->categories[$category->id] = $category;
            $this->nextId = max($this->nextId, $category->id + 1);
        }

        foreach ($inUseIds as $id) {
            $this->inUseIds[$id] = true;
        }
    }

    public function findById(int $id): ?Category
    {
        return $this->categories[$id] ?? null;
    }

    public function save(Category $category): Category
    {
        if ($category->id === null) {
            $category = new Category($this->nextId++, $category->name, $category->description);
        }

        $this->categories[$category->id] = $category;

        return $category;
    }

    public function listAll(
        ?string $search = null,
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $categories = $this->filtered($search);

        usort($categories, static function (Category $a, Category $b) use ($sortBy, $sortDir): int {
            $valueA = $sortBy === 'description' ? (string) $a->description : $a->name;
            $valueB = $sortBy === 'description' ? (string) $b->description : $b->name;
            $result = strcasecmp($valueA, $valueB);

            return $sortDir === 'desc' ? -$result : $result;
        });

        return array_slice($categories, $offset, $limit);
    }

    public function countAll(?string $search = null): int
    {
        return count($this->filtered($search));
    }

    public function isInUse(int $id): bool
    {
        return isset($this->inUseIds[$id]);
    }

    public function delete(int $id): void
    {
        unset($this->categories[$id]);
    }

    /**
     * @return Category[]
     */
    private function filtered(?string $search): array
    {
        $categories = array_values($this->categories);

        if ($search === null || $search === '') {
            return $categories;
        }

        return array_values(array_filter(
            $categories,
            static fn (Category $category): bool => stripos($category->name, $search) !== false,
        ));
    }
}
