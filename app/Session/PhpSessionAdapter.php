<?php

declare(strict_types=1);

namespace App\Session;

final class PhpSessionAdapter implements SessionInterface
{
    public function __construct()
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        // Brief §4.2 "session dikelola aman". Image php:8.2-cli tidak memuat
        // php.ini sendiri, jadi default PHP yang berlaku: httponly mati,
        // samesite kosong, use_strict_mode mati. Ditetapkan di sini (bukan
        // di php.ini container) supaya jaminannya ikut ke mana pun kode ini
        // dijalankan, termasuk saat test atau di luar Docker.
        //
        // - httponly: cookie sesi tidak terbaca document.cookie, jadi XSS
        //   tidak langsung berubah jadi pengambilalihan sesi.
        // - samesite Lax: cookie tidak ikut pada request lintas situs selain
        //   navigasi GET biasa - lapis kedua di belakang token CSRF.
        // - use_strict_mode: PHP menolak ID sesi yang tidak pernah ia
        //   terbitkan, menutup session fixation (penyerang menanam ID dulu,
        //   lalu memakai ID yang sama setelah korban login).
        //
        // `secure` sengaja mengikuti HTTPS-atau-tidaknya request: demo dan
        // penilaian berjalan di http://localhost, dan cookie `secure` tidak
        // akan pernah dikirim di sana.
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => ($_SERVER['HTTPS'] ?? '') !== '',
            'path' => '/',
        ]);

        session_start();
    }

    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public function regenerateId(): void
    {
        session_regenerate_id(true);
    }
}
