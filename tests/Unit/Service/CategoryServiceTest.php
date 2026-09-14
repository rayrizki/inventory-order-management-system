<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Category;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\InMemoryCategoryRepository;
use App\Service\CategoryService;
use PHPUnit\Framework\TestCase;

final class CategoryServiceTest extends TestCase
{
    public function testCreateCategorySucceedsWithValidName(): void
    {
        $service = new CategoryService(new InMemoryCategoryRepository());

        $category = $service->createCategory('Elektronik', 'Barang elektronik');

        self::assertNotNull($category->id);
        self::assertSame('Elektronik', $category->name);
    }

    public function testCreateCategoryTrimsWhitespaceAndBlankDescriptionBecomesNull(): void
    {
        $service = new CategoryService(new InMemoryCategoryRepository());

        $category = $service->createCategory('  Elektronik  ', '   ');

        self::assertSame('Elektronik', $category->name);
        self::assertNull($category->description);
    }

    public function testCreateCategoryRejectsEmptyName(): void
    {
        $service = new CategoryService(new InMemoryCategoryRepository());

        $this->expectException(ValidationException::class);

        $service->createCategory('   ', 'apa saja');
    }

    public function testUpdateCategoryThrowsNotFoundForUnknownId(): void
    {
        $service = new CategoryService(new InMemoryCategoryRepository());

        $this->expectException(NotFoundException::class);

        $service->updateCategory(999, 'Nama baru', null);
    }

    public function testUpdateCategoryChangesExistingRecord(): void
    {
        $repository = new InMemoryCategoryRepository();
        $service = new CategoryService($repository);
        $created = $service->createCategory('Elektronik', null);

        $updated = $service->updateCategory($created->id, 'Elektronik & Gadget', 'Update');

        self::assertSame($created->id, $updated->id);
        self::assertSame('Elektronik & Gadget', $updated->name);
    }

    public function testDeleteCategorySucceedsWhenNotInUse(): void
    {
        $repository = new InMemoryCategoryRepository();
        $service = new CategoryService($repository);
        $created = $service->createCategory('Elektronik', null);

        $service->deleteCategory($created->id);

        $this->expectException(NotFoundException::class);
        $service->getCategoryById($created->id);
    }

    public function testDeleteCategoryThrowsConflictWhenStillUsedByProducts(): void
    {
        $repository = new InMemoryCategoryRepository(
            categories: [new Category(1, 'Elektronik', null)],
            inUseIds: [1],
        );
        $service = new CategoryService($repository);

        $this->expectException(ConflictException::class);

        $service->deleteCategory(1);
    }

    public function testDeleteCategoryThrowsNotFoundForUnknownId(): void
    {
        $service = new CategoryService(new InMemoryCategoryRepository());

        $this->expectException(NotFoundException::class);

        $service->deleteCategory(999);
    }
}
