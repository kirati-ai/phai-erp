<?php

// e-Document Sign with people outside the company, through a running Phai Sign service (its own project,
// phai-sign): registering this machine, the settings only ADMIN sees, an envelope whose first step is an outsider,
// their mail and code, signing at the service with a typed name, the result pulled back with the service's times,
// the next step, the final document mailed to the outsider through the service, the outside witness, and withdrawing.
// Needs the service with dev = true (mail written to storage/outbox) and register.open; skipped when it is not there.
//   PHAI_SIGN_URL (default http://localhost/phai-sign), PHAI_SIGN_OUTBOX (default ../phai-sign/storage/outbox)
// Run through tests/e2e/run.php (needs a fresh install). Never prints passwords.

declare(strict_types=1);

require __DIR__ . '/lib.php';

$svc = rtrim(getenv('PHAI_SIGN_URL') ?: 'http://localhost/phai-sign', '/');
$outbox = getenv('PHAI_SIGN_OUTBOX') ?: dirname(PHAI_ROOT) . '/phai-sign/storage/outbox';
[$up] = raw('GET', "$svc/");
if ($up !== 200 || !is_dir($outbox)) {
    echo "  ok   skipped: no Phai Sign service at $svc (or no outbox at $outbox)\n";
    finish();
}

/** A PDF of one A4 page saying $text. */
function pdf1(string $text): string
{
    $c = "BT /F1 24 Tf 72 700 Td ($text) Tj ET";
    $o = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        '<< /Length ' . strlen($c) . " >>\nstream\n$c\nendstream", '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
    $out = "%PDF-1.4\n";
    $off = [];
    foreach ($o as $i => $b) {
        $off[] = strlen($out);
        $out .= ($i + 1) . " 0 obj\n$b\nendobj\n";
    }
    $x = strlen($out);
    $out .= "xref\n0 6\n0000000000 65535 f \n" . implode('', array_map(fn ($v) => sprintf("%010d 00000 n \n", $v), $off));
    return $out . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$x\n%%EOF\n";
}

/** A transparent PNG with a line, like the signing page draws (no GD needed). */
function png(int $w = 300, int $h = 90): string
{
    $raw = '';
    for ($y = 0; $y < $h; $y++) {
        $raw .= "\0";
        for ($x = 0; $x < $w; $x++) {
            $raw .= (int) ($x * $h / $w) === $y ? "\x1d\x2b\x7a\xff" : "\0\0\0\0";
        }
    }
    $chunk = fn (string $t, string $d) => pack('N', strlen($d)) . $t . $d . pack('N', crc32($t . $d));
    return 'data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 6, 0, 0, 0))
        . $chunk('IDAT', gzcompress($raw)) . $chunk('IEND', ''));
}

/** The newest mail to $to since $after whose text matches $like: [subject, text] (mails in the same second have no order). */
function mail_to(string $to, string $after, string $like): ?array
{
    global $outbox;
    $files = glob("$outbox/*.json") ?: [];
    rsort($files);
    foreach ($files as $f) {
        $m = json_decode((string) file_get_contents($f), true);
        if (($m['to'] ?? '') === $to && ($m['at'] ?? '') >= $after && preg_match($like, (string) ($m['text'] ?? ''))) {
            return [$m['subject'], $m['text']];
        }
    }
    return null;
}

/** A JSON post to the service as the signer's browser would, with its cookies. */
function svc_post(string $url, array $body, array &$cookies): array
{
    $h = ['Content-Type: application/json'];
    if ($cookies) {
        $h[] = 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($cookies), $cookies));
    }
    [$status, $head, $out] = raw('POST', $url, $h, json_encode($body, JSON_UNESCAPED_UNICODE));
    foreach ($head as $line) {
        if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $line, $m)) {
            $cookies[$m[1]] = $m[2];
        }
    }
    return [$status, json_decode($out, true)];
}

$start = date('Y-m-d H:i:s');
$tag = bin2hex(random_bytes(3));
$email = "jane.$tag@example.test";
$data = fn (string $bytes) => 'data:application/pdf;base64,' . base64_encode($bytes);
$a = login('admin', $pw);
$a->req('POST', '/api/roles', ['role_code' => 'PLAIN', 'role_desc' => 'No rights']);
$a->req('POST', '/api/users', ['us_userid' => 'plain', 'us_name' => 'Plain User', 'us_email' => 'plain@example.test', 'us_role' => 'PLAIN', 'password' => $pw]);
$plain = login('plain', $pw);

