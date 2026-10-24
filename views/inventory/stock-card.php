<?php
/**
 * Stock card / รายงานสินค้าและวัตถุดิบ: one item, or every item that has a balance or a movement, grouped per item.
 * @var array $sites  si_site, si_desc
 * @var array $config from, to, canPrint
 */
?>
<div class="phai-toolbar">
    <input class="form-control form-control-sm text-uppercase" style="width: 20rem; max-width: 100%" id="part" data-pick="card-parts" placeholder="<?= e(t('stock.card_all')) ?>" autocomplete="off">
    <select class="form-select form-select-sm w-auto" id="type">
        <option value=""><?= e(t('stock.all_types')) ?></option>
        <?php foreach (\Phai\Modules\Inventory\StockCardController::TYPES as $type): ?><option value="<?= e($type) ?>"><?= e(t('fields.pl_inventory_type.' . $type)) ?></option><?php endforeach ?>
    </select>
    <select class="form-select form-select-sm w-auto" id="site">
        <option value=""><?= e(t('stock.all_sites')) ?></option>
        <?php foreach ($sites as $s): ?><option value="<?= e($s['si_site']) ?>"><?= e($s['si_site']) ?></option><?php endforeach ?>
    </select>
    <?= \Phai\Core\View\View::partial('partials/month-picker', ['id' => 'card-month', 'all' => false]) ?>
    <input type="date" class="form-control form-control-sm w-auto" id="from" value="<?= e($config['from']) ?>">
    <span class="text-secondary">–</span>
    <input type="date" class="form-control form-control-sm w-auto" id="to" value="<?= e($config['to']) ?>">
    <?php if ($config['canPrint']): ?>
        <a class="btn btn-sm ms-auto" id="card-print" target="_blank" rel="noopener" href="#" hidden><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
    <?php endif ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table mb-0" id="card">
            <thead>
            <tr>
                <th rowspan="2"><?= e(t('stock.date')) ?></th>
                <th rowspan="2"><?= e(t('stock.doc_nbr')) ?></th>
                <th rowspan="2"><?= e(t('stock.card_type')) ?></th>
                <th rowspan="2" class="text-end"><?= e(t('stock.unit_cost')) ?></th>
                <th colspan="2" class="text-center"><?= e(t('stock.card_in')) ?></th>
                <th colspan="2" class="text-center"><?= e(t('stock.card_out')) ?></th>
                <th colspan="2" class="text-center"><?= e(t('stock.card_balance')) ?></th>
            </tr>
            <tr>
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <th class="text-end"><?= e(t('stock.qty')) ?></th><th class="text-end"><?= e(t('stock.card_value')) ?></th>
                <?php endfor ?>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<script id="card-config" type="application/json"><?= json_encode($config + ['text' => [
    'opening' => t('stock.card_opening'), 'total' => t('stock.card_total'), 'all' => t('stock.card_all'),
    'grand' => t('stock.card_grand'), 'none' => t('stock.card_none'), 'range' => t('stock.card_range'),
]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
