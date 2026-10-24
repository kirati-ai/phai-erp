<?php

declare(strict_types=1);

use Phai\Core\Xades;

/** A throwaway self-signed certificate as a PKCS#12 file, made fresh for each run. */
function xadesP12(string $password): string
{
    // Windows builds have no default openssl.cnf: give them a minimal one
    $cnf = sys_get_temp_dir() . '/phai-xades-test.cnf';
    file_put_contents($cnf, "[req]\ndistinguished_name = dn\n[dn]\n");
    $opt = ['config' => $cnf, 'digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
    $key = openssl_pkey_new($opt);
    $csr = openssl_csr_new(['countryName' => 'TH', 'organizationName' => 'Test, Co', 'commonName' => 'PhaiERP Test'], $key, $opt);
    $cert = openssl_csr_sign($csr, null, $key, 1, $opt, 12345);
    openssl_pkcs12_export($cert, $p12, $key, $password);
    return $p12;
}

test('XAdES signature verifies, and any change to the document breaks it', function () {
    $p12 = xadesP12('pw');
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadXML('<rsm:Doc xmlns:rsm="urn:test:rsm" xmlns:ram="urn:test:ram"><ram:ID>INV1</ram:ID><ram:Name>ใบกำกับภาษี &amp; ทดสอบ</ram:Name></rsm:Doc>');
    $xml = Xades::sign($doc, $p12, 'pw', new DateTimeImmutable('2026-10-05T10:00:00+07:00'));
    check(Xades::verify($xml), 'fresh signature');
    check(str_contains($xml, '<xades:SigningTime>2026-10-05T10:00:00+07:00</xades:SigningTime>'));
    check(str_contains($xml, '<ds:X509SerialNumber>12345</ds:X509SerialNumber>'));
    check(str_contains($xml, 'CN=PhaiERP Test,O=Test\, Co,C=TH'), 'issuer most specific first, comma escaped');
    check(!Xades::verify(str_replace('<ram:ID>INV1</ram:ID>', '<ram:ID>INV2</ram:ID>', $xml)), 'document changed');
    check(!Xades::verify(str_replace('2026-10-05T10:00:00+07:00', '2026-10-06T10:00:00+07:00', $xml)), 'signed properties changed');
});

test('XAdES refuses a wrong PKCS#12 password', function () {
    $doc = new DOMDocument();
    $doc->loadXML('<a/>');
    try {
        Xades::sign($doc, xadesP12('right'), 'wrong');
        check(false, 'should throw');
    } catch (RuntimeException $e) {
        check(str_starts_with($e->getMessage(), 'PKCS#12'));
    }
});
