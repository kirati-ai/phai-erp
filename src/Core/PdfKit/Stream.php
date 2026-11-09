<?php

declare(strict_types=1);

namespace Phai\Core\PdfKit;

/** A stream object: its dictionary and its bytes as stored in the file (still encoded by its /Filter). */
final class Stream
{
    public function __construct(public Dict $dict, public string $data)
    {
    }
}
