<?php

declare(strict_types=1);

namespace App\Session;

use App\Entity\Role;

final class CurrentUser
{
    public function __construct(
        public readonly int $id,
        public readonly Role $role,
    ) {
    }
}
