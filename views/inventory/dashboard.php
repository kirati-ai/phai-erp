<?php
/**
 * Inventory Dashboard view.
 * @var string $title
 * @var array $company, $kpis, $lowStockItems, $typeBreakdown, $recentMoves
 */
$curr = $company['co_base_curr'] ?? 'THB';
$money = fn (float $v) => number_format($v, 2);
?>
<div class="phai-module-dashboard">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h2 class="mb-0 fs-3 fw-bold"><i class="ti ti-building-warehouse text-purple me-2"></i><?= e(t('overview.inv_heading')) ?></h2>
            <div class="text-secondary small"><?= e(t('overview.inv_intro')) ?></div>
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
                                <?= !empty($k['is_qty']) ? number_format((int) $k['value']) : $money($k['value']) ?>
                                <?php if (empty($k['is_qty'])): ?>
                                    <span class="phai-kpi-curr"><?= e($curr) ?></span>
                                <?php else: ?>
                                    <span class="phai-kpi-curr"><?= e(t('overview.unit_list')) ?></span>
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

    <div class="row g-3">
        <!-- Low Stock Items Alert -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <h3 class="card-title text-danger"><i class="ti ti-alert-triangle me-2"></i><?= e(t('overview.inv_low_title')) ?></h3>
                    <a href="<?= e(url('/stock')) ?>" class="small"><?= e(t('overview.inv_low_link')) ?> &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-hover">
                        <thead>
                            <tr>
                                <th><?= e(t('overview.inv_col_part')) ?></th>
                                <th><?= e(t('overview.inv_col_desc')) ?></th>
                                <th class="text-end"><?= e(t('overview.inv_col_onhand')) ?></th>
                                <th class="text-end"><?= e(t('overview.inv_col_safety')) ?></th>
                                <th><?= e(t('overview.inv_col_um')) ?></th>
                                <th><?= e(t('overview.col_status')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($lowStockItems): ?>
                                <?php foreach ($lowStockItems as $it): ?>
                                    <tr onclick="location.href='<?= e(url('/parts/' . rawurlencode($it['pt_part']))) ?>'" style="cursor: pointer;">
                                        <td class="fw-medium font-monospace text-primary"><?= e($it['pt_part']) ?></td>
                                        <td class="text-truncate" style="max-width: 200px;"><?= e($it['pt_desc1']) ?></td>
                                        <td class="num text-end fw-bold text-danger"><?= number_format((float) $it['qty_oh'], 2) ?></td>
                                        <td class="num text-end"><?= number_format((float) $it['pt_sfty_stk'], 2) ?></td>
                                        <td><?= e($it['pt_um']) ?></td>
                                        <td>
                                            <?php if ((float) $it['qty_oh'] <= 0): ?>
                                                <span class="badge bg-danger-lt"><?= e(t('overview.inv_out')) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-lt"><?= e(t('overview.inv_below')) ?></span>
                                            <?php endif ?>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-success py-4">
                                        <i class="ti ti-check fs-1 d-block mb-1"></i><?= e(t('overview.inv_low_ok')) ?>
                                    </td>
                                </tr>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Inventory Breakdown -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header py-2">
                    <h3 class="card-title"><i class="ti ti-chart-pie text-secondary me-2"></i><?= e(t('overview.inv_mix_title')) ?></h3>
                </div>
                <div class="card-body py-2">
                    <?php if ($typeBreakdown): ?>
                        <?php
                        $totVal = array_sum(array_column($typeBreakdown, 'amt')) ?: 1;
                        $typeNames = ['FG' => t('overview.inv_type_FG'), 'RM' => t('overview.inv_type_RM'), 'FA' => t('overview.inv_type_FA'), 'OTHER' => t('overview.inv_type_OTHER')];
                        ?>
                        <?php foreach ($typeBreakdown as $b): ?>
                            <?php $pct = round((float) $b['amt'] / $totVal * 100); ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-medium"><?= e($typeNames[$b['inv_type']] ?? $b['inv_type']) ?></span>
                                    <span><?= $money((float) $b['amt']) ?> <?= e($curr) ?> (<?= $pct ?>%)</span>
                                </div>
                                <div class="progress progress-sm">
                                    <div class="progress-bar bg-primary" style="width: <?= max(3, $pct) ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach ?>
                    <?php else: ?>
                        <div class="text-center text-secondary py-4"><?= e(t('overview.inv_mix_none')) ?></div>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <!-- Recent Stock Transactions -->
        <?php if ($recentMoves): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-header py-2 d-flex align-items-center justify-content-between">
                        <h3 class="card-title"><i class="ti ti-history text-secondary me-2"></i><?= e(t('overview.inv_moves_title')) ?></h3>
                        <a href="<?= e(url('/stock/history')) ?>" class="small"><?= e(t('overview.inv_moves_link')) ?> &rarr;</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table table-hover">
                            <thead>
                                <tr>
                                    <th><?= e(t('overview.inv_col_seq')) ?></th>
                                    <th><?= e(t('overview.inv_col_time')) ?></th>
                                    <th><?= e(t('overview.inv_col_type')) ?></th>
                                    <th><?= e(t('overview.inv_col_part')) ?></th>
                                    <th><?= e(t('overview.inv_col_desc')) ?></th>
                                    <th><?= e(t('overview.inv_col_loc')) ?></th>
                                    <th class="text-end"><?= e(t('overview.inv_col_chg')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentMoves as $m): ?>
                                    <tr>
                                        <td class="font-monospace text-secondary">#<?= e($m['tr_trnbr']) ?></td>
                                        <td><?= e($m['tr_date']) ?></td>
                                        <td><span class="badge bg-secondary-lt"><?= e($m['tr_type']) ?></span></td>
                                        <td class="fw-medium font-monospace"><?= e($m['tr_part']) ?></td>
                                        <td class="text-truncate" style="max-width: 200px;"><?= e($m['pt_desc1'] ?? '') ?></td>
                                        <td><?= e($m['tr_loc'] ?? '-') ?></td>
                                        <td class="num text-end <?= (float) $m['tr_qty_chg'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= ((float) $m['tr_qty_chg'] > 0 ? '+' : '') . number_format((float) $m['tr_qty_chg'], 2) ?> <?= e($m['tr_um'] ?? '') ?>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>
