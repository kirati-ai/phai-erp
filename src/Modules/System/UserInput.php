<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\Validation\Email;
use Phai\Core\Validation\Length;
use Phai\Core\Validation\Pattern;

final class UserInput
{
    public function __construct(
        #[Length(max: 50), Pattern('/^[A-Za-z0-9._-]+$/', 'validation.username')]
        public string $us_userid,
        #[Length(max: 255)]
        public string $us_name,
        #[Length(max: 255), Email]
        public string $us_email,
        #[Length(max: 50)]
        public string $us_role,
        public bool $us_active = true,
        /** Required for a new user; on edit, blank keeps the old password. */
        #[Length(min: 8, max: 255)]
        public ?string $password = null,
    ) {
        $this->us_email = strtolower($us_email);
    }
}
