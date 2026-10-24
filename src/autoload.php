<?php

declare(strict_types=1);

defined('PHAI_ROOT') || define('PHAI_ROOT', dirname(__DIR__));

// PSR-4: Phai\Core\Http\Request -> src/Core/Http/Request.php
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'Phai\\')) {
        return;
    }
    $file = PHAI_ROOT . '/src/' . str_replace('\\', '/', substr($class, 5)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require PHAI_ROOT . '/src/helpers.php';
