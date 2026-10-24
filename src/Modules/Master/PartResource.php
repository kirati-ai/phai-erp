<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\Crud\Resource;
use Phai\Core\App;
use Phai\Core\Db\Db;
use Phai\Core\Http\Request;

class PartResource extends Resource
{
    public const CODE = 'parts';
    protected const INVENTORY_TYPE = 'FG';
    /** true: the list shows only INVENTORY_TYPE (the /api/materials view) */
    protected const FIXED_TYPE = false;
    protected static string $table = 'pt_mstr';
    protected static array $key = ['pt_part'];
    protected static ?string $files = 'PT'; // files attached in the dialog (AttachmentController)
    // Retained in older installations for compatibility; Product Line is authoritative.
    protected static array $hidden = ['pt_inventory_type'];
    protected static array $search = ['pt_part', 'pt_desc1', 'pt_desc2', 'pt_ad_code', 'pt_article'];
    protected static array $list = ['pt_part', 'pt_desc1', 'pt_um', 'pt_prod_line', 'pt_price', 'pt_status'];
    protected static ?string $asideScript = 'js/pages/part-form.js';
    // Tabs by importance: what the system works with first, then prices, planning, and plain reference data last.
    /** An item with stock, history, documents, a BOM or prices stays: set it to OBSOLETE instead. */
    protected static array $usedBy = [
        'ld_det' => 'ld_part', 'tr_hist' => 'tr_part', 'prd_det' => 'prd_part', 'pod_det' => 'pod_part', 'rcd_det' => 'rcd_part',
        'qtd_det' => 'qtd_part', 'sod_det' => 'sod_part', 'shd_det' => 'shd_part', 'ihd_det' => 'ihd_part',
        'wo_mstr' => 'wo_part', 'wod_det' => 'wod_part', 'ps_mstr' => ['ps_par', 'ps_comp'], 'ro_mstr' => 'ro_routing',
        'pc_mstr' => 'pc_part', 'pi_tag' => 'pit_part', 'qc_hist' => 'qc_part', 'tod_det' => 'tod_part',
    ];
    protected static array $form = [
        // general: identity, unit, classification and how stock is kept
        'pt_part'        => ['col' => 3, 'section' => 'general', 'upper' => true],
        'pt_desc1'       => ['col' => 6, 'section' => 'general'],
        'pt_um'          => ['col' => 3, 'section' => 'general', 'lookup' => 'uoms'],
        'pt_desc2'       => ['col' => 6, 'section' => 'general'],
        'pt_prod_line'   => ['col' => 3, 'section' => 'general', 'lookup' => 'product-lines'],
        // after the product line: its vendor (RM) or customer (others) — part-form.js switches the list
        'pt_ad_code'     => ['col' => 3, 'section' => 'general', 'upper' => true, 'pick' => 'customers'],
        'pt_part_type'   => ['col' => 3, 'section' => 'general', 'options' => ['P', 'M']], // MRP: purchase or work order
        // classification for reports and lists (set up as product line -> group -> type)
        'pt_item_group'  => ['col' => 3, 'section' => 'general', 'lookup' => 'part-groups'],
        'pt_item_type'   => ['col' => 3, 'section' => 'general', 'lookup' => 'part-types'],
        'pt_status'      => ['col' => 3, 'section' => 'general', 'options' => ['ACTIVE', 'OBSOLETE', 'PHASE-OUT']],
        'pt_lot_ser'     => [
            'col' => 3,
            'section' => 'general',
            'options' => ['L', 'S'],
            'default' => 'L', // finished goods and materials are kept by lot (WO number / tax invoice number)
        ],
        'pt_inventory'   => ['col' => 3, 'section' => 'general'],
        // prices and cost
        'pt_price'       => ['col' => 3, 'section' => 'cost'],
        'pt_cost'        => ['col' => 3, 'section' => 'cost'],
        'pt_cost_method' => ['col' => 3, 'section' => 'cost', 'options' => ['AVG', 'STD', 'FIFO']],
        // planning (MRP)
        'pt_lead_time'   => ['col' => 3, 'section' => 'planning'],
        'pt_min_ord'     => ['col' => 3, 'section' => 'planning'],
        'pt_mult_ord'    => ['col' => 3, 'section' => 'planning'],
        'pt_max_ord'     => ['col' => 3, 'section' => 'planning'],
        'pt_pack_qty'    => ['col' => 3, 'section' => 'planning'],
        'pt_routing'     => ['col' => 3, 'section' => 'planning'], // filled in when a routing is saved
        // reference only: nothing is calculated from these
        'pt_abc'         => ['col' => 3, 'section' => 'more', 'options' => ['A', 'B', 'C']],
        'pt_article'     => ['col' => 3, 'section' => 'more'],
        'pt_drw_ref'     => ['col' => 3, 'section' => 'more'],
        'pt_bom_code'    => ['col' => 3, 'section' => 'more'],
        'pt_max_qty'     => ['col' => 3, 'section' => 'more'],
        'pt_net_wt'      => ['col' => 3, 'section' => 'more'],
        'pt_wt_um'       => ['col' => 3, 'section' => 'more', 'lookup' => 'uoms'],
        'pt_dim_l'       => ['col' => 3, 'section' => 'more'],
        'pt_dim_w'       => ['col' => 3, 'section' => 'more'],
        'pt_dim_h'       => ['col' => 3, 'section' => 'more'],
        'pt_dim_um'      => ['col' => 3, 'section' => 'more', 'lookup' => 'uoms'],
    ];
    public static function list(array $opt = []): array
    {
        $columns = array_values(array_diff(static::columns(), static::$hidden));
        $select = 'SELECT ' . implode(', ', array_map(fn ($c) => 'p.' . Db::quote($c), $columns));
        // Keep unclassified legacy items accessible without guessing their type from codes/source.
        $from = "FROM pt_mstr p LEFT JOIN pl_mstr pl ON pl.pl_prod_line = p.pt_prod_line WHERE 1 = 1";
        $params = [];
        $type = static::FIXED_TYPE ? static::INVENTORY_TYPE : strtoupper((string) ($opt['type'] ?? ''));
        if ($type !== '') {
            $from .= " AND COALESCE(pl.pl_inventory_type, 'FG') = ?";
            $params[] = $type;
        }
        if (($line = (string) ($opt['pl'] ?? '')) !== '') {
            $from .= ' AND p.pt_prod_line = ?';
            $params[] = $line;
        }
        foreach ($opt['where'] ?? [] as $column => $value) {
            if (in_array($column, $columns, true)) {
                $from .= ' AND p.' . Db::quote($column) . ' = ?';
                $params[] = $value;
            }
        }
        $opt['grid_search'] ??= $opt['q'] ?? '';
        return App::db()->page($select, $from, $params, $opt,
            array_combine($columns, array_map(fn ($c) => 'p.' . Db::quote($c), $columns)), 'pt_part');
    }

