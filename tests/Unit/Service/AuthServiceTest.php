<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\InMemoryUserRepository;
use App\Service\AuthService;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private function makeUser(string $email, string $password, bool $isActive = true): User
    {
        return new User(
            id: 1,
            name: 'Test User',
            email: $email,
            passwordHash: password_hash($password, PASSWORD_DEFAULT),
            role: Role::Admin,
            isActive: $isActive,
        );
    }

    public function testAuthenticateSucceedsWithCorrectCredentials(): void
    {
        $user = $this->makeUser('admin@iom.test', 'Password123!');
        $service = new AuthService(new InMemoryUserRepository([$user]));

        $result = $service->authenticate('admin@iom.test', 'Password123!');

        self::assertNotNull($result);
        self::assertSame('admin@iom.test', $result->email);
    }

    public function testAuthenticateFailsWithWrongPassword(): void
    {
        $user = $this->makeUser('admin@iom.test', 'Password123!');
        $service = new AuthService(new InMemoryUserRepository([$user]));

        self::assertNull($service->authenticate('admin@iom.test', 'password-salah'));
    }

    public function testAuthenticateFailsWithUnknownEmail(): void
    {
        $service = new AuthService(new InMemoryUserRepository([]));

        self::assertNull($service->authenticate('tidak-ada@iom.test', 'apa saja'));
    }

    public function testAuthenticateFailsWhenUserIsInactive(): void
    {
        $user = $this->makeUser('nonaktif@iom.test', 'Password123!', isActive: false);
        $service = new AuthService(new InMemoryUserRepository([$user]));

        self::assertNull($service->authenticate('nonaktif@iom.test', 'Password123!'));
    }

    public function testAuthenticateFailsWithEmptyPassword(): void
    {
        $user = $this->makeUser('admin@iom.test', 'Password123!');
        $service = new AuthService(new InMemoryUserRepository([$user]));

        self::assertNull($service->authenticate('admin@iom.test', ''));
    }
}
