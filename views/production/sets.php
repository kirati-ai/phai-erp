<?php
/**
 * สูตรการผลิต: one item's materials (BOM) and steps (routing) in two tabs, with what one unit costs.
 * Driven by public/js/pages/set-editor.js.
 * @var array $config  bom / routing: {view, add, edit, delete, columns, more?}, rates {work center: cost an hour}, tab
 */
$tabs = array_filter(['bom' => t('production.tab_materials'), 'routing' => t('production.tab_steps')], fn ($k) => $config[$k]['view'], ARRAY_FILTER_USE_KEY);
$canDelete = $config['bom']['delete'] || $config['routing']['delete'];
$canSave = $config['bom']['add'] || $config['bom']['edit'] || $config['routing']['add'] || $config['routing']['edit'];
?>
<div class="row g-3">
    <div class="col-lg-3">
        <div class="phai-toolbar phai-pane-bar">
            <div class="input-icon flex-fill">
                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                <input type="search" id="q" class="form-control form-control-sm" placeholder="<?= e(t('common.search')) ?>" data-search>
            </div>
        </div>
        <div class="card">
            <div class="list-group list-group-flush" id="parents"></div>
        </div>
    </div>

    <div class="col-lg-9">
        <form id="set" novalidate autocomplete="off">
            <div class="phai-toolbar phai-sticky phai-pane-bar">
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-secondary text-nowrap" for="parent"><?= e(t('production.bom_parent')) ?></label>
                    <input type="text" class="form-control form-control-sm text-uppercase" id="parent" data-pick="parts" autocomplete="off" style="width: 14rem">
                </div>
                <div class="ms-auto d-flex gap-2">
                    <?php if ($canDelete): ?>
                        <button type="button" class="btn btn-sm btn-ghost-danger" id="set-delete" hidden><i class="ti ti-trash me-1"></i><?= e(t('common.delete')) ?></button>
                    <?php endif ?>
                    <?php if ($canSave): ?>
                        <button type="submit" class="btn btn-sm btn-primary" id="set-save" hidden><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
                    <?php endif ?>
                </div>
            </div>
            <div class="card" id="set-empty"><div class="card-body py-3 text-secondary"><?= e(t('production.pick_parent')) ?></div></div>
            <div id="set-body" hidden>
                <!-- compact tabs outside the cards (ux/ui.md), the cost of one unit on their right -->
                <div class="d-flex flex-wrap align-items-end gap-2 mb-2">
                    <ul class="nav nav-tabs phai-tabs flex-grow-1" role="tablist">
                        <?php foreach ($tabs as $k => $label): ?>
                            <li class="nav-item"><a class="nav-link" href="#" data-tab="<?= $k ?>"><?= e($label) ?><span class="text-secondary ms-1" data-count="<?= $k ?>"></span></a></li>
                        <?php endforeach ?>
                    </ul>
                    <div class="small text-secondary text-nowrap pb-1" id="set-cost"></div>
                </div>
                <?php foreach (array_keys($tabs) as $k): ?>
                    <div class="card" data-pane="<?= $k ?>" hidden>
                        <div class="table-responsive">
                            <table class="table table-sm table-vcenter card-table mb-0">
                                <thead>
                                <tr>
                                    <th class="w-1">#</th>
                                    <?php foreach ($config[$k]['columns'] as $c): ?>
                                        <th<?= in_array($c['type'], ['number', 'money'], true) ? ' class="text-end"' : '' ?><?= isset($c['width']) ? ' style="width: ' . e($c['width']) . '"' : ' style="min-width: 10rem"' ?>><?= e($c['label']) ?></th>
                                    <?php endforeach ?>
                                    <th class="w-1"></th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <?php if ($config[$k]['add'] || $config[$k]['edit']): ?>
                            <div class="px-2 py-1">
                                <button type="button" class="btn btn-sm btn-ghost-primary" data-add="<?= $k ?>"><i class="ti ti-plus me-1"></i><?= e(t('purchase.add_line')) ?></button>
                            </div>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        </form>
    </div>
</div>

<script id="set-config" type="application/json"><?= json_encode($config + ['text' => [
    'materials' => t('production.tab_materials'), 'steps' => t('production.tab_steps'), 'total' => t('production.cost_total'),
    'more' => t('production.more'), 'confirm' => t('production.delete_set'),
]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
