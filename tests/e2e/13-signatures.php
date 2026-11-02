<?php

// Signatures on printed documents: own signature image, boxes by kind (ours / approval / the other side), requests to
// people who may open the document, signing, rejecting, withdrawing, a change after signing, and the company stamp. @demo
// Run through tests/e2e/run.php (needs a fresh install). Never prints passwords.

declare(strict_types=1);

require __DIR__ . '/lib.php';

/** A small transparent PNG with a diagonal line, made without GD. */
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

$a = login('admin', $pw);
$inv = (string) one("SELECT ih_inv_nbr FROM ih_mstr WHERE ih_type = 'IN' AND ih_status <> 'C' ORDER BY ih_inv_nbr LIMIT 1");
$path = "/invoices/$inv/print";
$state = fn (Client $c) => $c->req('GET', '/api/signatures/people?path=' . rawurlencode($path));

// two people: one may print invoices, one may only look at them
$a->req('POST', '/api/roles', ['role_code' => 'SIGNER', 'role_desc' => 'Signs invoices']);
$a->req('PUT', '/api/roles/SIGNER/perms', ['perms' => ['invoices' => ['view', 'print']]]);
$a->req('POST', '/api/roles', ['role_code' => 'LOOKER', 'role_desc' => 'Looks at invoices']);
$a->req('PUT', '/api/roles/LOOKER/perms', ['perms' => ['invoices' => ['view']]]);
$a->req('POST', '/api/users', ['us_userid' => 'sam', 'us_name' => 'Sam Signer', 'us_email' => 'sam@example.test', 'us_role' => 'SIGNER', 'password' => $pw]);
$a->req('POST', '/api/users', ['us_userid' => 'lou', 'us_name' => 'Lou Looker', 'us_email' => 'lou@example.test', 'us_role' => 'LOOKER', 'password' => $pw]);
$sam = login('sam', $pw);
$lou = login('lou', $pw);

ok('page /signatures', $sam->page('/signatures')[0] === 200);
ok('a sample invoice to sign', $inv !== '', $inv);

// ------------------------------------------------------------------ my signature
ok('no signature at first', $sam->req('GET', '/api/me/sign')[1]['data']['image'] === null);
$r = $sam->req('PUT', '/api/me/sign', ['image' => 'data:image/jpeg;base64,' . base64_encode('not a picture')]);
ok('only a PNG is taken as a signature', $r[0] === 422, $r[1]);
$r = $sam->req('PUT', '/api/me/sign', ['image' => 'data:image/png;base64,' . base64_encode(str_repeat('x', 400000))]);
ok('a signature over 300 KB is refused', $r[0] === 422, $r[1]);

// ------------------------------------------------------------------ boxes
$html = $a->page("$path?html=1");
ok('the page to sign shows the boxes and the signing script', $html[0] === 200 && str_contains((string) $html[1], 'doc-sign-data') && str_contains((string) $html[1], 'doc-sign.js'));
ok('the PDF route keeps giving a PDF', str_starts_with((string) $a->page($path)[1], '%PDF') || !str_contains((string) $a->page($path)[1], 'doc-sign.js'));
$r = $sam->req('POST', '/api/signatures/sign', ['path' => $path, 'slot' => 1]);
ok('an approval box is not signed by just anyone', $r[0] === 403, $r[1]);
$r = $a->req('POST', '/api/signatures/sign', ['path' => $path, 'slot' => 0]);
ok('the customer\'s box is left for paper', $r[0] === 404, $r[1]);
$r = $lou->req('POST', '/api/signatures/sign', ['path' => $path, 'slot' => 1]);
ok('someone who may not print the document cannot sign it', $r[0] === 403, $r[1]);
ok('... nor see who could be asked', $state($lou)[0] === 403);
$r = $a->req('POST', '/api/signatures/sign', ['path' => '/invoices/../users/print', 'slot' => 1]);
ok('only a document\'s print page can be signed', $r[0] === 404, $r[1]);

// ------------------------------------------------------------------ request, sign
$people = array_column($state($a)[1]['data'] ?? [], 'us_userid');
ok('who may be asked: those who may print it', in_array('sam', $people, true) && !in_array('lou', $people, true) && !in_array('admin', $people, true), $people);
$r = $a->req('POST', '/api/signatures/request', ['path' => $path, 'slot' => 1, 'userid' => 'lou']);
ok('nobody is asked who cannot open the document', $r[0] === 422, $r[1]);
$r = $a->req('POST', '/api/signatures/request', ['path' => $path, 'slot' => 1, 'userid' => 'sam', 'note' => 'please sign']);
$box = $r[1]['data']['boxes'][1] ?? [];
ok('asking: the box waits for sam (no mail set up, so none sent)', $r[0] === 200 && $box['status'] === 'R' && $box['userid'] === 'sam' && $r[1]['data']['mailed'] === false, $r[1]);
$w = $sam->req('GET', '/api/signatures')[1]['data']['waiting'] ?? [];
ok('it is on sam\'s list', count($w) === 1 && $w[0]['sig_path'] === $path && $w[0]['sig_note'] === 'please sign', $w);
$id = (int) ($w[0]['sig_id'] ?? 0);
$r = $sam->req('GET', "/signatures/$id");
ok('the link in the mail opens the document\'s page', $r[0] === 302 && str_ends_with((string) $r[2], "$path?html=1"), [$r[0], $r[2]]);
ok('the link is no use to anyone else', $lou->req('GET', "/signatures/$id")[0] === 404);
$r = $sam->req('POST', '/api/signatures/sign', ['path' => $path, 'slot' => 1]);
ok('signing needs a signature image first', $r[0] === 422 && isset($r[1]['fields']['sign']), $r[1]);
$sam->req('PUT', '/api/me/sign', ['image' => png()]);
$r = $sam->req('POST', '/api/signatures/sign', ['path' => $path, 'slot' => 1]);
$box = $r[1]['data']['boxes'][1] ?? [];
ok('sam signs: image, name and date in the box', $r[0] === 200 && $box['signed'] === true && str_starts_with((string) $box['image'], 'data:image/png') && $box['name'] === 'Sam Signer', $r[1]);
$row = db("SELECT * FROM sig_det WHERE sig_path = '$path' AND sig_status = 'S'")[0] ?? [];
ok('kept: who, when, from where, what it said, and a copy of the signature', ($row['sig_userid'] ?? '') === 'sam' && ($row['sig_req_by'] ?? '') === 'admin'
    && strlen((string) ($row['sig_hash'] ?? '')) === 64 && ($row['sig_ip'] ?? '') !== '' && str_starts_with((string) ($row['sig_image'] ?? ''), 'data:image/png'), $row['sig_userid'] ?? null);
