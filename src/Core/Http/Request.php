<?php

declare(strict_types=1);

namespace Phai\Core\Http;

use Phai\Core\App;

final class Request
{
    private static ?Request $current = null;

    /** Route parameters filled in by the router, e.g. ['id' => '5']. */
    public array $params = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $basePath,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        public readonly string $ip,
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $basePath = App::config('app.url') !== null
            ? rtrim((string) parse_url((string) App::config('app.url'), PHP_URL_PATH), '/')
            : self::detectBasePath($path);
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }
        $path = '/' . trim($path, '/');

        $body = $_POST;
        if (str_contains($headers['content-type'] ?? '', 'application/json')) {
            $json = json_decode((string) file_get_contents('php://input'), true);
            $body = is_array($json) ? $json : [];
        }

        return self::$current = new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $basePath,
            $_GET,
            $body,
            $headers,
            $_SERVER['REMOTE_ADDR'] ?? '',
        );
    }

    /**
     * Base path when app.url is not set yet (installer): the folder of index.php,
     * or its parent when the root .htaccess hides "/public" from the URL.
     */
    private static function detectBasePath(string $path): string
    {
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($dir !== '' && !str_starts_with($path, $dir . '/') && $path !== $dir && str_ends_with($dir, '/public')) {
            $dir = substr($dir, 0, -7);
        }
        return $dir;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isWrite(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public function isApi(): bool
    {
        return $this->path === '/api' || str_starts_with($this->path, '/api/');
    }

    public function wantsJson(): bool
    {
        return $this->isApi() || str_contains($this->header('accept') ?? '', 'application/json');
    }
}
