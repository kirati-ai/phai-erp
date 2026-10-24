<?php
/** Receipts with their landed costs; a row opens its view (/landed-costs/{rc}). */
$cfg = ['text' => ['allocated' => t('purchase.lc_allocated')]];
?>
<div class="phai-toolbar">
    <div class="input-icon phai-search">
        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
        <input type="search" id="q" class="form-control form-control-sm" placeholder="<?= e(t('common.search')) ?>" data-search>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter table-hover card-table" id="receipts">
            <thead>
            <tr>
                <th data-sort="rc_nbr"><?= e(t('purchase.nbr')) ?></th>
                <th data-sort="rc_date"><?= e(t('purchase.date')) ?></th>
                <th><?= e(t('purchase.po')) ?></th>
                <th><?= e(t('purchase.vendor_name')) ?></th>
                <th><?= e(t('purchase.currency')) ?></th>
                <th data-sort="rc_net_amt" class="text-end"><?= e(t('purchase.lc_value')) ?></th>
                <th class="text-end"><?= e(t('purchase.lc_total')) ?></th>
                <th class="w-1"></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script id="lc-config" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
