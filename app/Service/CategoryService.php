<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\CategoryRepositoryInterface;

final class CategoryService
{
    public const PER_PAGE = 10;

    public function __construct(private readonly CategoryRepositoryInterface $categories)
    {
    }

    /**
     * @return Category[]
     */
    public function listCategories(
        ?string $search = null,
        int $page = 1,
        int $perPage = self::PER_PAGE,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $perPage = max(1, $perPage);
        $offset = (max(1, $page) - 1) * $perPage;

        return $this->categories->listAll($this->normalizeSearch($search), $perPage, $offset, $sortBy, $sortDir);
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

    public function getCategoryById(int $id): Category
    {
        $category = $this->categories->findById($id);

        if ($category === null) {
            throw new NotFoundException();
        }

        return $category;
    }

    public function createCategory(string $name, ?string $description): Category
    {
        $this->validate($name);

        return $this->categories->save(new Category(null, trim($name), $this->normalizeDescription($description)));
    }

    public function updateCategory(int $id, string $name, ?string $description): Category
    {
        $this->getCategoryById($id);
        $this->validate($name);

        return $this->categories->save(new Category($id, trim($name), $this->normalizeDescription($description)));
    }

    public function deleteCategory(int $id): void
    {
        $this->getCategoryById($id);

        if ($this->categories->isInUse($id)) {
            throw new ConflictException('Kategori masih dipakai produk lain, tidak bisa dihapus.');
        }

        $this->categories->delete($id);
    }

    private function validate(string $name): void
    {
        if (trim($name) === '') {
            throw new ValidationException(['name' => 'Nama kategori wajib diisi.']);
        }
    }

    private function normalizeDescription(?string $description): ?string
    {
        $trimmed = trim((string) $description);

        return $trimmed === '' ? null : $trimmed;
    }
}
