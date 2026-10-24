<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\Validation\Length;

final class PasswordInput
{
    public function __construct(
        public string $current_password,
        #[Length(min: 8, max: 255)]
        public string $new_password,
    ) {
    }
}
