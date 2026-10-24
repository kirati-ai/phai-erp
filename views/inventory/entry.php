<?php
/**
 * Stock document entry: receive / issue / convert / price adjust (?kind=).
 * @var array  $sites    [{si_site, si_desc, si_default_loc}]
 * @var array  $reasons  [{rs_code, rs_desc, rs_type}]
 */
$siteOptions = function () use ($sites): void {
    foreach ($sites as $s) {
        echo '<option value="' . e($s['si_site']) . '">' . e($s['si_site'] . ' · ' . $s['si_desc']) . '</option>';
    }
};
$cfg = [
    'sites'   => $sites,
    'reasons' => $reasons,
    'text'    => [
        'qty'        => t('stock.qty'),
        'posted'     => t('stock.posted', ['nbr' => ':nbr']),
        'unit_cost'  => t('stock.unit_cost'),
        'new_cost'   => t('stock.new_cost'),
    ],
];
?>
<form class="card" id="entry" novalidate autocomplete="off">
    <div class="card-body pb-2">
        <div class="btn-group mb-3" role="group">
            <?php foreach (['receipt', 'issue', 'convert', 'cost'] as $i => $k): ?>
                <input type="radio" class="btn-check" name="kind" id="kind-<?= $k ?>" value="<?= $k ?>"<?= $i === 0 ? ' checked' : '' ?>>
                <label class="btn btn-sm" for="kind-<?= $k ?>"><?= e(t('stock.' . $k)) ?></label>
            <?php endforeach ?>
        </div>
        <div class="row g-2">
            <div class="col-sm-3 col-lg-1">
                <label class="form-label" for="doc_nbr">
                    <?= e(t('common.doc_nbr')) ?>
                    <span class="text-secondary fw-normal small">(<?= e(t('common.editable_hint')) ?>)</span>
                </label>
                <input type="text" class="form-control form-control-sm text-uppercase phai-doc-nbr-input" id="doc_nbr" name="doc_nbr"
                    value="<?= e($docNbr ?? \Phai\Core\Numbering::peek('IC')) ?>"
                    autocomplete="off"
                    title="<?= e(t('common.editable_hint')) ?>" placeholder="<?= e(t('common.editable_hint')) ?>">
            </div>
            <div class="col-sm-3 col-lg-1">
                <label class="form-label required" for="date"><?= e(t('stock.date')) ?></label>
                <input type="date" class="form-control form-control-sm" id="date" name="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="col-sm-3" data-show="receipt issue convert">
                <label class="form-label required" for="site"><?= e(t('stock.site')) ?></label>
                <select class="form-select form-select-sm" id="site" name="site"><?php $siteOptions() ?></select>
            </div>
            <div class="col-sm-3">
                <label class="form-label" for="reason" id="reason-label"><?= e(t('stock.reason')) ?></label>
                <select class="form-select form-select-sm" id="reason" name="reason"></select>
            </div>
            <div class="col">
                <label class="form-label" for="remark"><?= e(t('stock.remark')) ?></label>
                <input type="text" class="form-control form-control-sm" id="remark" name="remark" maxlength="255">
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table mb-0" id="lines">
            <thead>
            <tr>
                <th class="w-1">#</th>
                <th><?= e(t('stock.part')) ?></th>
                <th data-show="receipt issue convert"><?= e(t('stock.loc')) ?></th>
                <th data-show="receipt issue convert"><?= e(t('stock.lot')) ?></th>
                <th class="text-end" data-show="issue cost convert"><?= e(t('stock.onhand_here')) ?></th>
                <th class="text-end" data-show="receipt issue convert" id="qty-head"><?= e(t('stock.qty')) ?></th>
                <th class="text-end" data-show="cost"><?= e(t('stock.cur_cost')) ?></th>
                <th class="text-end" data-show="receipt cost" id="cost-head"><?= e(t('stock.unit_cost')) ?></th>
                <th data-show="receipt"><?= e(t('stock.expire')) ?></th>
                <th data-show="convert"><?= e(t('stock.fg_part')) ?></th>
                <th class="text-end" data-show="convert"><?= e(t('stock.fg_qty')) ?></th>
                <th data-show="convert"><?= e(t('stock.to_loc')) ?></th>
                <th class="w-1"></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div class="card-footer d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-ghost-primary" id="add-line"><i class="ti ti-plus me-1"></i><?= e(t('stock.add_line')) ?></button>
        <button type="submit" class="btn btn-sm btn-primary ms-auto" id="save"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
    </div>
</form>

<script id="entry-config" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
