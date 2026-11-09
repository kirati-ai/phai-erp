<?php
/**
 * หลักฐานการลงนาม: the evidence pages at the end of a signed document (EnvelopePdf::evidence). What it holds: the
 * envelope, every file with its page count, where it sits in the final document and its fingerprints, every signer
 * with the times they received, opened and signed, every event with the end of the hash chain, who gets the final
 * document, the fingerprint of each document page, and how to check a copy. The bottom margin leaves room for the
 * envelope footer the overlay draws.
 * @var array $env, $ranges, $signers, $events, $fingerprints, $recipients
 * @var int $docPages, $evidencePages, $total
 * @var string $chain, $content, $at, $sender, $verify
 */
use Phai\Modules\Esign\Envelope;
use Phai\Modules\Esign\EnvelopePdf;

$when = fn (?string $v) => $v ? Envelope::when($v) : '–';
$short = fn (?string $ua) => $ua ? mb_strimwidth(preg_replace('/\s+/', ' ', $ua), 0, 60, '…') : '';
?>
<!doctype html>
<html><head><meta charset="utf-8">
<link href="<?= e(asset('fonts/ibm-plex-sans-thai/ibm-plex-sans-thai.css')) ?>" rel="stylesheet">
<style>
@page { size: A4; margin: 14mm 14mm 22mm; }
html, body { margin: 0; }
body { font-family: 'IBM Plex Sans Thai', Tahoma, sans-serif; font-size: 7.6pt; color: #111; line-height: 1.35; }
h1 { font-size: 13pt; margin: 0 0 1mm; color: #0b3d20; }
h2 { font-size: 9pt; margin: 4mm 0 1.2mm; color: #0b3d20; border-bottom: .5pt solid #9ab; padding-bottom: .6mm; }
.sub { color: #555; margin-bottom: 2mm; }
table { width: 100%; border-collapse: collapse; }
th, td { border: .4pt solid #bbb; padding: .8mm 1.2mm; text-align: left; vertical-align: top; }
th { background: #eef3ef; font-weight: 600; }
td.n, th.n { text-align: right; white-space: nowrap; }
.mono { font-family: Consolas, 'Courier New', monospace; font-size: 6.4pt; word-break: break-all; }
.meta td:first-child { width: 32mm; background: #f6f8f6; font-weight: 600; }
.total td { font-weight: 700; background: #f6f8f6; }
.fp { columns: 2; column-gap: 6mm; }
.fp div { break-inside: avoid; }
tr { break-inside: avoid; }
.note { color: #444; margin-top: 2mm; }
</style></head><body>
<h1><?= e(t('esign.ev_title')) ?></h1>
<div class="sub"><?= e(t('esign.ev_sub')) ?></div>

<table class="meta">
    <tr><td><?= e(t('esign.ev_nbr')) ?></td><td><?= e($env['env_nbr']) ?></td></tr>
    <tr><td><?= e(t('esign.ev_name')) ?></td><td><?= e($env['env_title']) ?></td></tr>
    <tr><td><?= e(t('esign.ev_sender')) ?></td><td><?= e($sender) ?></td></tr>
    <tr><td><?= e(t('esign.ev_created_at')) ?></td><td><?= e($when($env['created_at'])) ?></td></tr>
    <tr><td><?= e(t('esign.ev_sent_at')) ?></td><td><?= e($when($env['env_sent_at'])) ?></td></tr>
    <tr><td><?= e(t('esign.ev_done_at')) ?></td><td><?= e($when($at)) ?></td></tr>
    <tr><td><?= e(t('esign.ev_content')) ?></td><td class="mono"><?= e($content) ?></td></tr>
    <tr><td><?= e(t('esign.ev_clock')) ?></td><td><?= e(t('esign.ev_clock_text', ['tz' => date_default_timezone_get()])) ?></td></tr>
</table>

<h2><?= e(t('esign.ev_files')) ?></h2>
<table>
    <tr><th class="n">#</th><th><?= e(t('esign.ev_file')) ?></th><th class="n"><?= e(t('esign.ev_pages')) ?></th><th class="n"><?= e(t('esign.ev_at_pages')) ?></th><th><?= e(t('esign.ev_hashes')) ?></th></tr>
    <?php foreach ($ranges as $i => $r): ?>
    <tr>
        <td class="n"><?= $i + 1 ?></td>
        <td><?= e($r['name']) ?><br><span style="color:#555"><?= e($r['kind'] === 'M' ? t('esign.kind_M') : t('esign.kind_A_n', ['k' => $r['k']])) ?> · <?= e(number_format($r['size'] / 1024, 0)) ?> KB</span></td>
        <td class="n"><?= $r['pages'] ?></td>
        <td class="n"><?= $r['from'] === $r['to'] ? $r['from'] : $r['from'] . '–' . $r['to'] ?></td>
        <td class="mono"><?= e(t('esign.ev_uploaded')) ?> <?= e($r['src']) ?><br><?= e(t('esign.ev_cleaned')) ?> <?= e($r['clean']) ?></td>
    </tr>
    <?php endforeach ?>
    <tr><td class="n"></td><td><?= e(t('esign.ev_this')) ?></td><td class="n"><?= $evidencePages ?></td><td class="n"><?= $evidencePages > 1 ? ($docPages + 1) . '–' . $total : $docPages + 1 ?></td><td></td></tr>
    <tr class="total"><td></td><td><?= e(t('esign.ev_total')) ?></td><td class="n"><?= $total ?></td><td></td><td></td></tr>
</table>

<h2><?= e(t('esign.ev_signers')) ?></h2>
<table>
    <tr><th class="n"><?= e(t('esign.step')) ?></th><th><?= e(t('esign.signer')) ?></th><th><?= e(t('esign.role')) ?></th><th><?= e(t('esign.t_recv')) ?></th><th><?= e(t('esign.t_open')) ?></th><th><?= e(t('esign.t_sign')) ?></th><th><?= e(t('esign.ev_device')) ?></th></tr>
    <?php foreach ($signers as $s): ?>
    <tr>
        <td class="n"><?= (int) $s['envs_step'] ?></td>
        <td><b><?= e($s['envs_name']) ?></b><?php if ($s['envs_position']): ?><br><?= e($s['envs_position']) ?><?php endif ?>
            <br><span style="color:#555"><?= e($s['envs_kind'] === 'U' ? t('esign.ev_user', ['id' => $s['envs_userid']]) : t('esign.ev_outside')) ?><?= $s['envs_email'] ? ' · ' . e($s['envs_email']) : '' ?></span></td>
        <td><?= e(t('esign.role_' . $s['envs_role'])) ?><br><span style="color:#555"><?= e(t('esign.sstatus_' . $s['envs_status'])) ?></span><?php if ($s['envs_note']): ?><br><?= e($s['envs_note']) ?><?php endif ?></td>
        <td><?= e($when($s['envs_recv_at'])) ?></td>
        <td><?= e($when($s['envs_open_at'])) ?></td>
        <td><?= e($when($s['envs_sign_at'])) ?><?php if ($s['envs_hash']): ?><br><span class="mono"><?= e(t('esign.ev_seen')) ?> <?= e(substr($s['envs_hash'], 0, 16)) ?></span><?php endif ?></td>
        <td><?= e((string) $s['envs_ip']) ?><br><span style="color:#555"><?= e($short($s['envs_agent'])) ?></span></td>
    </tr>
    <?php endforeach ?>
</table>

<h2><?= e(t('esign.ev_events')) ?></h2>
<table>
    <tr><th><?= e(t('esign.ev_time')) ?></th><th><?= e(t('esign.ev_event')) ?></th><th><?= e(t('esign.ev_who')) ?></th><th>IP</th></tr>
    <?php foreach ($events as $h): ?>
    <tr>
        <td style="white-space:nowrap"><?= e($when($h['envh_at'])) ?></td>
        <td><?= e(t('esign.ev_' . $h['envh_event'])) ?></td>
        <td><?= e($h['envs_name'] ?? $h['us_name'] ?? $h['envh_userid'] ?? '') ?></td>
        <td><?= e((string) $h['envh_ip']) ?></td>
    </tr>
    <?php endforeach ?>
    <tr><td><?= e($when($at)) ?></td><td><?= e(t('esign.ev_completed')) ?></td><td></td><td></td></tr>
</table>
<div class="note"><?= e(t('esign.ev_chain')) ?> <span class="mono"><?= e($chain) ?></span></div>

<h2><?= e(t('esign.ev_recipients')) ?></h2>
<div><?= e(implode(', ', array_map(fn ($r) => $r['name'] . ($r['email'] ? ' <' . $r['email'] . '>' : ''), $recipients))) ?></div>

<h2><?= e(t('esign.ev_fingerprints')) ?></h2>
<div class="fp mono">
    <?php foreach ($fingerprints as $i => $fp): ?><div><?= e(t('esign.ev_page_n', ['n' => $i + 1])) ?> <?= e($fp) ?></div><?php endforeach ?>
</div>

<h2><?= e(t('esign.ev_how')) ?></h2>
<div><?= e(t('esign.ev_how_text', ['url' => $verify])) ?></div>
</body></html>
