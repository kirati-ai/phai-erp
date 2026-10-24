<?php /** @var array $config  canReceive */ ?>
<div class="phai-toolbar">
    <div class="input-icon phai-search">
        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
        <input type="search" id="q" class="form-control form-control-sm" placeholder="<?= e(t('common.search')) ?>" data-search>
    </div>
    <?php if ($config['canReceive']): ?>
        <a href="<?= e(url('/cash-receipts/new')) ?>" class="btn btn-sm btn-primary ms-auto"><i class="ti ti-cash me-1"></i><?= e(t('accounting.cr_new')) ?></a>
    <?php endif ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter table-hover card-table" id="aging">
            <thead>
            <tr>
                <th><?= e(t('sales.customer')) ?></th>
                <th class="text-end"><?= e(t('accounting.not_due')) ?></th>
                <th class="text-end">1–30</th>
                <th class="text-end">31–60</th>
                <th class="text-end">61–90</th>
                <th class="text-end">&gt; 90</th>
                <th class="text-end"><?= e(t('sales.total')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
            <tfoot class="fw-semibold"></tfoot>
        </table>
    </div>
</div>
<script id="ar-config" type="application/json"><?= json_encode($config + ['text' => [
    'empty' => t('accounting.no_open_items'), 'days' => t('accounting.days_late'),
]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
