<?php
/**
 * ตัวแก้ซองเอกสาร (EnvelopeController::editor), laid out like a PDF editor: the envelope's own bar (name, zoom,
 * undo, send), a toolbar (documents, add signer, signers, message, fields), a format bar for what is selected, and
 * the pages across the whole width. Documents, signers, the message and a new signer open in dialogs.
 * js/pages/envelope-edit.js
 */
echo \Phai\Core\View\View::partial('esign/_head', ['esignText' => $esignText, 'payload' => $payload]);
// icon-only buttons say what they are when pointed at, and to screen readers
$tip = fn (string $label) => ' title="' . e($label) . '" aria-label="' . e($label) . '"';
?>
<div class="es-top">
    <div class="es-bar">
        <input type="text" class="form-control form-control-sm" id="es-title" maxlength="255" aria-label="<?= e(t('esign.col_title')) ?>">
        <span class="badge bg-secondary-lt" id="es-nbr"></span>
        <span class="small text-secondary" id="es-saved"></span>
        <span class="es-grow"></span>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn" data-zoom="-1" title="<?= e(t('esign.zoom_out')) ?>"><i class="ti ti-zoom-out"></i></button>
            <button type="button" class="btn" data-zoom="0" title="<?= e(t('esign.zoom_fit')) ?>"><i class="ti ti-arrows-horizontal"></i></button>
            <button type="button" class="btn" data-zoom="1" title="<?= e(t('esign.zoom_in')) ?>"><i class="ti ti-zoom-in"></i></button>
        </div>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn" id="es-undo" title="<?= e(t('esign.undo')) ?> (Ctrl+Z)"><i class="ti ti-arrow-back-up"></i></button>
            <button type="button" class="btn" id="es-redo" title="<?= e(t('esign.redo')) ?> (Ctrl+Y)"><i class="ti ti-arrow-forward-up"></i></button>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" id="es-delete" data-cancel<?= $tip(t('esign.delete_draft')) ?>><i class="ti ti-trash"></i></button>
        <button type="button" class="btn btn-sm" id="es-save"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
        <button type="button" class="btn btn-sm btn-primary" id="es-send"><i class="ti ti-send me-1"></i><?= e(t('esign.send')) ?></button>
    </div>

    <div class="es-toolbar">
        <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#es-files-modal"<?= $tip(t('esign.files')) ?>><i class="ti ti-files"></i><span class="badge bg-secondary-lt ms-1" id="es-files-n"></span></button>
        <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#es-signers-modal"<?= $tip(t('esign.signers')) ?>><i class="ti ti-users"></i><span class="badge bg-secondary-lt ms-1" id="es-signers-n"></span></button>
        <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#es-note-modal"<?= $tip(t('esign.note')) ?>><i class="ti ti-message"></i><i class="ti ti-point-filled text-primary" id="es-note-dot" hidden></i></button>
        <span class="es-sep"></span>
        <button type="button" class="btn btn-sm btn-primary" id="es-add-sign"><i class="ti ti-signature me-1"></i><?= e(t('esign.add_signer')) ?></button>
        <span class="es-sep"></span>
        <span class="es-dot" id="es-active-dot"></span>
        <select class="form-select form-select-sm w-auto" id="es-active"<?= $tip(t('esign.tools_signer')) ?>></select>
        <div class="es-tools d-flex gap-1" id="es-tools-signer"></div>
        <div class="dropdown es-tools">
            <button type="button" class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="es-add-text"<?= $tip(t('esign.text_boxes')) ?>><i class="ti ti-forms"></i></button>
            <div class="dropdown-menu" id="es-tools-text"></div>
        </div>
        <span class="es-sep"></span>
        <div class="es-tools d-flex gap-1" id="es-tools-sender"></div>
        <span class="es-grow"></span>
        <div class="dropdown">
            <button type="button" class="btn btn-sm" data-bs-toggle="dropdown" data-bs-auto-close="outside"<?= $tip(t('esign.view_as')) ?>><i class="ti ti-adjustments-horizontal"></i></button>
            <div class="dropdown-menu dropdown-menu-end p-2 es-menu">
                <div class="form-check form-switch mb-2 small">
                    <input class="form-check-input" type="checkbox" id="es-snap" checked><label class="form-check-label" for="es-snap"><?= e(t('esign.snap')) ?></label>
                </div>
                <label class="form-label small mb-1" for="es-view"><?= e(t('esign.view_as')) ?></label>
                <select class="form-select form-select-sm" id="es-view"></select>
            </div>
        </div>
        <div class="dropdown">
            <button type="button" class="btn btn-sm" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="es-check-btn"<?= $tip(t('esign.check')) ?>><i class="ti ti-list-check"></i><span class="badge ms-1" id="es-check-n"></span></button>
            <div class="dropdown-menu dropdown-menu-end p-2 es-menu"><ul class="es-problems ps-3 mb-0" id="es-problems"></ul></div>
        </div>
    </div>

    <div class="es-format" id="es-props"></div>
