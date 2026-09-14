<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Dilempar Service saat aksi ditolak karena aturan bisnis - bukan validasi
 * input maupun otorisasi - mis. menghapus data yang masih dipakai di tempat
 * lain. Ditangkap di dalam Controller, bukan router global.
 */
final class ConflictException extends \RuntimeException
{
}
