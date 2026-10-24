<?php
use Phai\Core\App;
use Phai\Core\Lang;
use Phai\Core\View\View;

$labels = [];
foreach (App::db()->all('SELECT COLUMN_NAME, COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()') as $column) {
    $labels[$column['COLUMN_NAME']] = trim((string) preg_split('/\s*[(,]/u', $column['COLUMN_COMMENT'])[0]);
}
$label = fn ($key) => Lang::has("fields.$key") ? t("fields.$key") : (($labels[$key] ?? '') ?: $key);
$render = function (array $row) use ($label): void {
    echo '<dl class="print-fields">';
    foreach ($row as $key => $value) {
        if (str_starts_with((string) $key, '_') || in_array($key, ['created_by','created_at','updated_by','updated_at','reversible'], true) || !is_scalar($value) || $value === '') continue;
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) $value = implode('/', array_reverse(explode('-', $value)));
        echo '<dt>' . e($label($key)) . '</dt><dd>' . e((string) $value) . '</dd>';
    }
    echo '</dl>';
};
?>
<?= View::partial('doc/print-style') ?>
<?= View::partial('doc/print-toolbar') ?>
<style>
.print-fields { display:grid;grid-template-columns: minmax(7rem,1fr) 2fr minmax(7rem,1fr) 2fr;gap: .3rem .75rem;font-size:11px;margin:0; }
.print-fields dt { color: #000;font-weight:400; }
.print-fields dd { margin:0;overflow-wrap:anywhere;white-space:pre-wrap; }
.print-section { padding:12px 0;border-bottom:1px solid #dde3eb;break-inside:avoid; }
.print-section h3 { font-size:12px;font-weight:400;color: #000;margin:0 0 8px; }
</style>
<div class="doc">
<header class="doc-head"><div class="doc-company"><?php if ($logo = \Phai\Modules\System\Logo::printSource()): ?><img src="<?= e($logo) ?>" alt="" class="doc-logo"><?php endif ?><div><div class="doc-company-name"><?= e($company['co_name'] ?? '') ?></div><div><?= e($company['co_address'] ?? '') ?></div><div><?= e($company['co_tax_id'] ?? '') ?></div></div></div><div class="doc-title-box"><div class="doc-title"><?= e($name) ?></div><div><?= e($nbr) ?></div></div></header>
<section class="print-section"><?php $render($payload['head'] ?? []); ?></section>
<?php foreach ($payload as $section => $rows): if ($section === 'head' || !is_array($rows)) continue;
foreach ($rows as $i => $row): if (!is_array($row)) continue; ?>
<section class="print-section"><h3><?= e(t('js.view_details')) ?> <?= e((string) ($i + 1)) ?></h3><?php $render($row); ?></section>
<?php endforeach; endforeach; ?>
</div>
