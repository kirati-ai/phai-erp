<?php
/**
 * The owner's figures (HomeController::figures()), shared by the home page and its PDF. Nothing links away:
 * hover a chart for its numbers. Charts are SVG drawn here, so they print as they show.
 * @var array  $f     HomeController::figures()
 * @var string $curr  base currency
 */
$money = fn (float $v) => number_format($v, 2);
$short = function (float $v): string {
    $a = abs($v);
    return match (true) {
        $a >= 1e6 => number_format($v / 1e6, 1) . 'M',
        $a >= 1e3 => number_format($v / 1e3, 0) . 'K',
        default   => number_format($v, 0),
    };
};
// two cards side by side when both are there, else one full row
$cols = fn (bool $left, bool $right) => $left && $right ? ['col-lg-8', 'col-lg-4'] : ['col-12', 'col-12'];

$kpis = [];
if ($f['cash'] !== null) {
    $kpis[] = ['ti-building-bank', 'green', t('home.cash'), $f['cash'], ['note' => $f['petty'] != 0.0 ? t('home.cash_petty', ['amount' => $money($f['petty'])]) : t('home.cash_note')]];
}
if ($f['sales'] !== null) {
    $kpis[] = ['ti-trending-up', 'teal', t('home.sales_month'), $f['sales']['amt'], ['pct' => $f['sales']['pct'], 'text' => t('home.vs_last_month')]];
}
if ($f['gross'] !== null) {
    $kpis[] = ['ti-coins', 'lime', t('home.gross_month'), $f['gross']['amt'], ['note' => $f['gross']['pct'] !== null ? t('home.gross_pct', ['pct' => $f['gross']['pct']]) : '']];
}
if ($f['net'] !== null) {
    $kpis[] = ['ti-report-money', $f['net'] >= 0 ? 'green' : 'red', t('home.net_year'), $f['net'], ['note' => t('home.net_note')]];
}
if ($f['backlog'] !== null) {
    $kpis[] = ['ti-clipboard-list', 'cyan', t('home.backlog'), (float) $f['backlog']['amt'], ['note' => t('home.backlog_note', ['n' => (int) $f['backlog']['orders']])]];
}
if ($f['ar'] !== null) {
    $kpis[] = ['ti-cash', 'blue', t('home.receivables'), $f['ar']['open'], ['note' => $f['ar']['late'] > 0 ? t('home.overdue', ['amount' => $money($f['ar']['late'])]) : '', 'warn' => $f['ar']['late'] > 0]];
}
if ($f['ap'] !== null) {
    $kpis[] = ['ti-receipt-2', 'orange', t('home.payables'), $f['ap']['open'], ['note' => $f['ap']['soon'] > 0 ? t('home.due_7_days', ['amount' => $money($f['ap']['soon'])]) : '']];
}
if ($f['stock'] !== null) {
    $kpis[] = ['ti-building-warehouse', 'purple', t('home.stock_value'), $f['stock']['value'], ['note' => t('home.parts_in_stock', ['n' => number_format($f['stock']['parts'])])]];
}
?>
<?php if ($kpis): ?>
    <!-- key figures -->
    <div class="row g-3 mb-3">
        <?php foreach ($kpis as [$icon, $color, $label, $value, $sub]): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="card phai-kpi h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="phai-kpi-icon bg-<?= e($color) ?>-lt text-<?= e($color) ?>"><i class="ti <?= e($icon) ?>"></i></span>
                            <span class="text-secondary"><?= e($label) ?></span>
                        </div>
                        <div class="phai-kpi-value<?= $value < 0 ? ' text-danger' : '' ?>"><?= e($money($value)) ?> <span class="phai-kpi-curr"><?= e($curr) ?></span></div>
                        <?php if (array_key_exists('pct', $sub)): ?>
                            <?php if ($sub['pct'] !== null): ?>
                                <div class="small <?= $sub['pct'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <i class="ti <?= $sub['pct'] >= 0 ? 'ti-arrow-up-right' : 'ti-arrow-down-right' ?>"></i><?= e(abs($sub['pct']) . '%') ?>
                                    <span class="text-secondary"><?= e($sub['text']) ?></span>
                                </div>
                            <?php endif ?>
                        <?php elseif (($sub['note'] ?? '') !== ''): ?>
                            <div class="small <?= !empty($sub['warn']) ? 'text-danger' : 'text-secondary' ?>"><?= e($sub['note']) ?></div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?php [$cl, $cr] = $cols($f['trend'] !== null, $f['aging'] !== null); ?>
