<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Numbering;
use Phai\Core\Validation\Check;
use Phai\Modules\Master\Money;

/**
 * เงินสดย่อย: every user holds their own petty cash ("box"); only the holder works on it, and what they take in is theirs
 * to account for. The ledger has one account (gl_pc_acct) for all boxes; each box is its rows in cbh_hist.
 *
 *   WD  เบิกจากธนาคาร        box +   Dr เงินสดย่อย / Cr ธนาคาร
 *   RT  คืนเข้าธนาคาร         box −   Dr ธนาคาร / Cr เงินสดย่อย
 *   TF  รับเงินจากผู้ถืออื่น   receiver + (made by the receiver), with the giver's own TO ส่งเงินให้ − : two entries, each its
 *                             own number, tied by cb_ref; no entry in the ledger: it stays petty cash; never changed —
 *                             a mistake is put right by the one who gave it receiving it back
 *   IN  รับเงิน (รายรับ)        box +   Dr เงินสดย่อย / Cr รายได้ (scrap sold, change given back)
 *   EX  จ่ายค่าใช้จ่าย        box − (net + VAT − withholding)   Dr expenses (+ ภาษีซื้อ) / Cr หัก ณ ที่จ่าย / Cr เงินสดย่อย
 *   CT  ส่วนต่างจากการนับ    box ± so it equals the cash counted   Dr/Cr เงินขาดเกินบัญชี
 *   DO / DI  จ่าย / รับตามเอกสาร: a payment, cash receipt or deposit made with the petty cash "bank" (bk_petty) takes the
 *       cash from / puts it in the box of whoever makes it; that document posts (Cr / Dr เงินสดย่อย) and is where it is changed
 *
 * Month end: each holder counts their cash (cbc_mstr). Until then the box takes nothing dated in a later month, and once
 * counted its month is locked. A box with nothing in it and no entries has nothing to count.
 */
final class PettyCash
{
    public const TYPES = ['WD', 'RT', 'TF', 'TO', 'IN', 'EX', 'CT', 'DO', 'DI'];
    /** Notes and coins counted at month end (baht). */
    public const DENOMINATIONS = ['1000', '500', '100', '50', '20', '10', '5', '2', '1', '0.5', '0.25'];

    /** Cash in the box, up to and including $upto (all of it when null). */
    public static function balance(string $holder, ?string $upto = null): float
    {
        return round((float) App::db()->value(
            'SELECT COALESCE(SUM(cbh_amt), 0) FROM cbh_hist WHERE cbh_holder = ?' . ($upto ? ' AND cbh_date <= ?' : ''),
            $upto ? [$holder, $upto] : [$holder]
        ), 2);
    }

    /** Last month counted (yyyy-mm), or null. */
    public static function lastCounted(string $holder): ?string
    {
        return App::db()->value('SELECT MAX(cbc_month) FROM cbc_mstr WHERE cbc_holder = ?', [$holder]) ?: null;
    }

    /**
     * The month the box works in (yyyy-mm): the month after the last count while cash is left in it, else the month of
     * its first entry after the last count. Null when the box is empty and has no entries: any month after the last
     * count may start it.
     */
    public static function openMonth(string $holder): ?string
    {
        $last = self::lastCounted($holder);
        $lastEnd = $last ? self::monthEnd($last) : null;
        if ($last && self::balance($holder, $lastEnd) != 0.0) {
            return self::nextMonth($last);
        }
        $first = App::db()->value(
            'SELECT MIN(cbh_date) FROM cbh_hist WHERE cbh_holder = ?' . ($lastEnd ? ' AND cbh_date > ?' : ''),
            $lastEnd ? [$holder, $lastEnd] : [$holder]
        );
        return $first ? substr((string) $first, 0, 7) : null;
    }

    /** A box may take an entry dated $date: not later than today, not in a counted month, not past a month still to count. */
    public static function checkDate(Check $c, string $field, ?string $date, string $holder): void
    {
        if ($date === null || !$c->ok($field)) {
            return;
        }
        $ym = substr($date, 0, 7);
        $last = self::lastCounted($holder);
        $open = self::openMonth($holder);
        if ($date > date('Y-m-d')) {
            $c->fail($field, t('pettycash.future'));
        } elseif ($last && $ym <= $last) {
            $c->fail($field, t('pettycash.month_counted', ['who' => self::name($holder), 'month' => self::monthText($last)]));
        } elseif ($open && $ym > $open) {
            $c->fail($field, t('pettycash.count_first', ['who' => self::name($holder), 'month' => self::monthText($open)]));
        }
    }

