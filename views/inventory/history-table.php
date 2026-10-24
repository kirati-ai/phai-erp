<?php
/**
 * The stock history table, the same everywhere it shows (stock history page, stock line view):
 * one layout key (/api/stock/history:history), columns from js/stock-history-grid.js in this order.
 */
?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table" id="history">
            <thead>
            <tr>
                <th data-sort="tr_trnbr"><?= e(t('stock.transaction')) ?></th>
                <th data-sort="tr_date"><?= e(t('stock.date')) ?></th>
                <th><?= e(t('stock.time')) ?></th>
                <th data-sort="tr_type"><?= e(t('stock.type')) ?></th>
                <th data-sort="tr_nbr"><?= e(t('stock.nbr')) ?></th>
                <th data-sort="tr_part"><?= e(t('stock.part')) ?></th>
                <th><?= e(t('stock.desc')) ?></th>
                <th data-sort="tr_loc"><?= e(t('stock.loc')) ?></th>
                <th><?= e(t('stock.lot')) ?></th>
                <th class="text-end"><?= e(t('stock.begin_qty')) ?></th>
                <th data-sort="tr_chg_qty" class="text-end"><?= e(t('stock.qty')) ?></th>
                <th class="text-end"><?= e(t('stock.after_qty')) ?></th>
                <th><?= e(t('stock.um')) ?></th>
                <th class="text-end"><?= e(t('stock.movement_value')) ?></th>
                <th><?= e(t('stock.reason')) ?></th>
                <th><?= e(t('stock.user')) ?></th>
                <th><?= e(t('stock.line')) ?></th>
                <th class="text-end"><?= e(t('stock.price')) ?></th>
                <th class="text-end"><?= e(t('stock.unit_cost')) ?></th>
                <th><?= e(t('stock.remarks')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
