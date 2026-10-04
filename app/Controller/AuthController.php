<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use App\Session\CsrfToken;
use App\Session\SessionInterface;

final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly SessionInterface $session,
    ) {
    }

    public function showLoginForm(): void
    {
        $loginFailed = $this->session->get('login_failed') === true;
        $this->session->remove('login_failed');
        $csrfToken = (new CsrfToken($this->session))->get();

        require_once __DIR__ . '/../../views/auth/login.php';
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = ($email !== '' && $password !== '')
            ? $this->authService->authenticate($email, $password)
            : null;

        if ($user === null) {
            // Pesan aman - tidak membedakan "email tidak ada" vs "password salah" (AUTH-01).
            $this->session->set('login_failed', true);
            header('Location: /login', true, 303);
            exit;
        }

        // Session ID diperbarui setelah login berhasil (AUTH-01).
        $this->session->regenerateId();

        // Hanya id yang disimpan - nama dan role dibaca ulang dari database
        // setiap request oleh AuthGuard, supaya penonaktifan akun atau
        // perubahan role langsung berlaku tanpa menunggu user logout.
        $this->session->set('user_id', $user->id);

        header('Location: /dashboard', true, 303);
        exit;
    }

    public function logout(): void
    {
        $this->session->destroy();
        header('Location: /login', true, 303);
        exit;
    }
}
