<?php

/**
 * Tiny test runner, no PHPUnit needed:  php tests/run.php
 * Each tests/*Test.php file calls test('name', function () { check(...); }).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

Phai\Core\Lang::setLocale('th');

$tests = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['tests'][$name] = $fn;
}

function check(bool $ok, string $message = ''): void
{
    if (!$ok) {
        $trace = debug_backtrace()[0];
        throw new RuntimeException(trim($message . ' (line ' . $trace['line'] . ')'));
    }
}

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    require $file;
}

$failed = 0;
foreach ($tests as $name => $fn) {
    try {
        $fn();
        echo "  ok   $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  FAIL $name: " . $e->getMessage() . "\n";
    }
}

echo "\n" . count($tests) . ' tests, ' . $failed . " failed\n";
exit($failed ? 1 : 0);