// ------------------------------------------------------------------ settings
$r = $plain->req('GET', '/api/envelopes/remote');
ok('only ADMIN sees the service settings', $r[0] === 403, $r[0]);
$r = $a->req('GET', '/api/envelopes/remote');
ok('the service is off and not registered at first', $r[0] === 200 && $r[1]['data']['on'] === false && $r[1]['data']['registered'] === false, $r[1]);
$r = $a->req('PUT', '/api/envelopes/remote', ['on' => true, 'url' => $svc, 'days' => 7, 'witness' => true]);
ok('it cannot be turned on before registering', $r[0] === 422, $r[1]);

// a draft with an outsider cannot go while the service is off
$nbr0 = $a->req('POST', '/api/envelopes', ['env_title' => 'Draft with outsider'])[1]['data']['nbr'];
$a->req('POST', "/api/envelopes/$nbr0/files", ['kind' => 'M', 'name' => 'c.pdf', 'data' => $data(pdf1('Draft'))]);
$r = $a->req('PUT', "/api/envelopes/$nbr0", ['env_title' => 'Draft with outsider', 'signers' => [['key' => 'e', 'step' => 1, 'role' => 'S', 'kind' => 'E', 'name' => 'Ann', 'email' => "ann.$tag@example.test"]],
    'fields' => [['signer' => 'e', 'page' => 1, 'type' => 'signature', 'x' => 300, 'y' => 600, 'w' => 150, 'h' => 40], ['signer' => 'e', 'page' => 1, 'type' => 'stamp', 'x' => 60, 'y' => 600, 'w' => 80, 'h' => 80]]]);
$problems = $r[1]['data']['problems'] ?? [];
ok('while the service is off, an outsider is a problem', count(array_filter($problems, fn ($p) => str_contains($p, 'Phai Sign'))) === 1, $problems);
ok('a company stamp field for an outsider is a problem', count(array_filter($problems, fn ($p) => str_contains($p, 'Ann'))) >= 2, $problems);

$r = $a->req('POST', '/api/envelopes/remote/register', ['url' => $svc, 'name' => "E2E Co $tag", 'contact' => 'it@example.test']);
ok('register this machine with the service', $r[0] === 200 && $r[1]['data']['registered'] === true && strlen((string) $r[1]['data']['server']) === 24, $r[1]);
ok('the key never comes back to the page', !str_contains(json_encode($r[1]), (string) one('SELECT env_remote_key FROM env_ctrl WHERE ctrl_id = 1')));
$r = $a->req('PUT', '/api/envelopes/remote', ['on' => true, 'url' => $svc, 'days' => 7, 'witness' => true]);
ok('turn it on', $r[0] === 200 && $r[1]['data']['on'] === true, $r[1]);
$r = $a->req('POST', '/api/envelopes/remote/ping');
ok('the signed connection works', $r[0] === 200 && ($r[1]['data']['ok'] ?? false) === true, $r[1]);

