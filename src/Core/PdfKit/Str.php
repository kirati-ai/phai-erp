<?php

declare(strict_types=1);

namespace Phai\Core\PdfKit;

/** A PDF string, as its bytes (escapes already undone). */
final class Str
{
    public function __construct(public readonly string $v)
    {
    }
}
