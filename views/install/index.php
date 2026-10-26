<?php
/**
 * Installer wizard: requirements → database → company → administrator → confirm, then install.
 * Driven by public/js/pages/install.js; every step is checked by the server before "next".
 * @var array $requirements
 * @var bool  $ready
 * @var array $defaults database fields to start with (Installer::defaults)
 */
$steps = ['requirements', 'database', 'company', 'admin', 'confirm'];
$icons = ['ti-device-desktop-check', 'ti-database', 'ti-building', 'ti-user-shield', 'ti-rocket'];
?>
<div class="page page-center phai-install">
    <div class="container py-4" style="max-width: 40rem">
        <div class="text-center mb-3 phai-brand fs-2">
            <img src="<?= e(asset('img/phaierp-logo.svg')) ?>" alt="" class="phai-mark"><span class="phai-word">Phai<b>ERP</b></span>
        </div>

        <ul class="steps steps-primary steps-counter mb-3" id="wiz-steps">
            <?php foreach ($steps as $i => $s): ?>
                <li class="step-item<?= $i === 0 ? ' active' : '' ?>" data-goto="<?= $i ?>"><?= e(t("install.step_$s")) ?></li>
            <?php endforeach ?>
        </ul>

        <form class="card" id="install" novalidate autocomplete="off" data-no-cancel>
            <div class="card-body">

                <!-- 1. requirements -->
                <section class="wiz-step" data-step="0">
                    <h2 class="mb-1"><i class="ti <?= $icons[0] ?> text-primary me-1"></i><?= e(t('install.welcome')) ?></h2>
                    <p class="text-secondary mb-3"><?= e(t('install.welcome_sub')) ?></p>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($requirements as $r): ?>
                            <li class="py-1 <?= $r['ok'] ? 'text-success' : ($r['required'] ? 'text-danger' : 'text-warning') ?>">
                                <i class="ti <?= $r['ok'] ? 'ti-circle-check' : 'ti-circle-x' ?> me-1"></i><?= e($r['name']) ?>
                                <?= $r['required'] ? '' : '<span class="text-secondary small">(' . e(t('install.optional')) . ')</span>' ?>
                            </li>
                        <?php endforeach ?>
                    </ul>
                    <?php if (!$ready): ?>
                        <div class="text-danger small mt-2"><?= e(t('install.requirements_failed')) ?></div>
                    <?php endif ?>
                </section>

                <!-- 2. database -->
                <section class="wiz-step" data-step="1" hidden>
                    <h2 class="mb-3"><i class="ti <?= $icons[1] ?> text-primary me-1"></i><?= e(t('install.database')) ?></h2>
                    <div class="row g-2">
                        <div class="col-8">
                            <label class="form-label" for="db_host"><?= e(t('install.db_host')) ?></label>
                            <input type="text" class="form-control form-control-sm" id="db_host" name="db_host" value="<?= e($defaults['db_host']) ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="db_port"><?= e(t('install.db_port')) ?></label>
                            <input type="text" class="form-control form-control-sm" id="db_port" name="db_port" value="<?= e((string) $defaults['db_port']) ?>" inputmode="numeric">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="db_name"><?= e(t('install.db_name')) ?></label>
                            <input type="text" class="form-control form-control-sm" id="db_name" name="db_name" value="<?= e($defaults['db_name']) ?>">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="db_user"><?= e(t('install.db_user')) ?></label>
                            <input type="text" class="form-control form-control-sm" id="db_user" name="db_user" value="<?= e($defaults['db_user']) ?>">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="db_pass"><?= e(t('install.db_pass')) ?></label>
                            <input type="password" class="form-control form-control-sm" id="db_pass" name="db_pass" autocomplete="new-password"<?= !empty($preset) ? ' placeholder="' . e(t('install.db_pass_preset')) . '"' : '' ?>>
                        </div>
                    </div>
                    <div class="small mt-2" id="db-status"></div>
                </section>

                <!-- 3. company -->
                <section class="wiz-step" data-step="2" hidden>
                    <h2 class="mb-3"><i class="ti <?= $icons[2] ?> text-primary me-1"></i><?= e(t('install.company')) ?></h2>
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <label class="form-label"><?= e(t('install.logo')) ?></label>
                            <div class="phai-logo-drop" id="logo-drop" role="button" tabindex="0">
                                <img id="logo-preview" alt="" hidden>
                                <div id="logo-empty" class="text-secondary small text-center">
                                    <i class="ti ti-photo-up fs-1 d-block"></i><?= e(t('install.logo_pick')) ?>
                                </div>
                            </div>
                            <input type="file" id="logo-file" accept="image/png,image/jpeg,image/webp" hidden>
                            <input type="hidden" name="co_logo">
                            <button type="button" class="btn btn-sm btn-ghost-danger w-100 mt-1" id="logo-clear" hidden><i class="ti ti-trash me-1"></i><?= e(t('install.logo_clear')) ?></button>
                        </div>
                        <div class="col-sm-8">
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label required" for="co_name"><?= e(t('install.co_name')) ?></label>
                                    <input type="text" class="form-control form-control-sm" id="co_name" name="co_name" maxlength="255">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="co_tax_id"><?= e(t('install.co_tax_id')) ?></label>
                                    <input type="text" class="form-control form-control-sm" id="co_tax_id" name="co_tax_id" maxlength="13" inputmode="numeric">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="co_phone"><?= e(t('install.co_phone')) ?></label>
                                    <input type="text" class="form-control form-control-sm" id="co_phone" name="co_phone" maxlength="50">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="ad_line1"><?= e(t('install.ad_line1')) ?></label>
                                    <input type="text" class="form-control form-control-sm" id="ad_line1" name="ad_line1" maxlength="255">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="ad_state"><?= e(t('install.ad_state')) ?></label>
                                    <select class="form-select form-select-sm" id="ad_state" name="ad_state" data-geo="province"><option value=""></option></select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="ad_city"><?= e(t('install.ad_city')) ?></label>
                                    <select class="form-select form-select-sm" id="ad_city" name="ad_city" data-geo="district"><option value=""></option></select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="ad_sub_district"><?= e(t('install.ad_sub_district')) ?></label>
                                    <select class="form-select form-select-sm" id="ad_sub_district" name="ad_sub_district" data-geo="subdistrict"><option value=""></option></select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="ad_zip"><?= e(t('install.ad_zip')) ?></label>
                                    <input type="text" class="form-control form-control-sm" id="ad_zip" name="ad_zip" maxlength="20" inputmode="numeric" data-geo="zip">
                                </div>
                                <div class="col-12 small text-secondary"><?= e(t('install.address_later')) ?></div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 4. administrator -->
                <section class="wiz-step" data-step="3" hidden>
                    <h2 class="mb-3"><i class="ti <?= $icons[3] ?> text-primary me-1"></i><?= e(t('install.admin')) ?></h2>
                    <div class="row g-2">
                        <div class="col-sm-4">
                            <label class="form-label required" for="us_userid"><?= e(t('system.us_userid')) ?></label>
                            <input type="text" class="form-control form-control-sm" id="us_userid" name="us_userid" value="admin" maxlength="50">
                        </div>
                        <div class="col-sm-8">
                            <label class="form-label required" for="us_name"><?= e(t('system.us_name')) ?></label>
                            <input type="text" class="form-control form-control-sm" id="us_name" name="us_name" maxlength="255">
                        </div>
                        <div class="col-12">
                            <label class="form-label required" for="us_email"><?= e(t('system.us_email')) ?></label>
                            <input type="email" class="form-control form-control-sm" id="us_email" name="us_email" maxlength="255">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label required" for="password"><?= e(t('auth.password')) ?></label>
                            <input type="password" class="form-control form-control-sm" id="password" name="password" minlength="8" autocomplete="new-password">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label required" for="password2"><?= e(t('install.password_again')) ?></label>
                            <input type="password" class="form-control form-control-sm" id="password2" name="password2" autocomplete="new-password">
                        </div>
                    </div>
                </section>

                <!-- 5. confirm -->
                <section class="wiz-step" data-step="4" hidden>
                    <h2 class="mb-3"><i class="ti <?= $icons[4] ?> text-primary me-1"></i><?= e(t('install.confirm_title')) ?></h2>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img id="sum-logo" class="phai-logo-sm" alt="" hidden>
                        <div>
                            <div class="fw-semibold fs-3" id="sum-company"></div>
                            <div class="text-secondary small" id="sum-company-sub"></div>
                            <div class="text-secondary small" id="sum-address"></div>
                        </div>
                    </div>
                    <dl class="row small mb-3">
                        <dt class="col-4 text-secondary fw-normal"><?= e(t('install.database')) ?></dt><dd class="col-8" id="sum-db"></dd>
                        <dt class="col-4 text-secondary fw-normal"><?= e(t('install.admin')) ?></dt><dd class="col-8" id="sum-admin"></dd>
                    </dl>
                    <label class="form-selectgroup-item flex-fill w-100">
                        <input type="checkbox" name="demo" value="1" class="form-selectgroup-input">
                        <div class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                            <span class="form-selectgroup-check me-2"></span>
                            <span>
                                <span class="fw-medium d-block"><?= e(t('install.demo')) ?></span>
                                <span class="text-secondary small"><?= e(t('install.demo_sub')) ?></span>
                            </span>
                        </div>
                    </label>
                </section>

                <!-- installing -->
                <section class="wiz-step text-center py-4" data-step="5" hidden>
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <div class="fs-3 fw-medium mb-2" id="run-text"></div>
                    <div class="progress progress-sm mx-auto" style="max-width: 20rem">
                        <div class="progress-bar progress-bar-indeterminate"></div>
                    </div>
                </section>

                <!-- done -->
                <section class="wiz-step text-center py-4" data-step="6" hidden>
                    <div class="phai-done-icon mb-2"><i class="ti ti-circle-check"></i></div>
                    <h2 class="mb-1"><?= e(t('install.done')) ?></h2>
                    <p class="text-secondary" id="done-sub"></p>
                    <div class="small text-warning mb-2" id="demo-error" hidden></div>
                    <div id="config-box" class="text-start" hidden>
                        <p class="small"><?= e(t('install.save_config')) ?></p>
                        <textarea class="form-control form-control-sm font-monospace mb-3" rows="10" readonly id="config-text"></textarea>
                    </div>
                    <a href="#" class="btn btn-primary" id="go-login"><i class="ti ti-login me-1"></i><?= e(t('install.go_login')) ?></a>
                </section>
            </div>

            <div class="card-footer d-flex align-items-center gap-2" id="wiz-nav">
                <button type="button" class="btn btn-sm" id="wiz-back" hidden><i class="ti ti-chevron-left me-1"></i><?= e(t('install.back')) ?></button>
                <span class="text-secondary small ms-auto" id="wiz-count"></span>
                <button type="submit" class="btn btn-sm btn-primary" id="wiz-next" <?= $ready ? '' : 'disabled' ?>>
                    <span data-label><?= e(t('install.next')) ?></span><i class="ti ti-chevron-right ms-1"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script id="install-text" type="application/json"><?= json_encode([
    'ok'         => t('install.db_ok'),
    'new'        => t('install.db_new'),
    'empty'      => t('install.db_empty'),
    'next'       => t('install.next'),
    'install'    => t('install.install'),
    'of'         => t('install.step_of'),
    'mismatch'   => t('install.password_mismatch'),
    'logoType'   => t('system.logo_type'),
    'logoSize'   => t('system.logo_size'),
    'doneSub'    => t('install.done_sub'),
    'doneDemo'   => t('install.done_demo'),
    'running'    => [t('install.run_schema'), t('install.run_seeds'), t('install.run_company'), t('install.run_admin')],
    'runningDemo' => t('install.run_demo'),
    'maxLogo'    => \Phai\Modules\System\Logo::MAX_BYTES,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
