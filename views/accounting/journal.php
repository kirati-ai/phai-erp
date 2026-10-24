<?php /** @var array $config  entry, types, accounts, costCenters, today, periodStart */ ?>
<form id="journal" novalidate autocomplete="off">
    <div class="phai-toolbar phai-sticky">
<a class="btn btn-sm" href="<?= e(url('/journals')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
        <span class="badge d-none" id="je-type"></span>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-ghost-danger" id="je-reverse" hidden><i class="ti ti-arrow-back-up me-1"></i><?= e(t('accounting.reverse')) ?></button>
            <?php if (!empty($config['printUrl'])): ?>
                <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url($config['printUrl'])) ?>"><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
            <?php endif ?>
            <button type="submit" class="btn btn-sm btn-primary" id="je-save" hidden><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body pb-3">
            <div class="row g-2">
                <div class="col-sm-4 col-lg-1">
                    <label class="form-label" for="je_doc_nbr">
                        <?= e(t('common.doc_nbr')) ?>
                        <?php if (empty($config['entry'])): ?>
                            <span class="text-secondary fw-normal small">(<?= e(t('common.editable_hint')) ?>)</span>
                        <?php endif ?>
                    </label>
                    <input type="text" class="form-control form-control-sm text-uppercase phai-doc-nbr-input" id="je_doc_nbr" name="doc_nbr"
                        value="<?= e($config['entry'] ? explode('~', $config['entry'])[1] : \Phai\Core\Numbering::peek('JE')) ?>"
                        autocomplete="off"
                        <?= empty($config['entry']) ? 'title="' . e(t('common.editable_hint')) . '" placeholder="' . e(t('common.editable_hint')) . '"' : 'readonly data-locked' ?>>
                </div>
                <div class="col-sm-4 col-lg-1">
                    <label class="form-label required" for="date"><?= e(t('accounting.date')) ?></label>
                    <input type="date" class="form-control form-control-sm" id="date" name="date" min="<?= e($config['periodStart']) ?>">
                </div>
                <div class="col-sm-8 col-lg-7">
                    <label class="form-label required" for="desc"><?= e(t('accounting.desc')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="desc" name="desc" maxlength="255">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2" id="je-head">
            <button type="button" class="btn btn-sm ms-auto" id="je-add"><i class="ti ti-plus me-1"></i><?= e(t('accounting.add_line')) ?></button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="je-lines">
                <thead>
                <tr>
                    <th style="min-width:14rem"><?= e(t('accounting.account')) ?></th>
                    <th style="min-width:8rem"><?= e(t('accounting.cc')) ?></th>
                    <th><?= e(t('accounting.desc')) ?></th>
                    <th class="text-end" style="width:9rem"><?= e(t('accounting.debit')) ?></th>
                    <th class="text-end" style="width:9rem"><?= e(t('accounting.credit')) ?></th>
                    <th class="w-1"></th>
                </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                <tr class="fw-semibold">
                    <td colspan="3" class="text-end" id="je-diff"></td>
                    <td class="num" id="je-dr"></td>
                    <td class="num" id="je-cr"></td>
                    <td></td>
                </tr>
                </tfoot>
            </table>
        </div>
        <input type="hidden" name="lines">
    </div>
</form>
<div class="mt-3"><?= \Phai\Core\View\View::partial('partials/files', ['doc' => 'JE', 'nbr' => $config['entry'] ?? '']) ?></div>
<script id="journal-config" type="application/json"><?= json_encode($config + ['text' => [
    'diff'    => t('accounting.difference'),
    'confirm' => t('accounting.reverse_confirm'),
]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
