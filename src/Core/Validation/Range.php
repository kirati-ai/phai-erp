<?php

declare(strict_types=1);

namespace Phai\Core\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Range implements Rule
{
    public function __construct(
        public readonly int|float|null $min = null,
        public readonly int|float|null $max = null,
    ) {
    }

    public function check(mixed $value): ?string
    {
        if ($this->min !== null && $value < $this->min) {
            return t('validation.min', ['min' => $this->min]);
        }
        if ($this->max !== null && $value > $this->max) {
            return t('validation.max', ['max' => $this->max]);
        }
        return null;
    }
}
