<?php

declare(strict_types=1);

namespace Phai\Modules\Sales;

use Phai\Core\Crud\Resource;

/** Special prices and discounts. A customer uses the list in cm_pr_list; list ALL applies to everyone (promotions). */
final class PriceListResource extends Resource
{
    public const CODE = 'price-lists';
    protected static string $table = 'pc_mstr';
    protected static array $key = ['pc_list', 'pc_part', 'pc_curr', 'pc_um', 'pc_start'];
    protected static array $search = ['pc_list', 'pc_part'];
    protected static array $list = ['pc_list', 'pc_part', 'pc_amt_type', 'pc_price', 'pc_disc_pct', 'pc_min_qty', 'pc_start', 'pc_expire'];
    protected static array $form = [
        'pc_list'     => ['col' => 4, 'upper' => true],
        'pc_part'     => [
            'col'    => 4,
            'upper'  => true,
            'exists' => ['pt_mstr', 'pt_part'],
        ],
        'pc_um'       => ['col' => 4, 'lookup' => 'uoms'],
        'pc_curr'     => ['col' => 4, 'lookup' => 'currencies'],
        'pc_start'    => ['col' => 4],
        'pc_expire'   => ['col' => 4],
        'pc_amt_type' => [
            'col'     => 4,
            'options' => ['P', 'D'],
        ],
        'pc_price'    => ['col' => 4],
        'pc_disc_pct' => ['col' => 4],
        'pc_min_qty'  => ['col' => 4],
    ];
}
