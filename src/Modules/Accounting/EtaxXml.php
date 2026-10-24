<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\App;
use Phai\Core\Http\HttpException;
use Phai\Core\Xades;
use Phai\Modules\Master\Money;
use Phai\Modules\Sales\InvoiceController;
use Phai\Modules\System\Address;

/**
 * e-Tax Invoice & e-Receipt XML (ETDA ขมธอ.3-2560 v2.0) for the Revenue Department, signed with XAdES-BES:
 *
 *     388  ใบกำกับภาษี            TaxInvoice_CrossIndustryInvoice       ih_type IN
 *     81   ใบลดหนี้               DebitCreditNote_CrossIndustryInvoice   ih_type CM (refers to ih_ref_inv)
 *     80   ใบเพิ่มหนี้             DebitCreditNote_CrossIndustryInvoice   ih_type DN (refers to ih_ref_inv)
 *     T01  ใบรับ (ใบเสร็จรับเงิน)  Receipt_CrossIndustryInvoice          cr_mstr: one line per invoice paid
 *
 * Element order follows the schema (every group is a sequence); optional elements are left out rather than
 * written empty; amounts are never negative (a credit / debit note states the original, the corrected value and
 * the difference; a credit note set against a receipt is an allowance). The seller address must be structured with
 * TISI 1099 district / subdistrict codes, taken from the geography tables by name. A cancelled document has no
 * XML (the RD route is a credit note or a new invoice). Each signed file is kept in
 * storage/etax/<database>/xml/<yyyy-mm>/<number>.xml and handed out again as it is.
 */
final class EtaxXml
{
    private const FAMILIES = [
        '388' => ['TaxInvoice', 'ใบกำกับภาษี'],
        '81'  => ['DebitCreditNote', 'ใบลดหนี้'],
        '80'  => ['DebitCreditNote', 'ใบเพิ่มหนี้'],
        'T01' => ['Receipt', 'ใบรับ (ใบเสร็จรับเงิน)'],
    ];
    private const TYPES = ['IN' => '388', 'CM' => '81', 'DN' => '80'];

    private \DOMDocument $doc;
    private string $ram;
    private string $rsm;

    /** The signed XML of an invoice, credit note or debit note, made once and kept. */
    public static function signed(string $nbr): string
    {
        $h = self::head($nbr);
        if ((float) ($h['ih_dp_amt'] ?? 0) != 0.0) {
            throw new \Phai\Core\Http\HttpException(422, t('deposit.no_xml'));
        }
        if (!empty($h['ih_vat_on_rcpt'])) {
            // a service invoice is not a tax invoice: the tax invoice is the receipt when it is paid (ม.78/1)
            throw new \Phai\Core\Http\HttpException(422, t('sales.invoice_service_note'));
        }
        return self::store($h['ih_inv_date'], $nbr, self::TYPES[$h['ih_type']] ?? '388', $h['ih_ref_inv'] ?: null, fn () => (new self())->build($h));
    }

    /** The signed XML of a receipt (ใบรับ) for cash received, made once and kept. */
    public static function signedReceipt(string $nbr): string
    {
        $h = self::receiptHead($nbr);
        return self::store($h['cr_date'], $nbr, 'T01', null, fn () => (new self())->receipt($h));
    }

    /** The XML before signing (also used by tests to check it against the ETDA schema). */
    public static function unsigned(string $nbr, bool $receipt = false): \DOMDocument
    {
        return $receipt ? (new self())->receipt(self::receiptHead($nbr)) : (new self())->build(self::head($nbr));
    }

    /** storage/etax/<database>/xml/<yyyy-mm>/<number>.xml */
    public static function file(string $date, string $nbr): string
    {
        return Etax::dir() . '/xml/' . substr($date, 0, 7) . '/' . preg_replace('/[^A-Za-z0-9._-]/', '_', $nbr) . '.xml';
    }

