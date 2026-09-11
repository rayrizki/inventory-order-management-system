<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Dilempar AuthGuard saat halaman/aksi terlindungi diakses tanpa session
 * login. Ditangkap di public/index.php dan dipetakan ke redirect /login (ERR-01).
 */
final class UnauthenticatedException extends \RuntimeException
{
}
