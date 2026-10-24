<?php
/**
 * Every document's numbering the user may see (each module's settings page also shows its own).
 * @var array $docs     [{doc, prefix, next, format}]
 * @var bool  $canEdit
 */

use Phai\Core\View\View;

echo View::partial('system/numbering-table', ['docs' => $docs, 'canEdit' => $canEdit, 'title' => null]);