// ------------------------------------------------------------------ outsider first, then admin
$nbr = $a->req('POST', '/api/envelopes', ['env_title' => "สัญญาที่ปรึกษา $tag"])[1]['data']['nbr'];
$a->req('POST', "/api/envelopes/$nbr/files", ['kind' => 'M', 'name' => 'contract.pdf', 'data' => $data(pdf1('Contract'))]);
$a->req('POST', "/api/envelopes/$nbr/files", ['kind' => 'A', 'name' => 'annex.pdf', 'data' => $data(pdf1('Annex'))]);
$r = $a->req('PUT', "/api/envelopes/$nbr", ['env_title' => "สัญญาที่ปรึกษา $tag", 'env_note' => 'Please sign this week', 'signers' => [
    ['key' => 'e', 'step' => 1, 'role' => 'S', 'kind' => 'E', 'name' => 'Jane Outsider', 'email' => $email, 'position' => 'Consultant'],
    ['key' => 'a', 'step' => 2, 'role' => 'S', 'kind' => 'U', 'userid' => 'admin'],
], 'fields' => [
    ['signer' => 'e', 'page' => 1, 'type' => 'signature', 'x' => 330, 'y' => 600, 'w' => 160, 'h' => 50],
    ['signer' => 'e', 'page' => 1, 'type' => 'text', 'x' => 330, 'y' => 670, 'w' => 160, 'h' => 18, 'props' => ['required' => true, 'label' => 'Tax ID']],
    ['signer' => 'a', 'page' => 1, 'type' => 'signature', 'x' => 60, 'y' => 600, 'w' => 160, 'h' => 50],
    ['signer' => null, 'page' => 1, 'type' => 'mark', 'x' => 420, 'y' => 40, 'w' => 120, 'h' => 30, 'props' => ['text' => 'ลับ']],
]]);
ok('with the service on, the envelope can go', ($r[1]['data']['problems'] ?? ['x']) === [], $r[1]['data']['problems'] ?? $r[1]);
$fieldIds = array_column(array_filter($r[1]['data']['fields'], fn ($f) => $f['envx_type'] === 'text'), 'envx_id');
$r = $a->req('POST', "/api/envelopes/$nbr/send");
$jane = array_values(array_filter($r[1]['data']['signers'] ?? [], fn ($s) => $s['envs_kind'] === 'E'))[0] ?? [];
ok('sending puts the outsider at the service', $r[0] === 200 && strlen((string) ($jane['envs_ext'] ?? '')) === 24 && $jane['envs_ext_state'] === 'R' && $jane['envs_recv_at'], $jane);
ok('our own mail does not go to the outsider', one("SELECT JSON_EXTRACT(envh_detail, '$.mail') FROM envh_hist WHERE envh_nbr = '$nbr' AND envh_event = 'received'") === 'false');

