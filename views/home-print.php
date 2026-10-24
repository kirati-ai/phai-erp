<?php
/**
 * หน้าแรก on A4 landscape (HomeController::print): the same figures as the screen (views/partials/dashboard.php),
 * light, for printing or saving as PDF (?pdf=1).
 * @var string $today
 * @var array|null $company
 * @var ?string $logo
 * @var array $f  HomeController::figures()
 */
$curr = $company['co_base_curr'] ?? '';
?>
<?= \Phai\Core\View\View::partial('doc/print-toolbar') ?>
<style>
    @page { size: A4 landscape; margin: 9mm; }
    body { background: #e9edf2; }
    .dash-page { width: 279mm; margin: 0 auto 2rem; padding: 8mm; background: #fff; box-shadow: 0 4px 24px rgba(15, 23, 42, .12);
                 font-family: "IBM Plex Sans Thai", "Leelawadee UI", Tahoma, sans-serif; font-size: 11px; }
    .dash-head { display: flex; align-items: center; gap: 12px; padding-bottom: 8px; margin-bottom: 10px; border-bottom: 2px solid #12855f; }
    .dash-head img { max-height: 40px; max-width: 110px; object-fit: contain; }
    .dash-head .name { font-size: 15px; font-weight: 600; }
    .dash-head .title { margin-left: auto; text-align: right; }
    .dash-head .title div:first-child { font-size: 15px; font-weight: 600; color: #12855f; }
    /* the screen's grid at the printed width, without hover, motion or shadows */
    .dash-page .row { --tblr-gutter-x: 8px; --tblr-gutter-y: 8px; margin-bottom: 8px !important; }
    .dash-page .col-lg-8 { flex: 0 0 auto; width: 66.666%; }
    .dash-page .col-lg-6 { flex: 0 0 auto; width: 50%; }
    .dash-page .col-lg-4 { flex: 0 0 auto; width: 33.333%; }
    .dash-page .col-xl-3 { flex: 0 0 auto; width: 25%; }
    .dash-page .card { box-shadow: none !important; transform: none !important; animation: none !important; }
    /* a row of cards moves to the next page whole (a flex row split across pages overlaps the next one) */
    .dash-page .row { break-inside: avoid; page-break-inside: avoid; }
    .dash-page .phai-chart * { animation: none !important; }
    .dash-page * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @media print {
        body { background: #fff; }
        .dash-page { width: auto; margin: 0; padding: 0; box-shadow: none; }
    }
</style>
<div class="dash-page" data-bs-theme="light">
    <div class="dash-head">
        <?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php endif ?>
        <div class="name"><?= e($company['co_name'] ?? '') ?></div>
        <div class="title">
            <div><?= e(t('home.report_title')) ?></div>
            <div><?= e(t('home.as_of', ['date' => $today])) ?></div>
        </div>
    </div>
    <?= \Phai\Core\View\View::partial('partials/dashboard', ['f' => $f, 'curr' => $curr]) ?>
</div>
