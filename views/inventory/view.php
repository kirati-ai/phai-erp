<?php
/**
 * One stock line (ld_det row, opened by ld_id): where it is, how much, its value, the movements of that
 * warehouse, location and lot — and what can be done with it: convert to FG (raw material), transfer, edit the lot.
 * @var array $row    StockController::row() + isRm
 * @var array $types  tr_type => text
 * @var array $sites  [{si_site, si_desc, si_default_loc}]
 */
use Phai\Core\Auth\Auth;

$canHistory = Auth::can('stock-history', 'view');
$canAdd = Auth::can('stock-moves', 'add');
$canEdit = Auth::can('stock-moves', 'edit');
$hasLot = $row['lotted']; // lot-controlled item: supplier lot and lot remark too
$qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 5), '0'), '.');
$today = date('Y-m-d');
$cfg = [
    'id'      => (int) $row['ld_id'],
    'row'     => $row,
    'sites'   => $sites,
    'filter'  => ['part' => $row['ld_part'], 'site' => $row['ld_site'], 'loc' => $row['ld_loc'], 'lot' => $row['ld_lot']],
    'types'   => $types,
    'history' => $canHistory,
    'text'    => ['posted' => t('stock.posted', ['nbr' => ':nbr']), 'saved' => t('js.saved')],
];
$fields = \Phai\Modules\Inventory\StockController::fields($row);
$statusColor = \Phai\Modules\Inventory\StockController::statusColor($row['ld_status']);
$siteOptions = function () use ($sites): void {
    foreach ($sites as $s) echo '<option value="' . e($s['si_site']) . '">' . e($s['si_site'] . ' · ' . $s['si_desc']) . '</option>';
};
$modal = function (string $id, string $title, callable $body): void { ?>
    <div class="modal" id="<?= e($id) ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" novalidate autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title"><?= e($title) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><div class="row g-2"><?php $body() ?></div></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
                </div>
            </form>
        </div>
    </div>
<?php };
$n = 0; // field ids are unique across the modals
$input = function (string $name, string $label, string $attrs = '', string $col = 'col-6', bool $required = true) use (&$n): void {
    $id = 'f' . ++$n; ?>
    <div class="<?= e($col) ?>">
        <label class="form-label<?= $required ? ' required' : '' ?>" for="<?= $id ?>"><?= e($label) ?></label>
        <input class="form-control form-control-sm" id="<?= $id ?>" name="<?= e($name) ?>" <?= $attrs ?>>
    </div>
<?php };
?>
<div class="phai-toolbar">
    <a class="btn btn-sm" href="<?= e(url('/stock?type=' . rawurlencode($row['type']))) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
    <span class="badge bg-<?= $statusColor ?>-lt"><?= e(t('stock.ld_status_' . ($row['ld_status'] ?: 'ACTIVE'))) ?></span>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <?php if ($canAdd && $row['isRm']): ?>
            <button type="button" class="btn btn-sm btn-ghost-primary" data-open="convert-modal"><i class="ti ti-transform me-1"></i><?= e(t('stock.convert')) ?></button>
        <?php endif ?>
        <?php if ($canAdd): ?>
            <button type="button" class="btn btn-sm btn-ghost-primary" data-open="transfer-modal"><i class="ti ti-transfer me-1"></i><?= e(t('stock.transfer')) ?></button>
        <?php endif ?>
        <?php if ($canEdit): ?>
            <button type="button" class="btn btn-sm btn-primary" data-open="edit-modal"><i class="ti ti-pencil me-1"></i><?= e(t('common.edit')) ?></button>
        <?php endif ?>
    </div>
</div>

<?php if ($canHistory): ?>
    <ul class="nav nav-tabs phai-tabs mb-2" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-detail" role="tab"><?= e(t('stock.detail')) ?></a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-moves" role="tab"><?= e(t('stock.history_title')) ?></a></li>
    </ul>
<?php endif ?>
<div class="tab-content">
<div class="tab-pane active show" id="tab-detail" role="tabpanel">
<div class="card phai-view-mode">
    <div class="card-body pb-3">
        <div class="row g-2">
            <?php foreach ($fields as $f): [$label, $value, $col] = $f; $isNum = $f[3] ?? false; ?>
                <div class="<?= e($col) ?>">
                    <label class="form-label"><?= e($label) ?></label>
                    <?php if ($value === '' || $value === null): ?>
                        <div class="phai-view-value is-empty">–</div>
                    <?php else: ?>
                        <div class="phai-view-value<?= $isNum ? ' text-end' : '' ?>"<?= $isNum ? ' style="font-variant-numeric: tabular-nums"' : '' ?>><?= e($value) ?></div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>
</div>

