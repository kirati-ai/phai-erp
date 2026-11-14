<?php

// Thai is the main language and English is complete: every Thai line has an English one, the English lines are English,
// and screen text lives in lang/<locale>/*.php, not in code. Thai stays on purpose in the files listed in LANG_THAI_FILES.

/** The Revenue Department's own forms: printed in Thai in every language, so their English files hold the Thai words. */
const LANG_THAI_FORMS = ['pndform', 'whtform', 'thaidate'];

/** Code with Thai on purpose: baht in words, Thai address words, e-Tax XML data, SQL notes, sample data, Thai prints, per-language text. */
const LANG_THAI_FILES = [
    'src/Core/BahtText.php', 'src/Modules/System/Address.php', 'src/Modules/Accounting/EtaxXml.php', 'src/Modules/Accounting/VatReport.php',
    'src/Install/Demo.php', 'src/Install/Installer.php', 'src/Core/Crud/CrudController.php', 'public/js/xlsx.js',
    'views/assets/print.php', 'views/assets/depreciation-print.php', 'views/assets/report.php',
];
const LANG_THAI_DIRS = ['views/help/', 'views/learn/'];

function langLines(string $file): array
{
    $flat = function (array $a, string $prefix = '') use (&$flat): array {
        $out = [];
        foreach ($a as $k => $v) {
            $out += is_array($v) ? $flat($v, "$prefix$k.") : ["$prefix$k" => $v];
        }
        return $out;
    };
    return is_file($file) ? $flat(require $file) : [];
}

test('Every Thai line has an English one', function () {
    $root = dirname(__DIR__);
    $missing = [];
    foreach (glob("$root/lang/th/*.php") as $file) {
        $group = basename($file, '.php');
        foreach (array_keys(array_diff_key(langLines($file), langLines("$root/lang/en/$group.php"))) as $key) {
            $missing[] = "$group.$key";
        }
    }
    check(!$missing, count($missing) . ' missing in lang/en: ' . implode(', ', array_slice($missing, 0, 12)));
});

test('The English lines are English', function () {
    $root = dirname(__DIR__);
    $thai = [];
    foreach (glob("$root/lang/en/*.php") as $file) {
        $group = basename($file, '.php');
        if (in_array($group, LANG_THAI_FORMS, true)) {
            continue;
        }
        foreach (langLines($file) as $key => $text) {
            // the language switch names Thai in Thai
            if ("$group.$key" !== 'common.lang_th' && is_string($text) && preg_match('/\p{Thai}/u', $text)) {
                $thai[] = "$group.$key";
            }
        }
    }
    check(!$thai, 'Thai in lang/en: ' . implode(', ', array_slice($thai, 0, 12)));
});

test('Every help page and Learn ERP have an English one, Thai only to name a Thai document in brackets', function () {
    $root = dirname(__DIR__);
    $missing = [];
    $thai = [];
    $pages = array_map(fn ($f) => 'help/' . basename($f), glob("$root/views/help/th/*.php"));
    foreach ([...$pages, 'learn'] as $page) {
        $en = $page === 'learn' ? "$root/views/learn/en.php" : "$root/views/help/en/" . basename($page);
        if (!is_file($en)) {
            $missing[] = $page;
            continue;
        }
        // what is left once comments and bracketed Thai names (ใบกำกับภาษี) are taken out must be English
        $text = preg_replace('~/\*.*?\*/|<!--.*?-->|\([^()]*\)~s', '', (string) file_get_contents($en));
        if (preg_match('/\p{Thai}/u', $text)) {
            $thai[] = $page;
        }
    }
    check(!$missing, 'no English page: ' . implode(', ', $missing));
    check(!$thai, 'Thai outside brackets: ' . implode(', ', $thai));
    if (is_file("$root/views/learn/en.php")) {
        $shape = function ($a) use (&$shape) {
            return is_array($a) ? array_map($shape, $a) : null;
        };
        check($shape(require "$root/views/learn/th.php") == $shape(require "$root/views/learn/en.php"), 'views/learn/en.php is not shaped like th.php');
    }
});

test('Screen text is in the lang files, not in code', function () {
    $root = dirname(__DIR__);
    $found = [];
    foreach (['src', 'views', 'public/js'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = substr(strtr($file->getPathname(), '\\', '/'), strlen($root) + 1);
            if (!preg_match('/\.(php|js)$/', $path) || in_array($path, LANG_THAI_FILES, true)
                || array_filter(LANG_THAI_DIRS, fn ($d) => str_starts_with($path, $d))) {
                continue;
            }
            foreach (file($file->getPathname()) as $i => $line) {
                $trim = ltrim($line);
                if (str_starts_with($trim, '//') || str_starts_with($trim, '*') || str_starts_with($trim, '/*') || str_starts_with($trim, '#')) {
                    continue; // comments may be Thai
                }
                $code = preg_replace('~/\*.*?\*/|<!--.*?-->|<\?php\s*//.*?\?>|\s//\s.*$~', '', $line);
                if (preg_match('/\p{Thai}/u', $code)) {
                    $found[] = $path . ':' . ($i + 1);
                }
            }
        }
    }
    check(!$found, count($found) . ' lines with Thai in code (move them to lang/th + lang/en): ' . implode(', ', array_slice($found, 0, 12)));
});
