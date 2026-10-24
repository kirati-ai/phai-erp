<?php

declare(strict_types=1);

namespace Phai\Core\Http;

use Phai\Core\View\View;

final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** Successful API answer: {"ok": true, "data": ...} */
    public static function json(mixed $data = null, int $status = 200): self
    {
        return self::rawJson(['ok' => true, 'data' => $data], $status);
    }

    public static function rawJson(array $payload, int $status = 200): self
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return new self($body, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    /** Error as JSON for API calls, or as an HTML page for browser pages. */
    public static function error(int $status, string $message, ?Request $request, array $fields = []): self
    {
        if ($request === null || $request->wantsJson()) {
            $payload = ['ok' => false, 'error' => $message];
            if ($fields) {
                $payload['fields'] = $fields;
            }
            return self::rawJson($payload, $status);
        }
        if ($status === 401) {
            return self::redirect(url('/login') . '?next=' . rawurlencode($request->path));
        }
        return self::html(View::render('error', ['status' => $status, 'message' => $message], 'blank'), $status);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: same-origin');
            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        }
        echo $this->body;
    }
}