<?php if ($canHistory): ?>
    <div class="tab-pane" id="tab-moves" role="tabpanel">
        <?= \Phai\Core\View\View::partial('inventory/history-table') ?>
    </div>
<?php endif ?>
</div>

<?php if ($canAdd && $row['isRm']) $modal('convert-modal', t('stock.convert'), function () use ($input, $today, $row) {
    $input('date', t('stock.date'), 'type="date" value="' . e($today) . '" max="' . e($today) . '"');
    $input('lines.0.qty', t('stock.qty') . ' (' . $row['pt_um'] . ')', 'inputmode="decimal"');
    $input('lines.0.fg_part', t('stock.fg_part'), 'data-pick="parts" data-sale', 'col-12');
    $input('lines.0.fg_qty', t('stock.fg_qty'), 'inputmode="decimal"');
    ?>
    <div class="col-6">
        <label class="form-label required" for="convert-loc"><?= e(t('stock.to_loc')) ?></label>
        <select class="form-select form-select-sm" id="convert-loc" name="lines.0.to_loc"></select>
    </div>
    <?php $input('remark', t('stock.remark'), 'maxlength="255"', 'col-12', false);
}) ?>

<?php if ($canAdd) $modal('transfer-modal', t('stock.transfer'), function () use ($input, $today, $row, $siteOptions) {
    $input('date', t('stock.date'), 'type="date" value="' . e($today) . '" max="' . e($today) . '"');
    $input('qty', t('stock.qty') . ' (' . $row['pt_um'] . ')', 'inputmode="decimal"');
    ?>
    <div class="col-6">
        <label class="form-label required" for="to_site"><?= e(t('stock.to_site')) ?></label>
        <select class="form-select form-select-sm" id="to_site" name="to_site"><?php $siteOptions() ?></select>
    </div>
    <div class="col-6">
        <label class="form-label required" for="to_loc"><?= e(t('stock.to_loc')) ?></label>
        <select class="form-select form-select-sm" id="to_loc" name="to_loc"></select>
    </div>
    <div class="col-12 small text-secondary"><?= e(t('stock.value')) ?>: <span class="fw-bold text-body" data-value style="font-variant-numeric: tabular-nums">0.00</span></div>
    <?php $input('remark', t('stock.remark'), 'maxlength="255"', 'col-12', false);
}) ?>

<?php if ($canEdit) $modal('edit-modal', t('common.edit'), function () use ($input, $row, $hasLot, $qty) {
    $input('lot', t('stock.lot'), 'maxlength="50" value="' . e($row['ld_lot']) . '"', 'col-6', $row['ld_lot'] !== '');
    if (!$hasLot) { ?>
        <div class="col-6 d-flex align-items-end small text-warning"><?= e(t('stock.lot_turns_on')) ?></div>
    <?php }
    if ($hasLot) {
        $input('vend_lot', t('stock.vend_lot'), 'maxlength="50" value="' . e((string) $row['lot_vend_lot']) . '"', 'col-6', false);
    } ?>
    <div class="col-6">
        <label class="form-label required" for="edit-status"><?= e(t('stock.ld_status')) ?></label>
        <select class="form-select form-select-sm" id="edit-status" name="status">
            <?php foreach (\Phai\Modules\Inventory\Stock::LD_STATUSES as $st): ?>
                <option value="<?= e($st) ?>"<?= ($row['ld_status'] ?: 'ACTIVE') === $st ? ' selected' : '' ?>><?= e(t('stock.ld_status_' . $st)) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <?php
    $input('ref', t('stock.ref'), 'maxlength="50" value="' . e((string) $row['ld_ref']) . '"', 'col-6', false);
    $input('pack_qty', t('stock.pack_qty') . ' (' . $row['pt_um'] . ')', 'inputmode="decimal" value="' . e($qty($row['ld_pack_qty'])) . '"', 'col-6', false);
    $input('grade', t('stock.grade'), 'maxlength="20" value="' . e((string) $row['ld_grade']) . '"', 'col-6', false);
    $input('mfg_date', t('stock.mfg_date'), 'type="date" value="' . e((string) $row['ld_mfg_date']) . '"', 'col-6', false);
    $input('expire', t('stock.expire'), 'type="date" value="' . e((string) $row['ld_expire']) . '"', 'col-6', false);
    if ($hasLot) { ?>
        <div class="col-12">
            <label class="form-label" for="edit-rmks"><?= e(t('stock.lot_rmks')) ?></label>
            <textarea class="form-control form-control-sm" id="edit-rmks" name="lot_rmks" rows="2" maxlength="1000"><?= e((string) $row['lot_rmks']) ?></textarea>
        </div>
    <?php }
}) ?>

<script id="view-config" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
