<?php

declare(strict_types=1);

namespace Phai\Core\Routing;

use Attribute;

/** Base route attribute. Use Get / Post / Put / Delete on controller methods. */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
    ) {
    }
}
