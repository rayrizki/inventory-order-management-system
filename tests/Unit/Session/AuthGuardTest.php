<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use App\Entity\Role;
use App\Entity\User;
use App\Exception\ForbiddenException;
use App\Exception\UnauthenticatedException;
use App\Repository\InMemoryUserRepository;
use App\Session\AuthGuard;
use App\Session\SessionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Fake SessionInterface berbasis array biasa - tidak menyentuh $_SESSION/PHP
 * session sungguhan, supaya test ini murni logic (TEST-01).
 */
final class InMemorySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function destroy(): void
    {
        $this->data = [];
    }

    public function regenerateId(): void
    {
        // Tidak relevan untuk fake ini.
    }
}

final class AuthGuardTest extends TestCase
{
    private function makeUser(int $id, string $name, Role $role, bool $isActive = true): User
    {
        return new User($id, $name, "user{$id}@iom.test", 'hash-tidak-dipakai', $role, $isActive);
    }

    private function sessionFor(int $userId): InMemorySession
    {
        $session = new InMemorySession();
        $session->set('user_id', $userId);

        return $session;
    }

    public function testRequireLoginThrowsWhenNoSessionData(): void
    {
        $guard = new AuthGuard(new InMemorySession(), new InMemoryUserRepository());

        $this->expectException(UnauthenticatedException::class);

        $guard->requireLogin();
    }

    public function testRequireLoginReadsIdentityFromRepositoryNotFromSession(): void
    {
        $users = new InMemoryUserRepository([$this->makeUser(7, 'Sinta Sales', Role::Sales)]);

        $currentUser = (new AuthGuard($this->sessionFor(7), $users))->requireLogin();

        self::assertSame(7, $currentUser->id);
        self::assertSame('Sinta Sales', $currentUser->name);
        self::assertSame(Role::Sales, $currentUser->role);
    }

    /**
     * Inti AUTH-01/USR-01: akun yang dinonaktifkan Admin tidak boleh tetap
     * bisa dipakai hanya karena sesinya sudah terlanjur terbuka.
     */
    public function testRequireLoginRejectsUserDeactivatedAfterLogin(): void
    {
        $session = $this->sessionFor(7);
        $users = new InMemoryUserRepository([$this->makeUser(7, 'Sinta Sales', Role::Sales, isActive: false)]);

        $guard = new AuthGuard($session, $users);

        try {
            $guard->requireLogin();
            self::fail('user nonaktif seharusnya ditolak');
        } catch (UnauthenticatedException) {
            self::assertNull($session->get('user_id'), 'session yang menunjuk akun nonaktif harus ikut dibuang');
        }
    }

    public function testRequireLoginRejectsSessionPointingToDeletedUser(): void
    {
        $guard = new AuthGuard($this->sessionFor(99), new InMemoryUserRepository());

        $this->expectException(UnauthenticatedException::class);

        $guard->requireLogin();
    }

    /**
     * Role yang berlaku adalah yang ada di database sekarang - kalau Admin
     * menurunkan role seseorang, pembatasannya langsung terasa.
     */
    public function testRequireLoginReflectsRoleChangedAfterLogin(): void
    {
        $session = $this->sessionFor(7);
        $users = new InMemoryUserRepository([$this->makeUser(7, 'Sinta', Role::Admin)]);
        $guard = new AuthGuard($session, $users);

        self::assertSame(Role::Admin, $guard->requireLogin()->role);

        $users->save($this->makeUser(7, 'Sinta', Role::Sales));

        $afterDemotion = $guard->requireLogin();
        self::assertSame(Role::Sales, $afterDemotion->role);
        $this->expectException(ForbiddenException::class);
        $guard->requireRole($afterDemotion, [Role::Admin]);
    }

    public function testRequireRoleAllowsAnyOfThePermittedRoles(): void
    {
        $users = new InMemoryUserRepository([$this->makeUser(3, 'Wawan Gudang', Role::WarehouseStaff)]);
        $guard = new AuthGuard($this->sessionFor(3), $users);
        $currentUser = $guard->requireLogin();

        $guard->requireRole($currentUser, [Role::Admin, Role::WarehouseStaff]);

        self::assertSame(Role::WarehouseStaff, $currentUser->role);
    }

    public function testRequireRoleThrowsForbiddenForDisallowedRole(): void
    {
        $users = new InMemoryUserRepository([$this->makeUser(2, 'Sinta Sales', Role::Sales)]);
        $guard = new AuthGuard($this->sessionFor(2), $users);
        $currentUser = $guard->requireLogin();

        $this->expectException(ForbiddenException::class);

        $guard->requireRole($currentUser, [Role::Admin]);
    }
}
