<?php

// e-Document Sign: an envelope with a main document and an attachment, signers in two steps, fields placed and copied
// to every page, sending, the turn order (received / opened / signed), signing with a drawn and a saved signature, the
// final document (page count, footer, evidence) and checking files against it, the hash chain, declining, withdrawing,
// changing a signer, and who may see what. Run through tests/e2e/run.php (needs a fresh install). Never prints passwords.

declare(strict_types=1);

require __DIR__ . '/lib.php';
require_once PHAI_ROOT . '/src/autoload.php';

use Phai\Core\PdfKit\Reader;

/** A PDF of $n A4 pages, each saying "Page i" (classic cross-reference). */
function pdf(int $n, string $extraTrailer = ''): string
{
    $objs = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
    $kids = [];
    for ($i = 1; $i <= $n; $i++) {
        $content = "BT /F1 24 Tf 72 700 Td (Page $i) Tj ET";
        $c = count($objs) + 1;
        $objs[$c] = '<< /Length ' . strlen($content) . " >>\nstream\n$content\nendstream";
        $p = count($objs) + 1;
        $objs[$p] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $c 0 R >>";
        $kids[] = "$p 0 R";
    }
    $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . "] /Count $n >>";
    $out = "%PDF-1.4\n";
    $off = [];
    foreach ($objs as $k => $body) {
        $off[$k] = strlen($out);
        $out .= "$k 0 obj\n$body\nendobj\n";
    }
    $x = strlen($out);
    $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($off as $o) {
        $out .= sprintf("%010d 00000 n \n", $o);
    }
    return $out . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R $extraTrailer>>\nstartxref\n$x\n%%EOF\n";
}

/** A small transparent PNG with a line, made without GD. */
function png(int $w = 120, int $h = 40): string
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

$data = fn (string $bytes) => 'data:application/pdf;base64,' . base64_encode($bytes);
$a = login('admin', $pw);
// signing needs no right of its own: the signers have a role that may do nothing else
$a->req('POST', '/api/roles', ['role_code' => 'PLAIN', 'role_desc' => 'No rights']);
foreach ([['mgr', 'Manager Mana', 'mgr@example.test'], ['boss', 'Boss Somchai', 'boss@example.test'], ['amy', 'Amy Assistant', 'amy@example.test'], ['outsider', 'Not In It', 'out@example.test']] as [$id, $name, $mail]) {
    $a->req('POST', '/api/users', ['us_userid' => $id, 'us_name' => $name, 'us_email' => $mail, 'us_role' => 'PLAIN', 'password' => $pw]);
}
$mgr = login('mgr', $pw);
$boss = login('boss', $pw);
$out = login('outsider', $pw);

ok('page /envelopes', $a->page('/envelopes')[0] === 200);
ok('page /envelopes/verify', $a->page('/envelopes/verify')[0] === 200);
$r = $mgr->req('POST', '/api/envelopes', ['env_title' => 'Not allowed']);
ok('someone without the add right cannot make an envelope', $r[0] === 403, $r[1]);

// ------------------------------------------------------------------ draft
$r = $a->req('POST', '/api/envelopes', ['env_title' => 'สัญญาจ้างผู้รับเหมา']);
$nbr = $r[1]['data']['nbr'] ?? '';
ok('a new envelope gets a number', $r[0] === 200 && str_starts_with($nbr, 'ENV'), $r[1]);
ok('the editor opens', $a->page("/envelopes/$nbr/edit")[0] === 200);

$r = $a->req('POST', "/api/envelopes/$nbr/files", ['kind' => 'M', 'name' => 'locked.pdf', 'data' => $data(pdf(1, '/Encrypt << /Filter /Standard >> '))]);
ok('a password-protected PDF is refused', $r[0] === 422 && isset($r[1]['fields']['file']), $r[1]);
$r = $a->req('POST', "/api/envelopes/$nbr/files", ['kind' => 'M', 'name' => 'notes.txt', 'data' => 'data:text/plain;base64,' . base64_encode('hello')]);
ok('only PDF and pictures', $r[0] === 422, $r[1]);
$r = $a->req('POST', "/api/envelopes/$nbr/files", ['kind' => 'M', 'name' => 'contract.pdf', 'data' => $data(pdf(3))]);
ok('a PDF is taken as the main document, 3 pages', $r[0] === 200 && ($r[1]['data']['files'][0]['envf_pages'] ?? 0) === 3, $r[1]);
$r = $a->req('POST', "/api/envelopes/$nbr/files", ['kind' => 'A', 'name' => 'quote.pdf', 'data' => $data(pdf(2))]);
ok('an attachment', $r[0] === 200 && count($r[1]['data']['files']) === 2, $r[1]);
$files = $r[1]['data']['files'];
ok('the stored file is the cleaned one, with both fingerprints', $files[0]['envf_hash'] !== $files[0]['envf_src_hash'] && strlen($files[0]['envf_hash']) === 64);
ok('the editor knows the pages of the main document only', count($r[1]['data']['pages']) === 3);
ok('a draft lists what stops sending', in_array('ยังไม่มีผู้ลงนามหรือผู้อนุมัติ', $r[1]['data']['problems'], true) || count($r[1]['data']['problems']) > 0, $r[1]['data']['problems']);

