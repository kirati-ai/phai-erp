<?php
/** @var array $roles */
?>
<div class="phai-toolbar">
    <div class="input-icon phai-search">
        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
        <input type="search" id="q" class="form-control form-control-sm" placeholder="<?= e(t('common.search')) ?>" data-search>
    </div>
    <button type="button" class="btn btn-sm btn-primary ms-auto" id="add">
        <i class="ti ti-plus me-1"></i><?= e(t('common.add')) ?>
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter table-hover card-table" id="users">
            <thead>
            <tr>
                <th data-sort="us_userid"><?= e(t('system.us_userid')) ?></th>
                <th data-sort="us_name"><?= e(t('system.us_name')) ?></th>
                <th data-sort="us_email" class="d-none d-md-table-cell"><?= e(t('system.us_email')) ?></th>
                <th data-sort="us_role"><?= e(t('system.us_role')) ?></th>
                <th data-sort="us_last_login" class="d-none d-lg-table-cell"><?= e(t('system.us_last_login')) ?></th>
                <th data-sort="us_active" class="w-1"><?= e(t('common.status')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal" id="edit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" novalidate autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title"><?= e(t('system.users')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-2" id="user-avatar" hidden></div>
                <div class="row g-2">
                    <div class="col-sm-5">
                        <label class="form-label" for="us_userid"><?= e(t('system.us_userid')) ?></label>
                        <input type="text" class="form-control form-control-sm" id="us_userid" name="us_userid" maxlength="50">
                    </div>
                    <div class="col-sm-7">
                        <label class="form-label" for="us_name"><?= e(t('system.us_name')) ?></label>
                        <input type="text" class="form-control form-control-sm" id="us_name" name="us_name" maxlength="255">
                    </div>
                    <div class="col-sm-7">
                        <label class="form-label" for="us_email"><?= e(t('system.us_email')) ?></label>
                        <input type="email" class="form-control form-control-sm" id="us_email" name="us_email" maxlength="255">
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label" for="us_role"><?= e(t('system.us_role')) ?></label>
                        <select class="form-select form-select-sm" id="us_role" name="us_role">
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= e($r['role_code']) ?>"><?= e($r['role_desc']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="password" id="password-label"
                               data-new="<?= e(t('system.password_new_user')) ?>"
                               data-edit="<?= e(t('system.password_reset')) ?>"></label>
                        <input type="password" class="form-control form-control-sm" id="password" name="password" autocomplete="new-password" minlength="8">
                    </div>
                    <div class="col-12">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="us_active" checked>
                            <span class="form-check-label"><?= e(t('common.active')) ?></span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
                <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
            </div>
        </form>
    </div>
</div>
