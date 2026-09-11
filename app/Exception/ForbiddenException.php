<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Dilempar AuthGuard saat user login tapi rolenya tidak diizinkan mengakses
 * suatu aksi. Ditangkap di public/index.php dan dipetakan ke HTTP 403 (ERR-01).
 */
final class ForbiddenException extends \RuntimeException
{
}
