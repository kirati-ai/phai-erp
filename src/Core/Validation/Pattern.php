<?php

declare(strict_types=1);

namespace Phai\Core\Validation;

use Attribute;

/** Value must match a regex. Pass a message key from lang/<locale>/validation.php to explain the format. */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Pattern implements Rule
{
    public function __construct(
        public readonly string $regex,
        public readonly string $message = 'validation.format',
    ) {
    }

    public function check(mixed $value): ?string
    {
        return preg_match($this->regex, (string) $value) ? null : t($this->message);
    }
}
