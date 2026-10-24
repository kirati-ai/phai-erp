<?php
/**
 * @var array $sites
 * @var bool  $canAdd
 */
?>
<div class="phai-toolbar">
    <?php if ($canAdd): ?>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="add">
            <i class="ti ti-plus me-1"></i><?= e(t('stock.new_count')) ?>
        </button>
    <?php endif ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter table-hover card-table" id="counts">
            <thead>
            <tr>
                <th data-sort="pi_batch"><?= e(t('stock.nbr')) ?></th>
                <th data-sort="pi_date"><?= e(t('stock.date')) ?></th>
                <th data-sort="pi_site"><?= e(t('stock.site')) ?></th>
                <th><?= e(t('stock.count_desc')) ?></th>
                <th><?= e(t('stock.scope')) ?></th>
                <th class="text-end"><?= e(t('stock.counted')) ?></th>
                <th data-sort="pi_status" class="w-1"><?= e(t('common.status')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal" id="new-count" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form class="modal-content" novalidate autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title"><?= e(t('stock.new_count')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label required"><?= e(t('stock.site')) ?></label>
                    <select class="form-select form-select-sm" name="site">
                        <?php foreach ($sites as $s): ?>
                            <option value="<?= e($s['si_site']) ?>"><?= e($s['si_site'] . ' · ' . $s['si_desc']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label"><?= e(t('stock.scope')) ?></label>
                    <label class="form-check mb-1"><input class="form-check-input" type="radio" name="scope" value="all" checked><span class="form-check-label"><?= e(t('stock.scope_all')) ?></span></label>
                    <label class="form-check mb-1"><input class="form-check-input" type="radio" name="scope" value="locs"><span class="form-check-label"><?= e(t('stock.scope_locs')) ?></span></label>
                    <!-- the site's locations, ticked ones only are tagged and frozen -->
                    <div id="scope-locs" class="border rounded p-2 small" style="max-height: 11rem; overflow: auto" hidden></div>
                    <input type="hidden" name="locs">
                </div>
                <div class="mb-2">
                    <label class="form-label required"><?= e(t('stock.date')) ?></label>
                    <input type="date" class="form-control form-control-sm" name="date" value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label"><?= e(t('stock.reason')) ?></label>
                    <select class="form-select form-select-sm" name="reason">
                        <option value=""></option>
                        <?php foreach ($reasons as $r): ?>
                            <option value="<?= e($r['rs_code']) ?>"><?= e($r['rs_code'] . ' · ' . $r['rs_desc']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label class="form-label"><?= e(t('stock.count_desc')) ?></label>
                    <input type="text" class="form-control form-control-sm" name="desc" maxlength="100">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
                <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
            </div>
        </form>
    </div>
</div>

<script id="status-text" type="application/json"><?= json_encode(['O' => t('stock.status_O'), 'P' => t('stock.status_P'), 'all' => t('stock.scope_all'), 'pick' => t('stock.scope_pick')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
