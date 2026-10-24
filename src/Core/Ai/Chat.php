<?php

declare(strict_types=1);

namespace Phai\Core\Ai;

use Phai\Core\App;
use Phai\Core\Auth\Auth;

/**
 * Answers one question: the model may call the read-only tools several times, then replies.
 * Every question is written to ai_log with tokens and cost (USD, from ai.price_in / ai.price_out
 * per million tokens in config.php).
 */
final class Chat
{
    /** Tool rounds before giving up on a question. */
    private const MAX_STEPS = 8;
    /** Earlier turns sent with a question. */
    private const MAX_HISTORY = 12;

    public static function enabled(): bool
    {
        return (bool) App::config('ai.enabled', false) && (string) App::config('ai.api_key', '') !== '' && extension_loaded('curl');
    }

    /**
     * @param list<array{role: string, content: string}> $history earlier turns
     * @return array{answer: string, sources: list<string>, cost: float}
     */
    public static function ask(string $question, array $history = [], ?Provider $provider = null, ?Tools $tools = null): array
    {
        $provider ??= Provider::make();
        $tools ??= new Tools();
        $started = microtime(true);
        $in = $out = 0;
        $sources = [];
        $answer = '';

        $turns = array_slice(array_values(array_filter(
            $history,
            fn ($m) => is_array($m) && in_array($m['role'] ?? '', ['user', 'assistant'], true) && trim((string) ($m['content'] ?? '')) !== ''
        )), -self::MAX_HISTORY);
        $turns = array_map(fn ($m) => ['role' => $m['role'], 'content' => mb_substr((string) $m['content'], 0, 8000)], $turns);
        while ($turns && $turns[0]['role'] !== 'user') {
            array_shift($turns); // a conversation starts with the user
        }
        $turns[] = ['role' => 'user', 'content' => $question];

        try {
            $provider->start(self::system($tools), $turns, Tools::definitions());
            for ($i = 0; $i < self::MAX_STEPS; $i++) {
                $step = $provider->step();
                $in += $step['in'];
                $out += $step['out'];
                if (!$step['calls']) {
                    $answer = trim($step['text']);
                    break;
                }
                $results = [];
                foreach ($step['calls'] as $call) {
                    $sources[] = (string) ($call['args']['path'] ?? $call['name']);
                    $results[] = ['id' => $call['id'], 'name' => $call['name'], 'content' => $tools->call($call['name'], $call['args'])];
                }
                $provider->results($results);
            }
            if ($answer === '') {
                $answer = t('ai.no_answer');
            }
        } catch (AiError $e) {
            self::log($provider, $question, '', $sources, $in, $out, $started, $e->getMessage());
            throw $e;
        }

        $cost = self::log($provider, $question, $answer, $sources, $in, $out, $started, null);
        return ['answer' => $answer, 'sources' => array_values(array_unique($sources)), 'cost' => $cost];
    }

    private static function system(Tools $tools): string
    {
        $db = App::db();
        $company = (string) $db->value('SELECT co_name FROM co_mstr WHERE co_id = 1');
        $base = (string) ($db->value('SELECT gl_base_curr FROM gl_ctrl WHERE ctrl_id = 1') ?: 'THB');
        $user = Auth::user() ?? [];
        $today = date('Y-m-d');
        return <<<TXT
            You are the assistant inside PhaiERP, the ERP system of "{$company}". Today is {$today}. Base currency: {$base}.
            You are talking to {$user['us_name']} (role {$user['us_role']}).

            - Answer in the language of the question (usually Thai). Be short and direct; use a small markdown table for lists.
            - Use only data returned by the tools. Never guess numbers or records; if the data is not available to this user, say so.
            - You can only read. If asked to create, change or delete something, say which screen to use instead.
            - Lists are paged ({"rows", "total", "page", "per_page"}) and accept page, per_page (up to 500), sort and dir: filter, or raise per_page, when you need more rows.
            - Show dates as dd/mm/yyyy and money with 2 decimals. Ledger amounts are positive for debit, negative for credit.
            - For column meanings of master data use the fields tool.

            Data sources this user may read:
            {$tools->catalogue()}
            TXT;
    }

    private static function log(Provider $p, string $question, string $answer, array $sources, int $in, int $out, float $started, ?string $error): float
    {
        $cost = round($in / 1e6 * (float) App::config('ai.price_in', 0) + $out / 1e6 * (float) App::config('ai.price_out', 0), 6);
        $user = Auth::userId();
        App::db()->insert('ai_log', [
            'ai_userid' => $user, 'ai_provider' => $p->name(), 'ai_model' => $p->model(),
            'ai_question' => mb_substr($question, 0, 4000), 'ai_answer' => $answer, 'ai_tools' => json_encode(array_values($sources), JSON_UNESCAPED_UNICODE),
            'ai_in_tok' => $in, 'ai_out_tok' => $out, 'ai_cost' => $cost, 'ai_ms' => (int) ((microtime(true) - $started) * 1000),
            'ai_status' => $error === null ? 'S' : 'E', 'ai_error' => $error === null ? null : mb_substr($error, 0, 255),
            'created_by' => $user, 'updated_by' => $user,
        ]);
        return $cost;
    }
}
