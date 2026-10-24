<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\Crud\Resource;
use Phai\Modules\System\Address;

final class SiteResource extends Resource
{
    public const CODE = 'sites';
    protected static string $table = 'si_mstr';
    protected static array $key = ['si_site'];
    protected static array $search = ['si_site', 'si_desc'];
    protected static array $list = ['si_site', 'si_desc', 'si_default_loc', 'si_status'];
    protected static array $form = [
        'si_site'         => ['col' => 4, 'section' => 'general', 'upper' => true],
        'si_desc'         => ['col' => 8, 'section' => 'general'],
        'si_entity'       => ['col' => 6, 'section' => 'general'],
        'si_default_loc'  => ['col' => 6, 'section' => 'general', 'upper' => true],
        'si_status'       => ['col' => 12, 'section' => 'general', 'type' => 'bool'],
        // the address in the parts the Revenue Department forms ask for; si_line1 is made from them
        'si_house_no'     => ['col' => 4, 'section' => 'address'],
        'si_moo'          => ['col' => 4, 'section' => 'address'],
        'si_building'     => ['col' => 8, 'section' => 'address'],
        'si_floor'        => ['col' => 4, 'section' => 'address'],
        'si_room'         => ['col' => 4, 'section' => 'address'],
        'si_village'      => ['col' => 8, 'section' => 'address'],
        'si_soi'          => ['col' => 6, 'section' => 'address'],
        'si_yaek'         => ['col' => 6, 'section' => 'address'],
        'si_road'         => ['col' => 6, 'section' => 'address'],
        'si_line2'        => ['col' => 12, 'section' => 'address'],
        'si_province'     => ['col' => 6, 'section' => 'address', 'geo' => 'province'],
        'si_district'     => ['col' => 6, 'section' => 'address', 'geo' => 'district'],
        'si_sub_district' => ['col' => 6, 'section' => 'address', 'geo' => 'subdistrict'],
        'si_zip'          => ['col' => 3, 'section' => 'address', 'geo' => 'zip'],
    ];
    protected static array $usedBy = [
        'fa_mstr' => 'fa_site',
        'loc_mstr' => 'loc_site',
    ];

    /** The one-line address (si_line1) is made from the address parts. */
    public static function validate(array $data, bool $creating): array
    {
        return Address::withLine(parent::validate($data, $creating), 'si');
    }
}