    /** Holders who still have to count $month (yyyy-mm) or an earlier month: [{holder, name, month, balance}]. */
    public static function uncounted(string $month): array
    {
        $out = [];
        foreach (App::db()->all('SELECT DISTINCT cbh_holder AS h FROM cbh_hist') as $r) {
            $open = self::openMonth($r['h']);
            if ($open && $open <= $month) {
                $out[] = ['holder' => $r['h'], 'name' => self::name($r['h']), 'month' => $open, 'month_text' => self::monthText($open),
                          'balance' => self::balance($r['h'], self::monthEnd($open))];
            }
        }
        return $out;
    }

    /**
     * Check one entry of the signed-in user's box. $b: {type, date, bank, amount, from, payee, vendor, tax_id, tax_branch,
     * inv_nbr, inv_date, tax_code, wht_form, remarks, lines: [{acct, cc, desc, amt, wht}]}. Throws 422 with every wrong field.
     * @return array{0: array, 1: list<array>, 2: array<string, array>, 3: list<array>} [head row, expense lines, withholding by code, ledger lines]
     */
    private static function parse(array $b, string $holder): array
    {
        $c = new Check();
        $type = (string) ($b['type'] ?? '');
        if (!in_array($type, ['WD', 'RT', 'TF', 'IN', 'EX'], true)) {
            throw HttpException::invalid(['type' => t('validation.in')]);
        }
        $date = $c->date('date', $b['date'] ?? '', true);
        self::checkDate($c, 'date', $date, $holder);
        $remarks = $c->text('remarks', $b['remarks'] ?? '', false, 255);
        // every field of the head: an edit clears what the new content does not use
        $row = ['cb_type' => $type, 'cb_date' => $date, 'cb_holder' => $holder, 'cb_remarks' => $remarks, 'cb_from' => null, 'cb_bank' => null,
                'cb_payee' => null, 'cb_acct' => null, 'cb_rcpt_nbr' => null, 'cb_vd_code' => null, 'cb_tax_id' => null, 'cb_tax_branch' => null, 'cb_inv_nbr' => null, 'cb_inv_date' => null,
                'cb_tax_code' => null, 'cb_net_amt' => 0, 'cb_vat_amt' => 0, 'cb_wht_amt' => 0, 'cb_wht_form' => null];
        $lines = [];
        $whts = [];
        $post = [];
        $pcAcct = Gl::acct('pc');

        if ($type === 'WD' || $type === 'RT') {
            $row['cb_bank'] = $c->exists('bank', $b['bank'] ?? '', 'bk_mstr', 'bk_bank', true, 'bk_active = 1 AND bk_petty = 0');
            $row['cb_amt'] = $c->number('amount', $b['amount'] ?? '', true, 0, true, 2);
        } elseif ($type === 'IN') {
            // money that comes into the box from outside: a revenue account (e.g. รายได้อื่น), whom it came from
            $row['cb_payee'] = $c->text('payee', $b['payee'] ?? '', true, 255);
            $row['cb_acct'] = $c->exists('acct', $b['acct'] ?? '', 'ac_mstr', 'ac_acct', true, "ac_active = 1 AND ac_is_control = 0 AND ac_type = 'R'", false);
            $row['cb_rcpt_nbr'] = $c->text('rcpt_nbr', $b['rcpt_nbr'] ?? '', false, 50);
            $row['cb_amt'] = $c->number('amount', $b['amount'] ?? '', true, 0, true, 2);
            // only someone already holding petty cash takes money in this way: a box starts from the bank or another holder
            if (self::balance($holder) <= 0 && !App::db()->value("SELECT 1 FROM cb_mstr WHERE cb_nbr = ? AND cb_type = 'IN'", [(string) ($b['_nbr'] ?? '')])) {
                $c->fail('amount', t('pettycash.holders_only'));
            }
        } elseif ($type === 'TF') {
            $from = $c->exists('from', $b['from'] ?? '', 'us_mstr', 'us_userid', true, '', false);
            if ($from === $holder) {
                $c->fail('from', t('pettycash.from_self'));
            }
            $row['cb_amt'] = $c->number('amount', $b['amount'] ?? '', true, 0, true, 2);
            if ($from !== null && $c->ok('from')) {
                self::checkDate($c, 'date', $date, $from);
            }
            $row['cb_from'] = $from;
        } else {
            // จ่ายค่าใช้จ่าย: lines before VAT; a full tax invoice (เลขที่) makes its VAT input tax; withholding by line code
            $vendor = $c->exists('vendor', $b['vendor'] ?? '', 'vd_mstr', 'vd_code', false);
            $vd = $vendor ? App::db()->one('SELECT vd_name, vd_tax_id_num, vd_tax_branch FROM vd_mstr WHERE vd_code = ?', [$vendor]) : null;
            $payee = $c->text('payee', ($b['payee'] ?? '') !== '' ? $b['payee'] : ($vd['vd_name'] ?? ''), true, 255);
            $row['cb_rcpt_nbr'] = $c->text('rcpt_nbr', $b['rcpt_nbr'] ?? '', false, 50);
            // VAT is chosen line by line; any VAT needs the vendor's full tax invoice (its VAT is input tax)
            $hasVat = array_filter(array_map(fn ($l) => is_array($l) ? trim((string) ($l['vat'] ?? '')) : '', is_array($b['lines'] ?? null) ? $b['lines'] : [])) !== [];
            $inv = $hasVat ? $c->text('inv_nbr', $b['inv_nbr'] ?? '', true, 50) : null;
            // VAT (input tax on the vendor's tax invoice) and withholding (50 ทวิ) both name the vendor: its tax id and branch
            // come from the vendor register, so either one needs a vendor chosen
            $hasWht = array_filter(array_map(fn ($l) => is_array($l) ? trim((string) ($l['wht'] ?? '')) : '', is_array($b['lines'] ?? null) ? $b['lines'] : [])) !== [];
            if (($hasVat || $hasWht) && $vendor === null && $c->ok('vendor')) {
                $c->fail('vendor', t('pettycash.vendor_needed'));
            }
            if ($hasVat) {
                $row = array_replace($row, [
                    'cb_inv_nbr' => $inv, 'cb_inv_date' => $c->date('inv_date', $b['inv_date'] ?? '', true),
                    'cb_tax_id' => $vd['vd_tax_id_num'] ?? null, 'cb_tax_branch' => $vd['vd_tax_branch'] ?? null,
                ]);
                if ($vd && !preg_match('/^\d{13}$/', (string) $vd['vd_tax_id_num'])) {
                    $c->fail('vendor', t('pettycash.vendor_tax_id'));
                }
            }
            foreach (array_values(is_array($b['lines'] ?? null) ? $b['lines'] : []) as $i => $l) {
                if (trim((string) ($l['acct'] ?? '')) === '' && trim((string) ($l['desc'] ?? '')) === '' && trim((string) ($l['amt'] ?? '')) === '') {
                    continue;
                }
                $f = fn (string $k) => "lines.$i.$k";
                // the account may be left out: the line then goes to ค่าใช้จ่ายเงินสดย่อย (gl_pc_exp_acct), its description says what it was
                $acct = $c->exists($f('acct'), $l['acct'] ?? '', 'ac_mstr', 'ac_acct', false, 'ac_active = 1', false) ?? Gl::acct('pc_exp');
                if ($acct !== null && $c->ok($f('acct')) && App::db()->value('SELECT ac_is_control FROM ac_mstr WHERE ac_acct = ?', [$acct])) {
                    $c->fail($f('acct'), t('accounting.control_acct'));
                }
                $lines[] = [
                    'acct' => $acct,
                    'cc'   => $c->exists($f('cc'), $l['cc'] ?? '', 'cc_mstr', 'cc_cc', false),
                    'desc' => $c->text($f('desc'), $l['desc'] ?? '', true, 255),
                    'amt'  => $c->number($f('amt'), $l['amt'] ?? '', true, 0, true, 2),
                    'wht'  => $c->exists($f('wht'), $l['wht'] ?? '', 'wht_mstr', 'wht_code', false),
                    'vat'  => $c->exists($f('vat'), $l['vat'] ?? '', 'tx_mstr', 'tx_tax_code', false),
                ];
            }
            if (!$lines) {
                $c->fail('lines', t('pettycash.no_lines'));
            }
            $form = null;
            if (array_filter(array_column($lines, 'wht')) !== []) {
                $form = (string) ($b['wht_form'] ?? '');
                if (!in_array($form, ['PND3', 'PND53'], true)) {
                    $c->fail('wht_form', t('validation.required'));
                }
            }
            $c->done();
            $net = round(array_sum(array_column($lines, 'amt')), 2);
            $vat = round(array_sum(array_map(fn ($l) => $l['vat'] ? round($l['amt'] * Money::taxPct($l['vat']) / 100, 2) : 0.0, $lines)), 2);
            $row['cb_tax_code'] = array_values(array_filter(array_column($lines, 'vat')))[0] ?? null;
            foreach ($lines as $l) {
                if ($l['wht']) {
                    $whts[$l['wht']]['base'] = round(($whts[$l['wht']]['base'] ?? 0) + $l['amt'], 2);
                }
            }
            foreach ($whts as $code => &$w) {
                $w += App::db()->one('SELECT wht_desc, wht_pct, wht_acct, wht_form_row FROM wht_mstr WHERE wht_code = ?', [$code]);
                $w['code'] = $code;
                $w['tax'] = round($w['base'] * (float) $w['wht_pct'] / 100, 2);
                if (!$w['wht_acct']) {
                    throw HttpException::invalid(['lines' => t('accounting.wht_no_acct', ['code' => $code])]);
                }
            }
            unset($w);
            $whtAmt = round(array_sum(array_column($whts, 'tax')), 2);
            $amt = round($net + $vat - $whtAmt, 2);
            $row = array_replace($row, ['cb_payee' => $payee, 'cb_vd_code' => $vendor, 'cb_net_amt' => $net, 'cb_vat_amt' => $vat, 'cb_wht_amt' => $whtAmt,
                                        'cb_wht_form' => $whts ? $form : null, 'cb_amt' => $amt]);
            foreach ($lines as $l) {
                $post[] = [$l['acct'], $l['amt'], $l['desc'] ?: $payee, $l['cc']];
            }
            $post[] = [Gl::acct('vat_in'), $vat, t('pettycash.desc_vat', ['inv' => $inv ?? ''])];
            foreach ($whts as $w) {
                $post[] = [$w['wht_acct'], -$w['tax'], $w['wht_desc']];
            }
            $post[] = [$pcAcct, -$amt];
        }
        $c->done();
        if ($type === 'WD') {
            $post = [[$pcAcct, $row['cb_amt']], [CashReceiptController::bankAcct($row['cb_bank']), -$row['cb_amt']]];
        } elseif ($type === 'RT') {
            $post = [[CashReceiptController::bankAcct($row['cb_bank']), $row['cb_amt']], [$pcAcct, -$row['cb_amt']]];
        } elseif ($type === 'IN') {
            $post = [[$pcAcct, $row['cb_amt']], [$row['cb_acct'], -$row['cb_amt'], $row['cb_payee']]];
        }
        return [$row, $lines, $whts, $post];
    }

