<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\Ai\Provider;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Program;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Put;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;

/**
 * AI provider, model, key and prices — kept in config.php (never in the database or git) —
 * and what the AI has cost so far, from ai_log.
 */
#[Program('settings-ai')]
final class AiSettingsController
{
    public const PROVIDERS = ['claude', 'gemini'];

    #[Get('/settings/ai')]
    public function page(): string
    {
        return View::render('system/ai-settings', [
            'title'   => t('ai.settings_title'),
            'help'    => 'settings-ai',
            'config'  => ['providers' => array_combine(self::PROVIDERS, array_map([Provider::class, 'defaultModel'], self::PROVIDERS))],
            'scripts' => ['js/pages/ai-settings.js'],
        ]);
    }

    #[Get('/api/settings/ai')]
    public function show(): array
    {
        $ai = (array) App::config('ai', []);
        $key = (string) ($ai['api_key'] ?? '');
        return [
            'enabled'   => (bool) ($ai['enabled'] ?? false),
            'provider'  => (string) ($ai['provider'] ?? 'claude'),
            'model'     => (string) ($ai['model'] ?? ''),
            'price_in'  => (float) ($ai['price_in'] ?? 0),
            'price_out' => (float) ($ai['price_out'] ?? 0),
            'key_set'   => $key !== '',
            'key_hint'  => $key !== '' ? '…' . substr($key, -4) : '',
            'writable'  => is_writable(PHAI_ROOT . '/config.php'),
            'usage'     => self::usage(),
        ];
    }

    /** Body: {enabled, provider, model, api_key (blank keeps the saved key), price_in, price_out} */
    #[Put('/api/settings/ai')]
    public function update(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $provider = in_array($b['provider'] ?? '', self::PROVIDERS, true) ? $b['provider'] : null;
        if ($provider === null) {
            $c->fail('provider', t('validation.in'));
        }
        $model = $c->text('model', $b['model'] ?? '', false, 100, false);
        if ($model !== null && !preg_match('/^[A-Za-z0-9._\-\/:]+$/', $model)) {
            $c->fail('model', t('validation.in'));
        }
        $key = trim((string) ($b['api_key'] ?? ''));
        if (strlen($key) > 300 || preg_match('/\s/', $key)) {
            $c->fail('api_key', t('validation.in'));
        }
        $in = $c->number('price_in', $b['price_in'] ?? '0', false, 0, false, 4) ?? 0.0;
        $out = $c->number('price_out', $b['price_out'] ?? '0', false, 0, false, 4) ?? 0.0;
        $enabled = !empty($b['enabled']);
        $old = (array) App::config('ai', []);
        if ($enabled && $key === '' && (string) ($old['api_key'] ?? '') === '') {
            $c->fail('api_key', t('ai.key_needed'));
        }
        $c->done();

        $config = App::configAll();
        $config['ai'] = [
            'enabled'   => $enabled,
            'provider'  => $provider,
            'api_key'   => $key !== '' ? $key : (string) ($old['api_key'] ?? ''),
            'model'     => (string) $model,
            'price_in'  => $in,
            'price_out' => $out,
        ] + $old;
        App::saveConfig($config);
        $mask = fn ($a) => ['api_key' => ($a['api_key'] ?? '') !== '' ? '…' . substr($a['api_key'], -4) : ''] + $a;
        Audit::updated('config.php', 'ai', $mask($old), $mask($config['ai']));
        return $this->show();
    }

    /** Questions, tokens and cost per user: this month and all time. */
    private static function usage(): array
    {
        $month = date('Y-m-01');
        return App::db()->all(
            "SELECT l.ai_userid, u.us_name,
                    SUM(l.created_at >= ?) AS month_questions,
                    SUM(IF(l.created_at >= ?, l.ai_cost, 0)) AS month_cost,
                    COUNT(*) AS questions, SUM(l.ai_in_tok + l.ai_out_tok) AS tokens, SUM(l.ai_cost) AS cost,
                    SUM(l.ai_status = 'E') AS errors, MAX(l.created_at) AS last_at
               FROM ai_log l LEFT JOIN us_mstr u ON u.us_userid = l.ai_userid
              GROUP BY l.ai_userid, u.us_name
              ORDER BY month_cost DESC, cost DESC",
            [$month, $month]
        );
    }
}
