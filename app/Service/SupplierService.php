<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Supplier;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\SupplierRepositoryInterface;

final class SupplierService
{
    public const PER_PAGE = 10;

    public function __construct(private readonly SupplierRepositoryInterface $suppliers)
    {
    }

    /**
     * @return Supplier[]
     */
    public function listSuppliers(
        ?string $search = null,
        ?bool $isActive = null,
        int $page = 1,
        int $perPage = self::PER_PAGE,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $perPage = max(1, $perPage);
        $offset = (max(1, $page) - 1) * $perPage;

        return $this->suppliers->listAll($this->normalizeSearch($search), $isActive, $perPage, $offset, $sortBy, $sortDir);
    }

    public function countSuppliers(?string $search = null, ?bool $isActive = null): int
    {
        return $this->suppliers->countAll($this->normalizeSearch($search), $isActive);
    }

    public function getSupplierById(int $id): Supplier
    {
        $supplier = $this->suppliers->findById($id);

        if ($supplier === null) {
            throw new NotFoundException();
        }

        return $supplier;
    }

    public function createSupplier(string $name, ?string $contact, ?string $address): Supplier
    {
        $this->validate($name);

        return $this->suppliers->save(new Supplier(
            null,
            trim($name),
            $this->normalizeOptional($contact),
            $this->normalizeOptional($address),
            true,
        ));
    }

    public function updateSupplier(int $id, string $name, ?string $contact, ?string $address): Supplier
    {
        $existing = $this->getSupplierById($id);
        $this->validate($name);

        return $this->suppliers->save(new Supplier(
            $id,
            trim($name),
            $this->normalizeOptional($contact),
            $this->normalizeOptional($address),
            $existing->isActive,
        ));
    }

    public function setActive(int $id, bool $isActive): void
    {
        $this->getSupplierById($id);
        $this->suppliers->setActive($id, $isActive);
    }

    private function validate(string $name): void
    {
        if (trim($name) === '') {
            throw new ValidationException(['name' => 'Nama supplier wajib diisi.']);
        }
    }

    private function normalizeOptional(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeSearch(?string $search): ?string
    {
        $search = trim((string) $search);

        return $search === '' ? null : $search;
    }
}
