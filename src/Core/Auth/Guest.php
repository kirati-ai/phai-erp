<?php

declare(strict_types=1);

namespace Phai\Core\Auth;

use Attribute;

/** Route (or whole controller) that works without logging in. Every other route needs a login. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Guest
{
}
