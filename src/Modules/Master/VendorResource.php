<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\Crud\Resource;
use Phai\Modules\System\Address;

final class VendorResource extends Resource
{
    public const CODE = 'vendors';
    protected static string $table = 'vd_mstr';
    protected static array $key = ['vd_code'];
    protected static ?string $files = 'VD'; // files attached in the dialog (AttachmentController)
    protected static array $search = ['vd_code', 'vd_name', 'vd_name2', 'vd_tax_id_num', 'vd_phone', 'vd_email'];
    protected static array $list = ['vd_code', 'vd_name', 'vd_phone', 'vd_province', 'vd_terms', 'vd_status'];
    /** A vendor on any document stays: set it to เลิกใช้ instead (documents and certificates must keep their name). */
    protected static array $usedBy = [
        'po_mstr' => 'po_vd_code', 'rc_mstr' => 'rc_vd_code', 'ap_mstr' => 'ap_vd_code', 'apv_mstr' => 'apv_vd_code',
        'vo_mstr' => 'vo_vd_code', 'lc_mstr' => 'lc_vd_code', 'wht_cert' => 'whtc_vd_cm_code', 'prd_det' => 'prd_pref_vd',
    ];
    protected static array $form = [
        'vd_code'         => ['col' => 4, 'section' => 'general', 'upper' => true],
        'vd_name'         => ['col' => 8, 'section' => 'general'],
        'vd_name2'        => ['col' => 12, 'section' => 'general'],
        'vd_tax_id_num'   => ['col' => 4, 'section' => 'general'],
        'vd_tax_branch'   => ['col' => 4, 'section' => 'general'],
        'vd_status'       => [
            'col'     => 4,
            'section' => 'general',
            'options' => ['ACTIVE', 'HOLD', 'INACTIVE'],
        ],
        'vd_attn'         => ['col' => 6, 'section' => 'general'],
        'vd_phone'        => ['col' => 3, 'section' => 'general'],
        'vd_phone2'       => ['col' => 3, 'section' => 'general'],
        'vd_email'        => ['col' => 6, 'section' => 'general'],
        'vd_website'      => ['col' => 6, 'section' => 'general'],
        'vd_house_no'     => ['col' => 4, 'section' => 'address'],
        'vd_moo'          => ['col' => 4, 'section' => 'address'],
        'vd_building'     => ['col' => 8, 'section' => 'address'],
        'vd_floor'        => ['col' => 4, 'section' => 'address'],
        'vd_room'         => ['col' => 4, 'section' => 'address'],
        'vd_village'      => ['col' => 8, 'section' => 'address'],
        'vd_soi'          => ['col' => 6, 'section' => 'address'],
        'vd_yaek'         => ['col' => 6, 'section' => 'address'],
        'vd_road'         => ['col' => 6, 'section' => 'address'],
        'vd_line2'        => ['col' => 12, 'section' => 'address'],
        'vd_province'     => ['col' => 6, 'section' => 'address', 'geo' => 'province'],
        'vd_district'     => ['col' => 6, 'section' => 'address', 'geo' => 'district'],
        'vd_sub_district' => ['col' => 6, 'section' => 'address', 'geo' => 'subdistrict'],
        'vd_zip'          => ['col' => 3, 'section' => 'address', 'geo' => 'zip'],
        'vd_country'      => ['col' => 3, 'section' => 'address'],
        'vd_type'         => ['col' => 4, 'section' => 'trade'],
        // due dates, the document currency and its VAT all start from these three
        'vd_terms'        => ['col' => 4, 'section' => 'trade', 'lookup' => 'credit-terms', 'required' => true],
        'vd_curr'         => ['col' => 4, 'section' => 'trade', 'lookup' => 'currencies', 'required' => true],
        'vd_tax_code'     => ['col' => 4, 'section' => 'trade', 'lookup' => 'taxes', 'required' => true],
        'vd_wht_code'     => ['col' => 4, 'section' => 'trade', 'lookup' => 'wht-codes'],
        'vd_tax_in_price' => ['col' => 4, 'section' => 'trade'],
        'vd_buyer'        => ['col' => 4, 'section' => 'trade'],
        'vd_remarks'      => ['col' => 12, 'section' => 'trade'],
        'vd_bank'         => ['col' => 6, 'section' => 'bank'],
        'vd_bank_branch'  => ['col' => 6, 'section' => 'bank'],
        'vd_bank_acct'    => ['col' => 6, 'section' => 'bank'],
        'vd_swift_code'   => ['col' => 6, 'section' => 'bank'],
    ];

    /** The one-line address (vd_line1) printed on documents is made from the address parts. */
    public static function validate(array $data, bool $creating): array
    {
        // the tax ID, branch and address go on to tax invoices, 50 ทวิ, ภ.ง.ด. and the VAT reports
        return Address::withLine(Address::requireParty($data, $creating, 'vd', fn () => parent::validate($data, $creating)), 'vd');
    }
}
