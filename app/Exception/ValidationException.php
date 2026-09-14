<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Dilempar Service saat input gagal validasi (VAL-01). Ditangkap di dalam
 * Controller (bukan router global) supaya form yang sama bisa dirender
 * ulang dengan pesan error + input yang sudah diisi dipertahankan.
 */
final class ValidationException extends \RuntimeException
{
    /**
     * @param array<string, string> $errors field => pesan
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Validasi gagal');
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
