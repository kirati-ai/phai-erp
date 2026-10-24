<?php
/**
 * Accounting Dashboard view.
 * @var string $title
 * @var array $company, $kpis, $overdueAr, $dueSoonAp, $banks
 */
$curr = $company['co_base_curr'] ?? 'THB';
$money = fn (float $v) => number_format($v, 2);
?>
<div class="phai-module-dashboard">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h2 class="mb-0 fs-3 fw-bold"><i class="ti ti-report-money text-primary me-2"></i><?= e(t('overview.acct_heading')) ?></h2>
            <div class="text-secondary small"><?= e(t('overview.acct_intro')) ?></div>
        </div>
    </div>

    <?= \Phai\Core\View\View::partial('partials/flow', ['flow' => $flow]) ?>

    <!-- KPI Cards -->
    <?php if ($kpis): ?>
        <div class="row g-3 mb-3">
            <?php foreach ($kpis as $k): ?>
                <div class="col-sm-6 col-xl-4">
                    <a class="card phai-kpi h-100" href="<?= e(url($k['link'])) ?>">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="phai-kpi-icon bg-<?= e($k['color']) ?>-lt text-<?= e($k['color']) ?>">
                                    <i class="ti <?= e($k['icon']) ?>"></i>
                                </span>
                                <span class="text-secondary fw-medium"><?= e($k['label']) ?></span>
                            </div>
                            <div class="phai-kpi-value">
                                <?= $money($k['value']) ?> <span class="phai-kpi-curr"><?= e($curr) ?></span>
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
        <!-- Overdue Receivables -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <h3 class="card-title text-danger"><i class="ti ti-user-dollar me-2"></i><?= e(t('overview.acct_ar_title')) ?></h3>
                    <a href="<?= e(url('/receivables')) ?>" class="small"><?= e(t('overview.view_all')) ?> &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-hover">
                        <thead>
                            <tr>
                                <th><?= e(t('overview.acct_col_ar')) ?></th>
                                <th><?= e(t('overview.col_customer')) ?></th>
                                <th><?= e(t('overview.acct_col_due')) ?></th>
                                <th class="text-end"><?= e(t('overview.acct_col_late')) ?></th>
                                <th class="text-end"><?= e(t('overview.acct_col_balance', ['curr' => $curr])) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($overdueAr): ?>
                                <?php foreach ($overdueAr as $ar): ?>
                                    <tr onclick="location.href='<?= e(url('/receivables')) ?>'" style="cursor: pointer;">
                                        <td class="fw-medium font-monospace text-primary"><?= e($ar['ar_nbr']) ?></td>
                                        <td class="text-truncate" style="max-width: 160px;"><?= e($ar['cm_name'] ?? $ar['ar_cm_code']) ?></td>
                                        <td><?= e($ar['ar_due_date']) ?></td>
                                        <td class="text-end text-danger fw-bold"><?= e(t('overview.acct_days', ['n' => (int) $ar['days_late']])) ?></td>
                                        <td class="num text-end fw-bold"><?= $money((float) $ar['balance']) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-success py-4">
                                        <i class="ti ti-check fs-1 d-block mb-1"></i><?= e(t('overview.acct_ar_ok')) ?>
                                    </td>
                                </tr>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Upcoming Payables -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <h3 class="card-title text-orange"><i class="ti ti-clock-dollar me-2"></i><?= e(t('overview.acct_ap_title')) ?></h3>
                    <a href="<?= e(url('/payables')) ?>" class="small"><?= e(t('overview.view_all')) ?> &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-hover">
                        <thead>
                            <tr>
                                <th><?= e(t('overview.acct_col_voucher')) ?></th>
                                <th><?= e(t('overview.col_vendor')) ?></th>
                                <th><?= e(t('overview.acct_col_pay_due')) ?></th>
                                <th class="text-end"><?= e(t('overview.acct_col_to_pay', ['curr' => $curr])) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($dueSoonAp): ?>
                                <?php foreach ($dueSoonAp as $ap): ?>
                                    <tr onclick="location.href='<?= e(url('/payables')) ?>'" style="cursor: pointer;">
                                        <td class="fw-medium font-monospace text-primary"><?= e($ap['ap_ref']) ?></td>
                                        <td class="text-truncate" style="max-width: 200px;"><?= e($ap['vd_name'] ?? $ap['ap_vd_code']) ?></td>
                                        <td><?= e($ap['ap_due_date']) ?></td>
                                        <td class="num text-end fw-bold"><?= $money((float) $ap['balance']) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-secondary py-4"><?= e(t('overview.acct_ap_ok')) ?></td>
                                </tr>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Bank & Cash Accounts -->
        <?php if ($banks): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-header py-2 d-flex align-items-center justify-content-between">
                        <h3 class="card-title"><i class="ti ti-building-bank text-secondary me-2"></i><?= e(t('overview.acct_banks_title')) ?></h3>
                        <a href="<?= e(url('/banks')) ?>" class="small"><?= e(t('overview.acct_banks_link')) ?> &rarr;</a>
                    </div>
                    <div class="card-body py-2">
                        <div class="row g-2">
                            <?php foreach ($banks as $b): ?>
                                <div class="col-sm-6 col-md-3">
                                    <div class="p-2 border rounded d-flex align-items-center gap-2">
                                        <span class="avatar avatar-sm bg-primary-lt"><i class="ti ti-building-bank"></i></span>
                                        <div class="min-w-0">
                                            <div class="fw-medium text-truncate"><?= e($b['bk_desc']) ?></div>
                                            <div class="text-secondary small font-monospace"><?= e($b['bk_bank']) ?> · GL: <?= e($b['bk_acct']) ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>