    /** Save a new entry of the signed-in user's box. @return string the number given (PC + the entry's year and month) */
    public static function create(array $b): string
    {
        $holder = (string) Auth::userId();
        [$row, $lines, $whts, $post] = self::parse($b, $holder);
        return App::db()->transaction(function (Db $db) use ($row, $lines, $whts, $post, $holder) {
            self::lockAndCheck($db, $row, $holder);
            $user = Auth::userId();
            // money passed on is two entries, each with its own number, tied by cb_ref: the giver's TO (out), then this TF (in)
            $given = $row['cb_type'] === 'TF' ? Numbering::next('PC', $row['cb_date']) : null;
            $nbr = Numbering::next('PC', $row['cb_date']);
            if ($given) {
                $db->insert('cb_mstr', [
                    'cb_nbr' => $given, 'cb_type' => 'TO', 'cb_date' => $row['cb_date'], 'cb_holder' => $row['cb_from'], 'cb_from' => $holder,
                    'cb_ref' => $nbr, 'cb_amt' => $row['cb_amt'], 'cb_remarks' => $row['cb_remarks'], 'cb_status' => 'A', 'created_by' => $user, 'updated_by' => $user,
                ]);
                $row['cb_ref'] = $given;
            }
            $db->insert('cb_mstr', $row + ['cb_nbr' => $nbr, 'cb_status' => 'A', 'created_by' => $user, 'updated_by' => $user]);
            self::apply($db, $nbr, $row, $lines, $whts, $post, null, $given);
            return $nbr;
        });
    }