<?php if ($f['trend'] !== null || $f['aging'] !== null): ?>
    <div class="row g-3 mb-3">
        <?php if ($f['trend'] !== null):
            // ------------------------------------------------ sales bars, last year's line, gross margin % on the right
            $W = 720; $H = 230; $L = 52; $R = 44; $T = 14; $B = 30;
            $months = array_values($f['trend']);
            $n = count($months);
            $slot = ($W - $L - $R) / $n;
            $vals = array_merge(array_column($months, 'sales'), array_column($months, 'last'));
            $max = max(max($vals), 1) * 1.1;
            $min = min(0, min($vals) * 1.1);
            $y = fn (float $v) => round($T + ($max - $v) / ($max - $min) * ($H - $T - $B), 1);
            $gps = array_filter(array_column($months, 'gp'), fn ($v) => $v !== null);
            $pMax = $gps ? max(10, ceil(max($gps) / 10) * 10) : 100;
            $pMin = $gps ? min(0, floor(min($gps) / 10) * 10) : 0;
            $yp = fn (float $v) => round($T + ($pMax - $v) / ($pMax - $pMin) * ($H - $T - $B), 1);
            $x = fn (int $i) => round($L + $slot * ($i + .5), 1);
            ?>
            <div class="<?= $cl ?>">
                <div class="card h-100 phai-chart-card">
                    <div class="card-header py-2"><h3 class="card-title"><?= e(t('home.trend_title')) ?></h3></div>
                    <div class="card-body py-2">
                        <?php if (array_sum(array_map('abs', $vals)) == 0): ?>
                            <div class="text-center text-secondary py-5"><i class="ti ti-chart-bar fs-1 d-block mb-1"></i><?= e(t('home.no_sales')) ?></div>
                        <?php else: ?>
                            <svg class="phai-chart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="<?= e(t('home.trend_title')) ?>">
                                <?php for ($k = 0; $k <= 4; $k++): $v = $min + ($max - $min) * $k / 4; ?>
                                    <line class="grid" x1="<?= $L ?>" x2="<?= $W - $R ?>" y1="<?= $y($v) ?>" y2="<?= $y($v) ?>"/>
                                    <text class="axis" x="<?= $L - 6 ?>" y="<?= $y($v) + 4 ?>" text-anchor="end"><?= e($short($v)) ?></text>
                                    <?php if ($gps): $p = $pMin + ($pMax - $pMin) * $k / 4; ?>
                                        <text class="axis axis-gp" x="<?= $W - $R + 6 ?>" y="<?= $yp($p) + 4 ?>"><?= e(round($p) . '%') ?></text>
                                    <?php endif ?>
                                <?php endfor ?>
                                <line class="zero" x1="<?= $L ?>" x2="<?= $W - $R ?>" y1="<?= $y(0) ?>" y2="<?= $y(0) ?>"/>
                                <?php foreach ($months as $i => $m): $top = $y(max($m['sales'], 0)); $bottom = $y(min($m['sales'], 0)); ?>
                                    <g class="slot">
                                        <title><?= e($m['label'] . ' · ' . t('home.legend_sales') . ' ' . $money($m['sales']) . ' · ' . t('home.legend_last') . ' ' . $money($m['last'])
                                            . ($m['gp'] !== null ? ' · ' . t('home.legend_gp') . ' ' . $m['gp'] . '%' : '')) ?></title>
                                        <rect class="hit" x="<?= round($L + $slot * $i, 1) ?>" y="<?= $T ?>" width="<?= round($slot, 1) ?>" height="<?= $H - $T - $B ?>"/>
                                        <rect class="bar<?= $i === $n - 1 ? ' now' : '' ?><?= $m['sales'] < 0 ? ' neg' : '' ?>" x="<?= round($x($i) - $slot * .28, 1) ?>" y="<?= $top ?>"
                                              width="<?= round($slot * .56, 1) ?>" height="<?= max(1, round($bottom - $top, 1)) ?>" rx="3"/>
                                        <text class="axis" x="<?= $x($i) ?>" y="<?= $H - 10 ?>" text-anchor="middle"><?= e($m['label']) ?></text>
                                    </g>
                                <?php endforeach ?>
                                <polyline class="last" points="<?= e(implode(' ', array_map(fn ($i) => $x($i) . ',' . $y($months[$i]['last']), array_keys($months)))) ?>"/>
                                <?php
                                // the margin line, broken where a month has no sales
                                $run = [];
                                foreach ($months as $i => $m) {
                                    if ($m['gp'] !== null) {
                                        $run[] = $x($i) . ',' . $yp($m['gp']);
                                    }
                                    if (($m['gp'] === null || $i === $n - 1) && $run) {
                                        echo count($run) > 1 ? '<polyline class="gp" points="' . e(implode(' ', $run)) . '"/>' : '';
                                        $run = [];
                                    }
                                }
                                foreach ($months as $i => $m) {
                                    if ($m['gp'] !== null) {
                                        echo '<circle class="gp-dot" cx="' . $x($i) . '" cy="' . $yp($m['gp']) . '" r="3"/>';
                                    }
                                }
                                ?>
                            </svg>
                            <div class="phai-legend">
                                <span><i class="sales"></i><?= e(t('home.legend_sales')) ?></span>
                                <span><i class="last"></i><?= e(t('home.legend_last')) ?></span>
                                <?php if ($gps): ?><span><i class="gp"></i><?= e(t('home.legend_gp')) ?></span><?php endif ?>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        <?php endif ?>

        <?php if ($f['aging'] !== null):
            $agingTotal = array_sum($f['aging']);
            ?>
            <!-- receivables by how late they are -->
            <div class="<?= $cr ?>">
                <div class="card h-100">
                    <div class="card-header py-2"><h3 class="card-title"><?= e(t('home.aging_title')) ?></h3></div>
                    <div class="card-body py-2">
                        <?php if ($agingTotal <= 0): ?>
                            <div class="text-center text-secondary py-5"><i class="ti ti-circle-check fs-1 d-block mb-1 text-success"></i><?= e(t('home.aging_none')) ?></div>
                        <?php else: ?>
                            <div class="phai-aging-bar">
                                <?php foreach ($f['aging'] as $key => $amt): if ($amt <= 0) continue; ?>
                                    <span class="<?= e($key) ?>" style="width: <?= round($amt / $agingTotal * 100, 2) ?>%" title="<?= e(t("home.aging_$key") . ' ' . $money($amt)) ?>"></span>
                                <?php endforeach ?>
                            </div>
                            <table class="table table-sm phai-aging mb-0">
                                <?php foreach ($f['aging'] as $key => $amt): ?>
                                    <tr>
                                        <td><i class="dot <?= e($key) ?>"></i><?= e(t("home.aging_$key")) ?></td>
                                        <td class="num"><?= e($money($amt)) ?></td>
                                        <td class="num text-secondary"><?= e(round($amt / $agingTotal * 100) . '%') ?></td>
                                    </tr>
                                <?php endforeach ?>
                            </table>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
