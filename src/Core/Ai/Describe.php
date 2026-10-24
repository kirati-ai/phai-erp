<?php

declare(strict_types=1);

namespace Phai\Core\Ai;

use Attribute;

/**
 * Tells the built-in AI what a read API returns, so it can use it as a data source:
 *
 *     #[Get('/api/purchase-orders'), Describe('Purchase orders', ['q' => 'search number or vendor', 'status' => 'P, A, C, X'])]
 *
 * Only GET routes under /api/ with this attribute are offered to the AI, and only to users
 * allowed to open them. On a Resource class it adds to the description built from the table.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class Describe
{
    /** @param array<string, string> $params query parameter => meaning */
    public function __construct(
        public readonly string $text,
        public readonly array $params = [],
    ) {
    }
}