$signers = [
    ['key' => 'm', 'step' => 1, 'role' => 'A', 'kind' => 'U', 'userid' => 'mgr', 'position' => 'ผู้จัดการฝ่ายจัดซื้อ'],
    ['key' => 'b', 'step' => 2, 'role' => 'S', 'kind' => 'U', 'userid' => 'boss', 'position' => 'กรรมการผู้จัดการ'],
    ['key' => 'c', 'step' => 1, 'role' => 'C', 'kind' => 'U', 'userid' => 'amy'],
];
$r = $a->req('PUT', "/api/envelopes/$nbr", ['env_title' => 'สัญญาจ้างผู้รับเหมา', 'signers' => $signers, 'fields' => []]);
ok('signers saved, with names from the users', $r[0] === 200 && ($r[1]['data']['signers'][0]['envs_name'] ?? '') === 'Manager Mana', $r[1]);
ok('without signature fields it cannot be sent', count(array_filter($r[1]['data']['problems'], fn ($p) => str_contains($p, 'Manager Mana'))) === 1, $r[1]['data']['problems']);
$r = $a->req('POST', "/api/envelopes/$nbr/send");
ok('sending is refused while problems remain', $r[0] === 422, $r[1]);

$fields = [];
foreach ([1, 2, 3] as $p) {
    $fields[] = ['signer' => 'm', 'page' => $p, 'type' => 'initials', 'x' => 500, 'y' => 780, 'w' => 50, 'h' => 30, 'link' => 'ini1'];
}
$fields[] = ['signer' => 'm', 'page' => 3, 'type' => 'signature', 'x' => 60, 'y' => 600, 'w' => 150, 'h' => 40];
$fields[] = ['signer' => 'm', 'page' => 3, 'type' => 'text', 'x' => 60, 'y' => 560, 'w' => 150, 'h' => 18, 'props' => ['required' => true, 'label' => 'PO no.']];
$fields[] = ['signer' => 'm', 'page' => 3, 'type' => 'checkbox', 'x' => 220, 'y' => 562, 'w' => 14, 'h' => 14];
$fields[] = ['signer' => 'b', 'page' => 3, 'type' => 'signature', 'x' => 330, 'y' => 600, 'w' => 150, 'h' => 40];
$fields[] = ['signer' => 'b', 'page' => 3, 'type' => 'name', 'x' => 330, 'y' => 660, 'w' => 150, 'h' => 16];
$fields[] = ['signer' => 'b', 'page' => 3, 'type' => 'date', 'x' => 330, 'y' => 680, 'w' => 100, 'h' => 16];
$fields[] = ['signer' => 'b', 'page' => 3, 'type' => 'timeline', 'x' => 60, 'y' => 700, 'w' => 260, 'h' => 14];
$fields[] = ['signer' => null, 'page' => 1, 'type' => 'mark', 'x' => 400, 'y' => 40, 'w' => 120, 'h' => 30, 'props' => ['text' => 'ด่วน', 'color' => '#c92a2a', 'bold' => true]];
$fields[] = ['signer' => null, 'page' => 1, 'type' => 'label', 'x' => 60, 'y' => 40, 'w' => 200, 'h' => 20, 'props' => ['text' => 'ต้นฉบับ']];
$fields[] = ['signer' => 'c', 'page' => 1, 'type' => 'signature', 'x' => 0, 'y' => 0, 'w' => 50, 'h' => 20]; // a reader has no fields: dropped
$fields[] = ['signer' => 'm', 'page' => 9, 'type' => 'signature', 'x' => 0, 'y' => 0, 'w' => 50, 'h' => 20]; // no page 9: dropped
$fields[] = ['signer' => 'm', 'page' => 1, 'type' => 'script', 'x' => 0, 'y' => 0, 'w' => 50, 'h' => 20]; // unknown kind: dropped
$fields[] = ['signer' => 'b', 'page' => 2, 'type' => 'text', 'x' => 590, 'y' => 840, 'w' => 100, 'h' => 30]; // off the page: pulled back on
$r = $a->req('PUT', "/api/envelopes/$nbr", ['env_title' => 'สัญญาจ้างผู้รับเหมา', 'env_note' => 'กรุณาเซ็นภายในวันศุกร์', 'signers' => $signers, 'fields' => $fields]);
$saved = $r[1]['data']['fields'] ?? [];
ok('fields saved; ones for readers, missing pages and unknown kinds are dropped', $r[0] === 200 && count($saved) === 13, count($saved));
$off = array_values(array_filter($saved, fn ($f) => $f['envx_page'] == 2 && $f['envx_type'] === 'text'))[0] ?? null;
ok('a field off the page is moved back onto it', $off && $off['envx_x'] + $off['envx_w'] <= 595 && $off['envx_y'] + $off['envx_h'] <= 842, $off);
ok('linked copies keep their link', count(array_filter($saved, fn ($f) => $f['envx_link'] === 'ini1')) === 3);
ok('the draft is ready', $r[1]['data']['problems'] === [], $r[1]['data']['problems']);
ok('someone else cannot change the draft', $out->req('PUT', "/api/envelopes/$nbr", ['env_title' => 'x'])[0] === 404);
ok('nor see it', $out->req('GET', "/api/envelopes/$nbr")[0] === 404 && $out->page("/envelopes/$nbr/files/{$files[0]['envf_id']}")[0] === 404);

