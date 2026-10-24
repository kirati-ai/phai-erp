<?php
/**
 * A work queue above a page: documents of the step before that wait for this page (e.g. on sales orders, the
 * quotations still waiting for an answer). Hidden while empty. Filled by public/js/queue.js.
 * A page gets queues with View::render(..., ['queues' => [ [...], ... ]]); each:
 *   title    "รอออกใบสั่งขาย" (the count is added)
 *   api      GET returning a list of rows (array), e.g. '/api/quotations/waiting'; params: extra query
 *   key      the row field that identifies it (for ticking several)
 *   columns  [{key, label, type: text|date|money|num, link: '/quotations/{qt_nbr}'}]
 *   urgent   row field: when truthy the row shows in red (overdue)
 *   action   one row: {label, icon, href: '/shipments/new?so={so_nbr}'}
 *            or {label, icon, post: '/api/sales-orders/from-qt', body: {qt: '{qt_nbr}'}, open: '/sales-orders/{nbr}', confirm?}
 *   multi    several ticked rows at once: {label, icon, same: 'vendor', href: '/payables/new?vendor={vendor}&receipts={keys}'}
 *            ({keys} = the ticked rows' key, comma-separated; {field} = from the first ticked row; all must share `same`)
 *            or {label, icon, post: '/api/…', open: '/x/{nbr}'}: the ticked rows' keys are posted as {keys: [...]}
 * Only give action / multi when the user may do it.
 * Clicking a row opens its document (the first column with a link) in a window over the page, to read only (the page
 * itself with ?embed=1: no menu, no top bar, no buttons); Ctrl/⌘-click on the number opens it as a page.
 * @var array $q
 */
?>
<div class="card mb-3 phai-queue" data-queue="<?= e(json_encode($q, JSON_UNESCAPED_UNICODE)) ?>" hidden>
    <div class="card-header py-1 px-3">
        <h3 class="card-title small fw-normal"><i class="ti ti-inbox me-1"></i><?= e($q['title']) ?> <span class="badge bg-orange-lt ms-1" data-count></span></h3>
        <?php if (!empty($q['multi'])): ?>
            <button type="button" class="btn btn-sm btn-primary ms-auto" data-multi disabled>
                <i class="<?= e($q['multi']['icon'] ?? 'ti ti-arrow-right') ?> me-1"></i><?= e($q['multi']['label']) ?>
            </button>
        <?php endif ?>
    </div>
    <div class="table-responsive phai-queue-body">
        <table class="table table-sm table-vcenter card-table mb-0">
            <thead>
            <tr>
                <th class="phai-seq"><?= e(t('common.seq')) ?></th>
                <?php if (!empty($q['multi'])): ?><th class="w-1"></th><?php endif ?>
                <?php foreach ($q['columns'] as $c): ?>
                    <th<?= in_array($c['type'] ?? 'text', ['money', 'num'], true) ? ' class="text-end"' : '' ?>><?= e($c['label']) ?></th>
                <?php endforeach ?>
                <?php if (!empty($q['action'])): ?><th class="w-1"></th><?php endif ?>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
