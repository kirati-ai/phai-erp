<?php
/**
 * Purchasing Dashboard view.
 * @var string $title
 * @var array $company, $kpis, $overduePos, $recentPos, $topVendors
 */
$curr = $company['co_base_curr'] ?? 'THB';
$money = fn (float $v) => number_format($v, 2);
?>
<div class="phai-module-dashboard">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h2 class="mb-0 fs-3 fw-bold"><i class="ti ti-shopping-cart text-primary me-2"></i><?= e(t('overview.pur_heading')) ?></h2>
            <div class="text-secondary small"><?= e(t('overview.pur_intro')) ?></div>
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
                            <?php if (!empty($k['sub'])): ?>
                                <div class="small <?= $k['sub']['pct'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <i class="ti <?= $k['sub']['pct'] >= 0 ? 'ti-arrow-up-right' : 'ti-arrow-down-right' ?>"></i><?= e(abs($k['sub']['pct']) . '%') ?>
                                    <span class="text-secondary"><?= e($k['sub']['text']) ?></span>
                                </div>
                            <?php elseif (!empty($k['note'])): ?>
                                <div class="small text-secondary"><?= e($k['note']) ?></div>
                            <?php endif ?>
                        </div>
                    </a>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <!-- Overdue Alert if any -->
    <?php if ($overduePos): ?>
        <div class="alert alert-danger d-flex align-items-center gap-3 mb-3">
            <i class="ti ti-alert-triangle fs-2"></i>
            <div class="flex-fill">
                <div class="fw-bold"><?= e(t('overview.pur_late_title')) ?></div>
                <div class="small"><?= e(t('overview.pur_late_text', ['n' => count($overduePos)])) ?></div>
            </div>
            <a href="<?= e(url('/purchase-orders')) ?>" class="btn btn-sm btn-outline-danger"><?= e(t('overview.pur_late_link')) ?></a>
        </div>
    <?php endif ?>

    <div class="row g-3">
        <!-- Recent POs Table -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <h3 class="card-title"><i class="ti ti-file-invoice text-secondary me-2"></i><?= e(t('overview.pur_recent_title')) ?></h3>
                    <a href="<?= e(url('/purchase-orders')) ?>" class="small"><?= e(t('overview.view_all')) ?> &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-hover">
                        <thead>
                            <tr>
                                <th><?= e(t('overview.pur_col_po')) ?></th>
                                <th><?= e(t('overview.col_vendor')) ?></th>
                                <th><?= e(t('overview.col_order_date')) ?></th>
                                <th><?= e(t('overview.col_due')) ?></th>
                                <th><?= e(t('overview.col_status')) ?></th>
                                <th class="text-end"><?= e(t('overview.col_total', ['curr' => $curr])) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentPos): ?>
                                <?php foreach ($recentPos as $po): ?>
                                    <tr onclick="location.href='<?= e(url('/purchase-orders/' . rawurlencode($po['po_nbr']))) ?>'" style="cursor: pointer;">
                                        <td class="fw-medium font-monospace text-primary"><?= e($po['po_nbr']) ?></td>
                                        <td class="text-truncate" style="max-width: 180px;"><?= e($po['vd_name'] ?? $po['po_vd_code']) ?></td>
                                        <td><?= e($po['po_date']) ?></td>
                                        <td>
                                            <?php
                                            $isLate = !empty($po['po_due_date']) && $po['po_due_date'] < date('Y-m-d') && $po['po_status'] === 'A';
                                            ?>
                                            <span class="<?= $isLate ? 'text-danger fw-bold' : '' ?>"><?= e($po['po_due_date'] ?: '-') ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $stMap = [
                                                'A' => ['bg' => 'primary-lt', 'text' => t('overview.pur_status_A')],
                                                'C' => ['bg' => 'success-lt', 'text' => t('overview.pur_status_C')],
                                                'X' => ['bg' => 'danger-lt', 'text' => t('overview.status_X')],
                                            ];
                                            $st = $stMap[$po['po_status']] ?? ['bg' => 'secondary-lt', 'text' => $po['po_status']];
                                            ?>
                                            <span class="badge bg-<?= $st['bg'] ?>"><?= e($st['text']) ?></span>
                                        </td>
                                        <td class="num text-end"><?= $money((float) $po['amt']) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-4"><?= e(t('overview.pur_recent_none')) ?></td>
                                </tr>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Vendors -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header py-2">
                    <h3 class="card-title"><i class="ti ti-truck text-secondary me-2"></i><?= e(t('overview.pur_top_title')) ?></h3>
                </div>
                <div class="card-body py-2">
                    <?php if ($topVendors): ?>
                        <?php $maxAmt = (float) ($topVendors[0]['amt'] ?? 1) ?: 1; ?>
                        <?php foreach ($topVendors as $v): ?>
                            <div class="phai-rank mb-2">
                                <span class="phai-rank-name text-truncate">
                                    <span class="fw-medium"><?= e($v['code']) ?></span>
                                    <span class="text-secondary"><?= e($v['name'] ?? '') ?></span>
                                </span>
                                <span class="phai-rank-bar">
                                    <span style="width: <?= max(5, round((float) $v['amt'] / $maxAmt * 100)) ?>%"></span>
                                </span>
                                <span class="num"><?= $money((float) $v['amt']) ?></span>
                            </div>
                        <?php endforeach ?>
                    <?php else: ?>
                        <div class="text-center text-secondary py-4"><?= e(t('overview.pur_top_none')) ?></div>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</div>