// ------------------------------------------------------------------ send: step 1
$r = $a->req('POST', "/api/envelopes/$nbr/send");
ok('sent', $r[0] === 200 && $r[1]['data']['env']['env_status'] === 'S', $r[1]);
$st = array_column($r[1]['data']['signers'], 'envs_status', 'envs_userid');
ok('step 1 has it, step 2 waits, the reader waits for the end', $st === ['mgr' => 'R', 'amy' => 'W', 'boss' => 'W'] || ($st['mgr'] === 'R' && $st['boss'] === 'W' && $st['amy'] === 'W'), $st);
ok('a sent envelope cannot be edited', $a->req('PUT', "/api/envelopes/$nbr", ['env_title' => 'x'])[0] === 409);
ok('boss does not see it before his turn', $boss->req('GET', "/api/envelopes/$nbr")[0] === 404);
ok('boss cannot sign before his turn', in_array($boss->req('POST', "/api/envelopes/$nbr/sign", ['values' => []])[0], [404, 409], true));
$list = $mgr->req('GET', '/api/envelopes')[1]['data'];
ok('it waits on mgr\'s list, not opened yet', count($list['waiting']) === 1 && $list['waiting'][0]['envs_open_at'] === null, $list['waiting']);
ok('mgr opens the signing page', $mgr->page("/envelopes/$nbr/sign")[0] === 200);
$r = $mgr->req('GET', "/api/envelopes/$nbr");
$me = array_values(array_filter($r[1]['data']['signers'], fn ($s) => $s['envs_userid'] === 'mgr'))[0];
ok('opening is recorded', $me['envs_open_at'] !== null && $me['envs_recv_at'] !== null, $me);

$mine = array_values(array_filter($r[1]['data']['fields'], fn ($f) => $f['envx_signer'] === (int) $me['envs_id']));
$textId = array_values(array_filter($mine, fn ($f) => $f['envx_type'] === 'text'))[0]['envx_id'];
$boxId = array_values(array_filter($mine, fn ($f) => $f['envx_type'] === 'checkbox'))[0]['envx_id'];
$r = $mgr->req('POST', "/api/envelopes/$nbr/sign", ['values' => [], 'image' => png()]);
ok('a required field must be filled', $r[0] === 422 && isset($r[1]['fields']["f$textId"]), $r[1]);
$r = $mgr->req('POST', "/api/envelopes/$nbr/sign", ['values' => [$textId => 'PO000123', $boxId => '1'], 'image' => '']);
ok('no saved and no drawn signature: refused', $r[0] === 422 && isset($r[1]['fields']['image']), $r[1]);
$r = $mgr->req('POST', "/api/envelopes/$nbr/sign", ['values' => [$textId => 'PO000123', $boxId => '1'], 'image' => 'data:image/png;base64,' . base64_encode('not a png')]);
ok('a drawn signature must be a real PNG', $r[0] === 422, $r[1]);
$r = $mgr->req('POST', "/api/envelopes/$nbr/sign", ['values' => [$textId => 'PO000123', $boxId => '1'], 'image' => png()]);
ok('mgr approves with a drawn signature', $r[0] === 200, $r[1]);
$st = array_column($r[1]['data']['signers'], 'envs_status', 'envs_userid');
ok('step 2 starts: boss has it', $st['mgr'] === 'S' && $st['boss'] === 'R', $st);
ok('mgr cannot sign twice', $mgr->req('POST', "/api/envelopes/$nbr/sign", ['values' => [], 'image' => png()])[0] === 409);

