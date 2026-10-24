<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Put;
use Phai\Core\View\View;
use Phai\Modules\Inventory\Stock;
use Phai\Modules\Purchase\PoController;
use Phai\Modules\Sales\SalesPrint;
use Phai\Modules\Sales\SoController;

/**
 * The delivery schedule (ตารางกำหนดส่ง) of a scheduled purchase order (type po: Supplier Schedule) or sales order
 * (type so: Customer Schedule), release by release (Schedule): the rights are those of the order's own screen.
 */
#[Program('purchase-orders', 'sales-orders')]
final class ScheduleController
{
    /** The scheduled orders of one side with the release in use (menu: ตารางกำหนดส่งของผู้ขาย / ให้ลูกค้า). */
    #[Get('/schedules/{type}')]
    public function listPage(string $type): string
    {
        $k = self::allow($type, 'view');
        $t = strtoupper($type);
        $po = $t === 'PO';
        return View::render('doc/list', [
            'help'    => 'schedules',
            'title'   => t('schedule.title_' . $t),
            'config'  => [
                'api'     => '/api/schedules/' . strtolower($t),
                'page'    => '/schedules/' . strtolower($t),
                'key'     => 'nbr',
                'canAdd'  => false, // a scheduled order is made on the order screen (ประเภทใบสั่ง = แบบกำหนดส่ง)
                'month'   => false, // agreements run for months
                'status'  => $po ? PoController::statusText() : SoController::statusText(),
                'print'   => Auth::can($k['prog'], 'print') ? '/schedules/' . strtolower($t) . '/{key}/print' : null,
                'columns' => [
                    ['key' => 'nbr', 'label' => t('schedule.order_' . $t), 'sort' => true, 'strong' => true],
                    ['key' => 'party', 'label' => $po ? t('purchase.vendor') : t('sales.customer'), 'sort' => true],
                    ['key' => 'party_name', 'label' => $po ? t('purchase.vendor_name') : t('sales.customer_name')],
                    ['key' => 'rlse', 'label' => t('schedule.rlse'), 'sort' => true],
                    ['key' => 'rlse_date', 'label' => t('schedule.rlse_date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'firm_date', 'label' => t('schedule.firm_date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'source_text', 'label' => t('schedule.source')],
                    ['key' => 'next_date', 'label' => t('schedule.next'), 'type' => 'date'],
                    ['key' => 'next_qty', 'label' => t('schedule.next_qty')],
                    ['key' => 'status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'scripts' => ['js/doclist.js'],
        ]);
    }

    #[Describe('Scheduled purchase orders (type po, supplier schedules) or sales orders (type so, customer schedules) with the release in use: rlse id, release date, firm date, source; next_date / next_qty = the earliest instalment still to come', ['q' => 'order number or party', 'status' => 'order status (po: A open, C closed, X cancelled; so: O open, C closed, X cancelled)'])]
    #[Get('/api/schedules/{type}')]
    public function list(string $type, Request $request): array
    {
        $k = self::allow($type, 'view');
        $t = strtoupper($type);
        [$party, $table, $code, $name] = $t === 'PO' ? ['po_vd_code', 'vd_mstr', 'vd_code', 'vd_name'] : ['so_cm_code', 'cm_mstr', 'cm_code', 'cm_name'];
        $where = ["o.{$k['sched']} = 1"];
        $params = [$t];
        if (($status = (string) $request->input('status', '')) !== '') {
            $where[] = "o.{$k['status']} = ?";
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = "(o.{$k['nbr']} LIKE ? OR o.$party LIKE ? OR x.$name LIKE ?)";
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        $data = App::db()->page(
            "SELECT o.{$k['nbr']} AS nbr, o.$party AS party, x.$name AS party_name, o.{$k['status']} AS status,
                    s.sch_rlse_id AS rlse, s.sch_date AS rlse_date, s.sch_firm_date AS firm_date, s.sch_source AS source",
            "FROM {$k['head']} o LEFT JOIN $table x ON x.$code = o.$party
               LEFT JOIN sch_mstr s ON s.sch_type = ? AND s.sch_nbr = o.{$k['nbr']}
                AND s.sch_rlse_id = (SELECT MAX(z.sch_rlse_id) FROM sch_mstr z WHERE z.sch_type = s.sch_type AND z.sch_nbr = s.sch_nbr)
              WHERE " . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['nbr' => "o.{$k['nbr']}", 'party' => "o.$party", 'rlse' => 's.sch_rlse_id', 'rlse_date' => 's.sch_date',
             'firm_date' => 's.sch_firm_date', 'status' => "o.{$k['status']}"],
            'nbr'
        );
        foreach ($data['rows'] as &$r) {
            $r['source_text'] = $r['source'] ? t('schedule.source_' . $r['source']) : '';
            [$r['next_date'], $r['next_qty']] = [null, ''];
            foreach (Schedule::lines($t, $r['nbr']) as $l) {
                foreach ($l['rows'] as $row) {
                    if ($row['qty'] - $row['done'] > 0.000005 && ($r['next_date'] === null || $row['date'] < $r['next_date'])) {
                        $r['next_date'] = $row['date'];
                        $r['next_qty'] = trim(Stock::fmt($row['qty'] - $row['done']) . ' ' . $l['um'] . ' ' . $l['part']
                            . ($row['fpps'] === 'P' ? ' (' . t('schedule.fpps_P') . ')' : ''));
                    }
                }
            }
        }
        unset($r);
        return $data;
    }

    #[Get('/schedules/{type}/{nbr}')]
    public function page(string $type, string $nbr): string
    {
        $k = self::allow($type, 'view');
        $h = self::head($k, $nbr);
        $t = strtoupper($type);
        $text = ['done' => t('schedule.done_' . $t), 'party' => (string) ($h['party'] ?? '')];
        foreach (['date', 'qty', 'left', 'add', 'total', 'rlse', 'rlse_date', 'firm_date', 'source', 'rmks', 'by', 'prev', 'fpps',
            'fpps_F', 'fpps_P', 'in_use', 'old', 'new_row', 'dropped', 'sum', 'confirm'] as $key) {
            $text[$key] = t('schedule.' . $key);
        }
        $text['remove'] = t('common.delete');
        $text['ref'] = t('schedule.ref_' . $t);
        foreach (Schedule::SOURCES as $s) {
            $text['source_' . $s] = t('schedule.source_' . $s);
        }
        return View::render('doc/schedule', [
            'help'       => 'schedules',
            'title'      => t('schedule.title_' . $t) . ' ' . $nbr,
            'ownToolbar' => true,
            'config'     => [
                'type'     => strtolower($type),
                'nbr'      => $nbr,
                'back'     => $k['page'] . '/' . rawurlencode($nbr),
                'canEdit'  => Auth::can($k['prog'], 'edit') && $h[$k['status']] === $k['open'],
                'canPrint' => Auth::can($k['prog'], 'print'),
                'sources'  => $k['sources'],
                'today'    => date('Y-m-d'),
                'firmDays' => Schedule::FIRM_DAYS,
                'text'     => $text,
            ],
            'scripts'    => ['js/pages/schedule.js'],
        ]);
    }

    /**
     * {head: {nbr, party, status, open, releases: [...]}, release: {...}, lines: [{line, part, desc, um, price, done,
     * rows: [{date, qty, done, fpps, prev}], removed: [{date, qty}]}]} — ?rlse= a release (default: the one in use),
     * compared with the release before it.
     */
    #[Describe('Delivery schedule of a scheduled purchase order (type po, supplier schedule) or sales order (type so, customer schedule): its releases (sch_rlse_id, date, firm date, source ORDER / MANUAL / MRP / CUSTOMER, ref), and for one release (rlse, default the one in use) each line\'s dated instalments: fpps F firm (shipping schedule) / P planned (forecast), how much is already received / shipped, prev = the quantity of that date in the release before, removed = dates the release dropped', ['rlse' => 'release id (empty: the one in use)'])]
    #[Get('/api/schedules/{type}/{nbr}')]
    public function show(string $type, string $nbr, ?Request $request = null): array
    {
        $k = self::allow($type, 'view');
        $h = self::head($k, $nbr);
        $t = strtoupper($type);
        $releases = Schedule::releases($t, $nbr);
        $ids = array_column($releases, 'sch_rlse_id');
        $want = (string) ($request?->input('rlse', '') ?? '');
        $i = $want === '' ? 0 : array_search($want, $ids, true);
        if ($i === false) {
            throw HttpException::notFound();
        }
        $rel = $releases[$i] ?? null;
        return [
            'head'    => ['nbr' => $nbr, 'party' => $h['party'], 'status' => $h[$k['status']], 'open' => $h[$k['status']] === $k['open'],
                          'releases' => array_map([self::class, 'release'], $releases)],
            'release' => $rel ? self::release($rel) + ['active' => $i === 0] : null,
            'lines'   => Schedule::lines($t, $nbr, $rel['sch_rlse_id'] ?? null, $ids[$i + 1] ?? null),
        ];
    }

    /** A new release (the first replaces the one made with the order). Body: Schedule::save(). */
    #[Put('/api/schedules/{type}/{nbr}')]
    public function save(string $type, string $nbr, Request $request): array
    {
        self::allow($type, 'edit');
        Schedule::save(strtoupper($type), $nbr, $request->body);
        return $this->show($type, $nbr);
    }

    /** Supplier Schedule Update from MRP: the draft of the next release ({lines: {line: [{date, qty}]}}), nothing saved. */
    #[Get('/api/schedules/{type}/{nbr}/from-mrp')]
    public function fromMrp(string $type, string $nbr, Request $request): array
    {
        $k = self::allow($type, 'edit');
        self::head($k, $nbr);
        if (strtoupper($type) !== 'PO') {
            throw HttpException::notFound();
        }
        $date = (string) $request->input('date', '') ?: date('Y-m-d');
        $firm = (string) $request->input('firm_date', '') ?: Schedule::firmDefault($date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $firm)) {
            throw HttpException::invalid(['firm_date' => t('validation.date')]);
        }
        return ['firm_date' => $firm, 'lines' => Schedule::fromMrp($nbr, $firm)];
    }

    /** The release printed for the supplier / as confirmed to the customer (?rlse=, default the one in use). */
    #[Get('/schedules/{type}/{nbr}/print'), Action('print')]
    public function print(string $type, string $nbr, Request $request): string
    {
        $k = self::allow($type, 'print');
        $h = self::head($k, $nbr);
        $t = strtoupper($type);
        $d = $this->show($type, $nbr, $request);
        $rel = $d['release'] ?? throw HttpException::notFound();
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 5), '0'), '.');
        $rows = [];
        foreach ($d['lines'] as $l) {
            foreach ($l['rows'] as $i => $r) {
                $rows[] = [$i === 0 ? $l['line'] : '', $i === 0 ? trim($l['part'] . ' ' . ($l['desc'] ?? '')) : '', SalesPrint::date($r['date']),
                    $q($r['qty']), $l['um'], t('schedule.fpps_' . $r['fpps'])];
            }
        }
        return SalesPrint::render([
            'title'  => t('schedule.print_' . $t),
            'nbr'    => $nbr . '-' . $rel['id'],
            'meta'   => [
                [t('schedule.order_' . $t), $nbr],
                [t('schedule.rlse'), $rel['id']],
                [t('schedule.rlse_date'), SalesPrint::date($rel['date'])],
                [t('schedule.firm_date'), SalesPrint::date($rel['firm_date'])],
                [t('schedule.ref_' . $t), (string) ($rel['ref'] ?? '')],
            ],
            'party'  => $t === 'PO' ? SalesPrint::vendor($h['po_vd_code']) : SalesPrint::customer($h['so_cm_code']),
            'lines'  => ['columns' => [
                ['label' => '#', 'width' => '28px'],
                ['label' => t('sales.part') . ' / ' . t('sales.desc')],
                ['label' => t('schedule.date'), 'width' => '90px'],
                ['label' => t('schedule.qty'), 'align' => 'r', 'width' => '80px'],
                ['label' => t('sales.um'), 'width' => '50px'],
                ['label' => t('schedule.fpps'), 'width' => '90px'],
            ], 'rows' => $rows],
            'totals' => [],
            'notes'  => trim(t('schedule.print_note_' . $t, ['date' => SalesPrint::date($rel['firm_date'])]) . "\n" . ($rel['rmks'] ?? '')),
            'signs'  => $t === 'PO' ? [t('purchase.buyer'), t('purchase.approve')] : [t('schedule.sign_SO')],
        ]);
    }

