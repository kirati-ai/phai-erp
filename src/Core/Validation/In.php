<?php

declare(strict_types=1);

namespace Phai\Core\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class In implements Rule
{
    public function __construct(public readonly array $values)
    {
    }

    public function check(mixed $value): ?string
    {
        return in_array($value, $this->values, true) ? null : t('validation.in');
    }
}
