<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Audit\Audit;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Put;
use Phai\Core\Routing\Router;
use ReflectionClass;

/** Shared display settings; access is checked against the target API's program. */
final class GridLayoutController
{
    private function key(Request $request): string
    {
        $source = $request->input('source', '');
        $table = $request->input('table', '');
        // a source the user cannot see answers 403 before any validation message
        if (!is_string($source) || strlen($source) > 160 || !preg_match('#^/api/[a-zA-Z0-9/_-]+$#', $source)) {
            throw new HttpException(403, t('auth.forbidden'));
        }
        $router = new Router();
        $router->load(PHAI_ROOT . '/src/Modules');
        // the GET route of the source: its own path first, else one with {params} (e.g. /api/schedules/{type})
        $match = null;
        foreach ($router->routes() as $route) {
            if ($route['method'] !== 'GET' || $route['class'] === self::class) continue;
            if ($route['path'] === $source) {
                $match = $route;
                break;
            }
            $pattern = implode('/', array_map(fn ($s) => preg_match('/^\{\w+\}$/', $s) ? '[^/]+' : preg_quote($s, '#'), explode('/', $route['path'])));
            if ($match === null && preg_match("#^$pattern$#", $source)) {
                $match = $route;
            }
        }
        if ($match !== null) {
            $program = isset($match['resource']) ? new Program($match['resource']::CODE)
                : ((new ReflectionClass($match['class']))->getAttributes(Program::class)[0] ?? null)?->newInstance();
            if ($program !== null && $program->allows('view')) {
                if (!is_string($table) || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $table)) {
                    throw HttpException::invalid(['table' => t('validation.in')]);
                }
                return $source . ':' . $table;
            }
        }
        throw new HttpException(403, t('auth.forbidden'));
    }

    private function ensureTable(): void
    {
        App::db()->run("CREATE TABLE IF NOT EXISTS grid_layout (
            layout_key varchar(255) NOT NULL PRIMARY KEY,
            layout_columns longtext NOT NULL,
            updated_by varchar(50) NOT NULL,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    #[Get('/api/grid-layout')]
    public function show(Request $request): array
    {
        $this->ensureTable();
        $key = $this->key($request);
        $json = App::db()->value('SELECT layout_columns FROM grid_layout WHERE layout_key = ?', [$key]);
        return ['columns' => $json === null ? null : json_decode($json, true)];
    }

    #[Put('/api/grid-layout')]
    public function save(Request $request): array
    {
        $this->ensureTable();
        $key = $this->key($request);
        $columns = $request->body['columns'] ?? null;
        if ($columns === null) {
            App::db()->transaction(function () use ($key) {
                $old = App::db()->one('SELECT * FROM grid_layout WHERE layout_key = ? FOR UPDATE', [$key]);
                if ($old) {
                    App::db()->run('DELETE FROM grid_layout WHERE layout_key = ?', [$key]);
                    Audit::deleted('grid_layout', $key, $old);
                }
            });
            return ['columns' => null];
        }

        if (!is_array($columns) || count($columns) > 200) throw HttpException::invalid(['columns' => t('validation.in')]);

        if (isset($columns['visible']) || isset($columns['order']) || isset($columns['widths'])) {
            if (isset($columns['visible'])) {
                if (!is_array($columns['visible']) || count($columns['visible']) > 200) throw HttpException::invalid(['columns.visible' => t('validation.in')]);
                foreach ($columns['visible'] as $name => $visible) {
                    if (!is_string($name) || !preg_match('/^[a-zA-Z0-9_]{1,80}$/', $name) || !is_bool($visible))
                        throw HttpException::invalid(['columns.visible' => t('validation.in')]);
                }
            }
            if (isset($columns['order'])) {
                if (!is_array($columns['order']) || count($columns['order']) > 200) throw HttpException::invalid(['columns.order' => t('validation.in')]);
                foreach ($columns['order'] as $name) {
                    if (!is_string($name) || !preg_match('/^[a-zA-Z0-9_]{1,80}$/', $name))
                        throw HttpException::invalid(['columns.order' => t('validation.in')]);
                }
            }
            if (isset($columns['widths'])) {
                if (!is_array($columns['widths']) || count($columns['widths']) > 200) throw HttpException::invalid(['columns.widths' => t('validation.in')]);
                foreach ($columns['widths'] as $name => $width) {
                    if (!is_string($name) || !preg_match('/^[a-zA-Z0-9_]{1,80}$/', $name) || (!is_int($width) && !is_float($width)) || $width < 20 || $width > 2000)
                        throw HttpException::invalid(['columns.widths' => t('validation.in')]);
                }
            }
        } else {
            foreach ($columns as $name => $visible) {
                if (!is_string($name) || !preg_match('/^[a-zA-Z0-9_]{1,80}$/', $name) || !is_bool($visible))
                    throw HttpException::invalid(['columns' => t('validation.in')]);
            }
        }

        App::db()->transaction(function () use ($key, $columns) {
            $old = App::db()->one('SELECT * FROM grid_layout WHERE layout_key = ? FOR UPDATE', [$key]);
            $row = ['layout_key' => $key, 'layout_columns' => json_encode($columns), 'updated_by' => Auth::userId()];
            if ($old) {
                App::db()->update('grid_layout', ['layout_columns' => $row['layout_columns'], 'updated_by' => $row['updated_by']], ['layout_key' => $key]);
                Audit::updated('grid_layout', $key, $old, $row);
            } else {
                App::db()->insert('grid_layout', $row);
                Audit::created('grid_layout', $key, $row);
            }
        });
        return ['columns' => $columns];
    }
}
