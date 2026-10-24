<?php
/**
 * @var int    $status
 * @var string $message
 */
?>
<div class="page page-center">
    <div class="container-tight py-4 text-center">
        <div class="display-6 text-secondary mb-2"><?= (int) $status ?></div>
        <p class="mb-3"><?= e($message) ?></p>
        <a href="<?= e(url('/')) ?>" class="btn btn-sm"><?= e(t('common.home')) ?></a>
    </div>
</div>
