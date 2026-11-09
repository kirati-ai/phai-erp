<?php

declare(strict_types=1);

namespace Phai\Core\PdfKit;

/** A PDF this code cannot read or write; the message is a short English reason ("encrypted", "no pages"). */
final class PdfException extends \RuntimeException
{
}
