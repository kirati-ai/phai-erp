<?php
/**
 * @var array $head      pi_mstr row + si_desc
 * @var bool  $canEdit
 * @var bool  $canPost
 * @var bool  $canDelete
 */
$date = implode('/', array_reverse(explode('-', (string) $head['pi_date'])));
$cfg = [
    'batch'   => $head['pi_batch'],
    'canEdit' => $canEdit,
    'text'    => [
        'post_confirm' => t('stock.post_confirm'),
        'post_done'    => t('stock.post_done', ['n' => ':n']),
        'not_counted'  => t('stock.not_counted'),
        'void'         => t('stock.tag_void'),
        'void_do'      => t('stock.tag_void_do'),
        'restore'      => t('stock.tag_restore'),
        'recount'      => t('stock.tag_recount'),
        'first'        => t('stock.recount_first', ['qty' => ':qty', 'who' => ':who']),
    ],
];
?>
<div class="phai-toolbar phai-sticky">
    <a class="btn btn-sm" href="<?= e(url('/stock-counts')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
    <span class="badge <?= $head['pi_status'] === 'P' ? 'bg-green-lt' : 'bg-yellow-lt' ?>"><?= e(t('stock.status_' . $head['pi_status'])) ?></span>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <?php if ($canPost): ?>
            <button type="button" class="btn btn-sm btn-ghost-primary" id="post"><i class="ti ti-check me-1"></i><?= e(t('stock.post_count')) ?></button>
        <?php endif ?>
        <?php if ($canDelete): ?>
            <button type="button" class="btn btn-sm btn-ghost-danger" id="delete"><i class="ti ti-trash me-1"></i><?= e(t('common.delete')) ?></button>
        <?php endif ?>
        <?php if (\Phai\Core\Auth\Auth::can('stock-counts', 'print')): ?>
            <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url('/stock-counts/' . rawurlencode($head['pi_batch']) . '/tags/print')) ?>"><i class="ti ti-tags me-1"></i><?= e(t('stock.tags_print')) ?></a>
            <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url('/documents/stock-counts/' . rawurlencode($head['pi_batch']) . '/print')) ?>"><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
        <?php endif ?>
        <?php if ($canEdit): ?>
            <button type="button" class="btn btn-sm btn-primary" id="save"><i class="ti ti-device-floppy me-1"></i><?= e(t('stock.save_counts')) ?></button>
        <?php endif ?>
    </div>
</div>

<?= \Phai\Core\View\View::partial('doc/view-fields', ['fields' => [
    [t('common.doc_nbr'), $head['pi_batch'], 'col-6 col-md-2'],
    [t('stock.site'), $head['pi_site'] . ' · ' . $head['si_desc'], 'col-6 col-md-3'],
    [t('stock.date'), $date, 'col-6 col-md-2'],
    [t('stock.reason'), $head['pi_rsn_code'], 'col-6 col-md-2'],
    [t('stock.count_desc'), $head['pi_desc'], 'col-12 col-md-3'],
    [t('stock.scope'), $head['pi_locs'] ? str_replace(',', ', ', $head['pi_locs']) : t('stock.scope_all'), 'col-12'],
]]) ?>

<div class="phai-toolbar">
    <div class="input-icon phai-search">
        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
        <input type="search" id="q" class="form-control form-control-sm" placeholder="<?= e(t('common.search')) ?>" data-search>
    </div>
    <?php if ($canEdit): ?>
        <!-- the name written on the tags being keyed in -->
        <input class="form-control form-control-sm" style="max-width: 16rem" id="counter" data-pick="people" data-free placeholder="<?= e(t('stock.counter')) ?>" autocomplete="off">
    <?php endif ?>
    <label class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="only-diff">
        <span class="form-check-label"><?= e(t('stock.only_diff')) ?></span>
    </label>
</div>

<form class="card" id="count" novalidate autocomplete="off">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table mb-0">
            <thead>
            <tr>
                <th class="w-1"><?= e(t('stock.tag')) ?></th>
                <th><?= e(t('stock.loc')) ?></th>
                <th><?= e(t('stock.part')) ?></th>
                <th><?= e(t('stock.desc')) ?></th>
                <th><?= e(t('stock.lot')) ?></th>
                <th class="text-end"><?= e(t('stock.sys_qty')) ?></th>
                <th class="text-end" style="width: 8rem"><?= e(t('stock.count_qty')) ?></th>
                <th class="text-end"><?= e(t('stock.variance')) ?></th>
                <th><?= e(t('stock.um')) ?></th>
                <th><?= e(t('stock.counter')) ?></th>
                <th class="text-end" style="width: 8rem"><?= e(t('stock.unit_cost')) ?></th>
                <?php if ($canEdit): ?><th class="w-1"></th><?php endif ?>
            </tr>
            </thead>
            <tbody></tbody>
            <?php if ($canEdit): ?>
                <tfoot class="d-print-none">
                <tr id="new-tag">
                    <td><i class="ti ti-plus text-secondary"></i></td>
                    <td><input class="form-control form-control-sm text-uppercase" name="loc" placeholder="<?= e(t('stock.loc')) ?>"></td>
                    <td colspan="2"><input class="form-control form-control-sm text-uppercase" name="part" data-pick="parts" placeholder="<?= e(t('stock.add_tag')) ?>" autocomplete="off"></td>
                    <td><input class="form-control form-control-sm" name="lot" placeholder="<?= e(t('stock.lot')) ?>"></td>
                    <td></td>
                    <td><input class="form-control form-control-sm text-end" name="qty" inputmode="decimal" placeholder="0"></td>
                    <td colspan="3"><button type="button" class="btn btn-sm" id="add-tag"><i class="ti ti-plus me-1"></i><?= e(t('common.add')) ?></button></td>
                    <td><input class="form-control form-control-sm text-end" name="cost" inputmode="decimal" placeholder="<?= e(t('stock.unit_cost')) ?>"></td>
                    <td></td>
                </tr>
                </tfoot>
            <?php endif ?>
        </table>
    </div>
</form>
<div class="mt-3"><?= \Phai\Core\View\View::partial('partials/files', ['doc' => 'PI', 'nbr' => $head['pi_batch']]) ?></div>

<script id="count-config" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
