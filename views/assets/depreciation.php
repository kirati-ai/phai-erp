<div class="phai-toolbar"><a class="btn btn-sm" href="<?= e(url('/fixed-assets')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('assets.back')) ?></a>
<label for="depr-month"><?= e(t('assets.month')) ?></label><input id="depr-month" class="form-control form-control-sm w-auto" value="<?= e(date('Y-m', strtotime('first day of last month'))) ?>">
<?php if ($canPrint): ?><a class="btn btn-sm" id="depr-print" target="_blank" href="<?= e(url('/fixed-assets/depreciation/print?pdf=1')) ?>"><i class="ti ti-printer me-1"></i><?= e(t('assets.dep_print')) ?></a><?php endif ?>
<button class="btn btn-sm" id="preview"><i class="ti ti-calculator me-1"></i><?= e(t('assets.dep_preview')) ?></button>
<?php if ($canPost): ?><button class="btn btn-sm btn-primary ms-auto" id="post" disabled><i class="ti ti-send me-1"></i><?= e(t('assets.dep_post')) ?></button><?php endif ?>
</div>
<p class="text-secondary small"><?= e(t('assets.dep_hint')) ?></p>
<div class="card"><div class="table-responsive"><table class="table table-sm table-vcenter card-table" id="depr-table"><thead><tr><th><input type="checkbox" id="select-all" aria-label="<?= e(t('assets.dep_select_all')) ?>"></th><th><?= e(t('assets.dep_code')) ?></th><th><?= e(t('assets.dep_name')) ?></th><th><?= e(t('assets.dep_method')) ?></th><th class="text-end"><?= e(t('assets.depr')) ?></th><th><?= e(t('assets.dep_check')) ?></th></tr></thead><tbody></tbody></table></div><div class="card-footer" id="depr-total"><?= e(t('assets.dep_not_run')) ?></div></div>
<script type="application/json" id="depr-config"><?= json_encode(['text' => ['total' => t('assets.dep_total'), 'select' => t('assets.dep_select'), 'confirm' => t('assets.dep_confirm'), 'none' => t('assets.dep_none')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
