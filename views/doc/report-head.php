<?php
/**
 * Standard head of a printed report (not a government form): the company block on the left,
 * the report title and its meta (bank, period, as of, …) on the right — the same look as doc/print.
 * @var string $title
 * @var list<array{0: string, 1: string}> $meta
 */
?>
<header class="doc-head">
    <?= \Phai\Core\View\View::partial('doc/company', ['co' => \Phai\Modules\Sales\SalesPrint::company()]) ?>
    <div class="doc-title-box">
        <div class="doc-title"><?= e($title) ?></div>
        <table class="doc-meta">
            <?php foreach ($meta ?? [] as [$label, $value]): ?>
                <?php if ((string) $value !== ''): ?><tr><th><?= e($label) ?></th><td><?= e($value) ?></td></tr><?php endif ?>
            <?php endforeach ?>
        </table>
    </div>
</header>
