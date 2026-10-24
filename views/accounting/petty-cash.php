<?php
/**
 * เงินสดย่อย (PettyCashController, public/js/pages/petty-cash.js): one box (mine, or another holder's to read) for a
 * month, its entries with a running balance, and every box below. Entries, the month-end count and a view of one entry
 * open in windows over the page.
 * @var array $config
 */
$T = fn (string $k, array $p = []) => e(t("pettycash.$k", $p));
?>
<div class="phai-toolbar">
    <select class="form-select form-select-sm w-auto" id="pc-holder" aria-label="<?= $T('holder') ?>"></select>
    <div class="btn-group" role="group" aria-label="<?= $T('month') ?>">
        <button type="button" class="btn btn-sm" id="pc-prev" aria-label="‹"><i class="ti ti-chevron-left"></i></button>
        <button type="button" class="btn btn-sm" id="pc-month" disabled><i class="ti ti-calendar me-1"></i><span></span></button>
        <button type="button" class="btn btn-sm" id="pc-next" aria-label="›"><i class="ti ti-chevron-right"></i></button>
    </div>
    <button type="button" class="btn btn-sm ms-auto" id="pc-export"><i class="ti ti-file-spreadsheet me-1"></i><?= $T('export') ?></button>
    <?php if ($config['canPrint']): ?>
        <a class="btn btn-sm" id="pc-register" target="_blank" rel="noopener"><i class="ti ti-printer me-1"></i><?= $T('register') ?></a>
    <?php endif ?>
</div>

<!-- the box: cash in hand, the month it works in, what the holder can do -->
<div class="card mb-3 phai-pc-box">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div>
                <div class="text-secondary small" id="pc-name"></div>
                <div class="phai-kpi-value"><span id="pc-balance"></span> <span class="phai-kpi-curr">THB</span></div>
            </div>
            <div class="small">
                <div><span class="text-secondary"><?= $T('open') ?>:</span> <b id="pc-open"></b></div>
                <div><span class="text-secondary"><?= $T('last') ?>:</span> <span id="pc-last"></span></div>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-1" id="pc-actions" hidden>
                <button type="button" class="btn btn-sm" data-type="WD"><i class="ti ti-building-bank me-1"></i><?= $T('type_WD') ?></button>
                <button type="button" class="btn btn-sm" data-type="TF"><i class="ti ti-arrows-exchange me-1"></i><?= $T('type_TF') ?></button>
                <button type="button" class="btn btn-sm" data-type="IN"><i class="ti ti-coin me-1"></i><?= $T('type_IN') ?></button>
                <button type="button" class="btn btn-sm btn-primary" data-type="EX"><i class="ti ti-receipt me-1"></i><?= $T('type_EX') ?></button>
                <button type="button" class="btn btn-sm" data-type="RT"><i class="ti ti-arrow-back-up me-1"></i><?= $T('type_RT') ?></button>
                <button type="button" class="btn btn-sm" id="pc-count-btn" hidden><i class="ti ti-coins me-1"></i><span></span></button>
                <button type="button" class="btn btn-sm" id="pc-uncount-btn" hidden><i class="ti ti-lock-open me-1"></i><span></span></button>
            </div>
            <div class="ms-auto small text-secondary" id="pc-readonly" hidden><i class="ti ti-eye me-1"></i><?= $T('not_mine') ?></div>
        </div>
    </div>
</div>

<div class="alert alert-warning py-2 d-flex align-items-center gap-2" id="pc-warn" hidden><i class="ti ti-alert-triangle"></i><span></span></div>

<!-- every holder's petty cash for the month: brought forward, the entries, what is left -->
<div class="card mb-3">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter table-hover card-table phai-pc-table" id="pc-rows">
            <colgroup>
                <col style="width: 3.5%"><col style="width: 9%"><col style="width: 6.5%"><col style="width: 7.5%"><col>
                <col style="width: 8%"><col class="m"><col class="m"><col class="m"><col class="m"><col class="m"><col class="m">
                <col style="width: 2.5%"><col style="width: 8%">
            </colgroup>
            <thead>
            <tr>
                <th class="text-end"><?= $T('seq') ?></th>
                <th><?= $T('nbr') ?></th>
                <th><?= $T('date') ?></th>
                <th><?= $T('type') ?></th>
                <th><?= $T('what') ?></th>
                <th><?= $T('receipt_col') ?></th>
                <th class="text-end"><?= $T('bill') ?></th>
                <th class="text-end"><?= $T('vat') ?></th>
                <th class="text-end"><?= $T('wht') ?></th>
                <th class="text-end"><?= $T('in') ?></th>
                <th class="text-end"><?= $T('out') ?></th>
                <th class="text-end"><?= $T('balance') ?></th>
                <th class="w-1"><i class="ti ti-paperclip"></i></th>
                <th><?= $T('holder') ?></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<!-- one entry: withdraw, return, receive from another holder, or pay an expense -->
