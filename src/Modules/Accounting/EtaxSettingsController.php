<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Mail;
use Phai\Core\Routing\Delete;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;
use Phai\Core\Xades;

/** e-Tax Invoice by Email and the outgoing mail server — kept in config.php (etax.*, mail.*), the password never leaves it. */
#[Program('settings-etax')]
final class EtaxSettingsController
{
    #[Get('/settings/etax')]
    public function page(): string
    {
        return View::render('accounting/etax-settings', [
            'title'   => t('etax.settings_title'),
            'help'    => 'etax-settings',
            'scripts' => ['js/pages/etax-settings.js'],
        ]);
    }

    #[Get('/api/settings/etax')]
    public function show(): array
    {
        $e = (array) App::config('etax', []);
        $m = (array) App::config('mail', []);
        return [
            'enabled'   => (bool) ($e['enabled'] ?? false),
            'year'      => (string) ($e['year'] ?? 'BE'),
            'cc'        => (string) ($e['cc'] ?? Etax::ETDA),
            'host'      => (string) ($m['host'] ?? ''),
            'port'      => (int) ($m['port'] ?? 587),
            'secure'    => (string) ($m['secure'] ?? 'tls'),
            'user'      => (string) ($m['user'] ?? ''),
            'pass_set'  => (string) ($m['pass'] ?? '') !== '',
            'from'      => (string) ($m['from'] ?? ''),
            'from_name' => (string) ($m['from_name'] ?? ''),
            'writable'  => is_writable(PHAI_ROOT . '/config.php'),
            'cert'      => self::certInfo(),
        ];
    }

    /** Body: the fields of show(); pass blank keeps the saved password. */
    #[Put('/api/settings/etax')]
    public function update(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $host = $c->text('host', $b['host'] ?? '', false, 200, false);
        if ($host !== null && !preg_match('/^[A-Za-z0-9.\-]+$/', $host)) {
            $c->fail('host', t('validation.in'));
        }
        $port = $c->number('port', $b['port'] ?? '587', false, 1, false, 0) ?? 587;
        $secure = in_array($b['secure'] ?? '', ['tls', 'ssl', 'none'], true) ? $b['secure'] : 'tls';
        $user = $c->text('user', $b['user'] ?? '', false, 200, false);
        $from = trim((string) ($b['from'] ?? ''));
        if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $c->fail('from', t('validation.email'));
        }
        $fromName = $c->text('from_name', $b['from_name'] ?? '', false, 200, false);
        $cc = trim((string) ($b['cc'] ?? ''));
        if ($cc !== '' && !filter_var($cc, FILTER_VALIDATE_EMAIL)) {
            $c->fail('cc', t('validation.email'));
        }
        $enabled = !empty($b['enabled']);
        if ($enabled && ($host === null || $host === '' || $from === '')) {
            $c->fail($from === '' ? 'from' : 'host', t('validation.required'));
        }
        $pass = (string) ($b['pass'] ?? '');
        if (strlen($pass) > 300) {
            $c->fail('pass', t('validation.in'));
        }
        $c->done();

        $config = App::configAll();
        $oldMail = (array) ($config['mail'] ?? []);
        $oldEtax = (array) ($config['etax'] ?? []);
        $config['mail'] = [
            'host' => (string) $host, 'port' => (int) $port, 'secure' => $secure, 'user' => (string) $user,
            'pass' => $pass !== '' ? $pass : (string) ($oldMail['pass'] ?? ''), 'from' => $from, 'from_name' => (string) $fromName,
        ] + $oldMail;
        $config['etax'] = ['enabled' => $enabled, 'year' => ($b['year'] ?? 'BE') === 'CE' ? 'CE' : 'BE', 'cc' => $cc] + $oldEtax;
        App::saveConfig($config);
        $mask = fn ($a) => ['pass' => ($a['pass'] ?? '') !== '' ? '***' : ''] + $a;
        Audit::updated('config.php', 'mail', $mask($oldMail), $mask($config['mail']));
        Audit::updated('config.php', 'etax', $oldEtax, $config['etax']);
        return $this->show();
    }

    /** Body: {p12: base64 of the .p12/.pfx file, password} — checked by opening it, then kept in storage/etax. */
    #[Put('/api/settings/etax/cert')]
    public function uploadCert(Request $request): array
    {
        $raw = base64_decode(preg_replace('#^data:[^,]*,#', '', (string) ($request->body['p12'] ?? '')), true);
        $password = (string) ($request->body['password'] ?? '');
        if ($raw === false || $raw === '' || strlen($raw) > 100000) {
            throw HttpException::invalid(['p12' => t('etax.cert_bad')]);
        }
        try {
            Xades::load($raw, $password);
        } catch (\RuntimeException $e) {
            throw HttpException::invalid(['cert_password' => t('etax.cert_open')]);
        }
        $dir = dirname(Etax::certFile());
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) || @file_put_contents(Etax::certFile(), $raw, LOCK_EX) === false) {
            throw new HttpException(409, t('etax.cert_write'));
        }
        $config = App::configAll();
        $config['etax'] = ['p12_pass' => $password] + (array) ($config['etax'] ?? []);
        App::saveConfig($config);
        Audit::updated('config.php', 'etax.cert', [], ['cert' => self::certInfo()['subject'] ?? '']);
        return $this->show();
    }

    #[Delete('/api/settings/etax/cert')]
    public function removeCert(): array
    {
        @unlink(Etax::certFile());
        $config = App::configAll();
        unset($config['etax']['p12_pass']);
        App::saveConfig($config);
        Audit::updated('config.php', 'etax.cert', ['cert' => 'set'], ['cert' => null]);
        return $this->show();
    }

    /** Subject, issuer and validity of the saved certificate, or null. */
    private static function certInfo(): ?array
    {
        if (!is_file(Etax::certFile())) {
            return null;
        }
        try {
            $i = Xades::load((string) file_get_contents(Etax::certFile()), (string) App::config('etax.p12_pass', ''))['info'];
        } catch (\RuntimeException $e) {
            return ['error' => t('etax.cert_open')];
        }
        return [
            'subject' => (string) ($i['subject']['CN'] ?? $i['name']),
            'issuer'  => (string) ($i['issuer']['CN'] ?? $i['issuer']['O'] ?? ''),
            'from'    => date('Y-m-d', $i['validFrom_time_t']),
            'to'      => date('Y-m-d', $i['validTo_time_t']),
            'expired' => $i['validTo_time_t'] < time(),
        ];
    }

    /** {to} — a short message through the saved server. */
    #[Post('/api/settings/etax/test'), Action('edit')]
    public function test(Request $request): array
    {
        $to = trim((string) ($request->body['to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw HttpException::invalid(['test_to' => t('validation.email')]);
        }
        try {
            Mail::send(['to' => $to, 'subject' => t('etax.test_subject'), 'body' => t('etax.test_body')]);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, t('etax.failed', ['msg' => $e->getMessage()]));
        }
        return ['to' => $to];
    }
}
