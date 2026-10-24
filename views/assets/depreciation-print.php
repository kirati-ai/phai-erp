<?php use Phai\Core\View\View; ?>
<?= View::partial('doc/print-style') ?>
<?= View::partial('doc/print-toolbar') ?>
<div class="doc"><?= View::partial('doc/report-head', ['title' => 'รายงานค่าเสื่อมราคา', 'meta' => [['เดือน', $month]]]) ?>
<p>รายการค่าเสื่อมที่ลงทะเบียนสำหรับเดือนนี้ รวมเฉพาะรายการที่ยังไม่กลับรายการ</p>
<table class="doc-lines"><thead><tr><th>รหัส / ชื่อ</th><th>หมวด</th><th>เลขรายการ</th><th class="r">ค่าเสื่อม</th><th>สถานะ</th></tr></thead><tbody>
<?php $total = 0; foreach ($rows as $r): if (!$r['fat_reversed']) $total += $r['fat_amount']; ?>
<tr><td><?= e($r['fat_asset']) ?><br><?= e($r['fa_desc']) ?></td><td><?= e($r['fa_category']) ?></td><td>FA-<?= e($r['fat_id']) ?></td><td class="r"><?= number_format((float) $r['fat_amount'], 2) ?></td><td><?= $r['fat_reversed'] ? 'กลับรายการแล้ว' : 'ลงบัญชีแล้ว' ?></td></tr>
<?php endforeach ?></tbody><tfoot><tr><td colspan="3">รวมรายการที่ยังมีผล</td><td class="r"><?= number_format($total, 2) ?></td><td></td></tr></tfoot></table>
<p class="text-secondary">การกลับรายการที่ลงบัญชีคนละเดือน ให้ตรวจวันที่และยอดในสมุดรายวันประกอบรายงานนี้</p>
</div>