    /**
     * Correct an entry: by whoever made it, while its month is not counted (undo the count first). It keeps its number
     * (and its 50 ทวิ number) and its kind; the register and lines are written again, the ledger entry reversed and posted anew.
     */
    public static function update(string $nbr, array $b): void
    {
        $holder = (string) Auth::userId();
        $old = self::editable($nbr);
        [$row, $lines, $whts, $post] = self::parse(['type' => $old['cb_type'], '_nbr' => $nbr] + $b, $holder);
        App::db()->transaction(function (Db $db) use ($nbr, $old, $row, $lines, $whts, $post, $holder) {
            $db->one('SELECT cb_nbr FROM cb_mstr WHERE cb_nbr = ? FOR UPDATE', [$nbr]);
            // the old entry leaves the boxes first, so the new one is checked against the cash there would be without it
            $db->run('DELETE FROM cbh_hist WHERE cbh_nbr = ?', [$nbr]);
            self::lockAndCheck($db, $row, $holder);
            $db->run('DELETE FROM cbd_det WHERE cbd_nbr = ?', [$nbr]);
            $cert = $db->value("SELECT whtc_nbr FROM wht_cert WHERE whtc_doc_ref = ? AND whtc_status <> 'C'", [$nbr]) ?: null;
            if ($cert) {
                $db->run('DELETE FROM whtd_det WHERE whtd_nbr = ?', [$cert]);
                if (!$whts) {
                    $db->update('wht_cert', ['whtc_status' => 'C', 'updated_by' => $holder], ['whtc_nbr' => $cert]);
                }
            }
            $db->update('cb_mstr', $row + ['updated_by' => $holder], ['cb_nbr' => $nbr]);
            if ($old['cb_type'] !== 'TF') {
                Gl::reverse($nbr, 'PC', max($old['cb_date'], Gl::periodStart()), t('pettycash.desc_edit', ['nbr' => $nbr]));
            }
            self::apply($db, $nbr, $row, $lines, $whts, $post, $cert);
            // with the corrected entry in place no box may be below zero (less taken out, money already passed on or spent)
            foreach (array_filter([$old['cb_holder'], $old['cb_from'], $row['cb_from'] ?? null]) as $who) {
                if (self::balance($who) < -0.001) {
                    throw new HttpException(409, t('pettycash.cancel_spent', ['who' => self::name($who)]));
                }
            }
        });
    }

