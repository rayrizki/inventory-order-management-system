<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Dilempar Service saat data dengan ID tertentu tidak ditemukan. Ditangkap
 * di public/index.php dan dipetakan ke HTTP 404 (ERR-01).
 */
final class NotFoundException extends \RuntimeException
{
}
