<?php

declare(strict_types=1);

namespace Phai\Core\Ai;

/** Google Gemini generateContent with function calling. */
final class Gemini extends Provider
{
    private array $body = [];

    public function name(): string
    {
        return 'gemini';
    }

    public function start(string $system, array $history, array $tools): void
    {
        $this->body = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'tools'             => [['functionDeclarations' => array_map(
                fn ($t) => ['name' => $t['name'], 'description' => $t['description'], 'parameters' => $t['params']],
                $tools
            )]],
            'contents'          => array_map(
                fn ($m) => ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $m['content']]]],
                $history
            ),
        ];
    }

    public function step(): array
    {
        $data = $this->post(
            ($this->endpoint ?? 'https://generativelanguage.googleapis.com') . '/v1beta/models/' . rawurlencode($this->model) . ':generateContent',
            ['x-goog-api-key: ' . $this->apiKey],
            $this->body
        );
        $content = $data['candidates'][0]['content'] ?? ['role' => 'model', 'parts' => []];
        $this->body['contents'][] = $content;
        $text = '';
        $calls = [];
        foreach ($content['parts'] ?? [] as $i => $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            } elseif (isset($part['functionCall'])) {
                $calls[] = [
                    'id'   => (string) ($part['functionCall']['id'] ?? $i),
                    'name' => (string) $part['functionCall']['name'],
                    'args' => (array) ($part['functionCall']['args'] ?? []),
                ];
            }
        }
        return [
            'text'  => $text,
            'calls' => $calls,
            'in'    => (int) ($data['usageMetadata']['promptTokenCount'] ?? 0),
            'out'   => (int) ($data['usageMetadata']['candidatesTokenCount'] ?? 0),
        ];
    }

    public function results(array $results): void
    {
        $this->body['contents'][] = ['role' => 'user', 'parts' => array_map(
            fn ($r) => ['functionResponse' => ['name' => $r['name'], 'response' => ['result' => $r['content']]]],
            $results
        )];
    }
}
