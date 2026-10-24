<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\Validation\Length;
use Phai\Core\Validation\Pattern;

final class RoleInput
{
    public function __construct(
        #[Length(max: 50), Pattern('/^[A-Za-z0-9_-]+$/', 'validation.code')]
        public string $role_code,
        #[Length(max: 100)]
        public string $role_desc,
        public bool $role_active = true,
    ) {
        $this->role_code = strtoupper($role_code);
    }
}
