<?php
$actions = \Phai\Core\Auth\Auth::ACTIONS;
?>
<div class="row g-3">
    <div class="col-md-4 col-xl-3">
        <div class="phai-toolbar phai-pane-bar">
            <button type="button" class="btn btn-sm btn-primary ms-auto" id="add">
                <i class="ti ti-plus me-1"></i><?= e(t('common.add')) ?>
            </button>
        </div>
        <div class="card">
            <div class="list-group list-group-flush" id="role-list"></div>
        </div>
    </div>

    <div class="col-md-8 col-xl-9">
        <div class="phai-toolbar phai-sticky phai-pane-bar">
            <span class="fw-semibold" id="role-title"></span>
            <button type="button" class="btn btn-sm btn-icon btn-ghost-secondary" id="edit-role" hidden title="<?= e(t('common.edit')) ?>">
                <i class="ti ti-pencil"></i>
            </button>
            <button type="button" class="btn btn-sm btn-primary ms-auto" id="save-perms" hidden><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
        </div>
        <div class="card">
            <div class="card-body py-3 text-secondary" id="perms-note"><?= e(t('system.pick_role')) ?></div>
            <div class="table-responsive" id="perms-box" hidden>
                <table class="table table-sm table-vcenter card-table" id="perms">
                    <thead>
                    <tr>
                        <th><?= e(t('system.program')) ?></th>
                        <?php foreach ($actions as $a): ?>
                            <th class="w-1 text-center">
                                <label class="d-block mb-0">
                                    <?= e(t('system.perm_' . $a)) ?><br>
                                    <input type="checkbox" class="form-check-input m-0 mt-1" data-all="<?= e($a) ?>">
                                </label>
                            </th>
                        <?php endforeach ?>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="role-modal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form class="modal-content" novalidate autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title"><?= e(t('system.roles')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label" for="role_code"><?= e(t('system.role_code')) ?></label>
                    <input type="text" class="form-control form-control-sm text-uppercase" id="role_code" name="role_code" maxlength="50">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="role_desc"><?= e(t('system.role_desc')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="role_desc" name="role_desc" maxlength="100">
                </div>
                <label class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="role_active" checked>
                    <span class="form-check-label"><?= e(t('common.active')) ?></span>
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-ghost-danger me-auto" id="del"><i class="ti ti-trash me-1"></i><?= e(t('common.delete')) ?></button>
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
                <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
            </div>
        </form>
    </div>
</div>

<script id="perm-actions" type="application/json"><?= json_encode($actions, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script id="admin-note" type="application/json"><?= json_encode(t('system.admin_all'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
