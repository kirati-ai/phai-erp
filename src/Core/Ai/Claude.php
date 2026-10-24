<?php

declare(strict_types=1);

namespace Phai\Core\Ai;

/** Anthropic Messages API with tool use. */
final class Claude extends Provider
{
    private string $system = '';
    private array $tools = [];
    private array $messages = [];

    public function name(): string
    {
        return 'claude';
    }

    public function start(string $system, array $history, array $tools): void
    {
        $this->system = $system;
        $this->tools = array_map(fn ($t) => ['name' => $t['name'], 'description' => $t['description'], 'input_schema' => $t['params']], $tools);
        $this->messages = array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $history);
    }

    public function step(): array
    {
        $data = $this->post(
            ($this->endpoint ?? 'https://api.anthropic.com') . '/v1/messages',
            ['x-api-key: ' . $this->apiKey, 'anthropic-version: 2023-06-01'],
            ['model' => $this->model, 'max_tokens' => 4096, 'system' => $this->system, 'tools' => $this->tools, 'messages' => $this->messages]
        );
        $content = $data['content'] ?? [];
        $this->messages[] = ['role' => 'assistant', 'content' => $content];
        $text = '';
        $calls = [];
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            } elseif (($block['type'] ?? '') === 'tool_use') {
                $calls[] = ['id' => (string) $block['id'], 'name' => (string) $block['name'], 'args' => (array) ($block['input'] ?? [])];
            }
        }
        return [
            'text'  => $text,
            'calls' => $calls,
            'in'    => (int) ($data['usage']['input_tokens'] ?? 0),
            'out'   => (int) ($data['usage']['output_tokens'] ?? 0),
        ];
    }

    public function results(array $results): void
    {
        $this->messages[] = ['role' => 'user', 'content' => array_map(
            fn ($r) => ['type' => 'tool_result', 'tool_use_id' => $r['id'], 'content' => $r['content']],
            $results
        )];
    }
}
