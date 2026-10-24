<?php

declare(strict_types=1);

namespace Phai\Core\Ai;

use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Router;
use ReflectionClass;
use ReflectionMethod;

/**
 * What the AI may read: GET routes under /api/ marked #[Describe] (and every Resource list),
 * limited to the ones the signed-in user may open. The AI calls them through the router,
 * so it gets exactly what the user would see — never more, and it cannot change anything.
 */
final class Tools
{
    /** Longest tool result sent back to the model (characters). */
    private const MAX_RESULT = 24000;

    private Router $router;
    /** @var list<array{path: string, regex: string, text: string, params: array, resource: ?string}> */
    private array $sources = [];

    public function __construct(?Router $router = null)
    {
        if ($router === null) {
            $router = new Router();
            $router->load(PHAI_ROOT . '/src/Modules');
        }
        $this->router = $router;
        foreach ($router->routes() as $route) {
            if ($route['method'] === 'GET' && str_starts_with($route['path'], '/api/') && ($s = self::source($route))) {
                $this->sources[] = $s;
            }
        }
    }

    /** @return list<array{path: string, text: string, params: array}> */
    public function sources(): array
    {
        return $this->sources;
    }

    /** Catalogue for the system prompt: one line per source. */
    public function catalogue(): string
    {
        $lines = [];
        foreach ($this->sources as $s) {
            $params = $s['params'] ? ' — params: ' . implode('; ', array_map(fn ($k, $v) => "$k: $v", array_keys($s['params']), $s['params'])) : '';
            $lines[] = "GET {$s['path']} — {$s['text']}$params";
        }
        return implode("\n", $lines);
    }

    /** Tool definitions in a provider-neutral form (name, description, JSON schema). */
    public static function definitions(): array
    {
        return [
            [
                'name'        => 'api_get',
                'description' => 'Read data from one of the listed GET sources. Pass the path with its query string, '
                    . 'e.g. /api/purchase-orders?status=A&per_page=50 or /api/invoices/INV000012. Returns JSON.',
                'params'      => [
                    'type'       => 'object',
                    'properties' => ['path' => ['type' => 'string', 'description' => 'Path and query string, starting with /api/']],
                    'required'   => ['path'],
                ],
            ],
            [
                'name'        => 'fields',
                'description' => 'Column names and their meaning for a master-data source (one marked "master data"), e.g. /api/parts.',
                'params'      => [
                    'type'       => 'object',
                    'properties' => ['path' => ['type' => 'string', 'description' => 'Source path, e.g. /api/customers']],
                    'required'   => ['path'],
                ],
            ],
        ];
    }

    /** Run one tool call; always returns text for the model (errors included). */
    public function call(string $name, array $args): string
    {
        $path = trim((string) ($args['path'] ?? ''));
        return match ($name) {
            'api_get' => $this->get($path),
            'fields'  => $this->fields($path),
            default   => json_encode(['error' => "unknown tool $name"]),
        };
    }

    private function get(string $target): string
    {
        $path = (string) parse_url($target, PHP_URL_PATH);
        parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
        if (!$this->allowed($path)) {
            return json_encode(['error' => 'Not an available source: ' . $path]);
        }
        $current = Request::current();
        $request = new Request('GET', $path, $current?->basePath ?? '', $query, [], ['accept' => 'application/json'], $current?->ip ?? '');
        try {
            $response = $this->router->dispatch($request);
            $json = $response->body;
        } catch (HttpException $e) {
            return json_encode(['error' => $e->getMessage(), 'status' => $e->status], JSON_UNESCAPED_UNICODE);
        }
        if (strlen($json) > self::MAX_RESULT) {
            $json = mb_strcut($json, 0, self::MAX_RESULT) . "\n…(cut: ask for fewer rows, a filter or the next page)";
        }
        return $json;
    }

    private function fields(string $path): string
    {
        foreach ($this->sources as $s) {
            if ($s['resource'] && $s['path'] === $path) {
                $out = [];
                foreach ($s['resource']::fields() as $f) {
                    $out[$f['name']] = $f['label'] . ($f['options'] && !$f['lookup']
                        ? ' (' . implode(', ', array_map(fn ($o) => "{$o['value']}={$o['label']}", $f['options'])) . ')'
                        : '');
                }
                return json_encode($out, JSON_UNESCAPED_UNICODE);
            }
        }
        return json_encode(['error' => 'Not a master-data source: ' . $path]);
    }

    private function allowed(string $path): bool
    {
        foreach ($this->sources as $s) {
            if (preg_match($s['regex'], $path)) {
                return true;
            }
        }
        return false;
    }

    /** A route the AI may use, or null. */
    private static function source(array $route): ?array
    {
        if (isset($route['resource'])) {
            $r = $route['resource'];
            if ($route['action'] !== 'list' && !$r::isSingle() || !Auth::can($r::CODE, 'view')) {
                return null;
            }
            $extra = (new ReflectionClass($r))->getAttributes(Describe::class)[0] ?? null;
            $text = 'master data: ' . $r::title() . ($extra ? '. ' . $extra->newInstance()->text : '');
            return [
                'path' => $route['path'], 'regex' => $route['regex'], 'resource' => $r, 'text' => $text,
                'params' => $r::isSingle() ? [] : ['q' => 'search', 'sort' => 'column', 'dir' => 'asc|desc', 'page' => 'page', 'per_page' => 'rows (max 200)'],
            ];
        }

        $method = new ReflectionMethod($route['class'], $route['action']);
        $describe = $method->getAttributes(Describe::class)[0] ?? null;
        if (!$describe) {
            return null;
        }
        $program = ($method->getDeclaringClass()->getAttributes(Program::class)[0] ?? null)?->newInstance();
        $action = ($method->getAttributes(Action::class)[0] ?? null)?->newInstance()->name ?? 'view';
        if ($program !== null && !$program->allows($action)) {
            return null;
        }
        $d = $describe->newInstance();
        return ['path' => $route['path'], 'regex' => $route['regex'], 'resource' => null, 'text' => $d->text, 'params' => $d->params];
    }
}