    /** An entry its maker may still change or cancel: theirs, in use, not a count difference, its month(s) not counted. */
    public static function editable(string $nbr): array
    {
        $h = App::db()->one('SELECT * FROM cb_mstr WHERE cb_nbr = ?', [$nbr]) ?? throw HttpException::notFound();
        if ($h['cb_status'] !== 'A' || $h['cb_type'] === 'CT') {
            throw new HttpException(409, t('purchase.wrong_status'));
        }
        if ($h['cb_type'] === 'TO') {
            throw new HttpException(409, t('pettycash.handover_locked'));
        }
        self::own($h);
        if (in_array($h['cb_type'], ['DO', 'DI'], true)) {
            throw new HttpException(409, t('pettycash.from_document', ['ref' => $h['cb_ref']]));
        }
        // money passed on changes two boxes at once: it stays as it is; the giver receives it back to put it right
        if ($h['cb_type'] === 'TF') {
            throw new HttpException(409, t('pettycash.handover_locked'));
        }
        foreach (array_filter([$h['cb_holder'], $h['cb_from']]) as $who) {
            $last = self::lastCounted($who);
            if ($last && substr($h['cb_date'], 0, 7) <= $last) {
                throw new HttpException(409, t('pettycash.month_counted', ['who' => self::name($who), 'month' => self::monthText($last)]));
            }
        }
        if (Gl::reconciled($nbr, 'PC')) {
            throw new HttpException(409, t('accounting.reconciled'));
        }
        return $h;
    }

    /** Only what one entered oneself, in one's own box. */
    private static function own(array $h): void
    {
        if (Auth::userId() !== $h['created_by'] || Auth::userId() !== $h['cb_holder']) {
            throw new HttpException(403, t('pettycash.not_maker'));
        }
    }

    /** Files (receipts, slips) go on any entry its maker entered, money passed on too, while the month is not counted. */
    public static function fileable(string $nbr): array
    {
        $h = App::db()->one('SELECT * FROM cb_mstr WHERE cb_nbr = ?', [$nbr]) ?? throw HttpException::notFound();
        self::own($h);
        $last = self::lastCounted($h['cb_holder']);
        if ($h['cb_status'] !== 'A' || $h['cb_type'] === 'CT' || ($last && substr($h['cb_date'], 0, 7) <= $last)) {
            throw new HttpException(409, t('pettycash.files_locked'));
        }
        return $h;
    }

    /** Lock the box the cash leaves and check it holds enough (inside the saving transaction). */
    private static function lockAndCheck(Db $db, array $row, string $holder): void
    {
        $type = $row['cb_type'];
        $out = $type === 'TF' ? $row['cb_from'] : $holder;
        $db->all('SELECT cbh_id FROM cbh_hist WHERE cbh_holder = ? FOR UPDATE', [$out]);
        if (in_array($type, ['RT', 'TF', 'EX'], true) && (float) $row['cb_amt'] > self::balance($out) + 0.001) {
            throw HttpException::invalid([$type === 'EX' ? 'lines' : 'amount' =>
                t('pettycash.not_enough', ['who' => self::name($out), 'amount' => number_format(self::balance($out), 2)])]);
        }
    }

