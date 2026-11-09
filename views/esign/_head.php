<?php
/** What every e-Document Sign page starts with: its stylesheet, its text (lang/<locale>/esign.php) and its data for the script. */
$json = fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES);
?>
<link rel="stylesheet" href="<?= e(asset('css/esign.css')) ?>">
<script id="esign-text" type="application/json"><?= $json($esignText) ?></script>
<script id="esign-data" type="application/json"><?= $json($payload ?? null) ?></script>
