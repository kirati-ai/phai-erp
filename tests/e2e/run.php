<?php

/**
 * End-to-end suites against a running copy of PhaiERP:
 *
 *     php tests/e2e/run.php            all suites
 *     php tests/e2e/run.php security   suites whose file name contains "security"
 *
 * Every suite gets a freshly installed test database (PHAI_TEST_DB, default phaierp_e2e)
 * through the web installer. This deletes config.php, so it refuses to run while
 * config.php points at any other database. Suites marked "@demo" get the sample data.
 * The AI suite talks to mock_ai.php, started here on 127.0.0.1:8899.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$config = PHAI_ROOT . '/config.php';
if (is_file($config) && ((include $config)['db']['name'] ?? '') !== E2E_DB) {
    fwrite(STDERR, "config.php is a real install (database is not " . E2E_DB . "). Not touching it.\n");
    exit(2);
}

/** Fresh install of the test database; the admin password goes to a temp file. */
function install(bool $demo): void
{
    @unlink(PHAI_ROOT . '/config.php');
    pdo(null)->exec('DROP DATABASE IF EXISTS `' . E2E_DB . '`');
    $pw = bin2hex(random_bytes(12));
    file_put_contents(E2E_PW_FILE, $pw);
    $c = new Client(E2E_URL);
    $c->page('/install');
    [$status, $res] = $c->req('POST', '/api/install/run', [
        'db_host' => E2E_DB_HOST, 'db_port' => '3306', 'db_name' => E2E_DB, 'db_user' => E2E_DB_USER, 'db_pass' => E2E_DB_PASS,
        'co_name' => 'E2E Test Co', 'co_tax_id' => '', 'us_userid' => 'admin', 'us_name' => 'Admin',
        'us_email' => 'admin@example.test', 'password' => $pw, 'demo' => $demo,
    ]);
    if ($status !== 200 || empty($res['data']['config_written']) || !empty($res['data']['demo_error'])) {
        throw new RuntimeException('Install failed: ' . json_encode($res, JSON_UNESCAPED_UNICODE));
    }
    // the suites check numbers like PO000001: use the 6-digit format (02-install checks the year-month default)
    require_once PHAI_ROOT . '/src/autoload.php';
    foreach (Phai\Core\Numbering::DOCS as [$table, $stem]) {
        pdo()->exec("UPDATE `$table` SET `{$stem}_format` = 'N6'");
    }
}

$filter = $argv[1] ?? '';
$suites = array_filter(glob(__DIR__ . '/[0-9]*.php'), fn ($f) => $filter === '' || str_contains(basename($f), $filter));
sort($suites);

$mock = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . E2E_MOCK_PORT, __DIR__ . '/mock_ai.php'],
    [['pipe', 'r'], ['file', sys_get_temp_dir() . '/phaierp-e2e-mock.log', 'a'], ['file', sys_get_temp_dir() . '/phaierp-e2e-mock.log', 'a']],
    $pipes
);
usleep(300000);

$failed = [];
$started = microtime(true);
try {
    foreach ($suites as $file) {
        $name = basename($file, '.php');
        install(str_contains((string) file_get_contents($file), '@demo'));
        $out = [];
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
        $okCount = count(preg_grep('/^  ok /', $out));
        echo str_pad($name, 16), $okCount, ' ok', $code === 0 ? '' : '  FAILED', "\n";
        if ($code !== 0) {
            $failed[] = $name;
            echo implode("\n", array_filter($out, fn ($l) => !str_starts_with($l, '  ok '))), "\n";
        }
    }
} finally {
    if (is_resource($mock)) {
        proc_terminate($mock);
    }
    @unlink(PHAI_ROOT . '/config.php');
    pdo(null)->exec('DROP DATABASE IF EXISTS `' . E2E_DB . '`');
    @unlink(E2E_PW_FILE);
    @unlink(E2E_MOCK_LAST);
    // the test database's own files (logos are kept per database, so the real one is never touched)
    foreach (glob(PHAI_ROOT . '/storage/uploads/' . E2E_DB . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir(PHAI_ROOT . '/storage/uploads/' . E2E_DB);
}

printf("%s in %.0fs\n", $failed ? 'FAILED: ' . implode(', ', $failed) : 'all suites passed', microtime(true) - $started);
exit($failed ? 1 : 0);