    /** Write an entry's register rows, lines, 50 ทวิ ($cert: keep that number) and ledger entry. */
    private static function apply(Db $db, string $nbr, array $row, array $lines, array $whts, array $post, ?string $cert, ?string $given = null): void
    {
        $user = Auth::userId();
        $amt = (float) $row['cb_amt'];
        $holder = $row['cb_holder'];
        $add = fn (string $who, float $v, string $ref) => $db->insert('cbh_hist', [
            'cbh_holder' => $who, 'cbh_date' => $row['cb_date'], 'cbh_nbr' => $ref, 'cbh_amt' => $v, 'created_by' => $user, 'updated_by' => $user,
        ]);
        match ($row['cb_type']) {
            'WD', 'IN' => $add($holder, $amt, $nbr),
            'RT', 'EX' => $add($holder, -$amt, $nbr),
            // it stays petty cash: only the register moves (the giver's own entry, the receiver's), no ledger entry
            'TF' => [$add($row['cb_from'], -$amt, (string) $given), $add($holder, $amt, $nbr)],
        };
        foreach ($lines as $i => $l) {
            $db->insert('cbd_det', [
                'cbd_nbr' => $nbr, 'cbd_line' => $i + 1, 'cbd_acct' => $l['acct'], 'cbd_cc' => $l['cc'], 'cbd_desc' => $l['desc'],
                'cbd_amt' => $l['amt'], 'cbd_tax_code' => $l['vat'], 'cbd_wht_code' => $l['wht'], 'created_by' => $user, 'updated_by' => $user,
            ]);
        }
        if ($whts) {
            // หนังสือรับรองหัก ณ ที่จ่าย: the same series and register as payments (50 ทวิ, ภ.ง.ด.3/53)
            $head = [
                'whtc_type' => array_key_first($whts), 'whtc_doc_ref' => $nbr, 'whtc_vd_cm_code' => $row['cb_vd_code'], 'whtc_date' => $row['cb_date'],
                'whtc_form_type' => $row['cb_wht_form'], 'whtc_base_amt' => round(array_sum(array_column($whts, 'base')), 2),
                'whtc_tax_amt' => $row['cb_wht_amt'], 'whtc_status' => 'A', 'updated_by' => $user,
            ];
            if ($cert) {
                $db->update('wht_cert', $head, ['whtc_nbr' => $cert]);
            } else {
                $cert = Numbering::next('WHT', $row['cb_date']);
                $db->insert('wht_cert', $head + ['whtc_nbr' => $cert, 'created_by' => $user]);
            }
            foreach (array_values($whts) as $i => $w) {
                $db->insert('whtd_det', [
                    'whtd_nbr' => $cert, 'whtd_line' => $i + 1, 'whtd_code' => $w['code'], 'whtd_desc' => $w['wht_desc'], 'whtd_pct' => $w['wht_pct'],
                    'whtd_form_row' => $w['wht_form_row'], 'whtd_base_amt' => $w['base'], 'whtd_tax_amt' => $w['tax'], 'created_by' => $user, 'updated_by' => $user,
                ]);
            }
        }
        if ($post) {
            Gl::post($nbr, 'PC', $row['cb_date'], $post, t('pettycash.desc_gl', ['nbr' => $nbr, 'who' => self::name($holder)]));
        }
    }

    /** Cancel an entry its maker entered, of a month not yet counted. */
    public static function cancel(string $nbr): void
    {
        App::db()->transaction(function (Db $db) use ($nbr) {
            $h = self::editable($nbr);
            $db->one('SELECT cb_nbr FROM cb_mstr WHERE cb_nbr = ? FOR UPDATE', [$nbr]);
            // taking cash back out of a box must not leave it below nothing
            $gain = $db->one('SELECT cbh_holder, cbh_amt FROM cbh_hist WHERE cbh_nbr = ? AND cbh_amt > 0', [$nbr]);
            if ($gain && self::balance($gain['cbh_holder']) - (float) $gain['cbh_amt'] < -0.001) {
                throw new HttpException(409, t('pettycash.cancel_spent', ['who' => self::name($gain['cbh_holder'])]));
            }
            $me = Auth::userId();
            $db->run('DELETE FROM cbh_hist WHERE cbh_nbr = ?', [$nbr]);
            $db->update('cb_mstr', ['cb_status' => 'X', 'updated_by' => $me], ['cb_nbr' => $nbr]);
            $db->run("UPDATE wht_cert SET whtc_status = 'C', updated_by = ? WHERE whtc_doc_ref = ?", [$me, $nbr]);
            if ($h['cb_type'] !== 'TF') {
                // on its own date: the month is still open in the box, and so in the books (a period closes only once counted)
                Gl::reverse($nbr, 'PC', max($h['cb_date'], Gl::periodStart()), t('pettycash.desc_cancel', ['nbr' => $nbr]));
            }
        });
    }

    /** The "bank" of a payment or receipt is petty cash: the cash goes through the box of whoever makes it. */
    public static function isPetty(?string $bank): bool
    {
        return $bank !== null && (bool) App::db()->value('SELECT bk_petty FROM bk_mstr WHERE bk_bank = ?', [$bank]);
    }

