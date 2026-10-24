<?php

declare(strict_types=1);

namespace Phai\Core\Auth;

use Attribute;

/** Overrides the permission a route needs: view, add, edit, delete or print. */
#[Attribute(Attribute::TARGET_METHOD)]
final class Action
{
    public function __construct(public readonly string $name)
    {
    }
}
