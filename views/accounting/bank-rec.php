<?php
/**
 * Bank reconciliation screen (public/js/pages/bank-rec.js).
 * @var list<array> $banks bk_bank, bk_desc, bk_acct
 */
?>
<div class="phai-toolbar" id="rec">
    <select class="form-select form-select-sm w-auto" id="bank" aria-label="<?= e(t('accounting.bank')) ?>">
        <?php foreach ($banks as $b): ?>
            <option value="<?= e($b['bk_bank']) ?>"><?= e($b['bk_bank'] . ' · ' . $b['bk_desc']) ?></option>
        <?php endforeach ?>
    </select>
    <label class="text-secondary small mb-0" for="date"><?= e(t('accounting.bank_rec_date')) ?></label>
    <input type="date" class="form-control form-control-sm w-auto" id="date" value="<?= e(date('Y-m-d')) ?>">
    <button type="button" class="btn btn-sm btn-primary ms-auto" id="save"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
</div>

<div class="card mb-2">
    <div class="card-body py-2 phai-rec-sum">
        <div><span><?= e(t('accounting.bank_rec_opening')) ?> <span class="text-secondary" id="last-date"></span></span><b id="s-opening"></b></div>
        <div><span><?= e(t('accounting.bank_rec_in')) ?></span><b id="s-in"></b></div>
        <div><span><?= e(t('accounting.bank_rec_out')) ?></span><b id="s-out"></b></div>
        <div><span><?= e(t('accounting.bank_rec_cleared')) ?></span><b id="s-cleared"></b></div>
        <div><span><?= e(t('accounting.bank_rec_book')) ?></span><b id="s-book"></b></div>
    </div>
</div>

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter table-hover card-table" id="rec-table">
            <thead>
            <tr>
                <th class="w-1"><input type="checkbox" class="form-check-input m-0" id="all" aria-label="<?= e(t('js.all')) ?>"></th>
                <th><?= e(t('accounting.date')) ?></th>
                <th><?= e(t('accounting.bank_rec_type')) ?></th>
                <th><?= e(t('accounting.ref')) ?></th>
                <th><?= e(t('accounting.bank_rec_desc')) ?></th>
                <th class="text-end"><?= e(t('accounting.bank_rec_deposit')) ?></th>
                <th class="text-end"><?= e(t('accounting.bank_rec_withdraw')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<h3 class="card-title mb-2"><?= e(t('accounting.bank_rec_history')) ?></h3>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table" id="hist-table">
            <thead>
            <tr>
                <th><?= e(t('accounting.bank_rec_date')) ?></th>
                <th class="text-end"><?= e(t('accounting.bank_rec_balance')) ?></th>
                <th class="text-end"><?= e(t('accounting.bank_rec_book')) ?></th>
                <th class="text-end"><?= e(t('accounting.bank_rec_lines')) ?></th>
                <th><?= e(t('accounting.bank_rec_by')) ?></th>
                <th class="phai-act"></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