    /**
     * A payment (DO, cash out) or receipt / deposit (DI, cash in) made with petty cash, inside that document's transaction:
     * its maker's box must take that date ($field: the document's date field for the error) and, paying, hold the cash.
     * The document posts its own entry against the petty cash account; this only writes the box.
     */
    public static function fromDocument(Db $db, string $ref, string $kind, string $date, float $amt, string $who, string $field): void
    {
        $holder = (string) Auth::userId();
        $c = new Check();
        self::checkDate($c, $field, $date, $holder);
        $c->done();
        $db->all('SELECT cbh_id FROM cbh_hist WHERE cbh_holder = ? FOR UPDATE', [$holder]);
        if ($kind === 'DO' && $amt > self::balance($holder) + 0.001) {
            throw HttpException::invalid([$field => t('pettycash.not_enough', ['who' => self::name($holder), 'amount' => number_format(self::balance($holder), 2)])]);
        }
        $nbr = Numbering::next('PC', $date);
        $user = Auth::userId();
        $db->insert('cb_mstr', [
            'cb_nbr' => $nbr, 'cb_type' => $kind, 'cb_date' => $date, 'cb_holder' => $holder, 'cb_ref' => $ref, 'cb_payee' => $who,
            'cb_amt' => round($amt, 2), 'cb_status' => 'A', 'created_by' => $user, 'updated_by' => $user,
        ]);
        $db->insert('cbh_hist', ['cbh_holder' => $holder, 'cbh_date' => $date, 'cbh_nbr' => $nbr, 'cbh_amt' => $kind === 'DO' ? -round($amt, 2) : round($amt, 2),
                                 'created_by' => $user, 'updated_by' => $user]);
    }

    /** The document made with petty cash is cancelled: its box entry goes too, while that month is not counted. */
    public static function documentCancelled(Db $db, string $ref): void
    {
        $h = $db->one("SELECT * FROM cb_mstr WHERE cb_ref = ? AND cb_status = 'A' AND cb_type IN ('DO', 'DI') FOR UPDATE", [$ref]);
        if (!$h) {
            return;
        }
        $last = self::lastCounted($h['cb_holder']);
        if ($last && substr($h['cb_date'], 0, 7) <= $last) {
            throw new HttpException(409, t('pettycash.month_counted', ['who' => self::name($h['cb_holder']), 'month' => self::monthText($last)]));
        }
        if ($h['cb_type'] === 'DI' && self::balance($h['cb_holder']) - (float) $h['cb_amt'] < -0.001) {
            throw new HttpException(409, t('pettycash.cancel_spent', ['who' => self::name($h['cb_holder'])]));
        }
        $db->run('DELETE FROM cbh_hist WHERE cbh_nbr = ?', [$h['cb_nbr']]);
        $db->update('cb_mstr', ['cb_status' => 'X', 'updated_by' => Auth::userId()], ['cb_nbr' => $h['cb_nbr']]);
    }

    /**
     * Count the signed-in user's box for its open month: $notes = [denomination => pieces]. A difference becomes a CT
     * entry on the month's last day (Dr/Cr เงินขาดเกินบัญชี); the month is then locked.
     */
    public static function count(string $month, array $notes, ?string $remarks): array
    {
        $holder = (string) Auth::userId();
        return App::db()->transaction(function (Db $db) use ($holder, $month, $notes, $remarks) {
            $db->all('SELECT cbh_id FROM cbh_hist WHERE cbh_holder = ? FOR UPDATE', [$holder]);
            $open = self::openMonth($holder);
            if ($open === null || $month !== $open) {
                throw HttpException::invalid(['month' => $open ? t('pettycash.count_first', ['who' => self::name($holder), 'month' => self::monthText($open)]) : t('pettycash.nothing_to_count')]);
            }
            $end = self::monthEnd($month);
            if (date('Y-m-d') < $end) {
                throw HttpException::invalid(['month' => t('pettycash.month_not_over', ['date' => self::dateText($end)])]);
            }
            // every bill paid in the month shows its proof: a receipt number, a tax invoice or a file
            $bare = array_column($db->all(
                "SELECT cb_nbr FROM cb_mstr c WHERE c.cb_holder = ? AND c.cb_type = 'EX' AND c.cb_status = 'A' AND c.cb_date BETWEEN ? AND ?
                    AND COALESCE(c.cb_rcpt_nbr, '') = '' AND COALESCE(c.cb_inv_nbr, '') = ''
                    AND NOT EXISTS (SELECT 1 FROM att_det a WHERE a.att_doc = 'PC' AND a.att_nbr = c.cb_nbr) ORDER BY cb_nbr",
                [$holder, "$month-01", $end]
            ), 'cb_nbr');
            if ($bare) {
                throw HttpException::invalid(['month' => t('pettycash.receipt_missing', ['nbrs' => implode(', ', $bare)])]);
            }
            $c = new Check();
            $pieces = [];
            $counted = 0.0;
            foreach (self::DENOMINATIONS as $d) {
                $n = $c->number("notes.$d", $notes[$d] ?? '0', false, 0, false, 0) ?? 0;
                if ($n > 0) {
                    $pieces[$d] = (int) $n;
                    $counted += $n * (float) $d;
                }
            }
            $c->done();
            $counted = round($counted, 2);
            $book = self::balance($holder, $end);
            $diff = round($counted - $book, 2);
            $user = Auth::userId();
            $ct = null;
            if ($diff != 0.0) {
                $ct = Numbering::next('PC', $end);
                $db->insert('cb_mstr', [
                    'cb_nbr' => $ct, 'cb_type' => 'CT', 'cb_date' => $end, 'cb_holder' => $holder, 'cb_amt' => $diff, 'cb_status' => 'A',
                    'cb_remarks' => $remarks, 'created_by' => $user, 'updated_by' => $user,
                ]);
                $db->insert('cbh_hist', ['cbh_holder' => $holder, 'cbh_date' => $end, 'cbh_nbr' => $ct, 'cbh_amt' => $diff, 'created_by' => $user, 'updated_by' => $user]);
                Gl::post($ct, 'PC', $end, [[Gl::acct('pc'), $diff], [Gl::acct('cash_diff'), -$diff]],
                    t($diff < 0 ? 'pettycash.desc_short' : 'pettycash.desc_over', ['who' => self::name($holder), 'month' => self::monthText($month)]));
            }
            $db->insert('cbc_mstr', [
                'cbc_holder' => $holder, 'cbc_month' => $month, 'cbc_book' => $book, 'cbc_counted' => $counted, 'cbc_diff' => $diff,
                'cbc_notes' => json_encode($pieces), 'cbc_ct_nbr' => $ct, 'cbc_remarks' => $remarks, 'created_by' => $user, 'updated_by' => $user,
            ]);
            return ['month' => $month, 'book' => $book, 'counted' => $counted, 'diff' => $diff, 'nbr' => $ct];
        });
    }

