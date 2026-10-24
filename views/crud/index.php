<?php
/**
 * Standard screen of a Resource: list + modal form, or just a form for one-row tables.
 * @var class-string<\Phai\Core\Crud\Resource> $r
 * @var array $fields
 * @var array $can     add / edit / delete
 */

use Phai\Core\View\View;

$single = $r::isSingle();
$byName = array_column($r::gridFields(), null, 'name');

// Fields grouped by section; fields without a section go to the first one.
$sections = [];
foreach ($fields as $f) {
    $sections[$f['section'] ?? ''][] = $f;
}
if (isset($sections['']) && count($sections) > 1) {
    $first = array_key_first(array_diff_key($sections, ['' => 1]));
    $sections[$first] = [...$sections[''], ...$sections[$first]];
    unset($sections['']);
}
$tabs = count($sections) > 1;
$large = count($fields) > 8;

$columns = [];
foreach (array_unique([...$r::listColumns(), ...array_keys($byName)]) as $name) {
    $extra = $r::extraColumns()[$name] ?? null;
    $f = $byName[$name] ?? ['name' => $name, 'label' => $extra ? t('fields.' . $name) : $name, 'type' => $extra ?? 'text', 'scale' => 2, 'options' => [], 'lookup' => null];
    $columns[] = [
        'key'     => $name,
        'extra'   => $extra !== null, // worked out by the resource: not sortable
        'hidden'  => !in_array($name, $r::listColumns(), true),
        'label'   => $f['label'],
        'type'    => $f['type'],
        'scale'   => $f['scale'],
        // fixed options show their text; lookups show the code
        'options' => $f['lookup'] === null ? array_column($f['options'], 'label', 'value') : (object) [],
    ];
}

$config = [
    'api'    => '/api' . $r::path(),
    'key'    => $r::keyColumns(),
    'single' => $single,
    'can'    => $can,
    'print'  => $print ?? null,
    'files'  => $single ? null : $r::filesDoc(),
    'badge'  => $r::badge(),
    'columns' => $columns,
    'fields' => array_map(fn ($f) => [
        'name' => $f['name'], 'type' => $f['type'], 'key' => $f['key'], 'geo' => $f['geo'],
        'label' => $f['label'], 'required' => $f['required'], 'readonly' => $f['readonly'], 'scale' => $f['scale'],
        'options' => $f['type'] === 'select' ? $f['options'] : [],
    ], $fields),
];

ob_start(); ?>
<?php if ($tabs): ?>
    <ul class="nav nav-tabs nav-tabs-sm phai-tabs mb-3" role="tablist">
        <?php $i = 0; foreach ($sections as $name => $_): ?>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link py-1<?= $i++ === 0 ? ' active' : '' ?>" data-bs-toggle="tab"
                        data-bs-target="#tab-<?= e($name) ?>" role="tab"><?= e(t('fields.section_' . $name)) ?></button>
            </li>
        <?php endforeach ?>
    </ul>
    <div class="tab-content">
        <?php $i = 0; foreach ($sections as $name => $list): ?>
            <div class="tab-pane<?= $i++ === 0 ? ' active show' : '' ?>" id="tab-<?= e($name) ?>" role="tabpanel">
                <div class="row g-2">
                    <?php foreach ($list as $f) echo View::partial('crud/field', ['f' => $f]); ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php else: ?>
    <div class="row g-2">
        <?php foreach ($fields as $f) echo View::partial('crud/field', ['f' => $f]); ?>
    </div>
<?php endif ?>
<?php $formBody = ob_get_clean(); ?>

<?php // one width for every card of a one-row page (its aside above, the form, the numbering below), so they line up
$numbering = isset(\Phai\Modules\System\NumberingController::MODULES[$r::CODE]);
$width = $large ? '56rem' : ($numbering ? '50rem' : '40rem'); ?>
<?php if ($single && $r::aside()[0]): ?>
    <?= View::partial($r::aside()[0], ['can' => $can, 'width' => $width]) ?>
