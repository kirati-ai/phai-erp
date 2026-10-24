<?php

declare(strict_types=1);

require __DIR__ . '/autoload.php';

// No config.php yet -> the app runs the installer only.
$configFile = PHAI_ROOT . '/config.php';
Phai\Core\App::boot(is_file($configFile) ? require $configFile : null);
