<?php

declare(strict_types=1);

namespace Phai\Core\PdfKit;

/** A PDF name (/Type), without its slash. */
final class Name
{
    public function __construct(public readonly string $v)
    {
    }
}