<?php endif ?>

<?php [$cl, $cr] = $cols($f['flow'] !== null, (bool) $f['tax']); ?>
<?php if ($f['flow'] !== null || $f['tax']): ?>
    <div class="row g-3 mb-3">
        <?php if ($f['flow'] !== null):
            // ------------------------------------------------ money in (up) and out (down) each week, cash left as a line
            $W = 720; $H = 230; $L = 52; $R = 56; $T = 14; $B = 30;
            $weeks = $f['flow']['weeks'];
            $n = count($weeks);
            $slot = ($W - $L - $R) / $n;
            $m = max(1, max(array_merge(array_column($weeks, 'in'), array_column($weeks, 'out')))) * 1.1;
            $y = fn (float $v) => round($T + ($m - $v) / (2 * $m) * ($H - $T - $B), 1);
            $bals = array_column($weeks, 'bal');
            $bMax = max(1, max($bals)) * 1.1;
            $bMin = min(0, min($bals) * 1.1);
            $yb = fn (float $v) => round($T + ($bMax - $v) / ($bMax - $bMin) * ($H - $T - $B), 1);
            $x = fn (int $i) => round($L + $slot * ($i + .5), 1);
            $short_week = null;
            foreach ($weeks as $w) {
                if ($w['bal'] < 0) {
                    $short_week = $w['label'];
                    break;
                }
            }
            ?>
            <div class="<?= $cl ?>">
                <div class="card h-100 phai-chart-card">
                    <div class="card-header py-2"><h3 class="card-title"><?= e(t('home.flow_title')) ?></h3></div>
                    <div class="card-body py-2">
                        <svg class="phai-chart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="<?= e(t('home.flow_title')) ?>">
                            <?php for ($k = 0; $k <= 4; $k++): $v = -$m + 2 * $m * $k / 4; $b = $bMin + ($bMax - $bMin) * $k / 4; ?>
                                <line class="grid" x1="<?= $L ?>" x2="<?= $W - $R ?>" y1="<?= $y($v) ?>" y2="<?= $y($v) ?>"/>
                                <text class="axis" x="<?= $L - 6 ?>" y="<?= $y($v) + 4 ?>" text-anchor="end"><?= e($short(abs($v))) ?></text>
                                <text class="axis axis-bal" x="<?= $W - $R + 6 ?>" y="<?= $yb($b) + 4 ?>"><?= e($short($b)) ?></text>
                            <?php endfor ?>
                            <line class="zero" x1="<?= $L ?>" x2="<?= $W - $R ?>" y1="<?= $y(0) ?>" y2="<?= $y(0) ?>"/>
                            <?php foreach ($weeks as $i => $w): ?>
                                <g class="slot">
                                    <title><?= e($w['label'] . ' · ' . t('home.legend_in') . ' ' . $money($w['in']) . ' · ' . t('home.legend_out') . ' ' . $money($w['out'])
                                        . ' · ' . t('home.legend_bal') . ' ' . $money($w['bal'])) ?></title>
                                    <rect class="hit" x="<?= round($L + $slot * $i, 1) ?>" y="<?= $T ?>" width="<?= round($slot, 1) ?>" height="<?= $H - $T - $B ?>"/>
                                    <?php if ($w['in'] > 0): ?>
                                        <rect class="in" x="<?= round($x($i) - $slot * .3, 1) ?>" y="<?= $y($w['in']) ?>" width="<?= round($slot * .6, 1) ?>" height="<?= max(1, round($y(0) - $y($w['in']), 1)) ?>" rx="3"/>
                                    <?php endif ?>
                                    <?php if ($w['out'] > 0): ?>
                                        <rect class="out" x="<?= round($x($i) - $slot * .3, 1) ?>" y="<?= $y(0) ?>" width="<?= round($slot * .6, 1) ?>" height="<?= max(1, round($y(-$w['out']) - $y(0), 1)) ?>" rx="3"/>
                                    <?php endif ?>
                                    <text class="axis" x="<?= $x($i) ?>" y="<?= $H - 10 ?>" text-anchor="middle"><?= e($w['label']) ?></text>
                                </g>
                            <?php endforeach ?>
                            <polyline class="bal" points="<?= e(implode(' ', array_map(fn ($i) => $x($i) . ',' . $yb($weeks[$i]['bal']), array_keys($weeks)))) ?>"/>
                            <?php foreach ($weeks as $i => $w): ?>
                                <circle class="bal-dot<?= $w['bal'] < 0 ? ' neg' : '' ?>" cx="<?= $x($i) ?>" cy="<?= $yb($w['bal']) ?>" r="3.5"/>
                            <?php endforeach ?>
                        </svg>
                        <div class="phai-legend">
                            <span><i class="in"></i><?= e(t('home.legend_in')) ?></span>
                            <span><i class="out"></i><?= e(t('home.legend_out')) ?></span>
                            <span><i class="bal"></i><?= e(t('home.legend_bal')) ?></span>
                        </div>
                        <div class="small text-secondary mt-1">
                            <?= e(t('home.flow_note', ['amount' => $money((float) ($f['flow']['start'] ?? 0))])) ?>
                            <?php if ($short_week !== null): ?><span class="text-danger fw-medium ms-1"><i class="ti ti-alert-triangle"></i> <?= e(t('home.flow_short', ['week' => $short_week])) ?></span><?php endif ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif ?>

        <?php if ($f['tax']): ?>
            <!-- tax returns of last month and the open period -->
            <div class="<?= $cr ?>">
                <div class="card h-100">
                    <div class="card-header py-2"><h3 class="card-title"><?= e(t('home.tax_title')) ?></h3></div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($f['tax'] as $tx): ?>
                            <div class="list-group-item d-flex gap-2 py-2">
                                <i class="ti <?= $tx['done'] ? 'ti-circle-check text-success' : 'ti-clock text-warning' ?> fs-3"></i>
                                <div class="min-w-0">
                                    <div class="fw-medium"><?= e($tx['name']) ?></div>
                                    <div class="small text-secondary"><?= e($tx['text']) ?></div>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
