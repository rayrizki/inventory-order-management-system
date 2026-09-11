<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use App\Entity\Role;
use App\Exception\ForbiddenException;
use App\Exception\UnauthenticatedException;
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
    public function testRequireLoginThrowsWhenNoSessionData(): void
    {
        $guard = new AuthGuard(new InMemorySession());

        $this->expectException(UnauthenticatedException::class);

        $guard->requireLogin();
    }

    public function testRequireLoginReturnsCurrentUserWhenSessionSet(): void
    {
        $session = new InMemorySession();
        $session->set('user_id', 7);
        $session->set('user_role', Role::Sales->value);

        $guard = new AuthGuard($session);
        $currentUser = $guard->requireLogin();

        self::assertSame(7, $currentUser->id);
        self::assertSame(Role::Sales, $currentUser->role);
    }

    public function testRequireRoleAllowsPermittedRole(): void
    {
        $session = new InMemorySession();
        $session->set('user_id', 1);
        $session->set('user_role', Role::Admin->value);

        $guard = new AuthGuard($session);
        $currentUser = $guard->requireLogin();

        $guard->requireRole($currentUser, [Role::Admin]);

        $this->expectNotToPerformAssertions();
    }

    public function testRequireRoleThrowsForbiddenForDisallowedRole(): void
    {
        $session = new InMemorySession();
        $session->set('user_id', 2);
        $session->set('user_role', Role::Sales->value);

        $guard = new AuthGuard($session);
        $currentUser = $guard->requireLogin();

        $this->expectException(ForbiddenException::class);

        $guard->requireRole($currentUser, [Role::Admin]);
    }
}
