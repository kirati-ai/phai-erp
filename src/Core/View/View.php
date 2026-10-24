<?php

declare(strict_types=1);

namespace Phai\Core\View;

/**
 * Plain PHP templates in views/. A page renders into $content, then the layout wraps it.
 *
 *     return View::render('master/uoms', ['title' => t('menu.uoms')]);
 */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'app'): string
    {
        $content = self::file("views/$template.php", $data);
        if ($layout === null) {
            return $content;
        }
        return self::file("views/layouts/$layout.php", $data + ['content' => $content]);
    }

    /** A piece of a page without layout, e.g. View::partial('crud/field', ['f' => $field]). */
    public static function partial(string $template, array $data = []): string
    {
        return self::file("views/$template.php", $data);
    }

    private static function file(string $relative, array $data): string
    {
        $path = PHAI_ROOT . '/' . $relative;
        if (!preg_match('#^views/[a-z0-9_/-]+\.php$#', $relative) || !is_file($path)) {
            throw new \RuntimeException("View not found: $relative");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
