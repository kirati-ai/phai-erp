<?php

declare(strict_types=1);

namespace Phai\Core\Routing;

use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Guest;
use Phai\Core\Auth\Program;
use Phai\Core\Crud\CrudController;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Http\Response;
use Phai\Core\Lang;
use Phai\Core\Pdf;
use Phai\Core\Validation\Validator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Finds every *Controller.php under src/Modules, reads its route attributes
 * and calls the matching method:
 *
 *     #[Get('/api/uoms/{um_um}')]
 *     public function show(string $um_um): array
 */
final class Router
{
    private const ACTION_BY_METHOD = ['GET' => 'view', 'POST' => 'add', 'PUT' => 'edit', 'PATCH' => 'edit', 'DELETE' => 'delete'];

    /** @var list<array{method: string, path: string, regex: string, class: string, action: string}> */
    private array $routes = [];

    public function load(string $modulesDir): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modulesDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $name = $file->getFilename();
            if (!str_ends_with($name, 'Controller.php') && !str_ends_with($name, 'Resource.php')) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($modulesDir) + 1, -4);
            $class = 'Phai\\Modules\\' . str_replace(['/', '\\'], '\\', $relative);
            str_ends_with($name, 'Resource.php') ? $this->addResource($class) : $this->addController($class);
        }
    }

    public function addController(string $class): void
    {
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF) as $attr) {
                $route = $attr->newInstance();
                $this->routes[] = [
                    'method' => $route->method,
                    'path'   => $route->path,
                    'regex'  => self::toRegex($route->path),
                    'class'  => $class,
                    'action' => $method->getName(),
                ];
            }
        }
    }

    /** Standard page + API routes for a Resource (see Crud\Resource). */
    public function addResource(string $resource): void
    {
        $path = $resource::path();
        $api = '/api' . $path;
        $routes = $resource::isSingle()
            ? [['GET', $path, 'page'], ['GET', "$api/row", 'show'], ['PUT', "$api/row", 'update']]
            : [
                ['GET', $path, 'page'], ['GET', $api, 'list'], ['POST', $api, 'create'], ['POST', "$api/import", 'import'],
                ['GET', "$api/row", 'show'], ['PUT', "$api/row", 'update'], ['DELETE', "$api/row", 'delete'],
            ];
        foreach ($routes as [$method, $p, $action]) {
            $this->routes[] = [
                'method'   => $method,
                'path'     => $p,
                'regex'    => self::toRegex($p),
                'class'    => CrudController::class,
                'action'   => $action,
                'resource' => $resource,
            ];
        }
    }

    public function routes(): array
    {
        return $this->routes;
    }

    public function dispatch(Request $request): Response
    {
        // Fixed paths win over paths with {params}: /receipts/new before /receipts/{nbr}.
        $routes = $this->routes;
        usort($routes, fn ($a, $b) => str_contains($a['path'], '{') <=> str_contains($b['path'], '{'));

        $pathMatched = false;
        foreach ($routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $request->method) {
                continue;
            }
            $request->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return $this->call($route, $request);
        }

        throw $pathMatched ? new HttpException(405, t('error.method')) : HttpException::notFound();
    }

    /** '/api/uoms/{um_um}' -> '#^/api/uoms/(?P<um_um>[^/]+)$#' */
    public static function toRegex(string $path): string
    {
        $regex = preg_replace_callback(
            '/\{(\w+)\}|[^{]+/',
            fn ($m) => isset($m[1]) ? '(?P<' . $m[1] . '>[^/]+)' : preg_quote($m[0], '#'),
            $path
        );
        return '#^' . $regex . '$#';
    }

    private function call(array $route, Request $request): Response
    {
        $method = new ReflectionMethod($route['class'], $route['action']);
        self::authorize($method, $request, isset($route['resource']) ? $route['resource']::CODE : null);
        if (str_ends_with($route['path'], '/print')) {
            Lang::setLocale('th'); // printed documents are Thai whatever the screen's language (tax invoices, forms of the Revenue Department)
        }
        $args = [];

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';

            if ($typeName === Request::class) {
                $args[] = $request;
            } elseif (array_key_exists($name, $request->params)) {
                $args[] = self::castParam($request->params[$name], $typeName);
            } elseif ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                // Input object: fill from the request body (route params win) and validate.
                $args[] = Validator::make($typeName, $request->params + $request->body);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }

        $controller = isset($route['resource']) ? new $route['class']($route['resource']) : new $route['class']();
        $result = $method->invokeArgs($controller, $args);

        // any printed document as a PDF file: /purchase-orders/PO2026100001/print?pdf=1
        if (is_string($result) && ($request->query['html'] ?? '') !== '1' && str_ends_with($route['path'], '/print')) {
            return Pdf::response($result, (string) ($request->params['nbr'] ?? array_values($request->params)[0] ?? 'document'));
        }

        return match (true) {
            $result instanceof Response => $result,
            is_string($result)          => Response::html($result),
            default                     => Response::json($result),
        };
    }

    /** Every route needs a login unless marked #[Guest]; #[Program] adds a permission check. */
    private static function authorize(ReflectionMethod $method, Request $request, ?string $program = null): void
    {
        $class = $method->getDeclaringClass();
        if ($method->getAttributes(Guest::class) || $class->getAttributes(Guest::class)) {
            return;
        }
        if (!Auth::check()) {
            throw new HttpException(401, t('auth.required'));
        }

        $attr = $program !== null ? new Program($program) : ($class->getAttributes(Program::class)[0] ?? null)?->newInstance();
        if ($attr === null) {
            return;
        }
        $action = ($method->getAttributes(Action::class)[0] ?? null)?->newInstance()->name
            ?? self::ACTION_BY_METHOD[$request->method]
            ?? 'view';
        if (!$attr->allows($action)) {
            throw new HttpException(403, t('auth.forbidden'));
        }
    }

    private static function castParam(string $value, string $type): mixed
    {
        return match ($type) {
            'int'   => preg_match('/^-?\d+$/', $value) ? (int) $value : throw HttpException::notFound(),
            'float' => is_numeric($value) ? (float) $value : throw HttpException::notFound(),
            default => $value,
        };
    }
}
