<?php
/**
 * Buttons above a printed document. PDF: a real file made on the server when a browser is set
 * up there (Phai\Core\Pdf), else the print dialog's "Save as PDF".
 */
$serverPdf = \Phai\Core\Pdf::available();
?>
<style>
    .doc-toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: center; align-items: center; gap: 8px; padding: 10px;
                   margin-bottom: 14px; background: rgba(233, 237, 242, .92); backdrop-filter: blur(4px); }
    .doc-toolbar .hint { font-size: 10px; color: #000; }
    @media print { .doc-toolbar { display: none !important; } }
</style>
<div class="doc-toolbar no-print">
    <button type="button" class="btn btn-sm" onclick="window.print()"><i class="ti ti-printer me-1"></i><?= e(t('sales.print')) ?></button>
    <?php if ($serverPdf): ?>
        <a class="btn btn-sm btn-primary" href="?pdf=1" target="_blank" rel="noopener"><i class="ti ti-file-type-pdf me-1"></i><?= e(t('sales.save_pdf')) ?></a>
    <?php else: ?>
        <button type="button" class="btn btn-sm btn-primary" onclick="document.getElementById('pdf-hint').hidden = false; window.print()">
            <i class="ti ti-file-type-pdf me-1"></i><?= e(t('sales.save_pdf')) ?>
        </button>
        <span class="hint" id="pdf-hint" hidden><?= e(t('sales.pdf_hint')) ?></span>
    <?php endif ?>
</div>
