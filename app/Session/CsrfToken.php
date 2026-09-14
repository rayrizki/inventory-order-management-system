<?php

declare(strict_types=1);

namespace App\Session;

/**
 * Token CSRF sederhana, satu per session (bukan per-form) - disimpan di
 * session lewat SessionInterface yang sama dipakai AuthGuard, bukan lewat
 * $_SESSION langsung (ARCH-01).
 */
final class CsrfToken
{
    private const SESSION_KEY = 'csrf_token';

    public function __construct(private readonly SessionInterface $session)
    {
    }

    /**
     * Ambil token yang sudah ada, atau buat baru kalau session ini belum punya.
     */
    public function get(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function isValid(?string $submitted): bool
    {
        $token = $this->session->get(self::SESSION_KEY);

        return is_string($token) && $token !== '' && is_string($submitted) && hash_equals($token, $submitted);
    }
}
