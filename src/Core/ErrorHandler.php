<?php

declare(strict_types=1);

namespace Phai\Core;

use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Http\Response;
use Throwable;

final class ErrorHandler
{
    private static bool $debug = false;

    public static function register(bool $debug): void
    {
        self::$debug = $debug;
        error_reporting(E_ALL);
        ini_set('display_errors', '0');

        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $level)) {
                return false;
            }
            throw new \ErrorException($message, 0, $level, $file, $line);
        });

        set_exception_handler(function (Throwable $e): void {
            self::render($e, Request::current())->send();
        });
    }

    public static function render(Throwable $e, ?Request $request): Response
    {
        if ($e instanceof HttpException) {
            return Response::error($e->status, $e->getMessage(), $request, $e->fields);
        }

        self::log($e);
        $message = self::$debug
            ? get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine()
            : t('error.server');
        return Response::error(500, $message, $request);
    }

    private static function log(Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        @file_put_contents(PHAI_ROOT . '/storage/logs/' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
