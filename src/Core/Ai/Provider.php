<?php

declare(strict_types=1);

namespace Phai\Core\Ai;

use Phai\Core\App;

/**
 * One AI service. A conversation: start() with the system text, earlier turns and tools,
 * then step() until it returns no tool calls, passing tool output back with results().
 */
abstract class Provider
{
    public function __construct(
        protected readonly string $apiKey,
        protected readonly string $model,
        protected readonly ?string $endpoint = null,
    ) {
    }

    /** From config.php ai.* */
    public static function make(): self
    {
        $cfg = (array) App::config('ai', []);
        $provider = (string) ($cfg['provider'] ?? 'claude');
        $model = (string) ($cfg['model'] ?? '') ?: self::defaultModel($provider);
        $endpoint = ($cfg['endpoint'] ?? '') ?: null;
        return match ($provider) {
            'gemini' => new Gemini((string) ($cfg['api_key'] ?? ''), $model, $endpoint),
            default  => new Claude((string) ($cfg['api_key'] ?? ''), $model, $endpoint),
        };
    }

    public static function defaultModel(string $provider): string
    {
        return $provider === 'gemini' ? 'gemini-2.5-flash' : 'claude-sonnet-5-5';
    }

    abstract public function name(): string;

    public function model(): string
    {
        return $this->model;
    }

    /**
     * @param list<array{role: string, content: string}> $history earlier turns, user/assistant
     * @param list<array{name: string, description: string, params: array}> $tools
     */
    abstract public function start(string $system, array $history, array $tools): void;

    /** @return array{text: string, calls: list<array{id: string, name: string, args: array}>, in: int, out: int} */
    abstract public function step(): array;

    /** @param list<array{id: string, name: string, content: string}> $results */
    abstract public function results(array $results): void;

    /** POST JSON and return the decoded answer. Throws AiError with the service's message. */
    protected function post(string $url, array $headers, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new AiError(t('ai.unreachable') . ($error !== '' ? " ($error)" : ''));
        }
        $data = json_decode((string) $raw, true);
        if ($status >= 400 || !is_array($data)) {
            $message = is_array($data) ? ($data['error']['message'] ?? json_encode($data['error'] ?? $data)) : substr((string) $raw, 0, 200);
            throw new AiError(t('ai.service_error', ['status' => $status, 'message' => $message]));
        }
        return $data;
    }
}
