<?php

declare(strict_types=1);

use Phai\Core\Audit\Audit;
use Phai\Core\Http\HttpException;
use Phai\Core\Routing\Router;
use Phai\Core\Validation\Length;
use Phai\Core\Validation\Range;
use Phai\Core\Validation\Validator;

final class SampleInput
{
    public function __construct(
        #[Length(max: 5)]
        public string $code,
        #[Range(min: 0)]
        public float $qty,
        public ?string $note = null,
        public bool $active = true,
        public int $line = 1,
    ) {
    }
}

function invalidFields(callable $fn): array
{
    try {
        $fn();
    } catch (HttpException $e) {
        return $e->fields;
    }
    return [];
}

test('route regex matches params', function () {
    $re = Router::toRegex('/api/uoms/{um_um}');
    check(preg_match($re, '/api/uoms/EA', $m) === 1 && $m['um_um'] === 'EA');
    check(preg_match($re, '/api/uoms') === 0);
    check(preg_match($re, '/api/uoms/EA/x') === 0);
    check(preg_match(Router::toRegex('/a.b'), '/aXb') === 0, 'dots are literal');
});

test('validator converts types', function () {
    $in = Validator::make(SampleInput::class, ['code' => ' AB ', 'qty' => '2.5', 'active' => 'false', 'line' => '3']);
    check($in->code === 'AB');
    check($in->qty === 2.5);
    check($in->active === false);
    check($in->line === 3);
    check($in->note === null);
});

test('validator uses defaults for missing fields', function () {
    $in = Validator::make(SampleInput::class, ['code' => 'A', 'qty' => 0]);
    check($in->active === true && $in->line === 1);
});

test('validator collects all errors', function () {
    $fields = invalidFields(fn () => Validator::make(SampleInput::class, ['code' => 'TOOLONG', 'qty' => 'x', 'line' => '1.5']));
    check(isset($fields['code'], $fields['qty'], $fields['line']), json_encode($fields, JSON_UNESCAPED_UNICODE));
});

test('validator requires non-nullable fields', function () {
    $fields = invalidFields(fn () => Validator::make(SampleInput::class, ['code' => '', 'qty' => '-1']));
    check($fields['code'] === t('validation.required'));
    check($fields['qty'] === t('validation.min', ['min' => 0]));
});

test('translation replaces params', function () {
    check(t('validation.max_length', ['max' => 10]) === 'ไม่เกิน 10 ตัวอักษร');
    check(t('no.such_key') === 'no.such_key');
});

test('audit compares values like the database stores them', function () {
    check(Audit::same('1.50000', 1.5));
    check(Audit::same(1, true));
    check(!Audit::same('007', '7'), 'codes are text');
    check(!Audit::same(null, ''));
    check(Audit::key(['a' => 1, 'b' => 'X']) === 'a=1,b=X');
});

test('sql splitter keeps ; and -- inside quotes', function () {
    $sql = "-- header\nCREATE TABLE `a;b` (x int COMMENT 'a; -- b');\nINSERT INTO t VALUES ('it''s;', \"q;\", 'back\\'s;');\n\n-- tail\n";
    $parts = Phai\Install\Installer::statements($sql);
    check(count($parts) === 2, json_encode($parts));
    check(str_starts_with($parts[0], 'CREATE TABLE `a;b`'));
    check(str_ends_with($parts[1], "'back\\'s;')"), $parts[1]);
});

test('install page defaults: install.defaults.php over the usual, no other keys', function () {
    $file = tempnam(sys_get_temp_dir(), 'phai');
    try {
        $plain = Phai\Install\Installer::defaults($file . '.missing');
        check($plain === ['db_host' => '127.0.0.1', 'db_port' => 3306, 'db_name' => 'phaierp', 'db_user' => 'root'], json_encode($plain));
        file_put_contents($file, "<?php return ['db_port' => '3307', 'db_user' => 'root', 'db_pass' => 'secret', 'x' => 1];");
        $given = Phai\Install\Installer::defaults($file);
        check($given['db_port'] === 3307 && $given['db_host'] === '127.0.0.1', json_encode($given));
        check(!isset($given['db_pass']) && !isset($given['x']), json_encode($given));
    } finally {
        unlink($file);
    }
});

test('login redirect stays inside the app', function () {
    $next = [Phai\Modules\System\AuthController::class, 'safeNext'];
    check($next('/uoms') === '/uoms');
    check($next('//evil.example') === '/');
    check($next('/' . chr(92) . 'evil.example') === '/');
    check($next('https://evil.example') === '/');
    check($next('/api/users') === '/');
    check($next(null) === '/');
});

test('baht text', function () {
    $t = [Phai\Core\BahtText::class, 'of'];
    check($t(0) === 'ศูนย์บาทถ้วน');
    check($t(1) === 'หนึ่งบาทถ้วน');
    check($t(11) === 'สิบเอ็ดบาทถ้วน');
    check($t(21.25) === 'ยี่สิบเอ็ดบาทยี่สิบห้าสตางค์', $t(21.25));
    check($t(101) === 'หนึ่งร้อยเอ็ดบาทถ้วน', $t(101));
    check($t(1250.5) === 'หนึ่งพันสองร้อยห้าสิบบาทห้าสิบสตางค์', $t(1250.5));
    check($t(1000000) === 'หนึ่งล้านบาทถ้วน', $t(1000000));
    check($t(1000001) === 'หนึ่งล้านเอ็ดบาทถ้วน', $t(1000001));
    check($t(21000000) === 'ยี่สิบเอ็ดล้านบาทถ้วน', $t(21000000));
    check($t(0.01) === 'หนึ่งสตางค์', $t(0.01));
    check($t(1234567.89) === 'หนึ่งล้านสองแสนสามหมื่นสี่พันห้าร้อยหกสิบเจ็ดบาทแปดสิบเก้าสตางค์', $t(1234567.89));
});

test('fixed routes win over {param} routes', function () {
    $r = new Phai\Core\Routing\Router();
    $r->addController(RouteOrderController::class);
    $req = fn (string $path) => new Phai\Core\Http\Request('GET', $path, '', [], [], [], '');
    // RouteOrderController is a #[Guest] class, so no login is needed.
    check(str_contains($r->dispatch($req('/things/new'))->body, '"new"'));
    check(str_contains($r->dispatch($req('/things/42'))->body, '"42"'));
});

#[Phai\Core\Auth\Guest]
final class RouteOrderController
{
    #[Phai\Core\Routing\Get('/things/{id}')]
    public function show(string $id): array
    {
        return ['got' => $id];
    }

    #[Phai\Core\Routing\Get('/things/new')]
    public function new(): array
    {
        return ['got' => 'new'];
    }
}
