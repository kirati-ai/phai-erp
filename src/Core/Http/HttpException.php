<?php

declare(strict_types=1);

namespace Phai\Core\Http;

use RuntimeException;

/** Throw from anywhere to stop the request with a status code and a message for the user. */
class HttpException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message = '',
        public readonly array $fields = [],
    ) {
        parent::__construct($message);
    }

    public static function notFound(?string $message = null): self
    {
        return new self(404, $message ?? t('error.not_found'));
    }

    public static function invalid(array $fields): self
    {
        return new self(422, t('error.invalid'), $fields);
    }
}
