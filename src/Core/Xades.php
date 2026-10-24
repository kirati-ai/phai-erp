<?php

declare(strict_types=1);

namespace Phai\Core;

/**
 * Enveloped XAdES-BES signature (XMLDSig + ETSI SignedProperties) with RSA-SHA512 and SHA-512 digests (as the Revenue Department requires), made with PHP's
 * openssl and DOM only. The certificate and key come from a PKCS#12 (.p12/.pfx) file.
 *
 *     $signed = Xades::sign($dom, file_get_contents('cert.p12'), 'password');   // XML string
 *     Xades::verify($signed);                                                  // true when every digest and the signature hold
 *
 * Canonicalization is inclusive C14N 1.0; the Signature element is appended as the last child of the root.
 */
final class Xades
{
    public const DS = 'http://www.w3.org/2000/09/xmldsig#';
    public const XADES = 'http://uri.etsi.org/01903/v1.3.2#';
    private const C14N = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    private const RSA_SHA512 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha512';
    private const SHA512 = 'http://www.w3.org/2001/04/xmlenc#sha512';
    private const ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';
    private const PROPS_TYPE = 'http://uri.etsi.org/01903#SignedProperties';

    /** @return array{cert: string, key: \OpenSSLAsymmetricKey, info: array} */
    public static function load(string $p12, string $password): array
    {
        if (!openssl_pkcs12_read($p12, $certs, $password)) {
            throw new \RuntimeException('PKCS#12: ' . (openssl_error_string() ?: 'cannot read (wrong password?)'));
        }
        $key = openssl_pkey_get_private($certs['pkey']);
        $info = openssl_x509_parse($certs['cert']);
        if (!$key || !$info) {
            throw new \RuntimeException('PKCS#12: no private key or certificate');
        }
        return ['cert' => $certs['cert'], 'key' => $key, 'info' => $info];
    }

    public static function sign(\DOMDocument $doc, string $p12, string $password, ?\DateTimeInterface $at = null): string
    {
        ['cert' => $pem, 'key' => $key, 'info' => $info] = self::load($p12, $password);
        $der = self::der($pem);
        $id = 'xmldsig-' . self::uuid();
        $root = $doc->documentElement;

        // the enveloped transform: the document as it is before the signature goes in
        $docDigest = self::digest($root->C14N());

        $sig = $doc->createElementNS(self::DS, 'ds:Signature');
        $sig->setAttribute('Id', $id);
        $root->appendChild($sig);
        $info_ = self::add($sig, 'ds:SignedInfo');
        self::add($info_, 'ds:CanonicalizationMethod')->setAttribute('Algorithm', self::C14N);
        self::add($info_, 'ds:SignatureMethod')->setAttribute('Algorithm', self::RSA_SHA512);
        $ref = self::add($info_, 'ds:Reference');
        $ref->setAttribute('Id', "$id-ref0");
        $ref->setAttribute('URI', '');
        self::add(self::add($ref, 'ds:Transforms'), 'ds:Transform')->setAttribute('Algorithm', self::ENVELOPED);
        self::add($ref, 'ds:DigestMethod')->setAttribute('Algorithm', self::SHA512);
        self::add($ref, 'ds:DigestValue', $docDigest);
        $propsRef = self::add($info_, 'ds:Reference');
        $propsRef->setAttribute('Type', self::PROPS_TYPE);
        $propsRef->setAttribute('URI', "#$id-signedprops");
        self::add($propsRef, 'ds:DigestMethod')->setAttribute('Algorithm', self::SHA512);
        $propsDigest = self::add($propsRef, 'ds:DigestValue');

        $value = self::add($sig, 'ds:SignatureValue');
        $value->setAttribute('Id', "$id-sigvalue");
        self::add(self::add(self::add($sig, 'ds:KeyInfo'), 'ds:X509Data'), 'ds:X509Certificate', base64_encode($der));

        $qp = $doc->createElementNS(self::XADES, 'xades:QualifyingProperties');
        $qp->setAttribute('Target', "#$id");
        self::add($sig, 'ds:Object')->appendChild($qp);
        $props = self::add($qp, 'xades:SignedProperties', null, self::XADES);
        $props->setAttribute('Id', "$id-signedprops");
        $ssp = self::add($props, 'xades:SignedSignatureProperties', null, self::XADES);
        self::add($ssp, 'xades:SigningTime', ($at ?? new \DateTimeImmutable())->format('Y-m-d\TH:i:sP'), self::XADES);
        $cert = self::add(self::add($ssp, 'xades:SigningCertificate', null, self::XADES), 'xades:Cert', null, self::XADES);
        $cd = self::add($cert, 'xades:CertDigest', null, self::XADES);
        self::add($cd, 'ds:DigestMethod')->setAttribute('Algorithm', self::SHA512);
        self::add($cd, 'ds:DigestValue', base64_encode(hash('sha512', $der, true)));
        $is = self::add($cert, 'xades:IssuerSerial', null, self::XADES);
        self::add($is, 'ds:X509IssuerName', self::dn($info['issuer']));
        self::add($is, 'ds:X509SerialNumber', (string) $info['serialNumber']);

        $propsDigest->textContent = self::digest($props->C14N());
        if (!openssl_sign($info_->C14N(), $raw, $key, OPENSSL_ALGO_SHA512)) {
            throw new \RuntimeException('Sign: ' . (openssl_error_string() ?: 'failed'));
        }
        $value->textContent = base64_encode($raw);
        return $doc->saveXML();
    }

