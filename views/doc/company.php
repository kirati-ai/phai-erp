<?php
/**
 * The company block at the top left of every printed document and report.
 * @var array $co SalesPrint::company(): name, address, tax, phone, logo
 */
?>
<div class="doc-company">
    <?php if (!empty($co['logo'])): ?><img src="<?= e($co['logo']) ?>" alt="" class="doc-logo"><?php endif ?>
    <div>
        <div class="doc-company-name"><?= e($co['name']) ?></div>
        <?php foreach (['address', 'tax', 'phone'] as $k): ?>
            <?php if (($co[$k] ?? '') !== ''): ?><div><?= e($co[$k]) ?></div><?php endif ?>
        <?php endforeach ?>
    </div>
</div>