<div class="modal" id="pc-entry" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <form class="modal-content" novalidate autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <span class="badge bg-primary-lt ms-2" id="pc-entry-nbr"><?= e($config['docNbr']) ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="type">
                <div class="row g-2">
                    <div class="col-sm-4">
                        <label class="form-label" for="pc-date"><?= $T('date') ?></label>
                        <input type="date" class="form-control form-control-sm" id="pc-date" name="date" max="<?= e($config['today']) ?>">
                    </div>
                    <div class="col-sm-8" data-for="WD RT">
                        <label class="form-label" for="pc-bank"><?= $T('bank') ?></label>
                        <input class="form-control form-control-sm" id="pc-bank" name="bank" data-pick="pc-banks">
                    </div>
                    <div class="col-sm-8" data-for="TF">
                        <label class="form-label" for="pc-from"><?= $T('from') ?></label>
                        <input class="form-control form-control-sm" id="pc-from" name="from" data-pick="pc-holders">
                    </div>
                    <div class="col-sm-4" data-for="WD RT TF IN">
                        <label class="form-label" for="pc-amount"><?= $T('amount') ?></label>
                        <input type="text" inputmode="decimal" class="form-control form-control-sm text-end" id="pc-amount" name="amount">
                    </div>
                    <div class="col-sm-6" data-for="EX IN">
                        <label class="form-label" for="pc-payee" data-label-ex="<?= $T('payee') ?>" data-label-in="<?= $T('payer') ?>"><?= $T('payee') ?></label>
                        <input type="text" class="form-control form-control-sm" id="pc-payee" name="payee" maxlength="255">
                    </div>
                    <div class="col-sm-4" data-for="EX">
                        <label class="form-label" for="pc-vendor"><?= $T('vendor') ?></label>
                        <input class="form-control form-control-sm" id="pc-vendor" name="vendor" data-pick="vendors">
                    </div>
                    <div class="col-sm-4" data-for="IN">
                        <label class="form-label" for="pc-acct"><?= $T('income_acct') ?></label>
                        <input class="form-control form-control-sm" id="pc-acct" name="acct" data-pick="pc-incomes">
                    </div>
                    <div class="col-sm-2" data-for="EX IN">
                        <label class="form-label" for="pc-rcpt"><?= $T('rcpt_nbr') ?></label>
                        <input type="text" class="form-control form-control-sm" id="pc-rcpt" name="rcpt_nbr" maxlength="50">
                    </div>
                </div>

                <div data-for="EX" class="mt-2">
                    <table class="table table-sm mb-1 phai-pc-lines">
                        <colgroup><col><col style="width: 15%"><col style="width: 13%"><col style="width: 14%"><col style="width: 22%"><col style="width: 2.5rem"></colgroup>
                        <thead>
                        <tr>
                            <th><?= $T('desc') ?></th>
                            <th class="text-end"><?= $T('amt') ?></th>
                            <th><?= $T('vat') ?></th>
                            <th><?= $T('cc') ?></th>
                            <th><?= $T('wht_code') ?></th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <button type="button" class="btn btn-sm" id="pc-add-line"><i class="ti ti-plus me-1"></i><?= $T('add_line') ?></button>

                    <!-- a line with VAT needs the vendor's full tax invoice (the vendor's tax id and branch come from the register) -->
                    <div class="row g-2 mt-1">
                        <div class="col-sm-4" data-inv>
                            <label class="form-label" for="pc-inv-nbr"><?= $T('inv_nbr') ?></label>
                            <input type="text" class="form-control form-control-sm" id="pc-inv-nbr" name="inv_nbr" maxlength="50">
                        </div>
                        <div class="col-sm-4" data-inv>
                            <label class="form-label" for="pc-inv-date"><?= $T('inv_date') ?></label>
                            <input type="date" class="form-control form-control-sm" id="pc-inv-date" name="inv_date">
                        </div>
                        <div class="col-sm-4" id="pc-wht-form-box" hidden>
                            <label class="form-label" for="pc-wht-form"><?= $T('wht_form') ?></label>
                            <select class="form-select form-select-sm" id="pc-wht-form" name="wht_form">
                                <option value="PND53"><?= e(t('accounting.form_PND53')) ?></option>
                                <option value="PND3"><?= e(t('accounting.form_PND3')) ?></option>
                            </select>
                        </div>
                    </div>
                    <table class="table table-sm phai-pc-sum mt-2 mb-0 ms-auto">
                        <tr><th><?= $T('net') ?></th><td class="num" id="pc-s-net"></td></tr>
                        <tr><th><?= $T('vat') ?></th><td class="num" id="pc-s-vat"></td></tr>
                        <tr><th><?= $T('wht') ?></th><td class="num" id="pc-s-wht"></td></tr>
                        <tr class="fw-bold"><th><?= $T('paid') ?></th><td class="num" id="pc-s-paid"></td></tr>
                    </table>
                </div>

                <div class="row g-2 mt-1">
                    <div class="col-sm-7">
                        <label class="form-label" for="pc-remarks"><?= $T('remarks') ?></label>
                        <input type="text" class="form-control form-control-sm" id="pc-remarks" name="remarks" maxlength="255">
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label" for="pc-files"><?= $T('attach') ?></label>
                        <input type="file" class="form-control form-control-sm" id="pc-files" accept="image/jpeg,image/png,image/webp,application/pdf" multiple>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
                <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- month-end count: notes and coins -->