ok('the request is done', ($sam->req('GET', '/api/signatures')[1]['data']['waiting'] ?? null) === []);
ok('the printed page carries the signature', str_contains((string) $a->page("$path?html=1")[1], 'doc-sign-area"><img'));
$sam->req('DELETE', '/api/me/sign');
ok('removing my signature leaves signed documents as they are', str_contains((string) $a->page("$path?html=1")[1], 'doc-sign-area"><img'));

// ------------------------------------------------------------------ a change after signing
$before = one("SELECT ih_po_ref FROM ih_mstr WHERE ih_inv_nbr = '$inv'");
pdo()->exec("UPDATE ih_mstr SET ih_po_ref = 'CHANGED-AFTER' WHERE ih_inv_nbr = '$inv'");
$box = $a->req('GET', '/api/signatures/people?path=' . rawurlencode($path))[0] === 200
    ? ($a->req('POST', '/api/signatures/request', ['path' => $path, 'slot' => 1, 'userid' => 'sam'])[1]['data']['boxes'][1] ?? []) : [];
ok('a document changed after signing no longer shows the signature, and can be asked again', ($box['signed'] ?? null) === false && ($box['status'] ?? '') === 'R', $box);
pdo()->prepare('UPDATE ih_mstr SET ih_po_ref = ? WHERE ih_inv_nbr = ?')->execute([$before, $inv]);

// ------------------------------------------------------------------ reject, withdraw
$r = $sam->req('POST', '/api/signatures/reject', ['path' => $path, 'slot' => 1, 'note' => '']);
ok('rejecting needs a reason', $r[0] === 422, $r[1]);
$r = $sam->req('POST', '/api/signatures/reject', ['path' => $path, 'slot' => 1, 'note' => 'wrong price']);
ok('sam rejects with the reason', $r[0] === 200 && ($r[1]['data']['boxes'][1]['status'] ?? '') === 'X', $r[1]);
$asked = $a->req('GET', '/api/signatures')[1]['data']['asked'][0] ?? [];
ok('the asker sees it rejected, and why', ($asked['sig_status'] ?? '') === 'X' && ($asked['sig_note'] ?? '') === 'wrong price', $asked);
$r = $a->req('POST', '/api/signatures/sign', ['path' => $path, 'slot' => 1]);
ok('an ADMIN signs an approval box directly', $r[0] === 422 || ($r[1]['data']['boxes'][1]['signed'] ?? false), $r[1]); // 422 = no signature image yet
$a->req('PUT', '/api/me/sign', ['image' => png(200, 60)]);
$r = $a->req('POST', '/api/signatures/sign', ['path' => $path, 'slot' => 1]);
ok('... once the ADMIN has a signature', $r[0] === 200 && ($r[1]['data']['boxes'][1]['signed'] ?? false) === true, $r[1]);
$r = $sam->req('POST', '/api/signatures/withdraw', ['path' => $path, 'slot' => 1]);
ok('nobody takes back another person\'s signature', $r[0] === 403, $r[1]);
$r = $a->req('POST', '/api/signatures/withdraw', ['path' => $path, 'slot' => 1]);
$box = $r[1]['data']['boxes'][1] ?? [];
ok('the signer takes it back: the box is empty again', $r[0] === 200 && ($box['signed'] ?? true) === false && array_key_exists('status', $box) && $box['status'] === null, $r[1]);

// ------------------------------------------------------------------ company stamp
$r = $sam->req('PUT', '/api/company/stamp', ['image' => png(100, 100)]);
ok('only who may edit the company sets the stamp', $r[0] === 403, $r[1]);
$r = $a->req('PUT', '/api/company/stamp', ['image' => png(100, 100)]);
ok('the stamp is kept', $r[0] === 200 && str_starts_with((string) ($r[1]['data']['image'] ?? ''), 'data:image/png'), $r[1]);
$page = (string) $a->page("$path?html=1")[1];
ok('no stamp on a document nobody signed', !str_contains($page, 'doc-stamp-area"><img'));
$a->req('POST', '/api/signatures/sign', ['path' => $path, 'slot' => 1]);
ok('the stamp beside a signed document', str_contains((string) $a->page("$path?html=1")[1], 'doc-stamp-area"><img'));

finish();
