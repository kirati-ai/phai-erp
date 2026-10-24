<?php /** @var array $config from, to, canPrint */ ?>
<div class="phai-toolbar">
    <input type="date" class="form-control form-control-sm w-auto" id="from" value="<?= e($config['from']) ?>" aria-label="<?= e(t('fs.from')) ?>">
    <span class="text-secondary">–</span>
    <input type="date" class="form-control form-control-sm w-auto" id="to" value="<?= e($config['to']) ?>" aria-label="<?= e(t('fs.to')) ?>">
    <?php if ($config['canPrint']): ?>
        <a class="btn btn-sm ms-auto" id="fs-print" target="_blank" rel="noopener" href="#"><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
    <?php endif ?>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header py-2"><h3 class="card-title"><?= e(t('fs.balance_sheet')) ?> <span class="text-secondary" id="bs-date"></span></h3></div>
            <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table mb-0" id="bs"><tbody></tbody></table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header py-2"><h3 class="card-title"><?= e(t('fs.income')) ?> <span class="text-secondary" id="pl-range"></span></h3></div>
            <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table mb-0" id="pl"><tbody></tbody></table>
            </div>
        </div>
    </div>
</div>
<script id="fs-config" type="application/json"><?= json_encode($config + ['text' => \Phai\Modules\Accounting\FinancialStatementController::text()], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