    /** Check the two digests and the RSA signature against the certificate in KeyInfo. */
    public static function verify(string $xml): bool
    {
        $doc = new \DOMDocument();
        if (!@$doc->loadXML($xml)) {
            return false;
        }
        $x = new \DOMXPath($doc);
        $x->registerNamespace('ds', self::DS);
        $x->registerNamespace('xades', self::XADES);
        $sig = $x->query('/*/ds:Signature')->item(0);
        if (!$sig) {
            return false;
        }
        $info = $x->query('ds:SignedInfo', $sig)->item(0);
        $cert = "-----BEGIN CERTIFICATE-----\n" . chunk_split(preg_replace('/\s+/', '', $x->query('ds:KeyInfo/ds:X509Data/ds:X509Certificate', $sig)->item(0)->textContent), 64, "\n") . "-----END CERTIFICATE-----\n";
        $props = $x->query('ds:Object/xades:QualifyingProperties/xades:SignedProperties', $sig)->item(0);
        // base64 values may be wrapped over several lines
        $b64 = fn (string $path) => preg_replace('/\s+/', '', (string) $x->query($path, $info->parentNode)->item(0)?->textContent);
        $propsDigest = $b64('ds:SignedInfo/ds:Reference[@Type="' . self::PROPS_TYPE . '"]/ds:DigestValue');
        $docDigest = $b64('ds:SignedInfo/ds:Reference[@URI=""]/ds:DigestValue');
        $signature = base64_decode($b64('ds:SignatureValue'));
        $signedInfo = $info->C14N();
        if (!$props || self::digest($props->C14N()) !== $propsDigest) {
            return false;
        }
        $sig->parentNode->removeChild($sig);
        if (self::digest($doc->documentElement->C14N()) !== $docDigest) {
            return false;
        }
        return openssl_verify($signedInfo, $signature, $cert, OPENSSL_ALGO_SHA512) === 1;
    }

    private static function add(\DOMElement $parent, string $name, ?string $text = null, string $ns = self::DS): \DOMElement
    {
        $el = $parent->ownerDocument->createElementNS($ns, $name);
        if ($text !== null) {
            $el->appendChild($parent->ownerDocument->createTextNode($text));
        }
        $parent->appendChild($el);
        return $el;
    }

    private static function digest(string $c14n): string
    {
        return base64_encode(hash('sha512', $c14n, true));
    }

    private static function der(string $pem): string
    {
        return base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem));
    }

    /** Issuer as an RFC 4514 string, most specific first: CN=...,O=...,C=TH */
    private static function dn(array $name): string
    {
        $parts = [];
        foreach (array_reverse($name, true) as $k => $v) {
            foreach ((array) $v as $one) {
                $parts[] = $k . '=' . addcslashes((string) $one, ',+"\\<>;=');
            }
        }
        return implode(',', $parts);
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