    public static function head(string $nbr): array
    {
        $h = App::db()->one('SELECT * FROM ih_mstr WHERE ih_inv_nbr = ?', [$nbr]) ?? throw HttpException::notFound();
        if ($h['ih_status'] === 'C') {
            throw new HttpException(409, t('etax.xml_cancelled'));
        }
        return $h;
    }

    public static function receiptHead(string $nbr): array
    {
        $h = App::db()->one('SELECT * FROM cr_mstr WHERE cr_nbr = ?', [$nbr]) ?? throw HttpException::notFound();
        if ($h['cr_status'] !== 'P') {
            throw new HttpException(409, t('etax.xml_cancelled'));
        }
        return $h;
    }

    /** Sign once with the saved certificate, keep the file, log it; later calls hand out the kept file. */
    private static function store(string $date, string $nbr, string $type, ?string $ref, callable $build): string
    {
        $file = self::file($date, $nbr);
        if (is_file($file)) {
            return (string) file_get_contents($file);
        }
        $p12 = Etax::certFile();
        if (!is_file($p12)) {
            throw new HttpException(409, t('etax.cert_missing'));
        }
        $doc = $build();
        try {
            $xml = Xades::sign($doc, (string) file_get_contents($p12), (string) App::config('etax.p12_pass', ''));
        } catch (\RuntimeException $e) {
            throw new HttpException(422, t('etax.failed', ['msg' => $e->getMessage()]));
        }
        if (!is_dir(dirname($file)) && !@mkdir(dirname($file), 0700, true) || @file_put_contents($file, $xml, LOCK_EX) === false) {
            throw new HttpException(409, t('etax.cert_write'));
        }
        Etax::log([
            'etax_kind' => $type, 'etax_doc' => $nbr, 'etax_ref' => $ref, 'etax_to' => 'XML', 'etax_subject' => basename($file),
            'etax_status' => 'S', 'etax_msgid' => hash('sha256', $xml),
        ]);
        return $xml;
    }

    /** The root element of one document family, with its rsm / ram namespaces and the ETDA guideline id. */
    private function start(string $type): \DOMElement
    {
        $family = self::FAMILIES[$type][0];
        $this->rsm = "urn:etda:uncefact:data:standard:{$family}_CrossIndustryInvoice:2";
        $this->ram = "urn:etda:uncefact:data:standard:{$family}_ReusableAggregateBusinessInformationEntity:2";
        $this->doc = new \DOMDocument('1.0', 'UTF-8');
        $root = $this->doc->createElementNS($this->rsm, "rsm:{$family}_CrossIndustryInvoice");
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ram', $this->ram);
        $this->doc->appendChild($root);
        $ctx = $this->el($root, 'ExchangedDocumentContext', null, $this->rsm);
        $id = $this->el($this->el($ctx, 'GuidelineSpecifiedDocumentContextParameter'), 'ID', 'ER3-2560');
        $id->setAttribute('schemeAgencyID', 'ETDA');
        $id->setAttribute('schemeVersionID', 'v2.0');
        return $root;
    }

