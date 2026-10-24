<?php
/**
 * สมุดบัญชีเงินฝาก (BankController, public/js/pages/bank-book.js): one bank account for a month —
 * brought forward, deposits and withdrawals with a running balance and their reconciliation, month-end balance.
 * @var array $config bank, month, banks, canPrint
 */
$T = fn (string $k) => e(t("accounting.$k"));
?>
<div class="phai-toolbar">
    <a class="btn btn-sm" href="<?= e(url('/banks')) ?>"><i class="ti ti-arrow-left me-1"></i><?= $T('bank_book_back') ?></a>
    <select class="form-select form-select-sm w-auto" id="bb-bank" aria-label="<?= $T('bank') ?>">
        <?php foreach ($config['banks'] as $b): ?>
            <option value="<?= e($b['bk_bank']) ?>"<?= $b['bk_bank'] === $config['bank'] ? ' selected' : '' ?>><?= e($b['bk_bank'] . ' · ' . $b['bk_desc']) ?></option>
        <?php endforeach ?>
    </select>
    <div class="btn-group" role="group" aria-label="<?= $T('bank_book_month') ?>">
        <button type="button" class="btn btn-sm" id="bb-prev" aria-label="‹"><i class="ti ti-chevron-left"></i></button>
        <button type="button" class="btn btn-sm" id="bb-month" disabled><i class="ti ti-calendar me-1"></i><span></span></button>
        <button type="button" class="btn btn-sm" id="bb-next" aria-label="›"><i class="ti ti-chevron-right"></i></button>
    </div>
    <button type="button" class="btn btn-sm ms-auto" id="bb-export"><i class="ti ti-file-spreadsheet me-1"></i><?= $T('bank_book_export') ?></button>
    <?php if ($config['canPrint']): ?>
        <a class="btn btn-sm" id="bb-print" target="_blank" rel="noopener"><i class="ti ti-printer me-1"></i><?= $T('bank_book_print') ?></a>
    <?php endif ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter table-hover card-table" id="bb-table">
            <thead>
            <tr>
                <th><?= $T('date') ?></th>
                <th><?= $T('bank_rec_type') ?></th>
                <th><?= $T('ref') ?></th>
                <th><?= $T('bank_rec_desc') ?></th>
                <th class="text-end"><?= $T('bank_rec_deposit') ?></th>
                <th class="text-end"><?= $T('bank_rec_withdraw') ?></th>
                <th class="text-end"><?= $T('bank_book_balance') ?></th>
                <th><?= $T('bank_book_rec') ?></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<script id="bb-config" type="application/json"><?= json_encode($config + ['text' => [
    'opening' => t('accounting.bank_book_opening'), 'closing' => t('accounting.bank_book_closing'), 'total' => t('accounting.bank_book_total'),
    'title' => t('accounting.bank_book'), 'date' => t('accounting.date'), 'type' => t('accounting.bank_rec_type'), 'ref' => t('accounting.ref'),
    'desc' => t('accounting.bank_rec_desc'), 'in' => t('accounting.bank_rec_deposit'), 'out' => t('accounting.bank_rec_withdraw'),
    'balance' => t('accounting.bank_book_balance'), 'rec' => t('accounting.bank_book_rec'),
]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
