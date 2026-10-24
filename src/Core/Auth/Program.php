<?php

declare(strict_types=1);

namespace Phai\Core\Auth;

use Attribute;

/**
 * Put on a controller: every route needs permission on this program (prog_mstr).
 * The action comes from the HTTP method — GET view, POST add, PUT edit, DELETE delete —
 * unless the method has #[Action('print' | 'edit' | ...)].
 *
 * Shared lookups used by several screens list them all: #[Program('purchase-orders', 'payables')]
 * — the permission on any one of them is enough.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Program
{
    /** @var list<string> */
    public readonly array $codes;

    public function __construct(public readonly string $code, string ...$more)
    {
        $this->codes = [$code, ...$more];
    }

    public function allows(string $action): bool
    {
        foreach ($this->codes as $code) {
            if (Auth::can($code, $action)) {
                return true;
            }
        }
        return false;
    }
}