    private function build(array $h): \DOMDocument
    {
        $type = self::TYPES[$h['ih_type']] ?? '388';
        $name = self::FAMILIES[$type][1];
        $root = $this->start($type);
        $rsm = $this->rsm;
        $curr = (string) $h['ih_curr'];
        $lines = InvoiceController::lines($h['ih_inv_nbr']);
        if ($h['ih_type'] === 'CM') { // keyed and kept negative; ขมธอ.3-2560 states a credit note's amounts as positive
            foreach (['ih_net_amt', 'ih_tax_amt', 'ih_tot_amt'] as $k) {
                $h[$k] = abs((float) $h[$k]);
            }
            $lines = array_map(fn ($l) => ['ihd_qty_inv' => abs((float) $l['ihd_qty_inv']), 'ihd_line_amt' => abs((float) $l['ihd_line_amt'])] + $l, $lines);
        }
        if (!$lines) {
            throw new HttpException(409, t('etax.xml_no_lines'));
        }
        // the tax code is on each line; the header states one ApplicableTradeTax per rate
        $rates = [];
        foreach ($lines as &$l) {
            $l['rate'] = Money::taxPct($l['ihd_tax_code']);
            $rates[(string) $l['rate']] = round(($rates[(string) $l['rate']] ?? 0) + (float) $l['ihd_line_amt'], 2);
        }
        unset($l);
        $ref = $type !== '388' ? (App::db()->one('SELECT * FROM ih_mstr WHERE ih_inv_nbr = ?', [$h['ih_ref_inv']])
            ?? throw new HttpException(409, t('etax.xml_no_ref'))) : null;

        $ed = $this->el($root, 'ExchangedDocument', null, $rsm);
        $this->el($ed, 'ID', $h['ih_inv_nbr']);
        $this->el($ed, 'Name', $name);
        $this->el($ed, 'TypeCode', $type);
        $this->el($ed, 'IssueDateTime', self::day($h['ih_inv_date']));
        if ($ref) {
            // the reason chosen on the note (older notes without one: "other"); the text is the remarks, else the code's words
            $code = $h['ih_reason'] ?: ($type === '81' ? 'CDNG99' : 'DBNG99');
            $text = trim((string) $h['ih_remarks'])
                ?: (str_ends_with($code, '99') ? t("etax.xml_purpose_$type", ['ref' => $h['ih_ref_inv']]) : t('accounting.reason_' . $code));
            $this->el($ed, 'Purpose', mb_substr($text, 0, 256));
            $this->el($ed, 'PurposeCode', $code);
        }
        $this->el($ed, 'CreationDateTime', date('Y-m-d\TH:i:s.000', strtotime((string) ($h['created_at'] ?? 'now'))));

        $tx = $this->el($root, 'SupplyChainTradeTransaction', null, $rsm);
        $agree = $this->el($tx, 'ApplicableHeaderTradeAgreement');
        $this->seller($this->el($agree, 'SellerTradeParty'));
        $this->buyer($this->el($agree, 'BuyerTradeParty'), (string) $h['ih_cm_code']);
        if ($type === '388' && trim((string) $h['ih_po_ref']) !== '') {
            $po = $this->el($agree, 'BuyerOrderReferencedDocument');
            $this->el($po, 'IssuerAssignedID', mb_substr(trim((string) $h['ih_po_ref']), 0, 35));
            $this->el($po, 'ReferenceTypeCode', 'ON');
        }
        if ($ref) {
            $ad = $this->el($agree, 'AdditionalReferencedDocument');
            $this->el($ad, 'IssuerAssignedID', $ref['ih_inv_nbr']);
            $this->el($ad, 'IssueDateTime', self::day($ref['ih_inv_date']));
            $this->el($ad, 'ReferenceTypeCode', '388');
        }
        $this->el($tx, 'ApplicableHeaderTradeDelivery');

        $set = $this->el($tx, 'ApplicableHeaderTradeSettlement');
        $this->el($set, 'InvoiceCurrencyCode', $curr)->setAttribute('listID', 'ISO 4217 3A');
        $net = round((float) $h['ih_net_amt'], 2);
        $tax = round((float) $h['ih_tax_amt'], 2);
        foreach ($rates as $r => $basis) {
            // one rate: the invoice's own VAT; several: each rate's share
            $this->tax($set, (float) $r, $basis, count($rates) === 1 ? $tax : round($basis * (float) $r / 100, 2));
        }
        if ($type === '388' && $h['ih_due_date']) {
            $this->el($this->el($set, 'SpecifiedTradePaymentTerms'), 'DueDateDateTime', self::day($h['ih_due_date']));
        }
        $sum = $this->el($set, 'SpecifiedTradeSettlementHeaderMonetarySummation');
        if ($ref) {
            $original = round((float) $ref['ih_net_amt'], 2);
            $this->el($sum, 'OriginalInformationAmount', self::amt($original));
            // the corrected value: less by a credit note, more by a debit note
            $this->el($sum, 'LineTotalAmount', self::amt($type === '81' ? max(0, $original - $net) : $original + $net));
            $this->el($sum, 'DifferenceInformationAmount', self::amt($net));
        } else {
            $this->el($sum, 'LineTotalAmount', self::amt($net));
        }
        $this->el($sum, 'TaxBasisTotalAmount', self::amt($net));
        $this->el($sum, 'TaxTotalAmount', self::amt($tax));
        $this->el($sum, 'GrandTotalAmount', self::amt(round($net + $tax, 2)));

        foreach ($lines as $i => $l) {
            $item = $this->el($tx, 'IncludedSupplyChainTradeLineItem');
            $this->el($this->el($item, 'AssociatedDocumentLineDocument'), 'LineID', (string) ($i + 1));
            $prod = $this->el($item, 'SpecifiedTradeProduct');
            if (trim((string) $l['ihd_part']) !== '') {
                $this->el($prod, 'ID', mb_substr((string) $l['ihd_part'], 0, 35));
            }
            $this->el($prod, 'Name', mb_substr(trim((string) $l['pt_desc1']) ?: (string) $l['ihd_part'], 0, 256));
            $qty = (float) $l['ihd_qty_inv'];
            $price = round((float) $l['ihd_price'], 5);
            $lineNet = round((float) $l['ihd_line_amt'], 2);
            $this->el($this->el($this->el($item, 'SpecifiedLineTradeAgreement'), 'GrossPriceProductTradePrice'), 'ChargeAmount', self::amt($price, 5));
            $this->el($this->el($item, 'SpecifiedLineTradeDelivery'), 'BilledQuantity', self::num($qty))
                ->setAttribute('unitCode', trim((string) $l['ihd_um']) ?: 'C62');
            $ls = $this->el($item, 'SpecifiedLineTradeSettlement');
            $lineTax = round($lineNet * $l['rate'] / 100, 2);
            $this->tax($ls, $l['rate'], $lineNet, $lineTax);
            $disc = round($qty * $price - $lineNet, 2);
            if ($disc > 0) {
                $ac = $this->el($ls, 'SpecifiedTradeAllowanceCharge');
                $this->el($ac, 'ChargeIndicator', 'false');
                $this->el($ac, 'ActualAmount', self::amt($disc));
            }
            $lm = $this->el($ls, 'SpecifiedTradeSettlementLineMonetarySummation');
            $this->el($lm, 'TaxTotalAmount', self::amt($lineTax));
            $this->el($lm, 'NetLineTotalAmount', self::amt($lineNet))->setAttribute('currencyID', $curr);
            $this->el($lm, 'NetIncludingTaxesLineTotalAmount', self::amt($lineNet + $lineTax))->setAttribute('currencyID', $curr);
        }
        return $this->doc;
    }