    private static function release(array $r): array
    {
        return ['id' => $r['sch_rlse_id'], 'date' => $r['sch_date'], 'firm_date' => $r['sch_firm_date'], 'source' => $r['sch_source'],
                'ref' => $r['sch_ref'], 'rmks' => $r['sch_rmks'], 'by' => $r['created_by']];
    }

    private static function allow(string $type, string $action): array
    {
        $k = Schedule::kind($type);
        if (!Auth::can($k['prog'], $action)) {
            throw new HttpException(403, t('auth.forbidden'));
        }
        return $k;
    }

    private static function head(array $k, string $nbr): array
    {
        $party = $k['pre'] === 'po'
            ? "CONCAT(h.po_vd_code, ' · ', COALESCE(v.vd_name, '')) AS party FROM po_mstr h LEFT JOIN vd_mstr v ON v.vd_code = h.po_vd_code"
            : "CONCAT(h.so_cm_code, ' · ', COALESCE(c.cm_name, '')) AS party FROM so_mstr h LEFT JOIN cm_mstr c ON c.cm_code = h.so_cm_code";
        $h = App::db()->one("SELECT h.*, $party WHERE h.{$k['nbr']} = ?", [$nbr]) ?? throw HttpException::notFound();
        if (!(int) $h[$k['sched']]) {
            throw HttpException::notFound(t('schedule.not_scheduled'));
        }
        return $h;
    }
}
