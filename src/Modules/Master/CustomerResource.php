<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\Crud\Resource;
use Phai\Modules\System\Address;

final class CustomerResource extends Resource
{
    public const CODE = 'customers';
    protected static string $table = 'cm_mstr';
    protected static array $key = ['cm_code'];
    protected static ?string $files = 'CM'; // files attached in the dialog (AttachmentController)
    protected static array $search = ['cm_code', 'cm_name', 'cm_name2', 'cm_tax_id_num', 'cm_phone', 'cm_email'];
    protected static array $list = ['cm_code', 'cm_name', 'cm_phone', 'cm_province', 'cm_terms', 'cm_status'];
    /** A customer on any document stays: set it to เลิกใช้ instead. */
    protected static array $usedBy = [
        'qt_mstr' => 'qt_cm_code', 'so_mstr' => 'so_cm_code', 'sh_mstr' => 'sh_cm_code', 'ih_mstr' => 'ih_cm_code',
        'ar_mstr' => 'ar_cm_code', 'cr_mstr' => 'cr_cm_code', 'bl_mstr' => 'bl_cm_code',
    ];
    protected static array $form = [
        'cm_code'         => ['col' => 4, 'section' => 'general', 'upper' => true],
        'cm_name'         => ['col' => 8, 'section' => 'general'],
        'cm_name2'        => ['col' => 12, 'section' => 'general'],
        'cm_tax_id_num'   => ['col' => 4, 'section' => 'general'],
        'cm_tax_branch'   => ['col' => 4, 'section' => 'general'],
        'cm_status'       => [
            'col'     => 4,
            'section' => 'general',
            'options' => ['ACTIVE', 'HOLD', 'INACTIVE'],
        ],
        'cm_attn'         => ['col' => 6, 'section' => 'general'],
        'cm_phone'        => ['col' => 3, 'section' => 'general'],
        'cm_phone2'       => ['col' => 3, 'section' => 'general'],
        'cm_email'        => ['col' => 6, 'section' => 'general'],
        'cm_website'      => ['col' => 6, 'section' => 'general'],
        'cm_house_no'     => ['col' => 4, 'section' => 'address'],
        'cm_moo'          => ['col' => 4, 'section' => 'address'],
        'cm_building'     => ['col' => 8, 'section' => 'address'],
        'cm_floor'        => ['col' => 4, 'section' => 'address'],
        'cm_room'         => ['col' => 4, 'section' => 'address'],
        'cm_village'      => ['col' => 8, 'section' => 'address'],
        'cm_soi'          => ['col' => 6, 'section' => 'address'],
        'cm_yaek'         => ['col' => 6, 'section' => 'address'],
        'cm_road'         => ['col' => 6, 'section' => 'address'],
        'cm_line2'        => ['col' => 12, 'section' => 'address'],
        'cm_province'     => ['col' => 6, 'section' => 'address', 'geo' => 'province'],
        'cm_district'     => ['col' => 6, 'section' => 'address', 'geo' => 'district'],
        'cm_sub_district' => ['col' => 6, 'section' => 'address', 'geo' => 'subdistrict'],
        'cm_zip'          => ['col' => 3, 'section' => 'address', 'geo' => 'zip'],
        'cm_country'      => ['col' => 3, 'section' => 'address'],
        'cm_type'         => ['col' => 4, 'section' => 'trade'],
        // due dates, the document currency and its VAT all start from these three
        'cm_terms'        => ['col' => 4, 'section' => 'trade', 'lookup' => 'credit-terms', 'required' => true],
        'cm_curr'         => ['col' => 4, 'section' => 'trade', 'lookup' => 'currencies', 'required' => true],
        'cm_tax_code'     => ['col' => 4, 'section' => 'trade', 'lookup' => 'taxes', 'required' => true],
        'cm_cr_limit'     => ['col' => 4, 'section' => 'trade'],
        'cm_tax_in_price' => ['col' => 4, 'section' => 'trade'],
        'cm_salesperson'  => ['col' => 6, 'section' => 'trade'],
        'cm_site'         => ['col' => 6, 'section' => 'trade', 'lookup' => 'sites'],
        'cm_pr_list'      => ['col' => 6, 'section' => 'trade', 'upper' => true],
        'cm_remarks'      => ['col' => 12, 'section' => 'trade'],
        'cm_bank'         => ['col' => 6, 'section' => 'bank'],
        'cm_bank_branch'  => ['col' => 6, 'section' => 'bank'],
        'cm_bank_acct'    => ['col' => 6, 'section' => 'bank'],
        'cm_swift_code'   => ['col' => 6, 'section' => 'bank'],
    ];

    /** The one-line address (cm_line1) printed on documents is made from the address parts. */
    public static function validate(array $data, bool $creating): array
    {
        // the tax ID, branch and address go on to tax invoices, 50 ทวิ, ภ.ง.ด. and the VAT reports
        return Address::withLine(Address::requireParty($data, $creating, 'cm', fn () => parent::validate($data, $creating)), 'cm');
    }
}
