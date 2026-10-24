<?php
/**
 * Sales Dashboard view.
 * @var string $title
 * @var array $company, $kpis, $recentSos, $topParts, $topCustomers
 */
$curr = $company['co_base_curr'] ?? 'THB';
$money = fn (float $v) => number_format($v, 2);
?>
<div class="phai-module-dashboard">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h2 class="mb-0 fs-3 fw-bold"><i class="ti ti-receipt text-success me-2"></i><?= e(t('overview.sales_heading')) ?></h2>
            <div class="text-secondary small"><?= e(t('overview.sales_intro')) ?></div>
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

    <div class="row g-3">
        <!-- Recent Sales Orders -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <h3 class="card-title"><i class="ti ti-file-dollar text-secondary me-2"></i><?= e(t('overview.sales_recent_title')) ?></h3>
                    <a href="<?= e(url('/sales-orders')) ?>" class="small"><?= e(t('overview.view_all')) ?> &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-hover">
                        <thead>
                            <tr>
                                <th><?= e(t('overview.sales_col_so')) ?></th>
                                <th><?= e(t('overview.col_customer')) ?></th>
                                <th><?= e(t('overview.col_order_date')) ?></th>
                                <th><?= e(t('overview.col_due')) ?></th>
                                <th><?= e(t('overview.col_status')) ?></th>
                                <th class="text-end"><?= e(t('overview.col_total', ['curr' => $curr])) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentSos): ?>
                                <?php foreach ($recentSos as $so): ?>
                                    <tr onclick="location.href='<?= e(url('/sales-orders/' . rawurlencode($so['so_nbr']))) ?>'" style="cursor: pointer;">
                                        <td class="fw-medium font-monospace text-primary"><?= e($so['so_nbr']) ?></td>
                                        <td class="text-truncate" style="max-width: 180px;"><?= e($so['cm_name'] ?? $so['so_cm_code']) ?></td>
                                        <td><?= e($so['so_date']) ?></td>
                                        <td>
                                            <?php
                                            $isLate = !empty($so['so_due_date']) && $so['so_due_date'] < date('Y-m-d') && $so['so_status'] === 'O';
                                            ?>
                                            <span class="<?= $isLate ? 'text-danger fw-bold' : '' ?>"><?= e($so['so_due_date'] ?: '-') ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $stMap = [
                                                'O' => ['bg' => 'primary-lt', 'text' => t('overview.sales_status_O')],
                                                'S' => ['bg' => 'info-lt', 'text' => t('overview.sales_status_S')],
                                                'C' => ['bg' => 'success-lt', 'text' => t('overview.sales_status_C')],
                                                'X' => ['bg' => 'danger-lt', 'text' => t('overview.status_X')],
                                            ];
                                            $st = $stMap[$so['so_status']] ?? ['bg' => 'secondary-lt', 'text' => $so['so_status']];
                                            ?>
                                            <span class="badge bg-<?= $st['bg'] ?>"><?= e($st['text']) ?></span>
                                        </td>
                                        <td class="num text-end"><?= $money((float) $so['amt']) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-4"><?= e(t('overview.sales_recent_none')) ?></td>
                                </tr>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Selling Items & Top Customers -->
        <div class="col-lg-4 d-flex flex-column gap-3">
            <!-- Top Selling Parts -->
            <div class="card flex-fill">
                <div class="card-header py-2">
                    <h3 class="card-title"><i class="ti ti-star text-secondary me-2"></i><?= e(t('overview.sales_top_parts')) ?></h3>
                </div>
                <div class="card-body py-2">
                    <?php if ($topParts): ?>
                        <?php $maxP = (float) ($topParts[0]['amt'] ?? 1) ?: 1; ?>
                        <?php foreach ($topParts as $p): ?>
                            <div class="phai-rank mb-2">
                                <span class="phai-rank-name text-truncate">
                                    <span class="fw-medium"><?= e($p['part']) ?></span>
                                    <span class="text-secondary"><?= e($p['desc1'] ?? '') ?></span>
                                </span>
                                <span class="phai-rank-bar">
                                    <span style="width: <?= max(5, round((float) $p['amt'] / $maxP * 100)) ?>%"></span>
                                </span>
                                <span class="num"><?= $money((float) $p['amt']) ?></span>
                            </div>
                        <?php endforeach ?>
                    <?php else: ?>
                        <div class="text-center text-secondary py-3"><?= e(t('overview.sales_top_parts_none')) ?></div>
                    <?php endif ?>
                </div>
            </div>

            <!-- Top Customers -->
            <div class="card flex-fill">
                <div class="card-header py-2">
                    <h3 class="card-title"><i class="ti ti-user-heart text-secondary me-2"></i><?= e(t('overview.sales_top_customers')) ?></h3>
                </div>
                <div class="card-body py-2">
                    <?php if ($topCustomers): ?>
                        <?php $maxC = (float) ($topCustomers[0]['amt'] ?? 1) ?: 1; ?>
                        <?php foreach ($topCustomers as $c): ?>
                            <div class="phai-rank mb-2">
                                <span class="phai-rank-name text-truncate">
                                    <span class="fw-medium"><?= e($c['code']) ?></span>
                                    <span class="text-secondary"><?= e($c['name'] ?? '') ?></span>
                                </span>
                                <span class="phai-rank-bar">
                                    <span style="width: <?= max(5, round((float) $c['amt'] / $maxC * 100)) ?>%"></span>
                                </span>
                                <span class="num"><?= $money((float) $c['amt']) ?></span>
                            </div>
                        <?php endforeach ?>
                    <?php else: ?>
                        <div class="text-center text-secondary py-3"><?= e(t('overview.sales_top_customers_none')) ?></div>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</div>
