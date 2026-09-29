<?php

declare(strict_types=1);

namespace App\Session;

use App\Entity\Role;
use App\Exception\ForbiddenException;
use App\Exception\UnauthenticatedException;
use App\Repository\UserRepositoryInterface;

final class AuthGuard
{
    public function __construct(
        private readonly SessionInterface $session,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    /**
     * Session hanya menyimpan user_id; nama dan role selalu dibaca ulang dari
     * database setiap request. Sebelumnya keduanya ikut disimpan di session,
     * yang berarti session adalah salinan hak akses pada detik user login -
     * akun yang dinonaktifkan Admin (atau yang rolenya diturunkan) tetap
     * memegang akses penuh sampai ia logout sendiri. Untuk sistem dengan
     * segregation of duties (§1.2), pencabutan akses harus langsung berlaku,
     * jadi satu SELECT per request adalah harga yang wajar.
     */
    public function requireLogin(): CurrentUser
    {
        $userId = $this->session->get('user_id');

        if ($userId === null) {
            throw new UnauthenticatedException();
        }

        $user = $this->users->findById((int) $userId);

        if ($user === null || !$user->isActive) {
            // Session menunjuk akun yang sudah tidak berlaku - dibuang supaya
            // request berikutnya tidak perlu memeriksa hal yang sama lagi.
            $this->session->destroy();

            throw new UnauthenticatedException();
        }

        return new CurrentUser($user->id, $user->name, $user->role);
    }

    /**
     * @param Role[] $allowed
     */
    public function requireRole(CurrentUser $user, array $allowed): void
    {
        if (!in_array($user->role, $allowed, true)) {
            throw new ForbiddenException();
        }
    }
}
