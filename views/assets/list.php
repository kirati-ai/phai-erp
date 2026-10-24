<?php use Phai\Core\Auth\Auth; use Phai\Core\View\View; ?>
<div class="d-flex gap-2 mb-3">
<?php if (Auth::can('fixed-asset-categories', 'view')): ?><a class="btn btn-sm" href="<?= e(url('/fixed-asset-categories')) ?>"><i class="ti ti-category me-1"></i><?= e(t('assets.categories')) ?></a><?php endif ?>
<a class="btn btn-sm" href="<?= e(url('/fixed-assets/depreciation')) ?>"><i class="ti ti-chart-line me-1"></i><?= e(t('assets.monthly_depr')) ?></a>
<?php if (Auth::can('fixed-assets', 'print')): ?><a class="btn btn-sm" target="_blank" href="<?= e(url('/fixed-assets/report/print?pdf=1')) ?>"><i class="ti ti-printer me-1"></i><?= e(t('assets.register_pdf')) ?></a><?php endif ?>
</div>
<?= View::partial('doc/list', ['config' => $config]) ?>
