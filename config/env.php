<?php

declare(strict_types=1);

/**
 * Loader environment sederhana (native, tanpa library). Docker Compose sudah
 * menyuntikkan env var langsung ke container (lihat compose.yaml), jadi file
 * .env di sini hanya dipakai sebagai fallback saat aplikasi dijalankan di
 * luar Docker dan variabelnya belum ada di environment proses.
 */

$envFile = __DIR__ . '/../.env';

if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
        $name = trim($name);
        $value = trim($value);

        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    return $value === false ? $default : $value;
}