    /**
     * ใบรับ (T01) for cash received: one line per invoice paid (amount settled, withholding tax included);
     * a credit note set against the receipt becomes an allowance, so the grand total is what was settled.
     * The VAT was stated on the tax invoices, so the receipt carries none.
     */
    private function receipt(array $h): \DOMDocument
    {
        $root = $this->start('T01');
        $lines = CashReceiptController::lines($h['cr_nbr']);
        if (!$lines) {
            throw new HttpException(409, t('etax.xml_no_lines'));
        }
        $ed = $this->el($root, 'ExchangedDocument', null, $this->rsm);
        $this->el($ed, 'ID', $h['cr_nbr']);
        $this->el($ed, 'Name', self::FAMILIES['T01'][1]);
        $this->el($ed, 'TypeCode', 'T01');
        $this->el($ed, 'IssueDateTime', self::day($h['cr_date']));
        $this->el($ed, 'CreationDateTime', date('Y-m-d\TH:i:s.000', strtotime((string) ($h['created_at'] ?? 'now'))));
        if ((float) $h['cr_wht_amt'] > 0) {
            $note = $this->el($ed, 'IncludedNote');
            $this->el($note, 'Subject', t('etax.xml_wht'));
            $this->el($note, 'Content', self::amt((float) $h['cr_wht_amt']));
        }

        $tx = $this->el($root, 'SupplyChainTradeTransaction', null, $this->rsm);
        $agree = $this->el($tx, 'ApplicableHeaderTradeAgreement');
        $this->seller($this->el($agree, 'SellerTradeParty'));
        $this->buyer($this->el($agree, 'BuyerTradeParty'), (string) $h['cr_cm_code']);
        $this->el($tx, 'ApplicableHeaderTradeDelivery');

        $paid = [];
        $credits = [];
        foreach ($lines as $l) {
            $amt = round((float) $l['crd_amt'] + (float) $l['crd_wht_amt'], 2);
            if ($amt >= 0) {
                $paid[] = [$l['crd_ar_nbr'], $amt];
            } else {
                $credits[] = [$l['crd_ar_nbr'], -$amt];
            }
        }
        $lineTotal = round(array_sum(array_column($paid, 1)), 2);
        $allowance = round(array_sum(array_column($credits, 1)), 2);

        $set = $this->el($tx, 'ApplicableHeaderTradeSettlement');
        $this->el($set, 'InvoiceCurrencyCode', Money::base())->setAttribute('listID', 'ISO 4217 3A');
        foreach ($credits as [$nbr, $amt]) {
            $ac = $this->el($set, 'SpecifiedTradeAllowanceCharge');
            $this->el($ac, 'ChargeIndicator', 'false');
            $this->el($ac, 'ActualAmount', self::amt($amt));
            $this->el($ac, 'Reason', mb_substr(t('etax.xml_rct_credit', ['ref' => $nbr]), 0, 256));
        }
        $sum = $this->el($set, 'SpecifiedTradeSettlementHeaderMonetarySummation');
        $this->el($sum, 'LineTotalAmount', self::amt($lineTotal));
        if ($allowance > 0) {
            $this->el($sum, 'AllowanceTotalAmount', self::amt($allowance));
        }
        $this->el($sum, 'GrandTotalAmount', self::amt($lineTotal - $allowance));

        foreach ($paid as $i => [$nbr, $amt]) {
            $item = $this->el($tx, 'IncludedSupplyChainTradeLineItem');
            $this->el($this->el($item, 'AssociatedDocumentLineDocument'), 'LineID', (string) ($i + 1));
            $this->el($this->el($item, 'SpecifiedTradeProduct'), 'Name', mb_substr(t('etax.xml_rct_line', ['ref' => $nbr]), 0, 256));
            $this->el($this->el($this->el($item, 'SpecifiedLineTradeAgreement'), 'GrossPriceProductTradePrice'), 'ChargeAmount', self::amt($amt));
            $this->el($this->el($item, 'SpecifiedLineTradeDelivery'), 'BilledQuantity', '1')->setAttribute('unitCode', 'C62');
            $lm = $this->el($this->el($item, 'SpecifiedLineTradeSettlement'), 'SpecifiedTradeSettlementLineMonetarySummation');
            $this->el($lm, 'NetLineTotalAmount', self::amt($amt));
            $this->el($lm, 'NetIncludingTaxesLineTotalAmount', self::amt($amt));
        }
        return $this->doc;
    }

