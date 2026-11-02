<?php
/** เอกสารรอเซ็น (SignatureController): requests waiting for my signature, and what I asked others to sign. */
$cols = fn (array $keys) => implode('', array_map(fn ($k) => '<th>' . e(t("sign.$k")) . '</th>', $keys));
?>
<div class="row g-3">
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header py-2"><h3 class="card-title"><i class="ti ti-writing-sign me-1"></i><?= e(t('sign.waiting')) ?></h3></div>
            <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table">
                    <thead><tr><?= $cols(['doc', 'box', 'from', 'when', 'note']) ?></tr></thead>
                    <tbody id="sig-waiting"><tr><td colspan="5" class="text-secondary"><?= e(t('js.loading')) ?></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header py-2"><h3 class="card-title"><i class="ti ti-send me-1"></i><?= e(t('sign.asked')) ?></h3></div>
            <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table">
                    <thead><tr><?= $cols(['doc', 'box', 'to', 'state', 'when']) ?></tr></thead>
                    <tbody id="sig-asked"><tr><td colspan="5" class="text-secondary"><?= e(t('js.loading')) ?></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script id="sig-text" type="application/json"><?= json_encode([
    'none_waiting' => t('sign.none_waiting'), 'none_asked' => t('sign.none_asked'),
    'status' => ['R' => t('sign.status_R'), 'S' => t('sign.status_S'), 'X' => t('sign.status_X')],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
