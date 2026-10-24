<?php use Phai\Core\View\View; use Phai\Modules\Accounting\FixedAssetController; ?>
<?= View::partial('doc/print-style') ?>
<?= View::partial('doc/print-toolbar') ?>
<div class="doc">
<?= \Phai\Core\View\View::partial('doc/report-head', ['title' => 'ทะเบียนสินทรัพย์ถาวร', 'meta' => [['ยอดปัจจุบัน ณ', date('d/m/Y')]]]) ?>
<table class="doc-lines"><thead><tr><th>รหัส / ชื่อ</th><th>หมวด</th><th>สถานะ</th><th class="r">ทุน</th><th class="r">ค่าเสื่อมสะสม</th><th class="r">มูลค่าตามบัญชี</th></tr></thead><tbody>
<?php $totals = [0, 0, 0]; foreach ($rows as $a): $book = $a['fa_status'] === 'DISPOSED' ? 0 : (float) $a['fa_cost'] - (float) $a['fa_accum']; if ($a['fa_status'] === 'ACTIVE') { $totals[0] += $a['fa_cost']; $totals[1] += $a['fa_accum']; $totals[2] += $book; } ?>
<tr><td><?= e($a['fa_code']) ?><br><?= e($a['fa_desc']) ?></td><td><?= e($a['fa_category']) ?></td><td><?= e(FixedAssetController::statuses()[$a['fa_status']]['text']) ?></td><td class="r"><?= number_format((float) $a['fa_cost'], 2) ?></td><td class="r"><?= number_format((float) $a['fa_accum'], 2) ?></td><td class="r"><?= number_format($book, 2) ?></td></tr>
<?php endforeach ?>
</tbody><tfoot><tr><td colspan="3">รวมเฉพาะสินทรัพย์ที่ใช้งาน</td><?php foreach ($totals as $t): ?><td class="r"><?= number_format($t, 2) ?></td><?php endforeach ?></tr></tfoot></table>
</div>
