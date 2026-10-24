<?php

declare(strict_types=1);

namespace Phai\Core\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Email implements Rule
{
    public function check(mixed $value): ?string
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : t('validation.email');
    }
}
