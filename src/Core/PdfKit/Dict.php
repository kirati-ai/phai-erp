<?php

declare(strict_types=1);

namespace Phai\Core\PdfKit;

/** A PDF dictionary: name (without slash) => value. PHP lists are PDF arrays, so a dictionary needs its own class. */
final class Dict
{
    public function __construct(public array $v = [])
    {
    }

    public function get(string $key): mixed
    {
        return $this->v[$key] ?? null;
    }

    public function name(string $key): ?string
    {
        $n = $this->v[$key] ?? null;
        return $n instanceof Name ? $n->v : null;
    }
}
