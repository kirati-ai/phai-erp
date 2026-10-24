<?php
/**
 * Shipment: new (pick a sales order) or view a saved one.
 * @var string|null $nbr
 * @var string      $so          order number from ?so=
 * @var bool        $canInvoice
 * @var bool        $canPrint
 */
$cfg = [
    'nbr'  => $nbr,
    'canInvoice' => $canInvoice, // after the shipment: on to its invoice, to check before saving
    'so'   => $so,
    'canPrint' => $canPrint,
    'text' => [
        'saved' => t('sales.shipped_nbr', ['nbr' => ':nbr']), 'invoiced' => t('sales.invoiced_nbr', ['nbr' => ':nbr']),
        'confirm' => t('sales.ship_confirm', ['n' => ':n']),
    ],
];
?>
<form id="shipment" novalidate autocomplete="off">
    <div class="phai-toolbar phai-sticky">
        <a class="btn btn-sm" href="<?= e(url('/shipments')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
        <span class="small text-secondary" id="sh-customer"></span>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <?php if ($nbr): ?>
                <a class="btn btn-sm" id="sh-invoice" hidden></a>
                <?php if ($canPrint): ?>
                    <a class="btn btn-sm" href="<?= e(url('/shipments/' . rawurlencode($nbr) . '/print')) ?>" target="_blank"><i class="ti ti-printer me-1"></i><?= e(t('sales.print')) ?></a>
                <?php endif ?>
            <?php else: ?>

                <button type="submit" class="btn btn-sm btn-primary" id="sh-save"><i class="ti ti-truck-delivery me-1"></i><?= e(t('sales.ship_confirm_btn')) ?></button>
            <?php endif ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body pb-3">
            <div class="row g-2">
                <div class="col-sm-6 col-lg-1">
                    <label class="form-label" for="sh_doc_nbr">
                        <?= e(t('common.doc_nbr')) ?>
                        <?php if (empty($nbr)): ?>
                            <span class="text-secondary fw-normal small">(<?= e(t('common.editable_hint')) ?>)</span>
                        <?php endif ?>
                    </label>
                    <input type="text" class="form-control form-control-sm text-uppercase phai-doc-nbr-input" id="sh_doc_nbr" name="doc_nbr"
                        value="<?= e($nbr ?? \Phai\Core\Numbering::peek('SH')) ?>"
                        autocomplete="off"
                        <?= empty($nbr) ? 'title="' . e(t('common.editable_hint')) . '" placeholder="' . e(t('common.editable_hint')) . '"' : 'readonly data-locked' ?>>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label required" for="so"><?= e(t('sales.so')) ?></label>
                    <input type="text" class="form-control form-control-sm text-uppercase" id="so" name="so" data-pick="open-sos" placeholder="<?= e(t('sales.pick_so')) ?>">
                </div>
                <div class="col-sm-4 col-lg-1">
                    <label class="form-label required" for="sh_date"><?= e(t('sales.date')) ?></label>
                    <input type="date" class="form-control form-control-sm" id="sh_date" name="sh_date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="col-sm-4 col-lg-1">
                    <label class="form-label" for="sh_ship_via"><?= e(t('sales.ship_via')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="sh_ship_via" name="sh_ship_via" maxlength="50">
                </div>
                <div class="col-sm-4 col-lg-2">
                    <label class="form-label" for="sh_tracking"><?= e(t('sales.tracking')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="sh_tracking" name="sh_tracking" maxlength="100">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="sh-lines">
                <thead>
                <tr>
                    <th class="w-1">#</th>
                    <th><?= e(t('sales.part')) ?></th>
                    <th><?= e(t('sales.desc')) ?></th>
                    <th class="text-end" data-new><?= e(t('sales.ordered')) ?></th>
                    <th class="text-end" data-new><?= e(t('sales.remaining')) ?></th>
                    <th class="text-end" style="width: 7rem"><?= e(t('sales.ship_qty')) ?></th>
                    <th><?= e(t('sales.um')) ?></th>
                    <th style="width: 8rem"><?= e(t('sales.loc')) ?></th>
                    <th style="width: 9rem"><?= e(t('sales.lot')) ?></th>
                    <th class="text-end" data-new><?= e(t('stock.onhand_here')) ?></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</form>

<script id="shipment-config" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
