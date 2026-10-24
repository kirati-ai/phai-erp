<?php /** @var array $config  from, to, period, canClose */ ?>
<div class="phai-toolbar">
    <input type="date" class="form-control form-control-sm w-auto" id="from" value="<?= e($config['from']) ?>">
    <span class="text-secondary">–</span>
    <input type="date" class="form-control form-control-sm w-auto" id="to" value="<?= e($config['to']) ?>">
    <span class="text-secondary small ms-2"><?= e(t('accounting.open_period')) ?> <strong id="period"><?= e($config['period']) ?></strong></span>
    <?php if ($config['canClose']): ?>
        <button type="button" class="btn btn-sm ms-auto" id="close-period"><i class="ti ti-lock me-1"></i><?= e(t('accounting.close_period')) ?></button>
    <?php endif ?>
</div>

<div class="card" id="tb-card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter table-hover card-table" id="tb">
            <thead>
            <tr>
                <th><?= e(t('accounting.account')) ?></th>
                <th class="text-end"><?= e(t('accounting.opening')) ?></th>
                <th class="text-end"><?= e(t('accounting.debit')) ?></th>
                <th class="text-end"><?= e(t('accounting.credit')) ?></th>
                <th class="text-end"><?= e(t('accounting.closing')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
            <tfoot class="fw-semibold"></tfoot>
        </table>
    </div>
</div>

<div class="card d-none" id="ledger-card">
    <div class="card-header py-2">
        <button type="button" class="btn btn-sm btn-ghost-secondary me-2 px-1" id="ledger-back"><i class="ti ti-arrow-left"></i></button>
        <h3 class="card-title" id="ledger-title"></h3>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-vcenter table-hover card-table" id="ledger">
            <thead>
            <tr>
                <th><?= e(t('accounting.date')) ?></th>
                <th><?= e(t('accounting.ref')) ?></th>
                <th><?= e(t('accounting.desc')) ?></th>
                <th><?= e(t('accounting.cc')) ?></th>
                <th class="text-end"><?= e(t('accounting.debit')) ?></th>
                <th class="text-end"><?= e(t('accounting.credit')) ?></th>
                <th class="text-end"><?= e(t('accounting.balance')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<script id="tb-config" type="application/json"><?= json_encode($config + ['text' => [
    'opening'  => t('accounting.opening'),
    'total'    => t('sales.total'),
    'empty'    => t('accounting.no_entries'),
    'confirm'  => t('accounting.close_confirm', ['period' => ':period']),
    'closed'   => t('accounting.closed_to', ['period' => ':period']),
    'unbalanced' => t('accounting.tb_unbalanced'),
]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
