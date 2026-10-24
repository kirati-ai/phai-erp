<?php
/**
 * MRP: run it, the planned orders of the last run (tick → requisition / work orders), the action messages, and the MRP
 * record of each item by week with the detail behind every number (Mrp::review, js/pages/mrp.js).
 * @var array $config  canRun, canPr, canWo, today
 */
use Phai\Modules\Production\Mrp;

$text = [];
foreach (['buy', 'make', 'late', 'ok', 'stock', 'past', 'start', 'level', 'lot', 'safety', 'onhand', 'need', 'coming', 'order', 'end',
    'g_gross', 'g_sched', 'g_poh', 'g_net', 'g_receipt', 'g_release', 'pegging', 'date', 'doc', 'never', 'plan_WO', 'plan_PO'] as $k) {
    $text[$k] = t('production.mrp_' . $k);
}
$text += [
    'ran' => t('production.mrp_ran', ['at' => ':at', 'by' => ':by']), 'lead' => t('production.mrp_lead', ['days' => ':days']),
    'order_by' => t('production.mrp_order_by', ['date' => ':date']),
    'pr_made' => t('production.mrp_pr_made', ['nbr' => ':nbr']), 'wo_made' => t('production.mrp_wo_made', ['list' => ':list']),
    'kind' => array_combine(Mrp::KINDS, array_map(fn ($k) => t('production.mrp_k_' . $k), Mrp::KINDS)),
    'msg' => array_combine(Mrp::MESSAGES, array_map(fn ($k) => t('production.mrp_msg_' . $k), Mrp::MESSAGES)),
    'say' => array_combine(Mrp::MESSAGES, array_map(fn ($k) => t('production.mrp_say_' . $k, ['date' => ':date', 'to' => ':to']), Mrp::MESSAGES)),
];
?>
<div class="phai-toolbar">
    <span class="small text-secondary" id="mrp-ran"></span>
    <div class="ms-auto d-flex gap-2">
        <?php if ($config['canPr']): ?><button type="button" class="btn btn-sm" id="mrp-pr"><i class="ti ti-shopping-cart me-1"></i><?= e(t('production.mrp_to_pr')) ?></button><?php endif ?>
        <?php if ($config['canWo']): ?><button type="button" class="btn btn-sm" id="mrp-wo"><i class="ti ti-tool me-1"></i><?= e(t('production.mrp_to_wo')) ?></button><?php endif ?>
        <?php if ($config['canRun']): ?><button type="button" class="btn btn-sm btn-primary" id="mrp-run"><i class="ti ti-calculator me-1"></i><?= e(t('production.mrp_run')) ?></button><?php endif ?>
    </div>
</div>

<div class="card">
    <div class="card-header py-2"><h3 class="card-title"><?= e(t('production.mrp_planned')) ?></h3></div>
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table" id="mrp">
            <thead>
            <tr>
                <th class="w-1"><input type="checkbox" class="form-check-input m-0" id="mrp-all"></th>
                <th><?= e(t('production.mrp_type')) ?></th>
                <th><?= e(t('production.item')) ?></th>
                <th><?= e(t('production.desc')) ?></th>
                <th class="text-end"><?= e(t('production.qty')) ?></th>
                <th><?= e(t('production.um')) ?></th>
                <th><?= e(t('production.mrp_release')) ?></th>
                <th><?= e(t('production.due_date')) ?></th>
                <th><?= e(t('production.mrp_for')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header py-2"><h3 class="card-title"><?= e(t('production.mrp_messages')) ?></h3></div>
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table" id="mrp-messages">
            <thead>
            <tr>
                <th><?= e(t('production.mrp_messages')) ?></th>
                <th><?= e(t('production.item')) ?></th>
                <th><?= e(t('production.desc')) ?></th>
                <th><?= e(t('production.mrp_doc')) ?></th>
                <th class="text-end"><?= e(t('production.qty')) ?></th>
                <th><?= e(t('production.mrp_note')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header py-2"><h3 class="card-title"><?= e(t('production.mrp_detail')) ?></h3></div>
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table" id="mrp-items">
            <thead>
            <tr>
                <th><?= e(t('production.item')) ?></th>
                <th><?= e(t('production.desc')) ?></th>
                <th><?= e(t('production.mrp_type')) ?></th>
                <th class="text-end"><?= e(t('production.mrp_level')) ?></th>
                <th class="text-end"><?= e(t('production.mrp_onhand')) ?></th>
                <th class="text-end"><?= e(t('production.mrp_safety')) ?></th>
                <th class="text-end"><?= e(t('production.mrp_need')) ?></th>
                <th class="text-end"><?= e(t('production.mrp_coming')) ?></th>
                <th class="text-end"><?= e(t('production.mrp_order')) ?></th>
                <th class="text-end"><?= e(t('production.mrp_end')) ?></th>
                <th><?= e(t('production.mrp_messages')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script id="mrp-config" type="application/json"><?= json_encode($config + ['text' => $text], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
