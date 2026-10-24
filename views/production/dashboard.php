<?php
/**
 * Production Dashboard view.
 * @var string $title
 * @var array $company, $kpis, $needs, $activeWos, $recentQc
 */
$today = date('Y-m-d');
$dmy = fn (?string $v) => $v ? implode('/', array_reverse(explode('-', substr($v, 0, 10)))) : '';
?>
<div class="phai-module-dashboard">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h2 class="mb-0 fs-3 fw-bold"><i class="ti ti-building-factory-2 text-primary me-2"></i><?= e(t('overview.prod_heading')) ?></h2>
            <div class="text-secondary small"><?= e(t('overview.prod_intro')) ?></div>
        </div>
    </div>

    <?= \Phai\Core\View\View::partial('partials/flow', ['flow' => $flow]) ?>

    <!-- KPI Cards -->
    <?php if ($kpis): ?>
        <div class="row g-3 mb-3">
            <?php foreach ($kpis as $k): ?>
                <div class="col-sm-6 col-xl-3">
                    <a class="card phai-kpi h-100" href="<?= e(url($k['link'])) ?>">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="phai-kpi-icon bg-<?= e($k['color']) ?>-lt text-<?= e($k['color']) ?>">
                                    <i class="ti <?= e($k['icon']) ?>"></i>
                                </span>
                                <span class="text-secondary fw-medium"><?= e($k['label']) ?></span>
                            </div>
                            <div class="phai-kpi-value">
                                <?php if (!empty($k['is_pct'])): ?>
                                    <?= number_format((float) $k['value'], 1) ?> <span class="phai-kpi-curr">%</span>
                                <?php elseif (!empty($k['is_qty'])): ?>
                                    <?= number_format((int) $k['value']) ?> <span class="phai-kpi-curr"><?= e(t('overview.prod_unit')) ?></span>
                                <?php else: ?>
                                    <?= number_format((float) $k['value'], 2) ?>
                                <?php endif ?>
                            </div>
                            <?php if (!empty($k['note'])): ?>
                                <div class="small text-secondary"><?= e($k['note']) ?></div>
                            <?php endif ?>
                        </div>
                    </a>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if ($needs): ?>
        <!-- What to make: sales-order lines the stock does not cover (Supply::lines, same as the shipment queue) -->
        <div class="card mb-3">
            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                <h3 class="card-title"><i class="ti ti-list-check text-secondary me-2"></i><?= e(t('overview.prod_needs_title')) ?></h3>
                <a href="<?= e(url('/work-orders')) ?>" class="small"><?= e(t('overview.prod_needs_link')) ?> &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table">
                    <thead>
                        <tr>
                            <th><?= e(t('overview.prod_col_part')) ?></th>
                            <th><?= e(t('overview.prod_col_so')) ?></th>
                            <th><?= e(t('overview.prod_col_want')) ?></th>
                            <th class="text-end"><?= e(t('overview.prod_col_open')) ?></th>
                            <th class="text-end"><?= e(t('overview.prod_col_from_stock')) ?></th>
                            <th><?= e(t('overview.prod_col_wo')) ?></th>
                            <th><?= e(t('overview.prod_col_ready')) ?></th>
                            <th><?= e(t('overview.col_status')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($needs as $n): ?>
                            <tr>
                                <td><?= e($n['sod_part']) ?> <span class="text-secondary"><?= e($n['part_desc'] ?? '') ?></span></td>
                                <td><?= e($n['so_nbr']) ?> <span class="text-secondary"><?= e($n['cm_name'] ?? '') ?></span></td>
                                <td><?= e($dmy($n['want_date'])) ?></td>
                                <td class="num"><?= e(\Phai\Modules\Inventory\Stock::fmt((float) $n['open_qty'])) ?> <span class="text-secondary"><?= e($n['sod_um']) ?></span></td>
                                <td class="num"><?= e(\Phai\Modules\Inventory\Stock::fmt((float) $n['from_stock'])) ?></td>
                                <td>
                                    <?php foreach ($n['docs'] as $i => $doc): ?><?= $i ? ', ' : '' ?><a href="<?= e(url('/work-orders/' . rawurlencode($doc))) ?>"><?= e($doc) ?></a><?php endforeach ?>
                                </td>
                                <td><?= e($dmy($n['ready_date'])) ?></td>
                                <td><span class="badge bg-<?= e($n['need_color']) ?>-lt"><?= e($n['need']) ?></span></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif ?>

    <div class="row g-3">
        <!-- Active Work Orders Table -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <h3 class="card-title"><i class="ti ti-clipboard-list text-secondary me-2"></i><?= e(t('overview.prod_active_title')) ?></h3>
                    <a href="<?= e(url('/work-orders')) ?>" class="small"><?= e(t('overview.prod_active_link')) ?> &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-hover">
                        <thead>
                            <tr>
                                <th><?= e(t('overview.prod_col_wo_nbr')) ?></th>
                                <th><?= e(t('overview.prod_col_make')) ?></th>
                                <th><?= e(t('overview.prod_col_due')) ?></th>
                                <th class="text-center" style="width: 140px;"><?= e(t('overview.prod_col_progress')) ?></th>
                                <th class="text-end"><?= e(t('overview.prod_col_qty')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($activeWos): ?>
                                <?php foreach ($activeWos as $wo): ?>
                                    <?php
                                    $ord = (float) $wo['wo_qty_ord'] ?: 1;
                                    $comp = (float) $wo['wo_qty_comp'];
                                    $pct = min(100, round($comp / $ord * 100));
                                    $isLate = !empty($wo['wo_due_date']) && $wo['wo_due_date'] < $today;
                                    ?>
                                    <tr onclick="location.href='<?= e(url('/work-orders/' . rawurlencode($wo['wo_nbr']))) ?>'" style="cursor: pointer;">
                                        <td class="fw-medium font-monospace text-primary"><?= e($wo['wo_nbr']) ?></td>
                                        <td>
                                            <div class="fw-medium"><?= e($wo['wo_part']) ?></div>
                                            <div class="text-secondary small text-truncate" style="max-width: 220px;"><?= e($wo['pt_desc1'] ?? '') ?></div>
                                        </td>
                                        <td>
                                            <span class="<?= $isLate ? 'text-danger fw-bold' : '' ?>"><?= e($wo['wo_due_date']) ?></span>
                                            <?php if ($isLate): ?><span class="badge bg-danger-lt ms-1"><?= e(t('overview.prod_late')) ?></span><?php endif ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress progress-sm flex-fill">
                                                    <div class="progress-bar bg-<?= $pct >= 100 ? 'success' : 'primary' ?>" style="width: <?= max(3, $pct) ?>%"></div>
                                                </div>
                                                <span class="small fw-medium"><?= $pct ?>%</span>
                                            </div>
                                        </td>
                                        <td class="num text-end">
                                            <?= number_format($comp, 1) ?> / <?= number_format($ord, 1) ?> <span class="small text-secondary"><?= e($wo['pt_um'] ?? '') ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-4"><?= e(t('overview.prod_active_none')) ?></td>
                                </tr>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent QC -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <h3 class="card-title"><i class="ti ti-flask text-secondary me-2"></i><?= e(t('overview.prod_qc_title')) ?></h3>
                    <a href="<?= e(url('/qc')) ?>" class="small"><?= e(t('overview.view_all')) ?> &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($recentQc): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentQc as $q): ?>
                                <div class="list-group-item py-2">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fw-medium font-monospace"><?= e($q['qc_test_nbr']) ?><?php if (!empty($q['qc_ref_doc'])): ?> <span class="text-secondary">· <?= e($q['qc_ref_doc']) ?></span><?php endif ?></span>
                                        <?php if ($q['qc_result'] === 'PASS'): ?>
                                            <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i><?= e(t('overview.prod_qc_pass')) ?></span>
                                        <?php elseif ($q['qc_result'] === 'REWORK'): ?>
                                            <span class="badge bg-warning-lt"><i class="ti ti-refresh me-1"></i><?= e(t('overview.prod_qc_rework')) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-lt"><i class="ti ti-x me-1"></i><?= e(t('overview.prod_qc_fail')) ?></span>
                                        <?php endif ?>
                                    </div>
                                    <div class="small text-truncate text-secondary mb-1"><?= e($q['qc_part']) ?> - <?= e($q['pt_desc1'] ?? '') ?></div>
                                    <div class="small text-secondary d-flex justify-content-between">
                                        <span><?= e(t('overview.prod_qc_sample', ['qty' => number_format((float) $q['qc_qty_inspect'], 2)])) ?></span>
                                        <span><?= e(substr((string) $q['qc_date'], 0, 10)) ?></span>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-secondary py-4"><?= e(t('overview.prod_qc_none')) ?></div>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</div>
