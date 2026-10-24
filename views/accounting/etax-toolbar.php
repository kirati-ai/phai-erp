<?php /** Extra controls on the e-Tax log: the month's signed XML as one zip for etax.rd.go.th (js/pages/etax-list.js). */ ?>
<?php if (\Phai\Core\Auth\Auth::can('etax', 'print')): ?>
    <input type="month" class="form-control form-control-sm w-auto ms-auto" id="xml-month" value="<?= e(date('Y-m', strtotime('first day of last month'))) ?>" aria-label="<?= e(t('etax.xml_month')) ?>">
    <a class="btn btn-sm" id="xml-zip" href="#"><i class="ti ti-file-zip me-1"></i><?= e(t('etax.xml_zip')) ?></a>
<?php endif ?>