    public static function filters(): array
    {
        $lines = [];
        foreach (App::db()->all('SELECT pl_prod_line, pl_desc FROM pl_mstr WHERE pl_active = 1 ORDER BY pl_prod_line') as $l) {
            $lines[$l['pl_prod_line']] = $l['pl_prod_line'] . ' · ' . $l['pl_desc'];
        }
        return [
            ['name' => 'type', 'all' => t('stock.all_types'), 'options' => ['FG' => t('fields.pl_inventory_type.FG'), 'RM' => t('fields.pl_inventory_type.RM'), 'FA' => t('fields.pl_inventory_type.FA')]],
            ['name' => 'pl', 'all' => t('stock.all_lines'), 'options' => $lines],
        ];
    }

    public static function fields(): array
    {
        $fields = parent::fields();
        // a new row gets the first line of the type being looked at (?type=RM on the page)
        $type = static::FIXED_TYPE ? static::INVENTORY_TYPE : (strtoupper((string) (Request::current()?->query['type'] ?? '')) ?: static::INVENTORY_TYPE);
        $default = App::db()->value('SELECT pl_prod_line FROM pl_mstr WHERE pl_inventory_type = ? AND pl_active = 1 ORDER BY pl_prod_line LIMIT 1', [$type]);
        foreach ($fields as &$field) {
            if ($field['name'] === 'pt_prod_line') $field['default'] = $default;
        }
        return $fields;
    }

    public static function validate(array $data, bool $creating): array
    {
        $row = parent::validate($data, $creating);
        $errors = [];
        if ($creating && !App::db()->value("SELECT 1 FROM pl_mstr WHERE pl_prod_line = ? AND pl_inventory_type IN ('FG','RM','FA') AND pl_active = 1", [$row['pt_prod_line'] ?? ''])) {
            $errors['pt_prod_line'] = t('production.classified_product_line');
        }
        foreach (['pt_min_ord', 'pt_max_ord', 'pt_mult_ord'] as $key) {
            if (isset($row[$key]) && (float) $row[$key] < 0) $errors[$key] = t('validation.positive');
        }
        $min = (float) ($row['pt_min_ord'] ?? 0);
        $max = (float) ($row['pt_max_ord'] ?? 0);
        $mult = (float) ($row['pt_mult_ord'] ?? 1);
        if ($mult <= 0) $errors['pt_mult_ord'] = t('validation.positive');
        if ($max > 0 && ($max < $min || $max < $mult || ceil($min / max($mult, .00001)) * $mult > $max + .000001)) {
            $errors['pt_max_ord'] = t('production.invalid_order_limits');
        }
        // pt_ad_code: a vendor for a raw material, a customer for anything else
        if (!empty($row['pt_ad_code'])) {
            $line = $row['pt_prod_line'] ?? App::db()->value('SELECT pt_prod_line FROM pt_mstr WHERE pt_part = ?', [(string) ($data['pt_part'] ?? '')]);
            $rm = App::db()->value("SELECT 1 FROM pl_mstr WHERE pl_prod_line = ? AND pl_inventory_type = 'RM'", [$line]);
            [$table, $column] = $rm ? ['vd_mstr', 'vd_code'] : ['cm_mstr', 'cm_code'];
            if (!App::db()->value("SELECT 1 FROM $table WHERE $column = ?", [$row['pt_ad_code']])) $errors['pt_ad_code'] = t('validation.in');
        }
        if ($errors) throw \Phai\Core\Http\HttpException::invalid($errors);
        return $row;
    }
}