// ------------------------------------------------------------------ step 2: boss signs with his saved signature
$boss->req('PUT', '/api/me/sign', ['image' => png(200, 60)]);
ok('boss sees it now', $boss->req('GET', "/api/envelopes/$nbr")[0] === 200);
$r = $boss->req('POST', "/api/envelopes/$nbr/sign", ['values' => [], 'image' => '']);
ok('boss signs with his saved signature; the envelope is complete', $r[0] === 200 && $r[1]['data']['env']['env_status'] === 'C', $r[1]);
$env = $r[1]['data']['env'];
ok('the final document has its page count: 3 + 2 + evidence', $env['env_pages'] >= 6, $env['env_pages']);
$final = $boss->page("/envelopes/$nbr/final")[1];
ok('the final document is a PDF of that many pages', is_string($final) && str_starts_with($final, '%PDF') && count((new Reader($final))->pages()) === (int) $env['env_pages']);
ok('its SHA-256 is the one kept', hash('sha256', (string) $final) === $env['env_final_hash']);
ok('the reader may see it now', login('amy', $pw)->req('GET', "/api/envelopes/$nbr")[0] === 200);
ok('an outsider still may not', $out->page("/envelopes/$nbr/final")[0] === 404);
$events = array_column($r[1]['data']['events'] ?? [], 'event');
$view = $a->req('GET', "/api/envelopes/$nbr")[1]['data'];
$events = array_column($view['events'], 'event');
ok('every step is in the history', !array_diff(['created', 'sent', 'received', 'opened', 'signed', 'completed', 'delivered'], $events), $events);
ok('the hash chain is whole', $view['chainBroken'] === null);
$times = array_values(array_filter($view['signers'], fn ($s) => $s['envs_userid'] === 'boss'))[0];
ok('boss received, opened and signed, in that order', $times['envs_recv_at'] <= $times['envs_open_at'] && $times['envs_open_at'] <= $times['envs_sign_at'], $times);

// ------------------------------------------------------------------ checking files
$r = $out->req('POST', '/api/envelopes/verify', ['data' => $data((string) $final)]);
ok('the final file checks as unchanged, even for someone not in it', $r[0] === 200 && $r[1]['data']['result'] === 'match' && $r[1]['data']['summary']['full'] === false, $r[1]);
$r = $a->req('POST', '/api/envelopes/verify', ['data' => $data(pdf(3))]);
ok('the original upload is known as the original', $r[1]['data']['result'] === 'original', $r[1]);
$resaved = preg_replace('/%%EOF\s*$/', "%%EOF\n% saved again\n", (string) $final);
$r = $a->req('POST', '/api/envelopes/verify', ['data' => $data($resaved)]);
ok('saved again but showing the same: every page matches', $r[1]['data']['result'] === 'same-pages', $r[1]);
$changed = (new Reader((string) $final));
$tampered = str_replace('(Page 2)', '(Page X)', (string) $final);
if ($tampered === $final) {
    // the page text is compressed in the final file: change the page through PdfKit instead
    $w = new Phai\Core\PdfKit\Writer();
    $orig = new Reader(pdf(3));
    foreach ($changed->pages() as $i => $p) {
        $w->addPage($i === 1 ? $orig : $changed, $i === 1 ? $orig->pages()[2] : $p, ['flatten' => false]);
    }
    $tampered = $w->output(['PhaiEnvelope' => $nbr]);
}
$r = $a->req('POST', '/api/envelopes/verify', ['data' => $data($tampered)]);
ok('a changed page is found', $r[1]['data']['result'] === 'changed' && in_array(2, $r[1]['data']['pages'], true), $r[1]);
ok('the check page opens on an envelope from its QR link', str_contains((string) $a->page('/envelopes/verify?n=' . $nbr)[1], $nbr));

