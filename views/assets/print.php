<?php
use Phai\Core\View\View;
use Phai\Modules\Accounting\FixedAssetController;
$money = fn ($v) => number_format((float) $v, 2);
$day = fn ($v) => $v ? implode('/', array_reverse(explode('-', $v))) : '–';
?>
<?= View::partial('doc/print-style') ?>
<?= View::partial('doc/print-toolbar') ?>
<style>.asset-info{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px 20px;margin:14px 0}.asset-info dt{font-weight:400;color: #000;font-size:11px}.asset-info dd{margin:3px 0 0;overflow-wrap:anywhere}.asset-values{margin:14px 0;border:1px solid #dfe4ea;padding:12px;border-radius:6px}.asset-values .asset-info{margin:0}</style>
<div class="doc">
<?= View::partial('doc/report-head', ['title' => 'ทะเบียนสินทรัพย์ถาวร', 'meta' => [['รหัส', $asset['fa_code']]]]) ?>
<div class="asset-values"><?= e($asset['fa_desc']) ?><div class="asset-info">
<?php foreach (['หมวด' => $asset['fa_category'], 'เลขเครื่อง/ทะเบียน' => $asset['fa_serial'] ?: '–', 'สถานะ' => FixedAssetController::statuses()[$asset['fa_status']]['text']] as $label => $value): ?><dl><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></dl><?php endforeach ?>
</div></div>
<div class="asset-info">
<?php foreach (['วันที่ได้มา' => $day($asset['fa_acquired']), 'วันที่เริ่มใช้' => $day($asset['fa_start']), 'วิธีค่าเสื่อม' => t('fields.fa_method.' . $asset['fa_method']), 'สาขา / สถานที่' => $asset['fa_site'] . ' / ' . ($asset['fa_location'] ?: '–'), 'ศูนย์ต้นทุน' => $asset['fa_cc'] ?: '–', 'ผู้รับผิดชอบ' => $asset['fa_custodian'] ?: '–', 'เอกสารซื้ออ้างอิง' => $asset['fa_source_ref'] ?: '–', 'อายุใช้งาน (เดือน)' => $asset['fa_life_months'], 'อัตรายอดลดลงต่อปี (%)' => $asset['fa_rate'], 'ราคาทุน' => $money($asset['fa_cost']), 'มูลค่าซาก' => $money($asset['fa_salvage']), 'ค่าเสื่อมสะสม' => $money($asset['fa_accum']), 'ลงค่าเสื่อมล่าสุดถึงเดือน' => $asset['fa_last_month'] ?: '–', 'มูลค่าตามบัญชีปัจจุบัน' => $money($asset['fa_status'] === 'DISPOSED' ? 0 : $asset['fa_cost'] - $asset['fa_accum']), 'วันที่จำหน่าย' => $day($asset['fa_disposed'])] as $label => $value): ?><dl><dt><?= e($label) ?></dt><dd><?= e((string) $value) ?></dd></dl><?php endforeach ?>
</div>
<?php if ($asset['fa_notes']): ?><p>หมายเหตุ: <?= e($asset['fa_notes']) ?></p><?php endif ?>
<table class="doc-lines"><thead><tr><th>วันที่</th><th>เลขรายการ</th><th>ประเภท</th><th class="r">จำนวนเงิน</th><th>หมายเหตุ / สถานะ</th></tr></thead><tbody>
<?php foreach (array_reverse($history) as $h): ?><tr><td><?= e($day($h['fat_date'])) ?></td><td>FA-<?= e($h['fat_id']) ?></td><td><?= e(t('fields.fat_type.' . $h['fat_type'])) ?></td><td class="r"><?= $money($h['fat_amount']) ?></td><td><?= e($h['fat_note'] ?? '') ?><?= $h['fat_reversed'] ? ' (กลับรายการแล้ว)' : '' ?></td></tr><?php endforeach ?>
</tbody></table>
</div>
