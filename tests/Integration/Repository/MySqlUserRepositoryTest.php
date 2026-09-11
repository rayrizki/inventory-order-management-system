<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\MySqlUserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Menyentuh MySQL sungguhan (TEST-02) - bergantung pada data seed di
 * database/schema-and-seed.sql (akun admin@iom.test). Dijalankan lewat
 * `docker compose exec app vendor/bin/phpunit --testsuite Integration`.
 */
final class MySqlUserRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../config/database.php';
        $this->pdo = createPdoConnection();
    }

    public function testFindByEmailReturnsSeededAdmin(): void
    {
        $repository = new MySqlUserRepository($this->pdo);

        $user = $repository->findByEmail('admin@iom.test');

        self::assertNotNull($user);
        self::assertSame('Admin Utama', $user->name);
        self::assertTrue($user->verifyPassword('Password123!'));
        self::assertFalse($user->verifyPassword('password-salah'));
    }

    public function testFindByEmailReturnsNullForUnknownEmail(): void
    {
        $repository = new MySqlUserRepository($this->pdo);

        self::assertNull($repository->findByEmail('tidak-ada@iom.test'));
    }

    public function testFindByIdReturnsSameUserAsFindByEmail(): void
    {
        $repository = new MySqlUserRepository($this->pdo);

        $byEmail = $repository->findByEmail('admin@iom.test');
        self::assertNotNull($byEmail);

        $byId = $repository->findById($byEmail->id);

        self::assertNotNull($byId);
        self::assertSame($byEmail->email, $byId->email);
    }
}
