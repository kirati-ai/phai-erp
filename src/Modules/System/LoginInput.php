<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\Validation\Length;

final class LoginInput
{
    public function __construct(
        #[Length(max: 255)]
        public string $login,
        #[Length(max: 255)]
        public string $password,
        public ?string $next = null,
    ) {
    }
}