// ------------------------------------------------------------------ the chain breaks when a row is changed
db("UPDATE envh_hist SET envh_ip = '10.9.9.9' WHERE envh_nbr = '$nbr' AND envh_event = 'opened' LIMIT 1");
ok('changing an event breaks the chain', $a->req('GET', "/api/envelopes/$nbr")[1]['data']['chainBroken'] !== null);

// ------------------------------------------------------------------ decline, withdraw, change a signer
$make = function (string $title, array $who) use ($a, $data): string {
    $nbr = $a->req('POST', '/api/envelopes', ['env_title' => $title])[1]['data']['nbr'];
    $a->req('POST', "/api/envelopes/$nbr/files", ['kind' => 'M', 'name' => 'a.pdf', 'data' => $data(pdf(1))]);
    $s = [];
    $f = [];
    foreach ($who as $i => $u) {
        $s[] = ['key' => "k$i", 'step' => $i + 1, 'role' => 'S', 'kind' => 'U', 'userid' => $u];
        $f[] = ['signer' => "k$i", 'page' => 1, 'type' => 'signature', 'x' => 60 + 160 * $i, 'y' => 600, 'w' => 150, 'h' => 40];
    }
    $a->req('PUT', "/api/envelopes/$nbr", ['env_title' => $title, 'signers' => $s, 'fields' => $f]);
    $a->req('POST', "/api/envelopes/$nbr/send");
    return $nbr;
};
$n2 = $make('Decline me', ['mgr', 'boss']);
ok('declining needs a reason', $mgr->req('POST', "/api/envelopes/$n2/decline", ['note' => ''])[0] === 422);
$r = $mgr->req('POST', "/api/envelopes/$n2/decline", ['note' => 'ราคาสูงเกินไป']);
ok('declined: the envelope stops', $r[0] === 200 && $r[1]['data']['env']['env_status'] === 'X', $r[1]);
ok('boss never gets it', $boss->req('GET', "/api/envelopes/$n2")[0] === 404);

$n3 = $make('Withdraw me', ['mgr']);
ok('only the sender withdraws', $mgr->req('POST', "/api/envelopes/$n3/recall")[0] === 404);
$r = $a->req('POST', "/api/envelopes/$n3/recall");
ok('withdrawn', $r[0] === 200 && $r[1]['data']['env']['env_status'] === 'W', $r[1]);
ok('nobody can sign a withdrawn envelope', $mgr->req('POST', "/api/envelopes/$n3/sign", ['values' => [], 'image' => png()])[0] !== 200);

$n4 = $make('Change the signer', ['mgr']);
$sid = array_values(array_filter($a->req('GET', "/api/envelopes/$n4")[1]['data']['signers'], fn ($s) => $s['envs_userid'] === 'mgr'))[0]['envs_id'];
$r = $a->req('POST', "/api/envelopes/$n4/replace", ['signer' => $sid, 'userid' => 'boss']);
ok('a signer who has not signed is replaced; the new one has it now', $r[0] === 200 && count(array_filter($r[1]['data']['signers'], fn ($s) => $s['envs_userid'] === 'boss' && $s['envs_status'] === 'R')) === 1, $r[1]);
ok('the one replaced cannot sign', $mgr->req('POST', "/api/envelopes/$n4/sign", ['values' => [], 'image' => png()])[0] !== 200);
$r = $boss->req('POST', "/api/envelopes/$n4/sign", ['values' => [], 'image' => '']);
ok('the new signer signs and it completes', $r[0] === 200 && $r[1]['data']['env']['env_status'] === 'C', $r[1]);

$n5 = $a->req('POST', '/api/envelopes', ['env_title' => 'Throw away'])[1]['data']['nbr'];
ok('a draft can be deleted', $a->req('DELETE', "/api/envelopes/$n5")[0] === 200 && $a->req('GET', "/api/envelopes/$n5")[0] === 404);
ok('a sent one cannot', $a->req('DELETE', "/api/envelopes/$nbr")[0] === 409);

$lists = $boss->req('GET', '/api/envelopes')[1]['data'];
ok('boss\'s documents list the finished envelopes he is in', count($lists['done']) === 2, count($lists['done']));
ok('pages of the envelope open for those in it', $boss->page("/envelopes/$nbr")[0] === 200);

finish();
