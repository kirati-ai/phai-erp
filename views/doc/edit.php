<?php
/**
 * Document editor: header fields + lines + totals + status actions. Driven by public/js/doc.js.
 * @var array $config  see PoController::editor() for the shape
 */
$field = function (array $f): void {
    $name = e($f['name']);
    $req = !empty($f['required']);
    $isDocNbr = ($name === 'doc_nbr');
    $labelExtra = '';
    if ($isDocNbr && empty($f['readonly'])) {
        $labelExtra = ' <span class="text-secondary fw-normal small">(' . e(t('common.editable_hint')) . ')</span>';
    }
    // one narrower than asked: more boxes a row, the row still fills up; short fields sit two a row on a phone
    echo '<div class="' . ((int) ($f['col'] ?? 3) <= 3 ? 'col-6 ' : '') . 'col-sm-6 col-lg-' . max(1, (int) ($f['col'] ?? 3) - 1) . '">';
    echo '<label class="form-label' . ($req ? ' required' : '') . '" for="h-' . $name . '">' . e($f['label']) . $labelExtra . '</label>';
    switch ($f['type']) {
        case 'select':
            echo '<select class="form-select form-select-sm' . (!empty($f['readonly']) ? ' phai-native" data-locked' : '"') . ' id="h-' . $name . '" name="' . $name . '">';
            if (!$req) {
                echo '<option value=""></option>';
            }
            foreach ($f['options'] as $o) {
                $pct = isset($o['pct']) ? ' data-pct="' . e($o['pct']) . '"' : '';
                echo '<option value="' . e($o['value']) . '"' . $pct . '>' . e($o['label']) . '</option>';
            }
            echo '</select>';
            break;
        case 'person':
            // a name from the employees, or typed (data-free)
            echo '<input type="text" class="form-control form-control-sm" id="h-' . $name . '" name="' . $name . '" autocomplete="off" data-pick="people" data-free>';
            break;
        case 'vendor':
        case 'customer':
            echo '<input type="text" class="form-control form-control-sm text-uppercase" id="h-' . $name . '" name="' . $name . '" autocomplete="off" data-pick="' . $f['type'] . 's" data-party="' . $f['type'] . '">';
            break;
        default:
            $type = $f['type'] === 'date' ? 'date' : 'text';
            $cls = ($f['type'] === 'number' ? ' text-end' : '') . (!empty($f['upper']) ? ' text-uppercase' : '');
            if ($isDocNbr) {
                $cls .= ' phai-doc-nbr-input';
            }
            $extra = '';
            if ($isDocNbr && empty($f['readonly'])) {
                $hint = e(t('common.editable_hint'));
                $extra = ' title="' . $hint . '" placeholder="' . $hint . '"';
            }
            echo '<input type="' . $type . '" class="form-control form-control-sm' . $cls . '" id="h-' . $name . '" name="' . $name . '" autocomplete="off"'
                . ($f['type'] === 'number' ? ' inputmode="decimal"' : '') . (!empty($f['readonly']) ? ' readonly data-locked' : '') . $extra . '>';
    }
    echo '</div>';
};
// The document number comes first: on a new document the next number, which may be changed
// to one of the user's own (Numbering::next takes it when saved); on a saved one it cannot change.
if (!empty($config['numbering'])) {
    $codes = explode(',', $config['numbering']);
    array_unshift($config['head'], [
        'name' => 'doc_nbr', 'label' => t('common.doc_nbr'), 'type' => 'text', 'col' => 3, 'upper' => true,
        'default' => $config['nbr'] ?? \Phai\Core\Numbering::peek($codes[0]), 'readonly' => !empty($config['nbr']),
    ]);
}
?>
<form id="doc" novalidate autocomplete="off">
    <div class="phai-toolbar phai-sticky">
        <span class="badge d-none" id="doc-status"></span>
        <div class="ms-auto d-flex flex-wrap gap-2" id="doc-actions"></div>
        <button type="submit" class="btn btn-sm btn-primary" id="doc-save"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
    </div>

    <?php $history = $config['history'] ?? []; ?>
    <div class="<?= $history ? 'row g-3' : '' ?>">
    <div class="<?= $history ? 'col-xl-9' : '' ?>">
    <div class="card mb-3">
        <div class="card-body pb-3">
            <div class="row g-2">
                <?php foreach ($config['head'] as $f) $field($f); ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="doc-lines">
                <thead>
                <tr>
                    <th class="w-1">#</th>
                    <?php foreach ($config['lines']['columns'] as $c): ?>
                        <th<?= in_array($c['type'], ['number', 'amount'], true) || !empty($c['number']) ? ' class="text-end"' : '' ?>
                            <?= !empty($c['existing']) ? ' data-existing' : '' ?>
                            <?= isset($c['width']) ? ' style="width: ' . e($c['width']) . '"' : ' style="min-width: 12rem"' ?>><?= e($c['label']) ?></th>
                    <?php endforeach ?>
                    <th class="w-1"></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="card-footer">
            <button type="button" class="btn btn-sm btn-ghost-primary" id="doc-add-line"><i class="ti ti-plus me-1"></i><?= e(t('purchase.add_line')) ?></button>
        </div>
    </div>
    <?php if (!empty($config['totals'])): /* the summary box sits on its own, below the lines */ ?>
        <div class="d-flex justify-content-end mt-3">
            <table class="phai-summary" id="doc-totals">
                <?php if ($config['totals'] === true): ?>
                    <tr><td><?= e(t('purchase.subtotal')) ?></td><td class="num" data-total="net"></td></tr>
                    <?php if (!empty($config['deduct'])): ?>
                        <tr data-deduct-row hidden><td><?= e($config['deductLabel'] ?? '') ?></td><td class="num" data-total="deduct"></td></tr>
                        <tr data-deduct-row hidden><td><?= e($config['afterLabel'] ?? '') ?></td><td class="num" data-total="after"></td></tr>
                    <?php endif ?>
                    <tr><td><?= e(t('purchase.vat')) ?> <span data-total="pct"></span></td><td class="num" data-total="tax"></td></tr>
                <?php endif ?>
                <tr class="doc-grand phai-summary-grand"><td><?= e(t('purchase.total')) ?></td><td class="num" data-total="total"></td></tr>
            </table>
        </div>
    <?php endif ?>
    <?php if (!empty($config['files'])): /* the files of a saved document: config.files = AttachmentController::DOCS code */ ?>
        <div class="mt-3"><?= \Phai\Core\View\View::partial('partials/files', ['doc' => $config['files'], 'nbr' => $config['nbr'] ?? '']) ?></div>
    <?php endif ?>
    </div>
    <?php if ($history): ?>
        <div class="col-xl-3">
            <div class="card">
                <div class="card-header py-2"><h3 class="card-title fs-5"><?= e($history['title']) ?></h3></div>
                <div class="list-group list-group-flush">
                    <?php foreach ($history['items'] as $h): ?>
                        <a class="list-group-item list-group-item-action py-2" href="<?= e(url($h['link'])) ?>">
                            <div class="d-flex"><strong><?= e($h['nbr']) ?></strong><span class="ms-auto text-secondary"><?= e($h['date']) ?></span></div>
                            <?php foreach ($h['lines'] as $l): ?>
                                <div class="d-flex small"><span class="text-truncate me-2"><?= e($l[0]) ?></span><span class="ms-auto num text-nowrap"><?= e($l[1]) ?></span></div>
                            <?php endforeach ?>
                            <?php if ($h['by'] !== ''): ?><div class="small text-secondary"><?= e($h['by']) ?></div><?php endif ?>
                        </a>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    <?php endif ?>
    </div>
</form>

<div class="modal" id="doc-vendor" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form class="modal-content" novalidate autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title" data-title></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label"><?= e(t('purchase.vendor_for_rest')) ?></label>
                <input type="text" class="form-control form-control-sm text-uppercase" name="vendor" autocomplete="off" data-pick="vendors">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
                <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-check me-1"></i><?= e(t('common.confirm')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php $config['uoms'] = array_map(fn ($o) => ['value' => $o['value'], 'label' => trim((string) preg_replace('/^[^·]*·/u', '', $o['label']))], \Phai\Core\Crud\Lookup::options('uoms')); ?>
<script id="doc-config" type="application/json"><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script id="doc-text" type="application/json"><?= json_encode([
    'saved'      => t('purchase.saved_nbr', ['nbr' => ':nbr']),
    'done'       => t('purchase.done'),
    'save_first' => t('js.save_first'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