    /** The company: name, 18-digit tax id + branch, structured address with TISI codes. */
    private function seller(\DOMElement $p): void
    {
        $co = App::db()->one('SELECT co_name, co_tax_id, co_branch FROM co_mstr WHERE co_id = 1') ?? [];
        $taxId = preg_replace('/\D/', '', (string) ($co['co_tax_id'] ?? ''));
        $branch = str_pad(preg_replace('/\D/', '', (string) ($co['co_branch'] ?? '')), 5, '0', STR_PAD_LEFT);
        if (strlen($taxId) !== 13 || strlen($branch) !== 5) {
            throw new HttpException(409, t('etax.xml_seller_tax'));
        }
        $this->el($p, 'Name', mb_substr((string) $co['co_name'], 0, 256));
        $this->el($this->el($p, 'SpecifiedTaxRegistration'), 'ID', $taxId . $branch)->setAttribute('schemeID', 'TXID');

        $ad = Address::companyParts();
        $houseNo = trim((string) $ad['ad_house_no']);
        if ($houseNo === '') {
            throw new HttpException(409, t('etax.xml_seller_address'));
        }
        $codes = self::geo((string) ($ad['ad_sub_district'] ?? ''), (string) ($ad['ad_city'] ?? ''), (string) ($ad['ad_state'] ?? ''));
        $zip = trim((string) ($ad['ad_zip'] ?? ''));
        if (!$codes || !preg_match('/^\d{5}$/', $zip)) {
            throw new HttpException(409, t('etax.xml_seller_address'));
        }
        $a = $this->el($p, 'PostalTradeAddress');
        $this->el($a, 'PostcodeCode', $zip);
        // schema order: BuildingName, LineOne, LineTwo, LineThree (ซอย), LineFour (หมู่บ้าน), LineFive (หมู่ที่), StreetName
        $part = fn (string $k) => trim((string) ($ad[$k] ?? ''));
        $building = trim($part('ad_building') . ($part('ad_floor') !== '' ? ' ชั้น ' . $part('ad_floor') : '') . ($part('ad_room') !== '' ? ' ห้อง ' . $part('ad_room') : ''));
        foreach ([
            'BuildingName' => [$building, 70], 'LineOne' => [$part('ad_line2'), 256],
            'LineThree' => [trim($part('ad_soi') . ($part('ad_yaek') !== '' ? ' แยก ' . $part('ad_yaek') : '')), 70],
            'LineFour' => [$part('ad_village'), 70], 'LineFive' => [$part('ad_moo'), 70], 'StreetName' => [$part('ad_road'), 70],
        ] as $el => [$v, $max]) {
            if ($v !== '') {
                $this->el($a, $el, mb_substr($v, 0, $max));
            }
        }
        $this->el($a, 'CityName', $codes['city']);
        $this->el($a, 'CitySubDivisionName', $codes['sub']);
        $this->el($a, 'CountryID', 'TH')->setAttribute('schemeID', '3166-1 alpha-2');
        $this->el($a, 'CountrySubDivisionID', $codes['province']);
        $this->el($a, 'BuildingNumber', mb_substr($houseNo, 0, 16));
    }

