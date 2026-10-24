<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\Crud\Resource;

/** Quality check results. A new record gets the next QC number. */
/**
 * QC records. Receiving a work order (WoController::receive) makes one with the order number, lot, good / scrap
 * and who checked; others (e.g. goods bought) are entered here. Changing quantities here does not move stock.
 */
final class QcResource extends Resource
{
    public const RESULTS = ['PASS', 'PARTIAL', 'FAIL', 'REWORK'];
    public const CODE = 'qc';
    protected static string $table = 'qc_hist';
    protected static array $key = ['qc_test_nbr'];
    protected static ?string $files = 'QC'; // files attached in the dialog (AttachmentController)
    protected static ?string $order = 'qc_test_nbr';
    protected static ?string $autoNumber = 'QC';
    protected static ?string $numberDate = 'qc_date';
    // a QC record is a document: changed only through the work order that made it (and not once it is closed)
    protected static bool $locked = true;
    protected static ?string $print = '/qc/{key}/print';
    protected static array $badge = ['qc_result' => ['PASS' => 'green', 'PARTIAL' => 'orange', 'FAIL' => 'red', 'REWORK' => 'yellow']];
    protected static array $search = ['qc_test_nbr', 'qc_ref_doc', 'qc_part', 'qc_lot'];
    protected static array $list = ['qc_test_nbr', 'qc_date', 'qc_ref_doc', 'qc_part', 'qc_lot', 'qc_qty_pass', 'qc_qty_fail', 'qc_result', 'qc_inspector'];
    protected static array $form = [
        'qc_test_nbr'    => ['col' => 4, 'readonly' => true],
        'qc_date'        => ['col' => 4, 'type' => 'date', 'readonly' => false, 'default' => 'today'],
        'qc_result'      => [
            'col'     => 4,
            'options' => self::RESULTS,
        ],
        'qc_part'        => [
            'col'    => 4,
            'upper'  => true,
            'exists' => ['pt_mstr', 'pt_part'],
        ],
        'qc_lot'         => ['col' => 4],
        'qc_ref_doc'     => ['col' => 4, 'upper' => true],
        'qc_qty_inspect' => ['col' => 4],
        'qc_qty_pass'    => ['col' => 4],
        'qc_qty_fail'    => ['col' => 4],
        'qc_inspector'   => ['col' => 8, 'pick' => 'people', 'free' => true, 'default' => 'me'],
        'qc_remarks'     => ['col' => 12],
    ];

    /** Inspected = good + scrap when only those were given. */
    public static function validate(array $data, bool $creating): array
    {
        $row = parent::validate($data, $creating);
        $sum = (float) ($row['qc_qty_pass'] ?? 0) + (float) ($row['qc_qty_fail'] ?? 0);
        if ($sum > 0 && (float) ($row['qc_qty_inspect'] ?? 0) <= 0) {
            $row['qc_qty_inspect'] = $sum;
        }
        return $row;
    }
}
