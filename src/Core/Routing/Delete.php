<?php

declare(strict_types=1);

namespace Phai\Core\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Delete extends Route
{
    public function __construct(string $path)
    {
        parent::__construct('DELETE', $path);
    }
}