    /** The customer: TXID (juristic, 13 digits starting with 0) + branch, NIDN (person), or OTHR N/A; address as one line. */
    private function buyer(\DOMElement $p, string $code): void
    {
        $c = App::db()->one('SELECT * FROM cm_mstr WHERE cm_code = ?', [$code]) ?? throw HttpException::notFound();
        $this->el($p, 'Name', mb_substr(trim($c['cm_name'] . ' ' . ($c['cm_name2'] ?? '')), 0, 256));
        $taxId = preg_replace('/\D/', '', (string) $c['cm_tax_id_num']);
        if (strlen($taxId) === 13 && $taxId[0] === '0') {
            $branch = str_pad(preg_replace('/\D/', '', (string) $c['cm_tax_branch']), 5, '0', STR_PAD_LEFT);
            [$scheme, $id] = ['TXID', $taxId . substr($branch, -5)];
        } elseif (strlen($taxId) === 13) {
            [$scheme, $id] = ['NIDN', $taxId];
        } else {
            [$scheme, $id] = ['OTHR', 'N/A'];
        }
        $this->el($this->el($p, 'SpecifiedTaxRegistration'), 'ID', $id)->setAttribute('schemeID', $scheme);

        $country = trim((string) $c['cm_country']);
        $iso = preg_match('/^(|th|tha|thailand|ไทย|ประเทศไทย)$/iu', $country) ? 'TH' : (preg_match('/^[A-Za-z]{2}$/', $country) ? strtoupper($country) : null);
        $line = trim(implode(' ', array_filter(array_map(fn ($v) => trim((string) $v), [
            $c['cm_line1'], $c['cm_line2'], $c['cm_line3'], $c['cm_sub_district'], $c['cm_district'], $c['cm_province'],
        ]))));
        if ($iso === null || $line === '') {
            throw new HttpException(409, t('etax.xml_buyer_address', ['code' => $code]));
        }
        $a = $this->el($p, 'PostalTradeAddress');
        $zip = trim((string) $c['cm_zip']);
        if ($iso === 'TH' && !preg_match('/^\d{5}$/', $zip)) {
            throw new HttpException(409, t('etax.xml_buyer_address', ['code' => $code]));
        }
        if ($zip !== '') {
            $this->el($a, 'PostcodeCode', mb_substr($zip, 0, 16));
        }
        $this->el($a, 'LineOne', mb_substr($line, 0, 256));
        $this->el($a, 'CountryID', $iso)->setAttribute('schemeID', '3166-1 alpha-2');
    }