<div class="modal" id="pc-count" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" novalidate autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="month">
                <table class="table table-sm mb-2 phai-pc-notes">
                    <thead><tr><th class="text-end">฿</th><th class="text-end"><?= $T('pieces') ?></th><th class="text-end"><?= $T('amount') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($config['notes'] as $d): ?>
                        <tr>
                            <td class="num"><?= e(rtrim(rtrim(number_format((float) $d, 2), '0'), '.')) ?></td>
                            <td><input type="text" inputmode="numeric" class="form-control form-control-sm text-end" name="notes.<?= e($d) ?>" data-note="<?= e($d) ?>"></td>
                            <td class="num" data-sum="<?= e($d) ?>"></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
                <table class="table table-sm phai-pc-sum mb-2 ms-auto">
                    <tr><th><?= $T('book') ?></th><td class="num" id="pc-c-book"></td></tr>
                    <tr><th><?= $T('counted') ?></th><td class="num" id="pc-c-counted"></td></tr>
                    <tr class="fw-bold"><th><?= $T('diff') ?></th><td class="num" id="pc-c-diff"></td></tr>
                </table>
                <label class="form-label" for="pc-c-remarks"><?= $T('remarks') ?></label>
                <input type="text" class="form-control form-control-sm" id="pc-c-remarks" name="remarks" maxlength="255">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
                <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-lock me-1"></i><?= e(t('common.save')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- one entry to read: its lines, files, print, cancel -->
<div class="modal" id="pc-view" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="pc-v-body"></div>
                <div class="mt-2">
                    <div class="fw-medium mb-1"><i class="ti ti-paperclip me-1"></i><?= $T('files') ?></div>
                    <div id="pc-v-files" data-files="PC" data-nbr="" data-bare="1"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-danger me-auto" id="pc-v-cancel" hidden><i class="ti ti-arrow-back-up me-1"></i><?= e(t('common.cancel')) ?> <?= $T('what') ?></button>
                <button type="button" class="btn btn-sm" id="pc-v-edit" hidden><i class="ti ti-pencil me-1"></i><?= $T('edit') ?></button>
                <a class="btn btn-sm" id="pc-v-wht" target="_blank" rel="noopener" hidden><i class="ti ti-receipt-tax me-1"></i><?= e(t('accounting.wht_cert')) ?></a>
                <a class="btn btn-sm" id="pc-v-print" target="_blank" rel="noopener" hidden><i class="ti ti-printer me-1"></i><?= e(t('sales.print')) ?></a>
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i><?= e(t('js.close')) ?></button>
            </div>
        </div>
    </div>
</div>

<script id="pc-config" type="application/json"><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
