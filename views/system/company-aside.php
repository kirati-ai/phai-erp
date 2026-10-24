<?php
/** Logo and address cards above the company form (CompanyResource::$aside), as wide as the form. @var array $can @var string $width */
$logo = \Phai\Modules\System\Logo::url();
$edit = $can['edit'];
?>
<div class="row g-3 mb-3" style="max-width: calc(<?= e($width ?? '40rem') ?> + var(--tblr-gutter-x, 1rem))">
    <div class="col-md-5">
        <div class="card h-100" id="company-logo">
            <div class="card-header py-2"><h3 class="card-title"><?= e(t('system.logo')) ?></h3></div>
            <div class="card-body d-flex align-items-center gap-3">
                <div class="phai-logo-box">
                    <img id="co-logo-img" src="<?= e($logo ?? '') ?>" alt=""<?= $logo ? '' : ' hidden' ?>>
                    <i class="ti ti-photo text-secondary fs-2" id="co-logo-none"<?= $logo ? ' hidden' : '' ?>></i>
                </div>
                <?php if ($edit): ?>
                    <div class="d-flex flex-column gap-1">
                        <input type="file" id="co-logo-file" accept="image/png,image/jpeg,image/webp" hidden>
                        <button type="button" class="btn btn-sm" id="co-logo-change"><i class="ti ti-upload me-1"></i><?= e(t('system.logo_change')) ?></button>
                        <button type="button" class="btn btn-sm btn-ghost-danger" id="co-logo-remove"<?= $logo ? '' : ' hidden' ?>><i class="ti ti-trash me-1"></i><?= e(t('system.logo_remove')) ?></button>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <form class="card h-100" id="company-address" novalidate autocomplete="off">
            <div class="card-header py-2">
                <h3 class="card-title"><?= e(t('system.company_address')) ?></h3>
                <?php if ($edit): ?>
                    <button type="button" class="btn btn-sm ms-auto" id="co-ad-edit"><i class="ti ti-pencil me-1"></i><?= e(t('common.edit')) ?></button>
                <?php endif ?>
            </div>
            <div class="card-body">
                <div id="co-ad-text" class="text-secondary"></div>
                <fieldset class="row g-2" id="co-ad-fields" hidden>
                    <?php foreach (['ad_house_no' => 3, 'ad_moo' => 3, 'ad_building' => 6, 'ad_floor' => 3, 'ad_room' => 3, 'ad_village' => 6,
                                    'ad_soi' => 4, 'ad_yaek' => 4, 'ad_road' => 4] as $f => $col): ?>
                        <div class="col-6 col-sm-<?= $col ?>">
                            <label class="form-label"><?= e(t("system.$f")) ?></label>
                            <input type="text" class="form-control form-control-sm" name="<?= $f ?>" maxlength="<?= \Phai\Modules\System\Address::PARTS[substr($f, 3)][0] ?>">
                        </div>
                    <?php endforeach ?>
                    <div class="col-12">
                        <label class="form-label"><?= e(t('system.ad_line2')) ?></label>
                        <input type="text" class="form-control form-control-sm" name="ad_line2" maxlength="255">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label"><?= e(t('install.ad_state')) ?></label>
                        <select class="form-select form-select-sm" name="ad_state" data-geo="province"><option value=""></option></select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label"><?= e(t('install.ad_city')) ?></label>
                        <select class="form-select form-select-sm" name="ad_city" data-geo="district"><option value=""></option></select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label"><?= e(t('install.ad_sub_district')) ?></label>
                        <select class="form-select form-select-sm" name="ad_sub_district" data-geo="subdistrict"><option value=""></option></select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label"><?= e(t('install.ad_zip')) ?></label>
                        <input type="text" class="form-control form-control-sm" name="ad_zip" maxlength="20" inputmode="numeric" data-geo="zip">
                    </div>
                    <div class="col-12 d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-sm" id="co-ad-cancel"><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
                    </div>
                </fieldset>
            </div>
        </form>
    </div>
</div>
<script id="co-logo-text" type="application/json"><?= json_encode([
    'type' => t('system.logo_type'), 'size' => t('system.logo_size'), 'max' => \Phai\Modules\System\Logo::MAX_BYTES,
    'confirm' => t('system.logo_remove') . ' ?', 'noAddress' => t('system.no_address'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
