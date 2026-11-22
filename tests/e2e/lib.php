<?php

/**
 * Helpers for the end-to-end suites. They drive a real install over HTTP, so they need
 * the site running (PHAI_URL) and a database server; run them through run.php, which
 * installs a fresh test database for every suite.
 *
 * Environment: PHAI_URL (http://localhost/phaierp), PHAI_TEST_DB (phaierp_e2e),
 * PHAI_DB_HOST (127.0.0.1), PHAI_DB_USER (root), PHAI_DB_PASS ('').
 */

declare(strict_types=1);

define('PHAI_ROOT', dirname(__DIR__, 2));
define('E2E_URL', rtrim(getenv('PHAI_URL') ?: 'http://localhost/phaierp', '/'));
/** Path of the site, e.g. /phaierp — links in pages start with it. */
define('E2E_BASE', rtrim((string) parse_url(E2E_URL, PHP_URL_PATH), '/'));
define('E2E_DB', getenv('PHAI_TEST_DB') ?: 'phaierp_e2e');
define('E2E_DB_HOST', getenv('PHAI_DB_HOST') ?: '127.0.0.1');
define('E2E_DB_USER', getenv('PHAI_DB_USER') ?: 'root');
define('E2E_DB_PASS', getenv('PHAI_DB_PASS') ?: '');
/** Generated admin password of the current test install (outside the repo). */
define('E2E_PW_FILE', sys_get_temp_dir() . '/phaierp-e2e.pw');
define('E2E_MOCK_PORT', 8899);
define('E2E_MOCK_LAST', sys_get_temp_dir() . '/phaierp-e2e-mock.json');

/** A browser-like client: keeps the session cookie and the CSRF token of the last page. */
final class Client
{
    public array $cookies = [];
    public string $token = '';

    public function __construct(private string $base)
    {
    }

    /** @return array{0: int, 1: mixed, 2: ?string} status, decoded JSON (or raw body), Location */
    public function req(string $method, string $path, ?array $json = null): array
    {
        $headers = ['Accept: application/json'];
        if ($this->cookies) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        }
        if ($this->token) {
            $headers[] = 'X-CSRF-Token: ' . $this->token;
        }
        $opts = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0];
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts['content'] = json_encode($json);
        }
        $opts['header'] = implode("\r\n", $headers);
        $body = file_get_contents($this->base . $path, false, stream_context_create(['http' => $opts]));
        $status = 0;
        $location = null;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) {
                $status = (int) $m[1];
            }
            if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $h, $m)) {
                $this->cookies[$m[1]] = $m[2];
            }
            if (preg_match('/^Location: (.*)$/i', $h, $m)) {
                $location = trim($m[1]);
            }
        }
        if (preg_match('/name="csrf-token" content="([a-f0-9]+)"/', (string) $body, $m)) {
            $this->token = $m[1];
        }
        return [$status, json_decode((string) $body, true) ?? $body, $location];
    }

    public function page(string $path): array
    {
        return $this->req('GET', $path);
    }
}

$B = E2E_URL;
$pw = is_file(E2E_PW_FILE) ? trim((string) file_get_contents(E2E_PW_FILE)) : '';
$fails = 0;

/** A vendor (vd) / customer (cm) row with what a Thai trading party must have (Address::requireParty, terms, currency, VAT). */
function party(string $p, array $row): array
{
    return $row + [
        "{$p}_tax_id_num" => '0999999999990', "{$p}_tax_branch" => '00000', "{$p}_province" => 'กรุงเทพมหานคร',
        "{$p}_district" => 'เขต บางรัก', "{$p}_sub_district" => 'สีลม', "{$p}_zip" => '10500',
        "{$p}_terms" => 'COD', "{$p}_curr" => 'THB', "{$p}_tax_code" => 'VAT7',
    ];
}

function ok(string $name, bool $cond, mixed $info = null): void
{
    global $fails;
    if (!$cond) {
        $fails++;
    }
    echo ($cond ? '  ok   ' : '  FAIL ') . $name
        . ($cond || $info === null ? '' : ' :: ' . json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "\n";
}

/** Print the result and exit with 1 when anything failed. */
function finish(): never
{
    global $fails;
    echo $fails ? "$fails FAILED\n" : "all passed\n";
    exit($fails ? 1 : 0);
}

function login(string $user, string $pass): Client
{
    $c = new Client(E2E_URL);
    $c->page('/login');
    $c->req('POST', '/api/login', ['login' => $user, 'password' => $pass]);
    $c->page('/');
    return $c;
}

function pdo(?string $db = E2E_DB): PDO
{
    return new PDO(
        'mysql:host=' . E2E_DB_HOST . ($db ? ';dbname=' . $db : '') . ';charset=utf8mb4',
        E2E_DB_USER,
        E2E_DB_PASS,
        [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function db(string $sql): array
{
    return pdo()->query($sql)->fetchAll();
}

function one(string $sql): mixed
{
    $r = db($sql);
    return $r ? array_values($r[0])[0] : null;
}

/** Raw HTTP request: [status, response headers, body]. */
function raw(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $opts = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'header' => implode("\r\n", $headers)];
    if ($body !== null) {
        $opts['content'] = $body;
    }
    $out = @file_get_contents($url, false, stream_context_create(['http' => $opts]));
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) {
            $status = (int) $m[1];
        }
    }
    return [$status, $http_response_header ?? [], (string) $out];
}

/** Cookie header carrying a client's session, for raw(). */
function cookie(Client $c): string
{
    return 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($c->cookies), $c->cookies));
}

/** Every route: method, path, program, action, guest. */
function routes(): array
{
    require_once PHAI_ROOT . '/src/autoload.php';
    $r = new Phai\Core\Routing\Router();
    $r->load(PHAI_ROOT . '/src/Modules');
    $out = [];
    foreach ($r->routes() as $route) {
        $m = new ReflectionMethod($route['class'], $route['action']);
        $class = $m->getDeclaringClass();
        $out[] = [
            'method'  => $route['method'],
            'path'    => $route['path'],
            'guest'   => (bool) ($m->getAttributes(Phai\Core\Auth\Guest::class) || $class->getAttributes(Phai\Core\Auth\Guest::class)),
            'program' => isset($route['resource']) ? $route['resource']::CODE : (($class->getAttributes(Phai\Core\Auth\Program::class)[0] ?? null)?->newInstance()->code),
        ];
    }
    return $out;
}
