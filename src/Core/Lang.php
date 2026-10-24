<?php

declare(strict_types=1);

namespace Phai\Core;

/** Screen text lives in lang/<locale>/<group>.php; keys look like 'group.key'. */
final class Lang
{
    /** The languages of the screens (lang/<code>/): Thai first, the default and the fallback of every missing line. */
    public const LOCALES = ['th', 'en'];

    private static string $locale = 'th';
    private static array $loaded = [];

    public static function setLocale(string $locale): void
    {
        self::$locale = preg_replace('/[^a-z_]/i', '', $locale) ?: 'th';
    }

    /** A known language from a stored value such as 'th-TH' or 'en', else null. */
    public static function pick(?string $value): ?string
    {
        $code = strtolower(substr((string) $value, 0, 2));
        return in_array($code, self::LOCALES, true) ? $code : null;
    }

    /**
     * A name the database keeps in Thai (mnu_det.mnu_name, prog_mstr.prog_name) in the screen's language:
     * lang/<locale>/<group>.php keyed by its code, else the Thai name as stored.
     */
    public static function name(string $group, string $code, string $thai): string
    {
        return self::$locale === 'th' ? $thai : (self::group(self::$locale, $group)[$code] ?? $thai);
    }

    /** The label of a column that has no line in fields.php: its database comment, which is Thai — in another language the column name. */
    public static function column(string $name, string $comment): string
    {
        if ($comment !== '' && (self::$locale === 'th' || !preg_match('/\p{Thai}/u', $comment))) {
            return $comment;
        }
        return ucfirst(str_replace('_', ' ', (string) preg_replace('/^[a-z]+_/', '', $name)));
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function get(string $key, array $params = []): string
    {
        [$group, $item] = array_pad(explode('.', $key, 2), 2, '');
        $text = self::group(self::$locale, $group)[$item]
            ?? self::group('th', $group)[$item]
            ?? $key;

        foreach ($params as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }
        return $text;
    }

    public static function has(string $key): bool
    {
        [$group, $item] = array_pad(explode('.', $key, 2), 2, '');
        return isset(self::group(self::$locale, $group)[$item]) || isset(self::group('th', $group)[$item]);
    }

    /** All lines of a group, used to hand screen text to JavaScript. */
    public static function group(string $locale, string $group): array
    {
        $id = "$locale/$group";
        if (!isset(self::$loaded[$id])) {
            $file = PHAI_ROOT . "/lang/$locale/" . preg_replace('/[^a-z_]/i', '', $group) . '.php';
            self::$loaded[$id] = is_file($file) ? require $file : [];
        }
        return self::$loaded[$id];
    }
}
