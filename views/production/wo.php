<?php
/**
 * Work order: header and, once saved, sections top to bottom: materials (issue / return slips), the slips, receipts of finished goods, labour and cost.
 * A saved order opens as a view (like a purchase order): Back, status, actions, Print, Edit (while firm).
 * Driven by public/js/pages/wo.js.
 * @var array $config
 */
$siteOptions = '';
foreach ($config['sites'] as $s) {
    $siteOptions .= '<option value="' . e($s['si_site']) . '">' . e($s['si_site'] . ' · ' . $s['si_desc']) . '</option>';
}
$options = fn (array $list) => implode('', array_map(fn ($o) => '<option value="' . e($o['value']) . '">' . e($o['label']) . '</option>', $list));
$cfg = $config + ['text' => [
    'saved'   => t('purchase.saved_nbr', ['nbr' => ':nbr']),
    'done'    => t('purchase.done'),
    'release' => t('production.release'), 'release_confirm' => t('production.release_confirm'),
    'close'   => t('production.close'), 'close_confirm' => t('production.close_confirm'), 'close_confirm_var' => t('production.close_confirm_var'),
    'slip_saved' => t('production.slip_saved'), 'slip_cancel' => t('production.slip_cancel'), 'slip_cancel_confirm' => t('production.slip_cancel_confirm'),
    'slip_cancelled' => t('production.slip_cancelled'), 'slip_I' => t('production.slip_I'), 'slip_R' => t('production.slip_R'),
    'no_slips' => t('production.no_slips'), 'labor_auto' => t('production.labor_auto'), 'issue_mode' => t('production.back_to_issue'),
    'return_mode' => t('production.return_mat'), 'total' => t('production.cost_total_row'),
    'cost_rows' => ['material' => t('production.cost_material'), 'labor' => t('production.cost_labor'), 'overhead' => t('production.cost_overhead')],
    'cancel'  => t('purchase.cancel_doc'), 'cancel_confirm' => t('production.cancel_confirm'),
]];
?>
<div class="phai-toolbar phai-sticky">
    <a class="btn btn-sm" href="<?= e(url('/work-orders')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
    <span class="badge d-none" id="wo-status"></span>
    <div class="ms-auto d-flex flex-wrap gap-2" id="wo-actions"></div>
    <?php if (!empty($config['printUrl'])): ?>
        <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url($config['printUrl'])) ?>"><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
    <?php endif ?>
    <button type="button" class="btn btn-sm btn-primary" id="wo-edit" hidden><i class="ti ti-pencil me-1"></i><?= e(t('common.edit')) ?></button>
    <button type="button" class="btn btn-sm" id="wo-undo" hidden><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
    <button type="submit" form="wo-head" class="btn btn-sm btn-primary" id="wo-save" hidden><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
</div>

<form class="card mb-3" id="wo-head" novalidate autocomplete="off">
    <div class="card-body pb-3">
        <div class="row g-2">
            <div class="col-sm-4 col-lg-1">
                <label class="form-label" for="wo_doc_nbr">
                    <?= e(t('common.doc_nbr')) ?>
                    <?php if (empty($config['nbr'])): ?>
                        <span class="text-secondary fw-normal small">(<?= e(t('common.editable_hint')) ?>)</span>
                    <?php endif ?>
                </label>
                <input type="text" class="form-control form-control-sm text-uppercase phai-doc-nbr-input" id="wo_doc_nbr" name="doc_nbr"
                    value="<?= e($config['nbr'] ?? \Phai\Core\Numbering::peek('WO')) ?>"
                    autocomplete="off"
                    <?= empty($config['nbr']) ? 'title="' . e(t('common.editable_hint')) . '" placeholder="' . e(t('common.editable_hint')) . '"' : 'readonly data-locked' ?>>
            </div>
            <div class="col-sm-8 col-lg-3">
                <label class="form-label required" for="wo_part"><?= e(t('production.item')) ?></label>
                <input type="text" class="form-control form-control-sm text-uppercase" id="wo_part" name="wo_part" data-pick="parts" autocomplete="off">
            </div>
            <div class="col-sm-4 col-lg-1">
                <label class="form-label required" for="wo_qty_ord"><?= e(t('production.qty_ord')) ?></label>
                <input type="text" class="form-control form-control-sm text-end" id="wo_qty_ord" name="wo_qty_ord" inputmode="decimal">
            </div>
            <div class="col-sm-4 col-lg-1">
                <label class="form-label required" for="wo_site"><?= e(t('production.site')) ?></label>
                <select class="form-select form-select-sm" id="wo_site" name="wo_site"><?= $siteOptions ?></select>
            </div>
            <div class="col-sm-4 col-lg-1">
                <label class="form-label required" for="wo_rel_date"><?= e(t('production.rel_date')) ?></label>
                <input type="date" class="form-control form-control-sm" id="wo_rel_date" name="wo_rel_date">
            </div>
            <div class="col-sm-4 col-lg-1">
                <label class="form-label required" for="wo_due_date"><?= e(t('production.due_date')) ?></label>
                <input type="date" class="form-control form-control-sm" id="wo_due_date" name="wo_due_date">
            </div>
            <div class="col-sm-12 col-lg-6">
                <label class="form-label" for="wo_rmks"><?= e(t('production.remarks')) ?></label>
                <input type="text" class="form-control form-control-sm" id="wo_rmks" name="wo_rmks">
            </div>
        </div>
    </div>
