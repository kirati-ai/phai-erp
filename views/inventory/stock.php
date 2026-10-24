<?php
/** @var array $sites  @var array $filters (PartResource::filters)  @var array $chosen page query */
?>
<div class="phai-toolbar">
    <div class="input-icon phai-search">
        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
        <input type="search" id="q" class="form-control form-control-sm" placeholder="<?= e(t('common.search')) ?>" data-search>
    </div>
    <select class="form-select form-select-sm w-auto" id="site">
        <option value=""><?= e(t('stock.all_sites')) ?></option>
        <?php foreach ($sites as $s): ?>
            <option value="<?= e($s['si_site']) ?>"><?= e($s['si_site'] . ' · ' . $s['si_desc']) ?></option>
        <?php endforeach ?>
    </select>
    <?php foreach ($filters as $filter): ?>
        <select class="form-select form-select-sm w-auto" id="<?= e($filter['name']) ?>">
            <option value=""><?= e($filter['all']) ?></option>
            <?php foreach ($filter['options'] as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= (string) $value === (string) ($chosen[$filter['name']] ?? '') ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach ?>
        </select>
    <?php endforeach ?>
    <label class="form-check form-switch mb-0 ms-1">
        <input class="form-check-input" type="checkbox" id="by-part">
        <span class="form-check-label"><?= e(t('stock.by_part')) ?></span>
    </label>
    <?php if (\Phai\Core\Auth\Auth::can('stock-moves', 'add')): // stock changes by hand open the entry page on that tab
        $type = strtoupper((string) ($chosen['type'] ?? ''));
        $go = fn (string $kind, string $icon) => '<a class="btn btn-sm" href="' . e(url('/stock/moves?kind=' . $kind)) . '"><i class="ti ti-' . $icon . ' me-1"></i>' . e(t('stock.' . $kind)) . '</a>'; ?>
        <div class="ms-auto d-flex gap-2">
            <?= $type !== 'RM' ? $go('cost', 'currency-baht') : '' ?>
        </div>
    <?php endif ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter table-hover card-table" id="stock">
            <thead>
            <tr>
                <th data-sort="ld_site"><?= e(t('stock.site')) ?></th>
                <th data-sort="ld_loc" data-detail><?= e(t('stock.loc')) ?></th>
                <th data-sort="ld_part"><?= e(t('stock.part')) ?></th>
                <th data-sort="pt_desc1"><?= e(t('stock.desc')) ?></th>
                <th data-sort="ld_lot" data-detail><?= e(t('stock.lot')) ?></th>
                <th data-sort="ld_expire" data-detail><?= e(t('stock.expire')) ?></th>
                <th data-sort="ld_qty_oh" class="text-end"><?= e(t('stock.onhand')) ?></th>
                <th><?= e(t('stock.um')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<?php // a line opened from Purchasing / Sales / Production (?type=) shows here, the page stays where it is ?>
<div class="modal" id="line-modal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title me-2" data-title><?= e(t('stock.line_title')) ?></h5>
                <span class="badge" data-status></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (\Phai\Core\Auth\Auth::can('stock-history', 'view')): ?>
                    <ul class="nav nav-tabs phai-tabs mb-2" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#line-detail" role="tab"><?= e(t('stock.detail')) ?></a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#line-moves" role="tab"><?= e(t('stock.history_title')) ?></a></li>
                    </ul>
                <?php endif ?>
                <div class="tab-content">
                    <div class="tab-pane active show" id="line-detail" role="tabpanel">
                        <div class="card phai-view-mode"><div class="card-body pb-3"><div class="row g-2" data-fields></div></div></div>
                    </div>
                    <?php if (\Phai\Core\Auth\Auth::can('stock-history', 'view')): ?>
                        <div class="tab-pane" id="line-moves" role="tabpanel">
                            <?= \Phai\Core\View\View::partial('inventory/history-table') ?>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script id="stock-types" type="application/json"><?= json_encode($types, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
