<?php /** e-Tax Invoice by Email and outgoing mail server (public/js/pages/etax-settings.js). */ ?>
<form id="etax-settings" class="card mb-3" novalidate autocomplete="off">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-12">
                <label class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" name="enabled">
                    <span class="form-check-label"><?= e(t('etax.enabled')) ?></span>
                </label>
            </div>
            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="from"><?= e(t('etax.from')) ?></label>
                <input type="email" class="form-control form-control-sm" id="from" name="from" maxlength="200" placeholder="<?= e(t('etax.ph_from')) ?>">
            </div>
            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="from_name"><?= e(t('etax.from_name')) ?></label>
                <input type="text" class="form-control form-control-sm" id="from_name" name="from_name" maxlength="200" placeholder="<?= e(t('etax.ph_from_name')) ?>">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label" for="year"><?= e(t('etax.year')) ?></label>
                <select class="form-select form-select-sm" id="year" name="year">
                    <option value="BE"><?= e(t('etax.year_BE')) ?></option>
                    <option value="CE"><?= e(t('etax.year_CE')) ?></option>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label" for="cc"><?= e(t('etax.cc')) ?></label>
                <input type="email" class="form-control form-control-sm" id="cc" name="cc" maxlength="200">
            </div>
            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="host"><?= e(t('etax.host')) ?></label>
                <input type="text" class="form-control form-control-sm" id="host" name="host" maxlength="200" placeholder="smtp.gmail.com">
            </div>
            <div class="col-sm-3 col-lg-2">
                <label class="form-label" for="port"><?= e(t('etax.port')) ?></label>
                <input type="text" class="form-control form-control-sm text-end" id="port" name="port" inputmode="numeric" placeholder="587">
            </div>
            <div class="col-sm-3 col-lg-2">
                <label class="form-label" for="secure"><?= e(t('etax.secure')) ?></label>
                <select class="form-select form-select-sm" id="secure" name="secure">
                    <option value="tls">STARTTLS</option>
                    <option value="ssl">SSL</option>
                    <option value="none"><?= e(t('etax.secure_none')) ?></option>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label" for="user"><?= e(t('etax.user')) ?></label>
                <input type="text" class="form-control form-control-sm" id="user" name="user" maxlength="200" autocomplete="off" placeholder="<?= e(t('etax.ph_user')) ?>">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label" for="pass"><?= e(t('etax.pass')) ?></label>
                <input type="password" class="form-control form-control-sm" id="pass" name="pass" autocomplete="new-password" placeholder="<?= e(t('etax.ph_pass')) ?>">
            </div>
        </div>
        <div class="text-danger small mt-2 d-none" id="not-writable"><?= e(t('error.config_write')) ?></div>
    </div>
    <div class="card-footer d-flex gap-2 align-items-center py-2">
        <input type="email" class="form-control form-control-sm w-auto" id="test_to" name="test_to" placeholder="<?= e(t('etax.test_to')) ?>">
        <button type="button" class="btn btn-sm" id="test"><i class="ti ti-mail-forward me-1"></i><?= e(t('etax.test')) ?></button>
        <button type="submit" class="btn btn-sm btn-primary ms-auto"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
    </div>
</form>

<form id="etax-cert" class="card" novalidate autocomplete="off">
    <div class="card-header py-2"><h3 class="card-title"><?= e(t('etax.cert_title')) ?></h3></div>
    <div class="card-body">
        <div class="mb-2 small" id="cert-info"></div>
        <div class="row g-2 align-items-end">
            <div class="col-sm-6">
                <label class="form-label" for="p12"><?= e(t('etax.cert_file')) ?></label>
                <input type="file" class="form-control form-control-sm" id="p12" name="p12" accept=".p12,.pfx,application/x-pkcs12">
            </div>
            <div class="col-sm-4">
                <label class="form-label" for="cert_password"><?= e(t('etax.cert_password')) ?></label>
                <input type="password" class="form-control form-control-sm" id="cert_password" name="cert_password" autocomplete="new-password">
            </div>
            <div class="col-sm-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-upload me-1"></i><?= e(t('etax.cert_upload')) ?></button>
            </div>
        </div>
    </div>
    <div class="card-footer py-2 text-end">
        <button type="button" class="btn btn-sm btn-ghost-danger" id="cert-remove"><i class="ti ti-trash me-1"></i><?= e(t('etax.cert_remove')) ?></button>
    </div>
</form>