</form>

<!-- a saved order: one page, top to bottom in the order the work happens (no tabs) -->
<div id="wo-body" hidden>
    <!-- materials: issue against the bill of materials, or give back what was not used — each save is one slip -->
    <form class="card mb-3" id="mat-form" novalidate autocomplete="off">
        <div class="card-header py-2">
            <h3 class="card-title"><?= e(t('production.sec_materials')) ?></h3>
            <span class="badge bg-green-lt ms-2" id="mat-done" hidden><?= e(t('production.all_issued')) ?></span>
            <div class="ms-auto d-flex gap-2" data-act>
                <button type="button" class="btn btn-sm" id="mat-more" hidden><i class="ti ti-plus me-1"></i><?= e(t('production.issue_more')) ?></button>
                <button type="button" class="btn btn-sm" id="mat-mode"><i class="ti ti-arrow-back-up me-1"></i><span><?= e(t('production.return_mat')) ?></span></button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0">
                <thead>
                <tr>
                    <th><?= e(t('production.component')) ?></th>
                    <th><?= e(t('production.desc')) ?></th>
                    <th class="text-end"><?= e(t('production.required')) ?></th>
                    <th class="text-end"><?= e(t('production.issued')) ?></th>
                    <th class="text-end"><?= e(t('production.remaining')) ?></th>
                    <th class="text-end" style="width: 7rem" data-act data-in><span data-issue><?= e(t('production.issue_now')) ?></span><span data-return hidden><?= e(t('production.return_now')) ?></span></th>
                    <th style="width: 8rem" data-act data-in><?= e(t('production.loc')) ?></th>
                    <th style="width: 8rem" data-act data-in><?= e(t('production.lot')) ?></th>
                    <th class="text-end" data-act data-in><span data-issue><?= e(t('stock.onhand_here')) ?></span><span data-return hidden><?= e(t('production.taken')) ?></span></th>
                    <th class="w-1" data-act data-in></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap align-items-center gap-2" data-act data-in>
            <input type="date" class="form-control form-control-sm w-auto" name="date" aria-label="<?= e(t('production.date')) ?>">
            <input class="form-control form-control-sm" name="reason" maxlength="255" style="max-width: 24rem"
                placeholder="<?= e(t('production.reason') . ' (' . t('production.issue_reason_hint') . ')') ?>" aria-label="<?= e(t('production.reason')) ?>">
            <button type="button" class="btn btn-sm" id="mat-fill" data-issue><i class="ti ti-list-check me-1"></i><?= e(t('production.issue_all')) ?></button>
            <button type="submit" class="btn btn-sm btn-primary ms-auto"><i class="ti ti-file-export me-1"></i><span data-issue><?= e(t('production.save_issue')) ?></span><span data-return hidden><?= e(t('production.save_return')) ?></span></button>
        </div>
    </form>

    <!-- what the order has used: every issue / return slip with its lines -->
    <div class="card mb-3">
        <div class="card-header py-2"><h3 class="card-title"><?= e(t('production.slips')) ?></h3></div>
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="slip-list">
                <thead>
                <tr>
                    <th><?= e(t('common.doc_nbr')) ?> / <?= e(t('production.component')) ?></th>
                    <th><?= e(t('production.lot')) ?></th>
                    <th><?= e(t('production.loc')) ?></th>
                    <th class="text-end"><?= e(t('production.qty')) ?></th>
                    <th class="text-end"><?= e(t('accounting.amount')) ?></th>
                    <th><?= e(t('production.reason')) ?></th>
                    <th class="w-1"></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- receive finished goods (each receipt is a QC record; the routing hours are booked with it) -->
    <div class="card mb-3">
        <div class="card-header py-2"><h3 class="card-title"><?= e(t('production.sec_receive')) ?></h3></div>
        <form class="card-body border-bottom" id="rcv-form" novalidate autocomplete="off" data-act>
            <div class="row g-2 align-items-end">
                <div class="col-sm-4 col-lg-1"><label class="form-label"><?= e(t('production.date')) ?></label><input type="date" class="form-control form-control-sm" name="date"></div>
                <div class="col-sm-4 col-lg-1"><label class="form-label"><?= e(t('production.good_qty')) ?></label><input class="form-control form-control-sm text-end" name="qty" inputmode="decimal"></div>
                <div class="col-sm-4 col-lg-1"><label class="form-label"><?= e(t('production.scrap_qty')) ?></label><input class="form-control form-control-sm text-end" name="scrap" inputmode="decimal"></div>
                <div class="col-sm-4 col-lg-1"><label class="form-label"><?= e(t('production.loc')) ?></label><select class="form-select form-select-sm" name="loc"></select></div>
                <div class="col-sm-4 col-lg-1"><label class="form-label"><?= e(t('stock.expire')) ?></label><input type="date" class="form-control form-control-sm" name="expire"></div>
                <div class="col-sm-8 col-lg-2"><label class="form-label"><?= e(t('production.inspector')) ?></label><input class="form-control form-control-sm" name="inspector" data-pick="people" data-free autocomplete="off"></div>
                <div class="col-auto ms-auto d-flex gap-2"><button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-package-import me-1"></i><?= e(t('production.receive')) ?></button></div>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm card-table mb-0" id="rcv-list">
                <thead>
                <tr>
                    <th><?= e(t('production.date')) ?></th><th><?= e(t('production.qc_nbr')) ?></th><th><?= e(t('production.lot')) ?></th>
                    <th class="text-end"><?= e(t('production.good_qty')) ?></th><th class="text-end"><?= e(t('production.scrap_qty')) ?></th>
                    <th><?= e(t('production.qc_result')) ?></th><th><?= e(t('production.inspector')) ?></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- labour: nobody types hours — each receipt books the routing's standard hours (good + scrap); read only -->
    <div class="card mb-3">
        <div class="card-header py-2"><h3 class="card-title"><?= e(t('production.sec_labor')) ?></h3></div>
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="lab-table">
                <thead>
                <tr>
                    <th><?= e(t('production.op')) ?></th>
                    <th><?= e(t('production.work_center')) ?></th>
                    <th class="text-end"><?= e(t('production.labor_per_unit')) ?></th>
                    <th class="text-end"><?= e(t('production.labor_setup')) ?></th>
                    <th class="text-end"><?= e(t('production.labor_plan')) ?></th>
                    <th class="text-end"><?= e(t('production.labor_booked')) ?></th>
                    <th class="text-end"><?= e(t('production.labor_cost')) ?></th>
                    <th class="text-end"><?= e(t('production.cost_overhead')) ?></th>
                </tr>
                </thead>
                <tbody></tbody>
                <tfoot></tfoot>
            </table>
        </div>
    </div>


    <!-- cost: standard for the good output against what was actually spent; the difference is the variance at closing -->
    <div class="card mb-3">
        <div class="card-header py-2"><h3 class="card-title"><?= e(t('production.sec_cost')) ?></h3></div>
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="cost">
                <thead>
                <tr>
                    <th></th>
                    <th class="text-end"><?= e(t('production.cost_std_unit')) ?></th>
                    <th class="text-end"><?= e(t('production.cost_std')) ?> <span class="text-secondary" data-c="done"></span></th>
                    <th class="text-end"><?= e(t('production.cost_actual')) ?></th>
                    <th class="text-end"><?= e(t('production.cost_var')) ?></th>
                </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                <tr><td class="text-secondary"><?= e(t('production.cost_received')) ?></td><td></td><td></td><td class="num" data-c="received"></td><td></td></tr>
                <tr class="fw-semibold"><td><?= e(t('production.cost_wip')) ?></td><td></td><td></td><td class="num" data-c="wip"></td><td></td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>


<script id="wo-config" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
