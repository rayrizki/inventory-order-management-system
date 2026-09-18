<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Entity\Role;
use App\Entity\User;
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

    private ?int $createdId = null;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../config/database.php';
        $this->pdo = createPdoConnection();
    }

    protected function tearDown(): void
    {
        if ($this->createdId !== null) {
            $this->pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $this->createdId]);
        }
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

    public function testSaveInsertsNewUserAsActiveByDefault(): void
    {
        $repository = new MySqlUserRepository($this->pdo);

        $saved = $repository->save(new User(null, 'Test User ZZZ', 'test.user.zzz@iom.test', password_hash('Password123!', PASSWORD_DEFAULT), Role::Sales, true));
        $this->createdId = $saved->id;

        self::assertNotNull($saved->id);
        $found = $repository->findById($saved->id);
        self::assertTrue($found->isActive);
        self::assertSame(Role::Sales, $found->role);
    }

    public function testSaveUpdatesFieldsWithoutTouchingActiveStatus(): void
    {
        $repository = new MySqlUserRepository($this->pdo);

        $saved = $repository->save(new User(null, 'Before Update', 'test.user.zzz@iom.test', password_hash('x', PASSWORD_DEFAULT), Role::Sales, true));
        $this->createdId = $saved->id;
        $repository->setActive($saved->id, false);

        $repository->save(new User($saved->id, 'After Update', 'test.user.zzz@iom.test', password_hash('y', PASSWORD_DEFAULT), Role::WarehouseStaff, true));

        $found = $repository->findById($saved->id);
        self::assertSame('After Update', $found->name);
        self::assertSame(Role::WarehouseStaff, $found->role);
        self::assertFalse($found->isActive, 'save() untuk UPDATE tidak boleh mengubah is_active - itu tugas setActive()');
    }

    public function testSetActiveTogglesStatus(): void
    {
        $repository = new MySqlUserRepository($this->pdo);

        $saved = $repository->save(new User(null, 'Test User ZZZ', 'test.user.zzz@iom.test', password_hash('x', PASSWORD_DEFAULT), Role::Sales, true));
        $this->createdId = $saved->id;

        $repository->setActive($saved->id, false);
        self::assertFalse($repository->findById($saved->id)->isActive);

        $repository->setActive($saved->id, true);
        self::assertTrue($repository->findById($saved->id)->isActive);
    }

    public function testListAllFiltersBySearchAndActiveStatus(): void
    {
        $repository = new MySqlUserRepository($this->pdo);

        $saved = $repository->save(new User(null, 'Test User Unik ZZZ', 'test.user.zzz@iom.test', password_hash('x', PASSWORD_DEFAULT), Role::Sales, true));
        $this->createdId = $saved->id;

        $byName = array_map(static fn (User $user): string => $user->email, $repository->listAll(search: 'Test User Unik ZZZ'));
        self::assertContains('test.user.zzz@iom.test', $byName);

        $byEmail = array_map(static fn (User $user): string => $user->name, $repository->listAll(search: 'test.user.zzz'));
        self::assertContains('Test User Unik ZZZ', $byEmail, 'search harus mencocokkan kolom email, bukan cuma name');

        $repository->setActive($saved->id, false);
        $activeOnly = array_map(static fn (User $user): string => $user->name, $repository->listAll(search: 'Test User Unik ZZZ', isActive: true));
        self::assertNotContains('Test User Unik ZZZ', $activeOnly, 'harus tersaring saat filter isActive=true karena baris ini dinonaktifkan');
    }
}
