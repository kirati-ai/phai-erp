<?php

declare(strict_types=1);

namespace Phai\Modules\System;

final class PermsInput
{
    /** @param array<string, list<string>> $perms prog_code => actions */
    public function __construct(
        public array $perms = [],
    ) {
    }
}