<?php endif ?>

<?php
$alertIcons = ['ar_late' => 'ti-clock-dollar', 'over_limit' => 'ti-alert-triangle', 'low_stock' => 'ti-package', 'so_late' => 'ti-truck',
               'wo_late' => 'ti-building-factory-2', 'po_late' => 'ti-shopping-cart'];
$showAlerts = $f['ar'] !== null || $f['stock'] !== null || $f['backlog'] !== null || $f['alerts'];
[$cl, $cr] = $showAlerts && $f['top'] ? ['col-lg-6', 'col-lg-6'] : ['col-12', 'col-12'];
?>
<?php if ($showAlerts || $f['top']): ?>
    <div class="row g-3">
        <?php if ($showAlerts): ?>
            <!-- what needs a decision -->
            <div class="<?= $cl ?>">
                <div class="card h-100">
                    <div class="card-header py-2"><h3 class="card-title"><?= e(t('home.alerts_title')) ?></h3></div>
                    <?php if (!$f['alerts']): ?>
                        <div class="card-body text-center text-secondary py-5"><i class="ti ti-circle-check fs-1 d-block mb-1 text-success"></i><?= e(t('home.alerts_none')) ?></div>
                    <?php else: ?>
                        <div class="card-body py-2 phai-alerts">
                            <?php foreach ($f['alerts'] as $key => $rows): ?>
                                <div class="phai-alert-group">
                                    <div class="phai-alert-head"><i class="ti <?= e($alertIcons[$key] ?? 'ti-alert-circle') ?>"></i><?= e(t("home.alert_$key")) ?><span class="badge bg-red-lt ms-1"><?= count($rows) ?></span></div>
                                    <?php foreach ($rows as [$text, $amt, $note]): ?>
                                        <div class="phai-alert-row">
                                            <span class="text-truncate"><?= e($text) ?></span>
                                            <?php if ($amt !== null): ?><span class="num"><?= e($money((float) $amt)) ?></span><?php endif ?>
                                            <span class="text-secondary text-nowrap"><?= e($note) ?></span>
                                        </div>
                                    <?php endforeach ?>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>

        <?php if ($f['top']): $topMax = max(1, (float) $f['top'][0]['sales']); ?>
            <!-- best customers and what they leave -->
            <div class="<?= $cr ?>">
                <div class="card h-100">
                    <div class="card-header py-2"><h3 class="card-title"><?= e(t('home.top_customers')) ?></h3></div>
                    <div class="card-body py-2">
                        <?php foreach ($f['top'] as $c): ?>
                            <div class="phai-rank">
                                <span class="phai-rank-name text-truncate"><span class="fw-medium"><?= e($c['code']) ?></span> <span class="text-secondary"><?= e($c['name'] ?? '') ?></span></span>
                                <span class="phai-rank-bar"><span style="width: <?= max(0, round((float) $c['sales'] / $topMax * 100)) ?>%"></span></span>
                                <span class="num"><?= e($money((float) $c['sales'])) ?></span>
                                <?php if (array_key_exists('gp', $c)): ?>
                                    <span class="num text-secondary phai-rank-gp" title="<?= e(t('home.top_gp')) ?>">
                                        <?= e($money($c['gp'])) ?> · <?= (float) $c['sales'] != 0.0 ? e(round($c['gp'] / (float) $c['sales'] * 100, 1) . '%') : '–' ?>
                                    </span>
                                <?php endif ?>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
<?php endif ?>
