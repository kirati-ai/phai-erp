<?php
/**
 * List of documents (PR, PO, receipts, ...). Driven by public/js/doclist.js.
 * @var array $config  api, page, canAdd, status {code: {text, color}}, columns [{key, label, type, sort, strong, suffix}]
 * @var ?string $toolbar  optional partial with extra controls for the toolbar (e.g. accounting/etax-toolbar)
 */
$status = (array) $config['status'];
// the print icon: documents with their own print page, or printed through /documents/<kind>/<nbr>/print
$page = ltrim((string) $config['page'], '/');
$ownPrint = ['ap-vouchers', 'ar-invoices', 'billings', 'cash-receipts', 'invoices', 'purchase-orders', 'quotations', 'shipments', 'vouchers', 'wht'];
$program = $page;
// row icons: in which statuses a document can still be edited / undone (cancelled, a journal reversed);
// the server checks again, these only grey the icon out
$editWhen = ['purchase-requisitions' => ['P'], 'purchase-orders' => ['P', 'A'], 'quotations' => ['O'], 'sales-orders' => ['P', 'O'], 'work-orders' => ['F']];
$undoWhen = ['purchase-requisitions' => ['P', 'A'], 'purchase-orders' => ['P', 'A'], 'sales-orders' => ['P', 'O'], 'work-orders' => ['F'],
    'invoices' => ['U'], 'ar-invoices' => ['U'], 'billings' => ['O'], 'cash-receipts' => ['P'],
    'payables' => ['O'], 'ap-vouchers' => ['O'], 'vouchers' => ['P'], 'journals' => ['JE']];
$config['editWhen'] ??= \Phai\Core\Auth\Auth::can($program, 'edit') ? ($editWhen[$page] ?? null) : null;
$config['undoWhen'] ??= \Phai\Core\Auth\Auth::can($program, $page === 'journals' ? 'add' : 'delete') ? ($undoWhen[$page] ?? null) : null;
$config['undoApi'] ??= $page === 'journals' ? '/reverse' : '/cancel';
// documents show one month at a time (latest first): the document date column, unless a screen turns it off ('month' => false)
$config['month'] ??= array_values(array_filter($config['columns'], fn ($c) => ($c['type'] ?? '') === 'date'))[0]['key'] ?? false;
$config['undoConfirm'] ??= t('purchase.cancel_confirm');
$config['print'] ??= !\Phai\Core\Auth\Auth::can($program, 'print') ? null
    : (in_array($page, $ownPrint, true) ? "/$page/{key}/print"
    : (in_array($page, \Phai\Modules\System\DocumentPrintController::DOCUMENTS, true) ? "/documents/$page/{key}/print" : null));
?>
<div class="phai-toolbar">
    <div class="input-icon phai-search">
        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
        <input type="search" id="q" class="form-control form-control-sm" placeholder="<?= e(t('common.search')) ?>" data-search>
    </div>
    <?php if ($status): ?>
        <select class="form-select form-select-sm w-auto" id="status">
            <option value=""><?= e(t('purchase.all_status')) ?></option>
            <?php foreach ($status as $code => $s): ?>
                <option value="<?= e($code) ?>"><?= e($s['text']) ?></option>
            <?php endforeach ?>
        </select>
    <?php endif ?>
    <?php if ($config['month']): ?>
        <?= \Phai\Core\View\View::partial('partials/month-picker', ['id' => 'month-picker', 'all' => true]) ?>
    <?php endif ?>
    <?php if (!empty($toolbar)) echo \Phai\Core\View\View::partial($toolbar); // extra controls of one screen ?>
    <?php if ($config['canAdd']): ?>
        <a href="<?= e(url($config['page'] . '/new')) ?>" class="btn btn-sm btn-primary ms-auto">
            <i class="ti ti-plus me-1"></i><?= e(t('common.add')) ?>
        </a>
    <?php endif ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter table-hover card-table" id="docs">
            <thead>
            <tr>
                <?php foreach ($config['columns'] as $c): ?>
                    <th<?= !empty($c['sort']) ? ' data-sort="' . e($c['key']) . '"' : '' ?><?= ($c['type'] ?? '') === 'money' ? ' class="text-end"' : '' ?>><?= e($c['label']) ?></th>
                <?php endforeach ?>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script id="doclist-config" type="application/json"><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