</div>

<div class="es-main">
    <div class="es-pages" id="es-pages"><div class="text-secondary small p-4" id="es-empty"><?= e(t('esign.empty_pages')) ?></div></div>
</div>

<div class="modal fade" id="es-files-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><?= e(t('esign.files')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div id="es-files"></div>
            <div class="d-flex gap-1 mt-2">
                <label class="btn btn-sm flex-fill"><i class="ti ti-file-upload me-1"></i><?= e(t('esign.add_main')) ?><input type="file" hidden multiple accept="application/pdf,image/png,image/jpeg,image/webp" data-kind="M"></label>
                <label class="btn btn-sm flex-fill"><i class="ti ti-paperclip me-1"></i><?= e(t('esign.add_attach')) ?><input type="file" hidden multiple accept="application/pdf,image/png,image/jpeg,image/webp" data-kind="A"></label>
            </div>
        </div>
    </div></div>
</div>

<div class="modal fade" id="es-signers-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><?= e(t('esign.signers')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div id="es-signers"></div></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-primary" data-add-sign><i class="ti ti-user-plus me-1"></i><?= e(t('esign.add_signer')) ?></button>
        </div>
    </div></div>
</div>

<div class="modal fade" id="es-note-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><?= e(t('esign.note')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><textarea class="form-control" id="es-note" rows="5" maxlength="2000"></textarea></div>
    </div></div>
</div>

<div class="modal fade" id="es-add-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" novalidate autocomplete="off" id="es-add-form" data-no-cancel>
        <div class="modal-header"><h5 class="modal-title"><i class="ti ti-signature me-1"></i><?= e(t('esign.add_signer')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="btn-group w-100 mb-3" role="group">
                <input type="radio" class="btn-check" name="es-add-kind" id="es-add-u" value="U" checked>
                <label class="btn btn-sm" for="es-add-u"><i class="ti ti-building me-1"></i><?= e(t('esign.who_inside')) ?></label>
                <input type="radio" class="btn-check" name="es-add-kind" id="es-add-e" value="E">
                <label class="btn btn-sm" for="es-add-e"><i class="ti ti-mail me-1"></i><?= e(t('esign.who_outside')) ?></label>
            </div>
            <div class="row g-2">
                <div class="col-12" data-kind-only="U">
                    <label class="form-label" for="es-add-user"><?= e(t('esign.signer')) ?></label>
                    <select class="form-select form-select-sm" id="es-add-user"></select>
                </div>
                <div class="col-md-6" data-kind-only="E" hidden>
                    <label class="form-label" for="es-add-name"><?= e(t('esign.name')) ?></label>
                    <input class="form-control form-control-sm" id="es-add-name" maxlength="150">
                </div>
                <div class="col-md-6" data-kind-only="E" hidden>
                    <label class="form-label" for="es-add-email"><?= e(t('esign.email')) ?></label>
                    <input class="form-control form-control-sm" id="es-add-email" type="email" maxlength="150">
                </div>
                <div class="col-12 small text-warning" data-kind-only="E" hidden><?= e(t('esign.outside_hint')) ?></div>
                <div class="col-md-6">
                    <label class="form-label" for="es-add-position"><?= e(t('esign.position')) ?></label>
                    <input class="form-control form-control-sm" id="es-add-position" maxlength="100">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="es-add-role"><?= e(t('esign.role')) ?></label>
                    <select class="form-select form-select-sm" id="es-add-role">
                        <?php foreach (['S', 'A', 'C'] as $r): ?><option value="<?= $r ?>"><?= e(t('esign.role_' . $r)) ?></option><?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="es-add-step"><?= e(t('esign.step')) ?></label>
                    <select class="form-select form-select-sm" id="es-add-step"></select>
                </div>
            </div>
            <div class="mt-3" id="es-add-fields-box">
                <label class="form-label"><?= e(t('esign.put_fields')) ?></label>
                <div class="es-checks" id="es-add-fields"></div>
                <div class="row g-2 mt-1 align-items-center">
                    <div class="col-auto"><label class="form-label mb-0" for="es-add-page"><?= e(t('esign.put_page')) ?></label></div>
                    <div class="col"><select class="form-select form-select-sm" id="es-add-page"></select></div>
                </div>
            </div>
            <div class="small text-secondary mt-3" id="es-add-cc" hidden><?= e(t('esign.cc_no_fields')) ?></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-sm" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
            <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-check me-1"></i><?= e(t('esign.create_place')) ?></button>
        </div>
    </form></div>
</div>