<?php endif ?>
<?php if ($single): ?>
    <form class="card" id="crud-form" novalidate autocomplete="off" style="max-width: <?= $width ?>">
        <fieldset class="card-body"<?= $can['edit'] ? '' : ' disabled' ?>>
            <?= $formBody ?>
        </fieldset>
        <?php if ($can['edit']): ?>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
            </div>
        <?php endif ?>
    </form>
    <?php // a module's settings page: its documents' numbering below
    if ($numbering) echo \Phai\Modules\System\NumberingController::table($r::CODE, $width); ?>
<?php else: ?>
    <div class="phai-toolbar">
        <div class="input-icon phai-search">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="search" id="q" class="form-control form-control-sm" placeholder="<?= e(t('common.search')) ?>" data-search>
        </div>
        <?php foreach ($r::filters() as $filter):
            $chosen = (string) (\Phai\Core\Http\Request::current()?->query[$filter['name']] ?? ''); ?>
            <select class="form-select form-select-sm w-auto" data-filter="<?= e($filter['name']) ?>">
                <option value=""><?= e($filter['all']) ?></option>
                <?php foreach ($filter['options'] as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= (string) $value === $chosen ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach ?>
            </select>
        <?php endforeach ?>
        <?php if ($can['add']): ?>
            <button type="button" class="btn btn-sm btn-primary ms-auto" id="add">
                <i class="ti ti-plus me-1"></i><?= e(t('common.add')) ?>
            </button>
        <?php endif ?>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-vcenter table-hover card-table" id="crud-table">
                <thead>
                <tr>
                    <?php foreach ($columns as $c): ?>
                        <th<?= $c['extra'] ? '' : ' data-sort="' . e($c['key']) . '"' ?><?= in_array($c['type'], ['int', 'number'], true) ? ' class="text-end"' : '' ?>><?= e($c['label']) ?></th>
                    <?php endforeach ?>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal" id="crud-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable<?= $large ? ' modal-lg' : '' ?>">
            <form class="modal-content" id="crud-form" novalidate autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title me-2"><?= e($title) ?></h5>
                    <span class="badge" id="crud-badge" hidden></span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <fieldset class="modal-body">
                    <?= $formBody ?>
                    <?php if ($r::filesDoc()): /* the files of the record on view (crud.js sets data-nbr) */ ?>
                        <div class="mt-3"><?= View::partial('partials/files', ['doc' => $r::filesDoc(), 'nbr' => '']) ?></div>
                    <?php endif ?>
                </fieldset>
                <div class="modal-footer">
                    <?php if ($can['delete']): ?>
                        <button type="button" class="btn btn-sm btn-ghost-danger me-auto" id="del"><i class="ti ti-trash me-1"></i><?= e(t('common.delete')) ?></button>
                    <?php endif ?>
                    <button type="button" class="btn btn-sm" id="crud-back" data-bs-dismiss="modal"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></button>
                    <button type="submit" class="btn btn-sm btn-primary" id="save"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php if ($can['add'] || $can['edit']): ?>
        <div class="modal" id="import-modal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" id="import-form" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title"><?= e(t('js.import_excel')) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="phai-drop" data-drop>
                            <input type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" hidden>
                            <i class="ti ti-file-spreadsheet"></i>
                            <span data-drop-name data-empty="<?= e(t('js.import_pick')) ?>"><?= e(t('js.import_pick')) ?></span>
                        </label>
                        <ul class="phai-import-errors list-unstyled small text-danger mt-2 mb-0" hidden></ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-ghost-secondary me-auto" id="import-template"><i class="ti ti-download me-1"></i><?= e(t('js.import_template')) ?></button>
                        <button type="button" class="btn btn-sm" data-bs-dismiss="modal"><?= e(t('js.cancel')) ?></button>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-file-download me-1"></i><?= e(t('js.import')) ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif ?>
<?php endif ?>

<script id="crud-config" type="application/json"><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