    private function tax(\DOMElement $parent, float $rate, float $basis, float $amount): void
    {
        $t = $this->el($parent, 'ApplicableTradeTax');
        $this->el($t, 'TypeCode', 'VAT');
        $this->el($t, 'CalculatedRate', self::num($rate));
        $this->el($t, 'BasisAmount', self::amt($basis));
        $this->el($t, 'CalculatedAmount', self::amt($amount));
    }

    /**
     * District (4 digits), subdistrict (6) and province (2) codes of a Thai address given by name — the geography
     * tables carry the same DOPA / TISI 1099 numbers. Prefixes like เขต, อำเภอ, แขวง, ตำบล and spaces are ignored.
     *
     * @return array{city: string, sub: string, province: string}|null
     */
    public static function geo(string $sub, string $city, string $province): ?array
    {
        $norm = fn (string $s) => preg_replace('/^(เขต|อำเภอ|อ\.|แขวง|ตำบล|ต\.|จังหวัด|จ\.)/u', '', preg_replace('/\s+/u', '', $s));
        $sub = $norm($sub);
        if ($sub === '') {
            return null;
        }
        $rows = App::db()->all(
            "SELECT t.tb_code, t.tb_name_th, a.am_code, a.am_name_th, p.pv_code, p.pv_name_th
               FROM tb_mstr t JOIN am_mstr a ON a.am_id = t.tb_am_id JOIN pv_mstr p ON p.pv_id = a.am_pv_id
              WHERE REPLACE(t.tb_name_th, ' ', '') LIKE ?",
            ['%' . $sub]
        );
        foreach ($rows as $r) {
            if ($norm($r['tb_name_th']) === $sub && $norm($r['am_name_th']) === $norm($city) && $norm($r['pv_name_th']) === $norm($province)) {
                return [
                    'city' => sprintf('%04d', $r['am_code']), 'sub' => sprintf('%06d', $r['tb_code']), 'province' => sprintf('%02d', $r['pv_code']),
                ];
            }
        }
        return null;
    }

    private function el(\DOMElement $parent, string $name, ?string $text = null, ?string $ns = null): \DOMElement
    {
        $ns ??= $this->ram;
        $el = $this->doc->createElementNS($ns, ($ns === $this->ram ? 'ram:' : 'rsm:') . $name);
        if ($text !== null) {
            $el->appendChild($this->doc->createTextNode($text));
        }
        $parent->appendChild($el);
        return $el;
    }

    private static function day(string $date): string
    {
        return substr($date, 0, 10) . 'T00:00:00.000';
    }

    private static function amt(float $v, int $scale = 2): string
    {
        $s = number_format(max(0.0, $v), $scale, '.', '');
        return $scale > 2 ? (rtrim(rtrim($s, '0'), '.') ?: '0') : $s;
    }

    private static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 5, '.', ''), '0'), '.') ?: '0';
    }
}
