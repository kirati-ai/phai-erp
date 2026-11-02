<?php

declare(strict_types=1);
namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Router;
use Phai\Core\View\View;

/** Printing missing document types, using the same authorized read APIs as their screens. */
final class DocumentPrintController
{
    public const DOCUMENTS = ['purchase-requisitions', 'receipts', 'landed-costs', 'sales-orders', 'work-orders', 'stock-counts', 'journals', 'payables'];

    #[Get('/documents/{kind}/{nbr}/print')]
    public function print(string $kind, string $nbr): string
    {
        if (!Auth::can($kind, 'print')) throw new HttpException(403, t('auth.forbidden'));
        if (!in_array($kind, self::DOCUMENTS, true)) throw HttpException::notFound();
        $router = new Router();
        $router->load(PHAI_ROOT . '/src/Modules');
        $response = $router->dispatch(new Request('GET', '/api/' . $kind . '/' . $nbr, '', [], [], [], ''));
        $data = json_decode($response->body, true)['data'] ?? [];
        if ($kind === 'sales-orders') {
            $h = $data['head'];
            $print = \Phai\Modules\Sales\SalesPrint::class;
            return $print::render([
                'title' => t('sales.so_title') . ((int) ($h['so_sched'] ?? 0) ? ' ' . t('schedule.kind') : ''), 'nbr' => $nbr,
                'meta' => [[t('sales.date'), $print::date($h['so_date'])],
                    [t('sales.req_date'), $print::date($h['so_req_date'] ?? null)],
                    [t('sales.terms'), $h['so_cr_terms'] ?? ''],
                    [t('sales.currency'), $h['so_curr']],
                    [t('sales.po_ref'), $h['so_po_ref'] ?? '']],
                'party' => $print::customer($h['so_cm_code']),
                'lines' => $print::priceLines($data['lines'], 'sod', 'sod_qty_ord'),
                'totals' => $print::totals($h['so_net_amt'] ?? ((float) $h['so_tot_amt'] - (float) $h['so_tax_amt']),
                    $h['so_tax_pct'], $h['so_tax_amt'], $h['so_tot_amt'], $h['so_curr']),
                'notes' => trim(($h['so_remarks'] ?? '') . "\n" . \Phai\Modules\Master\Schedule::notes('SO', $nbr)),
                'signs' => [t('sales.salesperson'), $print::approve(t('purchase.approve'))],
            ]);
        }
        // the other documents on the same A4 layout: their own lines, no field dump
        $print = \Phai\Modules\Sales\SalesPrint::class;
        $tables = ['prd' => 'prd_det', 'pr' => 'pr_mstr', 'rc' => 'rc_mstr', 'wod' => 'wod_det', 'wo' => 'wo_mstr', 'pit' => 'pi_tag', 'pi' => 'pi_mstr', 'gl' => 'gl_trhist'];
        $info = [];
        $f = function (string $col) use ($tables, &$info): string {
            if (\Phai\Core\Lang::has("docprint.$col")) {
                return t("docprint.$col");
            }
            $table = $tables[explode('_', $col)[0]] ?? null;
            if ($table !== null && !isset($info[$table])) {
                $info[$table] = App::db()->columnInfo($table);
            }
            return \Phai\Core\Crud\GridFields::describe($col, $table !== null ? ($info[$table][$col] ?? []) : [])['label'];
        };
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 5, '.', ','), '0'), '.');
        $m = fn ($v) => number_format((float) $v, 2);
        $title = (string) (App::db()->value('SELECT prog_name FROM prog_mstr WHERE prog_code = ?', [$kind]) ?? $kind);
        $status = fn (string $col, ?string $v) => $v === null ? '' : (\Phai\Core\Lang::has("fields.$col.$v") ? t("fields.$col.$v")
            : ($col === 'wo_status' ? (\Phai\Modules\Production\WoController::statusText()[$v]['text'] ?? $v)
            : ($col === 'pi_status' && \Phai\Core\Lang::has("stock.status_$v") ? t("stock.status_$v") : $v)));
        if ($kind === 'purchase-requisitions') {
            $h = $data['head'];
            $net = array_sum(array_map(fn ($l) => (float) $l['prd_line_amt'], $data['lines']));
            return $print::render([
                'title' => $title, 'nbr' => $nbr,
                'meta' => [[$f('pr_date'), $print::date($h['pr_date'])], [$f('pr_req_by'), $h['pr_req_by'] ?? ''], [$f('pr_dept'), $h['pr_dept'] ?? '']],
                'lines' => ['columns' => [
                    ['label' => '#', 'width' => '28px'], ['label' => t('stock.part') . ' / ' . t('stock.desc')], ['label' => $f('prd_qty_req'), 'align' => 'r'],
                    ['label' => $f('prd_um')], ['label' => $f('prd_req_date')], ['label' => $f('prd_pref_vd')],
                    ['label' => $f('prd_est_cost'), 'align' => 'r'], ['label' => t('accounting.amount'), 'align' => 'r', 'width' => '100px']],
                    'rows' => array_map(fn ($l) => [$l['prd_line'], $l['prd_part'] . ' ' . ($l['prd_desc'] ?? ''), $q($l['prd_qty_req']), $l['prd_um'] ?? '',
                        $print::date($l['prd_req_date'] ?? null), $l['prd_pref_vd'] ?? '', $m($l['prd_est_cost']), $m($l['prd_line_amt'])], $data['lines'])],
                'totals' => $print::totals($net, $h['pr_tax_pct'] ?? 0, $h['pr_tax_amt'] ?? 0, $h['pr_tot_amt'] ?? $net, \Phai\Modules\Master\Money::base()),
                'notes' => $h['pr_remarks'] ?? '',
                'signs' => [$f('pr_req_by'), $print::approve(t('purchase.approve'))],
            ]);
        }
        if ($kind === 'landed-costs') {
            $h = $data['head'];
            $costs = $data['costs'] ?? [];
            $total = array_sum(array_map(fn ($c) => (float) $c['amount'], $costs));
            return $print::render([
                'title' => $title, 'nbr' => $nbr,
                'meta' => [[$f('rc_date'), $print::date($h['rc_date'])], [$f('rc_po_nbr'), $h['rc_po_nbr'] ?? ''], [$f('rc_inv_nbr'), $h['rc_inv_nbr'] ?? ''],
                    [$f('rc_curr'), ($h['rc_curr'] ?? '') . ' · ' . $q($h['rc_ex_rate'] ?? 1)], [$f('rc_tot_amt'), $m($h['rc_tot_amt'] ?? 0)]],
                'party' => $print::vendor($h['rc_vd_code']),
                'lines' => ['columns' => [
                    ['label' => '#', 'width' => '28px'], ['label' => t('purchase.lc_type')], ['label' => t('purchase.lc_ref')], ['label' => t('purchase.lc_vendor')],
                    ['label' => t('purchase.lc_ap_ref')], ['label' => t('purchase.lc_col_alloc')], ['label' => t('accounting.amount') . ' (' . \Phai\Modules\Master\Money::base() . ')', 'align' => 'r', 'width' => '120px']],
                    'rows' => array_map(fn ($c) => [$c['line'], t('purchase.lc_' . $c['type']), $c['ref'] ?? '', $c['vendor'] ?? '', $c['ap_ref'] ?? '',
                        $c['allocated'] ? t('purchase.lc_state_done') : t('purchase.lc_state_wait'), $m($c['amount'])], $costs)],
                'totals' => ['rows' => [[t('sales.total'), $m($total), true]], 'words' => \Phai\Core\BahtText::of($total)],
                'notes' => implode("\n", array_map(fn ($l) => $l['rcd_part'] . ' ' . ($l['pt_desc1'] ?? '') . ' × ' . $q($l['rcd_qty_rcvd']) . ' = ' . $m($l['value']), $data['lines'] ?? [])),
                'signs' => [t('accounting.sign_prepared'), $print::approve(t('accounting.sign_approved'))],
            ]);
        }
        if ($kind === 'work-orders') {
            $h = $data['head'];
            return $print::render([
                'title' => $title, 'nbr' => $nbr,
                'meta' => [[$f('wo_part'), $h['wo_part'] . ' ' . ($h['pt_desc1'] ?? '')], [$f('wo_qty_ord'), $q($h['wo_qty_ord']) . ' ' . ($h['pt_um'] ?? '')],
                    [$f('wo_site'), $h['wo_site'] . ' ' . ($h['si_desc'] ?? '')], [$f('wo_rel_date'), $print::date($h['wo_rel_date'] ?? null)],
                    [$f('wo_due_date'), $print::date($h['wo_due_date'] ?? null)], [$f('wo_status'), $status('wo_status', $h['wo_status'])]],
                'lines' => ['columns' => [
                    ['label' => '#', 'width' => '28px'], ['label' => t('production.component')], ['label' => $f('wod_qty_req'), 'align' => 'r'],
                    ['label' => $f('wod_qty_iss'), 'align' => 'r'], ['label' => t('production.um')], ['label' => $f('wod_loc')]],
                    'rows' => array_map(fn ($i, $l) => [$i + 1, $l['wod_part'] . ' ' . ($l['pt_desc1'] ?? ''), $q($l['wod_qty_req']), $q($l['wod_qty_iss']),
                        $l['pt_um'] ?? '', $l['wod_loc'] ?? ''], array_keys($data['comps'] ?? []), $data['comps'] ?? [])],
                // the steps of the job, so the floor has them on the same sheet
                'notes' => trim(($h['wo_rmks'] ?? '') . "\n" . implode("\n", array_map(fn ($o) => t('production.op') . ' ' . $o['ro_op'] . ' · '
                    . $o['ro_wkctr'] . ' · ' . ($o['ro_desc'] ?? '') . ' · ' . $q($o['ro_run_time']) . ' ' . t('production.hours'), $data['ops'] ?? []))),
                'signs' => [t('production.sign_ordered'), $print::approve(t('production.sign_supervisor'))],
            ]);
        }
        if ($kind === 'stock-counts') {
            $h = $data['head'];
            return $print::render([
                'title' => $title, 'nbr' => $nbr,
                'meta' => [[$f('pi_site'), $h['pi_site'] . ' ' . ($h['si_desc'] ?? '')], [$f('pi_date'), $print::date($h['pi_date'])],
                    [$f('pi_desc'), $h['pi_desc'] ?? ''], [$f('pi_status'), $status('pi_status', $h['pi_status'])],
                    [t('stock.scope'), $h['pi_locs'] ? str_replace(',', ', ', $h['pi_locs']) : t('stock.scope_all')]],
                // a counting sheet: what the system holds, and room to write what is found
                'lines' => ['columns' => [
                    ['label' => $f('pit_tag'), 'width' => '40px'], ['label' => $f('pit_loc')], ['label' => t('stock.part') . ' / ' . t('stock.desc')],
                    ['label' => $f('pit_lot')], ['label' => $f('pit_sys_qty'), 'align' => 'r'], ['label' => $f('pit_count_qty'), 'align' => 'r', 'width' => '90px'],
                    ['label' => $f('pit_variance'), 'align' => 'r'], ['label' => t('stock.counter')]],
                    // every tag number, a voided one marked so: the tags add up on paper too
                    'rows' => array_map(fn ($l) => $l['pit_status'] === 'V'
                        ? [$l['pit_tag'], $l['pit_loc'], $l['pit_part'] . ' ' . ($l['pt_desc1'] ?? ''), $l['pit_lot'] ?? '', '', t('stock.tag_void'), '', '']
                        : [$l['pit_tag'], $l['pit_loc'], $l['pit_part'] . ' ' . ($l['pt_desc1'] ?? ''), $l['pit_lot'] ?? '',
                        $q($l['pit_sys_qty']) . ' ' . ($l['pt_um'] ?? ''), $l['pit_count_qty'] === null ? '' : $q($l['pit_count_qty']),
                        $l['pit_count_qty'] === null ? '' : $q($l['pit_variance']), $l['pit_counter'] ?? ''], $data['tags'] ?? [])],
                'signs' => [t('stock.sign_counter'), $print::approve(t('stock.sign_checker'))],
            ]);
        }
        if ($kind === 'journals') {
            $h = $data['head'];
            $dr = array_sum(array_map(fn ($l) => (float) $l['dr'], $data['lines']));
            $cr = array_sum(array_map(fn ($l) => (float) $l['cr'], $data['lines']));
            return $print::render([
                'title' => $title, 'nbr' => $h['ref'] ?? $nbr,
                'meta' => [[$f('gl_date'), $print::date($h['date'])], [$f('gl_type'), $h['type']]],
                'lines' => ['columns' => [
                    ['label' => '#', 'width' => '28px'], ['label' => t('accounting.account')], ['label' => t('accounting.cc')], ['label' => t('accounting.desc')],
                    ['label' => t('accounting.debit'), 'align' => 'r', 'width' => '100px'], ['label' => t('accounting.credit'), 'align' => 'r', 'width' => '100px']],
                    'rows' => array_map(fn ($i, $l) => [$i + 1, $l['gl_acct'] . ' ' . ($l['ac_desc_th'] ?? ''), $l['gl_cc'] ?? '', $l['gl_desc'] ?? '',
                        (float) $l['dr'] ? $m($l['dr']) : '', (float) $l['cr'] ? $m($l['cr']) : ''], array_keys($data['lines']), $data['lines'])],
                'totals' => ['rows' => [[t('accounting.debit'), $m($dr), false], [t('accounting.credit'), $m($cr), true]], 'words' => ''],
                'notes' => $h['desc'] ?? '',
                'signs' => [t('accounting.sign_prepared'), $print::approve(t('accounting.sign_approved'))],
            ]);
        }
        if ($kind === 'payables') {
            // a vendor invoice as booked: the expense / goods lines with their withholding, VAT and total
            $h = $data['head'];
            $print = \Phai\Modules\Sales\SalesPrint::class;
            $vat = \Phai\Modules\Accounting\Gl::acct('vat_in');
            $sign = $h['ap_type'] === 'CM' ? -1 : 1;
            $m = fn ($v) => number_format((float) $v, 2);
            $lines = array_values(array_filter($data['lines'], fn ($l) => $l['apd_acct'] !== $vat));
            $tax = $sign * array_sum(array_map(fn ($l) => (float) $l['apd_amt'], array_filter($data['lines'], fn ($l) => $l['apd_acct'] === $vat)));
            $net = $sign * array_sum(array_map(fn ($l) => (float) $l['apd_amt'], $lines));
            // the VAT rate only when every line carries it (a line without VAT makes a mixed rate: no % then)
            $pct = $net ? round($tax / $net * 100, 2) : 0;
            $pct = abs($pct - round($pct)) < 0.05 ? round($pct) : 0;
            return $print::render([
                'title' => t($h['ap_type'] === 'CM' ? 'accounting.ap_type_CM' : 'accounting.ap_doc'), 'nbr' => $nbr,
                'meta' => [[t('accounting.vendor_inv'), $h['ap_inv_nbr'] ?? ''], [t('accounting.date'), $print::date($h['ap_date'])],
                    [t('sales.due_date'), $print::date($h['ap_due_date'] ?? null)], [t('sales.terms'), $h['ap_cr_terms'] ?? ''],
                    [t('accounting.ap_acct'), $h['ap_acct'] ?? '']]
                    + ($h['ap_ref_ap'] ? [5 => [t('accounting.ap_ref_ap'), $h['ap_ref_ap']]] : []),
                'party' => $print::vendor($h['ap_vd_code']),
                'lines' => ['columns' => [
                    ['label' => '#', 'width' => '28px'], ['label' => t('accounting.account')], ['label' => t('accounting.desc')],
                    ['label' => t('accounting.wht_line')], ['label' => t('accounting.amount'), 'align' => 'r', 'width' => '110px']],
                    'rows' => (function () use ($lines, $m, $sign, $nbr, $print) {
                        $rows = [];
                        $goods = \Phai\Modules\Accounting\ApItems::goods($nbr);
                        foreach ($lines as $i => $l) {
                            $rows[] = [$i + 1, $l['apd_acct'] . ' ' . ($l['ac_desc_th'] ?? ''), $l['apd_desc'] ?? '',
                                $l['apd_wht_code'] ? $l['apd_wht_code'] . ' · ' . rtrim(rtrim((string) $l['wht_pct'], '0'), '.') . '%' : '',
                                $m($sign * (float) $l['apd_amt'])];
                            // under a receipt's (or return's) line: the goods it was for, once
                            $doc = \Phai\Modules\Accounting\ApItems::docOf((string) $l['apd_desc'], $goods);
                            if ($doc !== null) {
                                array_push($rows, ...array_map([$print, 'sub'], $goods[$doc]));
                                unset($goods[$doc]);
                            }
                        }
                        return $rows;
                    })()],
                'totals' => (function () use ($print, $net, $pct, $tax, $sign, $h, $lines, $m) {
                    $t = $print::totals($net, $pct, $tax, $sign * (float) $h['ap_amt'], \Phai\Modules\Master\Money::base());
                    // what the payment will withhold, per rate, and what is left to pay (as on the screen)
                    $plan = \Phai\Modules\Accounting\WhtPlan::of([$h['ap_ref'] => (float) $h['ap_amt']]);
                    foreach ($plan as $w) {
                        $t['rows'][] = [t('accounting.wht_est', ['pct' => \Phai\Modules\Accounting\WhtController::pct($w['pct'])]), '-' . $m($sign * $w['tax']), false];
                    }
                    if ($plan) {
                        $t['rows'][2][2] = 'bold'; // the total is no longer the bottom line
                        $netPay = $sign * ((float) $h['ap_amt'] - array_sum(array_column($plan, 'tax')));
                        $t['rows'][] = [t('accounting.net_to_pay'), $m($netPay), true];
                        $t['words'] = \Phai\Core\BahtText::of(abs($netPay)); // the words say what is paid
                    }
                    return $t;
                })(),
                'notes' => $h['ap_remarks'] ?? '',
                'signs' => [t('accounting.sign_prepared'), $print::approve(t('accounting.sign_approved'))],
            ]);
        }
        if ($kind === 'receipts') {
            $h = $data['head'];
            $q = fn ($value) => rtrim(rtrim(number_format((float) $value, 5, '.', ','), '0'), '.');
            return \Phai\Modules\Sales\SalesPrint::render([
                'title' => t('purchase.rc_title'), 'nbr' => $nbr,
                'meta' => [[t('purchase.date'), \Phai\Modules\Sales\SalesPrint::date($h['rc_date'])],
                    [t('purchase.po'), $h['rc_po_nbr']], [t('stock.site'), $h['rc_site']],
                    [t('purchase.inv_nbr'), $h['rc_inv_nbr'] ?? '']],
                'party' => \Phai\Modules\Sales\SalesPrint::vendor($h['rc_vd_code']),
                'lines' => ['columns' => [
                    ['label' => '#'], ['label' => t('stock.part') . ' / ' . t('stock.desc')],
                    ['label' => t('stock.qty'), 'align' => 'r'], ['label' => t('stock.um')],
                    ['label' => t('stock.loc')], ['label' => t('stock.lot')]],
                    'rows' => array_map(fn ($row) => [$row['rcd_line'], $row['rcd_part'] . ' ' . ($row['pt_desc1'] ?? ''),
                        $q($row['rcd_qty_rcvd']), $row['rcd_um'], $row['rcd_loc'], $row['rcd_lot'] ?? ''], $data['lines'])],
                'notes' => $h['rc_remarks'] ?? '', 'signs' => [t('stock.user'), $print::approve(t('purchase.approve'))],
            ]);
        }
        return View::render('doc/detail-print', ['title' => $nbr, 'docTitle' => $nbr,
            'name' => App::db()->value('SELECT prog_name FROM prog_mstr WHERE prog_code = ?', [$kind]) ?? $kind,
            'nbr' => $nbr, 'payload' => $data,
            'company' => App::db()->one('SELECT * FROM co_mstr WHERE co_id = 1') ?? []], 'blank');
    }
}
