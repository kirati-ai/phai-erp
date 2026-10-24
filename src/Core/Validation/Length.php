<?php

declare(strict_types=1);

namespace Phai\Core\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Length implements Rule
{
    public function __construct(
        public readonly ?int $max = null,
        public readonly ?int $min = null,
    ) {
    }

    public function check(mixed $value): ?string
    {
        $len = mb_strlen((string) $value);
        if ($this->min !== null && $len < $this->min) {
            return t('validation.min_length', ['min' => $this->min]);
        }
        if ($this->max !== null && $len > $this->max) {
            return t('validation.max_length', ['max' => $this->max]);
        }
        return null;
    }
}
