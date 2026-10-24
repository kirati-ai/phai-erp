<?php
/**
 * หน้าแรก (HomeController): the owner's view to read — nothing links away; the figures are views/partials/dashboard.php,
 * also printed by /dashboard/{date}/print (PDF). Each part only appears for screens the user may open.
 * @var string $greeting, $today
 * @var array|null $company, $period
 * @var ?string $logo
 * @var array $f     HomeController::figures()
 * @var bool $empty  admin on a system without parts yet
 */
$curr = $company['co_base_curr'] ?? '';
?>
<div class="phai-home">
    <!-- greeting -->
    <div class="phai-hero mb-3">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <?php if ($logo): ?>
                <div class="phai-hero-logo"><img src="<?= e($logo) ?>" alt=""></div>
            <?php endif ?>
            <div class="min-w-0">
                <div class="phai-hero-hello"><?= e($greeting) ?></div>
                <div class="phai-hero-sub">
                    <?= e($today) ?> · <?= e($company['co_name'] ?? '') ?>
                    · <?= e(t('home.period')) ?> <?= e(sprintf('%02d/%04d', $period['gl_curr_per'] ?? 0, ($period['gl_curr_year'] ?? 0) + (int) t('js.year_offset'))) ?>
                </div>
            </div>
        </div>
        <a class="btn btn-sm phai-hero-pdf" href="<?= e(url('/dashboard/' . date('Y-m-d') . '/print' . (\Phai\Core\Pdf::available() ? '?pdf=1' : ''))) ?>" target="_blank" rel="noopener">
            <i class="ti ti-file-type-pdf me-1"></i><?= e(t('home.export_pdf')) ?>
        </a>
    </div>

    <?php if ($empty): ?>
        <!-- a fresh install: what to set up first -->
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="mb-1"><i class="ti ti-rocket text-primary me-1"></i><?= e(t('home.start_title')) ?></h3>
                <p class="text-secondary mb-3"><?= e(t('home.start_sub')) ?></p>
                <div class="row g-2">
                    <?php foreach ([['/company', 'ti-building', 'start_company'], ['/parts', 'ti-package', 'start_parts'], ['/customers', 'ti-user-heart', 'start_customers'],
                                    ['/vendors', 'ti-truck', 'start_vendors'], ['/users', 'ti-users', 'start_users']] as $i => [, $icon, $key]): ?>
                        <div class="col-sm-6 col-lg">
                            <div class="phai-start">
                                <span class="phai-start-n"><?= $i + 1 ?></span>
                                <i class="ti <?= $icon ?>"></i><span><?= e(t("home.$key")) ?></span>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    <?php endif ?>

    <?= \Phai\Core\View\View::partial('partials/dashboard', ['f' => $f, 'curr' => $curr]) ?>
</div>
