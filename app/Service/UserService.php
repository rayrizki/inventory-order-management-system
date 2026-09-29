<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Role;
use App\Entity\User;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\UserRepositoryInterface;

final class UserService
{
    use NormalizesSearchTerm;

    public const PER_PAGE = 10;

    /**
     * USR-01: "Admin mengelola akun Sales dan Warehouse Staff" - role Admin
     * sengaja tidak ditawarkan lewat form ini (cuma dibuat lewat seed),
     * supaya UI ini tidak bisa dipakai membuat akun ber-privilese tinggi
     * baru sembarangan.
     */
    private const ASSIGNABLE_ROLES = [Role::Sales, Role::WarehouseStaff];

    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    /**
     * @return User[]
     */
    public function listUsers(
        ?string $search = null,
        ?bool $isActive = null,
        int $page = 1,
        int $perPage = self::PER_PAGE,
        string $sortBy = 'name',
        string $sortDir = 'asc',
    ): array {
        $perPage = max(1, $perPage);
        $offset = (max(1, $page) - 1) * $perPage;

        return $this->users->listAll($this->normalizeSearch($search), $isActive, $perPage, $offset, $sortBy, $sortDir);
    }

    public function countUsers(?string $search = null, ?bool $isActive = null): int
    {
        return $this->users->countAll($this->normalizeSearch($search), $isActive);
    }

    public function getUserById(int $id): User
    {
        $user = $this->users->findById($id);

        if ($user === null) {
            throw new NotFoundException();
        }

        return $user;
    }

    /**
     * @param array{name: string, email: string, password: string, role: string} $input
     */
    public function createUser(array $input): User
    {
        $data = $this->validate($input, null, passwordRequired: true);

        return $this->users->save(new User(
            null,
            $data['name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['role'],
            true,
        ));
    }

    /**
     * @param array{name: string, email: string, password: string, role: string} $input
     */
    public function updateUser(int $id, array $input): User
    {
        $existing = $this->getUserById($id);
        $data = $this->validate($input, $id, passwordRequired: false);

        $passwordHash = $data['password'] !== '' ? password_hash($data['password'], PASSWORD_DEFAULT) : $existing->passwordHash;

        return $this->users->save(new User(
            $id,
            $data['name'],
            $data['email'],
            $passwordHash,
            $data['role'],
            $existing->isActive,
        ));
    }

    public function setActive(int $id, bool $isActive): void
    {
        $this->getUserById($id);
        $this->users->setActive($id, $isActive);
    }

    /**
     * Tipe param sengaja "optional" (bukan wajib semua key ada) - $input ini
     * boundary ke $_POST lewat Controller::readInput(), yang secara runtime
     * tidak dijamin lengkap sekuat PHPDoc-nya - fallback `??` di bawah bukan
     * kode mati.
     *
     * @param array{name?: string, email?: string, password?: string, role?: string} $input
     * @return array{name: string, email: string, password: string, role: Role}
     */
    private function validate(array $input, ?int $excludeId, bool $passwordRequired): array
    {
        $errors = [];

        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $roleRaw = (string) ($input['role'] ?? '');

        if ($name === '') {
            $errors['name'] = 'Nama wajib diisi.';
        }

        if ($email === '') {
            $errors['email'] = 'Email wajib diisi.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Format email tidak valid.';
        } else {
            $existing = $this->users->findByEmail($email);
            if ($existing !== null && $existing->id !== $excludeId) {
                $errors['email'] = 'Email sudah dipakai user lain.';
            }
        }

        if ($passwordRequired && $password === '') {
            $errors['password'] = 'Password wajib diisi.';
        } elseif ($password !== '' && strlen($password) < 8) {
            $errors['password'] = 'Password minimal 8 karakter.';
        }

        $role = Role::tryFrom($roleRaw);
        if ($role === null || !in_array($role, self::ASSIGNABLE_ROLES, true)) {
            $errors['role'] = 'Role wajib dipilih (Sales atau Warehouse Staff).';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
        ];
    }
}
