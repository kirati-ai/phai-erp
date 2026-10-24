<?php
/**
 * Layout without menu: login, installer, error pages.
 * @var string $content
 * @var array  $scripts  extra JS files under public/
 */

use Phai\Core\Lang;
?>
<!doctype html>
<html lang="<?= e(Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="phai-base" content="<?= e(url('')) ?>">
    <title><?= e($docTitle ?? (($title ?? '') !== '' ? $title . ' · PhaiERP' : 'PhaiERP')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/phaierp-logo.svg')) ?>">
    <link href="<?= e(asset('fonts/ibm-plex-sans-thai/ibm-plex-sans-thai.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/tabler/tabler.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/tabler-icons/tabler-icons.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('css/phai.css')) ?>" rel="stylesheet">
    <?php if (!empty($themed)): // the login page: the theme kept in this browser, before it paints; prints and the installer stay light ?>
        <script>try { const t = localStorage.getItem('phai-theme'); if (t === 'dark') document.documentElement.dataset.bsTheme = 'dark'; else if (t && t !== 'light') document.documentElement.dataset.phaiTheme = t; } catch (_) {}</script>
    <?php endif ?>
</head>
<body class="d-flex flex-column">
<?= $content ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="phai-toasts"></div>

<?php if (!empty($scripts)): ?>
<script>window.PHAI_LANG = <?= json_encode(Lang::group(Lang::locale(), 'js') ?: Lang::group('th', 'js'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset('vendor/tabler/tabler.min.js')) ?>"></script>
<script src="<?= e(asset('js/phai.js')) ?>"></script>
<script src="<?= e(asset('js/datepicker.js')) ?>"></script>
<?php foreach ($scripts as $script): ?>
<script src="<?= e(asset($script)) ?>"></script>
<?php endforeach ?>
<?php endif ?>
</body>
</html>
