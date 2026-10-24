<?php
/**
 * Goods receipt: new (pick a PO) or view a saved one.
 * @var string|null $nbr
 * @var string      $po     PO number from ?po=
 * @var array       $sites
 */
$cfg = [
    'nbr'   => $nbr,
    'po'    => $po,
    'sites' => $sites,
    'text'  => ['saved' => t('purchase.saved_nbr', ['nbr' => ':nbr']), 'need_lot' => t('stock.lot_required'), 'over' => t('purchase.over_hint')],
];
?>
<form id="receipt" novalidate autocomplete="off">
    <div class="phai-toolbar phai-sticky">
        <a class="btn btn-sm" href="<?= e(url('/receipts')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
        <span class="small text-secondary" id="rc-vendor"></span>
        <?php if (!$nbr): ?>
            <button type="button" class="btn btn-sm ms-auto" id="rc-all"><i class="ti ti-list-check me-1"></i><?= e(t('purchase.receive_all')) ?></button>
            <button type="submit" class="btn btn-sm btn-primary" id="rc-save"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
        <?php endif ?>
    </div>

    <div class="card mb-3">
        <div class="card-body pb-3">
            <div class="row g-2">
                <div class="col-sm-6 col-lg-1">
                    <label class="form-label" for="rc_doc_nbr">
                        <?= e(t('common.doc_nbr')) ?>
                        <?php if (empty($nbr)): ?>
                            <span class="text-secondary fw-normal small">(<?= e(t('common.editable_hint')) ?>)</span>
                        <?php endif ?>
                    </label>
                    <input type="text" class="form-control form-control-sm text-uppercase phai-doc-nbr-input" id="rc_doc_nbr" name="doc_nbr"
                        value="<?= e($nbr ?? \Phai\Core\Numbering::peek('RC')) ?>"
                        autocomplete="off"
                        <?= empty($nbr) ? 'title="' . e(t('common.editable_hint')) . '" placeholder="' . e(t('common.editable_hint')) . '"' : 'readonly data-locked' ?>>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label required" for="po"><?= e(t('purchase.po')) ?></label>
                    <input type="text" class="form-control form-control-sm text-uppercase" id="po" name="po" data-pick="open-pos" placeholder="<?= e(t('purchase.pick_po')) ?>">
                </div>
                <div class="col-sm-4 col-lg-1">
                    <label class="form-label required" for="rc_date"><?= e(t('purchase.date')) ?></label>
                    <input type="date" class="form-control form-control-sm" id="rc_date" name="rc_date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="col-sm-4 col-lg-1">
                    <label class="form-label required" for="rc_site"><?= e(t('purchase.site')) ?></label>
                    <select class="form-select form-select-sm" id="rc_site" name="rc_site">
                        <?php foreach ($sites as $s): ?>
                            <option value="<?= e($s['si_site']) ?>"><?= e($s['si_site'] . ' · ' . $s['si_desc']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-sm-8 col-lg-3">
                    <label class="form-label" for="rc_inv_nbr"><?= e(t('purchase.inv_nbr')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="rc_inv_nbr" name="rc_inv_nbr" maxlength="50">
                </div>
                <div class="col-sm-4 col-lg-1">
                    <label class="form-label" for="rc_inv_date"><?= e(t('purchase.inv_date')) ?></label>
                    <input type="date" class="form-control form-control-sm" id="rc_inv_date" name="rc_inv_date">
                </div>
                <div class="col-sm-12 col-lg-7">
                    <label class="form-label" for="rc_remarks"><?= e(t('purchase.remarks')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="rc_remarks" name="rc_remarks">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="rc-lines">
                <thead>
                <tr>
                    <th class="w-1">#</th>
                    <th><?= e(t('purchase.part')) ?></th>
                    <th><?= e(t('purchase.desc')) ?></th>
                    <th class="text-end"><?= e(t('purchase.qty')) ?></th>
                    <th class="text-end" data-new><?= e(t('purchase.received')) ?></th>
                    <th class="text-end" data-new><?= e(t('purchase.remaining')) ?></th>
                    <th class="text-end" style="width: 7rem"><?= e(t('purchase.receive_qty')) ?></th>
                    <th><?= e(t('purchase.um')) ?></th>
                    <th style="width: 8rem"><?= e(t('purchase.loc')) ?></th>
                    <th style="width: 8rem"><?= e(t('purchase.lot')) ?></th>
                    <th style="width: 9.5rem" data-new><?= e(t('purchase.expire')) ?></th>
                    <th class="text-end" style="width: 6rem" data-new><?= e(t('purchase.pack_qty')) ?></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</form>

<script id="receipt-config" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
