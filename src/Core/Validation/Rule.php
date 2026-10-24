<?php

declare(strict_types=1);

namespace Phai\Core\Validation;

/** A validation attribute. check() returns an error message, or null when the value is fine. */
interface Rule
{
    public function check(mixed $value): ?string;
}
