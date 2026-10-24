<?php
/** Sign-in page: brand panel (wide screens) + form. Driven by public/js/pages/login.js. */

use Phai\Core\App;
use Phai\Modules\System\Logo;

$logo = Logo::url();
$company = (string) (App::db()->value('SELECT co_name FROM co_mstr WHERE co_id = 1') ?? '');
$modules = [
    ['ti-shopping-cart', t('auth.mod_purchase')], ['ti-receipt', t('auth.mod_sales')], ['ti-building-warehouse', t('auth.mod_stock')],
    ['ti-building-factory-2', t('auth.mod_production')], ['ti-report-money', t('auth.mod_accounting')], ['ti-sparkles', t('auth.mod_ai')],
];
?>
<div class="phai-login">
    <aside class="phai-login-brand d-none d-lg-flex">
        <div class="phai-login-brand-top">
            <img src="<?= e(asset('img/phaierp-logo-light.svg')) ?>" alt="" class="phai-mark"><span class="phai-word">Phai<b>ERP</b></span>
        </div>
        <div>
            <?php if ($logo): ?>
                <div class="phai-login-logo mb-3"><img src="<?= e($logo) ?>" alt=""></div>
            <?php endif ?>
            <h1 class="phai-login-company"><?= e($company !== '' ? $company : 'PhaiERP') ?></h1>
            <p class="phai-login-tagline"><?= e(t('auth.tagline')) ?></p>
            <div class="phai-login-modules">
                <?php foreach ($modules as [$icon, $label]): ?>
                    <span><i class="ti <?= $icon ?>"></i><?= e($label) ?></span>
                <?php endforeach ?>
            </div>
        </div>
        <div class="phai-login-brand-foot">PhaiERP <?= e(App::VERSION) ?></div>
    </aside>

    <main class="phai-login-main">
        <?php $other = \Phai\Core\Lang::locale() === 'th' ? 'en' : 'th'; ?>
        <button type="button" class="phai-login-lang" id="login-lang" data-lang="<?= $other ?>" title="<?= e(t('common.lang_' . $other)) ?>" aria-label="<?= e(t('common.lang_' . $other)) ?>"><?= \Phai\Core\View\View::partial('partials/flag', ['lang' => $other]) ?><span><?= strtoupper($other) ?></span></button>
        <button type="button" class="phai-login-theme" id="login-theme" data-names="<?= e(json_encode(array_combine(\Phai\Modules\System\AuthController::THEMES, array_map(fn ($k) => t('auth.theme_' . $k), \Phai\Modules\System\AuthController::THEMES)), JSON_UNESCAPED_UNICODE)) ?>"><i class="ti ti-moon"></i></button>
        <div class="phai-login-box">
            <div class="d-lg-none text-center mb-4">
                <?php if ($logo): ?>
                    <img src="<?= e($logo) ?>" alt="" class="phai-logo-login mb-2">
                <?php endif ?>
                <div class="phai-brand justify-content-center fs-3"><img src="<?= e(asset('img/phaierp-logo.svg')) ?>" alt="" class="phai-mark"><span class="phai-word">Phai<b>ERP</b></span></div>
            </div>

            <h2 class="mb-1"><?= e(t('auth.welcome')) ?></h2>
            <p class="text-secondary mb-4"><?= e($company !== '' ? t('auth.sign_in_to', ['company' => $company]) : t('auth.login')) ?></p>

            <?php if (isset($_GET['idle'])): ?>
                <div class="alert alert-warning py-2 small"><i class="ti ti-clock-pause me-1"></i><?= e(t('auth.idle_out')) ?></div>
            <?php endif ?>
            <form id="login" novalidate data-no-cancel>
                <div class="mb-3">
                    <label class="form-label" for="login-name"><?= e(t('auth.username')) ?></label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-user"></i></span>
                        <input type="text" class="form-control" id="login-name" name="login" autocomplete="username" autofocus>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="login-pass"><?= e(t('auth.password')) ?></label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-lock"></i></span>
                        <input type="password" class="form-control pe-5" id="login-pass" name="password" autocomplete="current-password">
                        <button type="button" class="phai-login-eye" id="login-eye" tabindex="-1" aria-label="<?= e(t('auth.show_password')) ?>">
                            <i class="ti ti-eye"></i>
                        </button>
                    </div>
                    <div class="small text-warning mt-1" id="login-caps" hidden><i class="ti ti-alert-triangle me-1"></i><?= e(t('auth.caps_lock')) ?></div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-3"><?= e(t('auth.login')) ?><i class="ti ti-arrow-right ms-1"></i></button>
            </form>
        </div>
    </main>
</div>
