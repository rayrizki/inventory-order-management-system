<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Role;
use App\Entity\User;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\InMemoryUserRepository;
use App\Service\UserService;
use PHPUnit\Framework\TestCase;

final class UserServiceTest extends TestCase
{
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sales Baru',
            'email' => 'sales.baru@iom.test',
            'password' => 'Password123!',
            'role' => 'Sales',
        ], $overrides);
    }

    public function testCreateUserSucceedsAsActiveWithHashedPassword(): void
    {
        $service = new UserService(new InMemoryUserRepository());

        $user = $service->createUser($this->validInput());

        self::assertNotNull($user->id);
        self::assertTrue($user->isActive);
        self::assertNotSame('Password123!', $user->passwordHash, 'password tidak boleh disimpan plaintext');
        self::assertTrue($user->verifyPassword('Password123!'));
    }

    public function testCreateUserRejectsEmptyRequiredFields(): void
    {
        $service = new UserService(new InMemoryUserRepository());

        try {
            $service->createUser($this->validInput(['name' => '', 'email' => '', 'password' => '', 'role' => '']));
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            self::assertArrayHasKey('name', $errors);
            self::assertArrayHasKey('email', $errors);
            self::assertArrayHasKey('password', $errors);
            self::assertArrayHasKey('role', $errors);
        }
    }

    public function testCreateUserRejectsInvalidEmailFormat(): void
    {
        $service = new UserService(new InMemoryUserRepository());

        $this->expectException(ValidationException::class);

        $service->createUser($this->validInput(['email' => 'bukan-email']));
    }

    public function testCreateUserRejectsDuplicateEmail(): void
    {
        $users = new InMemoryUserRepository([
            new User(1, 'Existing', 'sales.baru@iom.test', password_hash('x', PASSWORD_DEFAULT), Role::Sales, true),
        ]);
        $service = new UserService($users);

        $this->expectException(ValidationException::class);

        $service->createUser($this->validInput());
    }

    public function testCreateUserRejectsPasswordShorterThanEightChars(): void
    {
        $service = new UserService(new InMemoryUserRepository());

        $this->expectException(ValidationException::class);

        $service->createUser($this->validInput(['password' => 'short']));
    }

    public function testCreateUserRejectsAdminRole(): void
    {
        $service = new UserService(new InMemoryUserRepository());

        $this->expectException(ValidationException::class);

        $service->createUser($this->validInput(['role' => 'Admin']));
    }

    public function testCreateUserRejectsUnknownRole(): void
    {
        $service = new UserService(new InMemoryUserRepository());

        $this->expectException(ValidationException::class);

        $service->createUser($this->validInput(['role' => 'SuperUser']));
    }

    public function testUpdateUserAllowsKeepingOwnEmail(): void
    {
        $users = new InMemoryUserRepository([
            new User(1, 'Sales Lama', 'sales.baru@iom.test', password_hash('x', PASSWORD_DEFAULT), Role::Sales, true),
        ]);
        $service = new UserService($users);

        $updated = $service->updateUser(1, $this->validInput(['name' => 'Sales Diubah']));

        self::assertSame('Sales Diubah', $updated->name);
        self::assertSame('sales.baru@iom.test', $updated->email);
    }

    public function testUpdateUserWithBlankPasswordKeepsExistingHash(): void
    {
        $originalHash = password_hash('OriginalPass1', PASSWORD_DEFAULT);
        $users = new InMemoryUserRepository([
            new User(1, 'Sales Lama', 'sales.baru@iom.test', $originalHash, Role::Sales, true),
        ]);
        $service = new UserService($users);

        $updated = $service->updateUser(1, $this->validInput(['password' => '']));

        self::assertSame($originalHash, $updated->passwordHash);
        self::assertTrue($updated->verifyPassword('OriginalPass1'));
    }

    public function testUpdateUserWithNewPasswordChangesHash(): void
    {
        $originalHash = password_hash('OriginalPass1', PASSWORD_DEFAULT);
        $users = new InMemoryUserRepository([
            new User(1, 'Sales Lama', 'sales.baru@iom.test', $originalHash, Role::Sales, true),
        ]);
        $service = new UserService($users);

        $updated = $service->updateUser(1, $this->validInput(['password' => 'NewPassword1']));

        self::assertNotSame($originalHash, $updated->passwordHash);
        self::assertTrue($updated->verifyPassword('NewPassword1'));
    }

    public function testUpdateUserPreservesActiveStatus(): void
    {
        $users = new InMemoryUserRepository([
            new User(1, 'Sales Lama', 'sales.baru@iom.test', password_hash('x', PASSWORD_DEFAULT), Role::Sales, false),
        ]);
        $service = new UserService($users);

        $updated = $service->updateUser(1, $this->validInput());

        self::assertFalse($updated->isActive, 'update tidak boleh diam-diam mengaktifkan kembali user yang nonaktif');
    }

    public function testUpdateUserThrowsNotFoundForUnknownId(): void
    {
        $service = new UserService(new InMemoryUserRepository());

        $this->expectException(NotFoundException::class);

        $service->updateUser(999, $this->validInput());
    }

    public function testSetActiveTogglesStatus(): void
    {
        $service = new UserService(new InMemoryUserRepository());
        $created = $service->createUser($this->validInput());

        $service->setActive($created->id, false);

        self::assertFalse($service->getUserById($created->id)->isActive);
    }

    public function testListUsersFiltersByActiveStatus(): void
    {
        $users = new InMemoryUserRepository([
            new User(1, 'Sales Aktif', 'aktif@iom.test', password_hash('x', PASSWORD_DEFAULT), Role::Sales, true),
            new User(2, 'Sales Nonaktif', 'nonaktif@iom.test', password_hash('x', PASSWORD_DEFAULT), Role::Sales, false),
        ]);
        $service = new UserService($users);

        $result = $service->listUsers(isActive: true);

        self::assertCount(1, $result);
        self::assertSame('Sales Aktif', $result[0]->name);
    }
}