$invite = mail_to($email, $start, '#/s/#');
ok('the outsider got the service\'s mail with a link', $invite !== null && str_contains($invite[0], "สัญญาที่ปรึกษา $tag"), $invite);
preg_match('#/s/([A-Za-z0-9_-]+)#', (string) ($invite[1] ?? ''), $m);
$link = "$svc/s/" . ($m[1] ?? 'none');
[$c, , $page] = raw('GET', $link);
ok('the link opens the code step', $c === 200 && str_contains($page, 'send-code'));
$jar = [];
svc_post("$link/code", [], $jar);
preg_match('/^(\d{6})$/m', (string) (mail_to($email, $start, '/^\d{6}$/m')[1] ?? ''), $m);
[$c] = svc_post("$link/verify", ['code' => $m[1] ?? ''], $jar);
ok('the code from the mail opens the document', $c === 200 && $jar);
[$c, , $doc] = raw('GET', "$link/file/0", ['Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($jar), $jar))]);
ok('the document the outsider sees is a PDF', $c === 200 && str_starts_with($doc, '%PDF-'));
[$c] = svc_post("$link/sign", ['values' => [$fieldIds[0] => '0105561234567'], 'typed' => 'เจนนิเฟอร์ ฤทธิ์ณรงค์', 'font' => 'Charm', 'image' => png(), 'consent' => true], $jar);
ok('the outsider signs at the service', $c === 200);

$r = $a->req('POST', '/api/envelopes/sync');
ok('asking for results brings the signature back', $r[0] === 200 && ($r[1]['data']['signed'] ?? 0) === 1, $r[1]);
$r = $a->req('GET', "/api/envelopes/$nbr");
$d = $r[1]['data'];
$jane = array_values(array_filter($d['signers'], fn ($s) => $s['envs_kind'] === 'E'))[0];
ok('the outsider is signed, with the service\'s times', $jane['envs_status'] === 'S' && $jane['envs_open_at'] && $jane['envs_sign_at'] && $jane['envs_ip'], $jane);
ok('their signature image came back', str_starts_with((string) $jane['envs_image'], 'data:image/png;base64,'));
ok('what they typed in their field came back', one("SELECT envx_value FROM envx_det WHERE envx_id = {$fieldIds[0]}") === '0105561234567');
ok('the service deleted its copy', $jane['envs_ext_state'] === 'D', $jane['envs_ext_state']);
$signed = array_values(array_filter($d['events'], fn ($e) => $e['event'] === 'signed'))[0] ?? [];
ok('the signing event names the typed name and style', ($signed['detail']['remote']['typed'] ?? '') === 'เจนนิเฟอร์ ฤทธิ์ณรงค์' && ($signed['detail']['remote']['font'] ?? '') === 'Charm', $signed);
$by = db("SELECT envh_userid FROM envh_hist WHERE envh_nbr = '$nbr' AND envh_event IN ('opened', 'signed') AND envh_signer = {$jane['envs_id']}");
ok('those events are not put down to whoever asked for results', count($by) === 2 && $by[0]['envh_userid'] === null && $by[1]['envh_userid'] === null, $by);
ok('the hash chain is whole', $d['chainBroken'] === null);
ok('step 2 is the admin\'s turn now', $d['can']['sign'] === true && (int) $d['env']['env_step'] === 2);

$r = $a->req('POST', "/api/envelopes/$nbr/sign", ['values' => [], 'image' => png()]);
$d = $r[1]['data'] ?? [];
ok('the admin signs and the envelope is done', $r[0] === 200 && $d['env']['env_status'] === 'C', $r[1]);
ok('the final document went to the outsider through the service', strlen((string) $d['env']['env_remote_final']) === 24, $d['env']['env_remote_final'] ?? null);
ok('its SHA-256 is recorded at the service as a witness', strlen((string) $d['env']['env_witness_id']) === 24 && $d['env']['env_witness_at'], $d['env']);
$final = mail_to($email, $start, '#/d/#');
ok('the outsider got the download link', $final !== null && str_contains($final[1], (string) $d['env']['env_final_hash']), $final);
preg_match('#/d/([A-Za-z0-9_-]+)#', (string) ($final[1] ?? ''), $m);
[$c, , $pdf] = raw('GET', "$svc/d/" . ($m[1] ?? 'none') . '/file');
ok('and downloads exactly the final document', $c === 200 && hash('sha256', $pdf) === $d['env']['env_final_hash']);
[, , $w] = raw('GET', "$svc/api/public/witness?sha256=" . $d['env']['env_final_hash']);
$w = json_decode($w, true);
ok('the public check at the service finds it, under this company\'s name', ($w['found'] ?? false) === true && ($w['by'] ?? '') === "E2E Co $tag", $w);
$r = $a->req('POST', '/api/envelopes/verify', ['data' => 'data:application/pdf;base64,' . base64_encode($pdf)]);
ok('our own check page shows the witness too', ($r[1]['data']['result'] ?? '') === 'match' && ($r[1]['data']['summary']['witness']['id'] ?? '') === $d['env']['env_witness_id'], $r[1]);
$ev = (string) one("SELECT COUNT(*) FROM envh_hist WHERE envh_nbr = '$nbr' AND envh_event IN ('remote_sent', 'remote_final', 'witnessed')");
ok('sent, final and witness are events', $ev === '3', $ev);

// ------------------------------------------------------------------ withdrawing
$nbr2 = $a->req('POST', '/api/envelopes', ['env_title' => "Withdraw $tag"])[1]['data']['nbr'];
$a->req('POST', "/api/envelopes/$nbr2/files", ['kind' => 'M', 'name' => 'w.pdf', 'data' => $data(pdf1('Withdraw'))]);
$a->req('PUT', "/api/envelopes/$nbr2", ['env_title' => "Withdraw $tag", 'signers' => [['key' => 'e', 'step' => 1, 'role' => 'A', 'kind' => 'E', 'name' => 'Bob Out', 'email' => "bob.$tag@example.test"]],
    'fields' => [['signer' => 'e', 'page' => 1, 'type' => 'signature', 'x' => 300, 'y' => 600, 'w' => 150, 'h' => 40]]]);
$a->req('POST', "/api/envelopes/$nbr2/send");
$r = $a->req('POST', "/api/envelopes/$nbr2/remind");
ok('a reminder sends the outsider a new link', ($r[1]['data']['mailed'] ?? 0) === 1, $r[1]);
$r = $a->req('POST', "/api/envelopes/$nbr2/recall");
$bob = $r[1]['data']['signers'][0] ?? [];
ok('withdrawing the envelope withdraws it at the service', $bob['envs_ext_state'] === 'W', $bob);
$told = mail_to("bob.$tag@example.test", $start, "/Withdraw $tag/");
ok('and the outsider is told', $told !== null && str_contains($told[0], "Withdraw $tag"), $told);

finish();