    /** The latest count of a box can be undone: nothing in the box is dated after it and the books still take that month. */
    public static function canUncount(string $holder): bool
    {
        $last = self::lastCounted($holder);
        return $last !== null && self::monthEnd($last) >= Gl::periodStart()
            && !App::db()->value('SELECT 1 FROM cbh_hist WHERE cbh_holder = ? AND cbh_date > ? LIMIT 1', [$holder, self::monthEnd($last)]);
    }

    /**
     * Undo the signed-in user's latest count (forgot an entry: undo, correct the month, count again) while nothing is
     * dated after it and its period is still open in the books. Its difference entry is cancelled with it.
     */
    public static function uncount(): void
    {
        $holder = (string) Auth::userId();
        App::db()->transaction(function (Db $db) use ($holder) {
            $db->all('SELECT cbh_id FROM cbh_hist WHERE cbh_holder = ? FOR UPDATE', [$holder]);
            $last = self::lastCounted($holder) ?? throw HttpException::notFound();
            $end = self::monthEnd($last);
            if ($db->value('SELECT 1 FROM cbh_hist WHERE cbh_holder = ? AND cbh_date > ? LIMIT 1', [$holder, $end])) {
                throw new HttpException(409, t('pettycash.uncount_later'));
            }
            if ($end < Gl::periodStart()) {
                throw new HttpException(409, t('accounting.period_closed', ['date' => Gl::periodStart()]));
            }
            $row = $db->one('SELECT * FROM cbc_mstr WHERE cbc_holder = ? AND cbc_month = ?', [$holder, $last]);
            if ($row['cbc_ct_nbr']) {
                $db->run('DELETE FROM cbh_hist WHERE cbh_nbr = ?', [$row['cbc_ct_nbr']]);
                $db->update('cb_mstr', ['cb_status' => 'X', 'updated_by' => $holder], ['cb_nbr' => $row['cbc_ct_nbr']]);
                Gl::reverse($row['cbc_ct_nbr'], 'PC', $end, t('pettycash.desc_cancel', ['nbr' => $row['cbc_ct_nbr']]));
            }
            $db->run('DELETE FROM cbc_mstr WHERE cbc_holder = ? AND cbc_month = ?', [$holder, $last]);
        });
    }

    public static function name(string $userId): string
    {
        static $names = [];
        return $names[$userId] ??= (string) (App::db()->value('SELECT us_name FROM us_mstr WHERE us_userid = ?', [$userId]) ?: $userId);
    }

    public static function monthEnd(string $month): string
    {
        return date('Y-m-t', strtotime("$month-01"));
    }

    public static function nextMonth(string $month): string
    {
        return date('Y-m', strtotime("$month-01 +1 month"));
    }

    /** "ต.ค. 2569" */
    public static function monthText(string $month): string
    {
        $names = explode(',', t('home.months'));
        return ($names[(int) substr($month, 5, 2) - 1] ?? '') . ' ' . ((int) substr($month, 0, 4) + (int) t('js.year_offset'));
    }

    public static function dateText(string $date): string
    {
        return implode('/', array_reverse(explode('-', $date)));
    }
}
