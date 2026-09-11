<?php

declare(strict_types=1);

namespace App\Session;

interface SessionInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    public function destroy(): void;

    public function regenerateId(): void;
}
