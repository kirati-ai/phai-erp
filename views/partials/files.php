<?php
/**
 * The attached files of a document (Phai.files, System\AttachmentController): a card with the files, แนบไฟล์ and ×
 * as the document's own rights allow; hidden while there is no number (a page that learns it later sets data-nbr).
 * @var string  $doc  AttachmentController::DOCS code, e.g. AP
 * @var ?string $nbr  the document number (empty: not saved yet)
 */
?>
<div data-files="<?= e($doc) ?>" data-nbr="<?= e((string) ($nbr ?? '')) ?>" data-hint="<?= e(t('files.hint_' . $doc)) ?>" hidden></div>
