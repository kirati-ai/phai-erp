<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\Crud\Resource;

/** Main menu: rename, reorder or add links. Group headers have a code ending in ".0"; groups follow the header's mnu_seq. */
final class MenuResource extends Resource
{
    public const CODE = 'menus';
    protected static string $table = 'mnu_det';
    protected static array $key = ['mnu_menu', 'mnu_code'];
    protected static array $search = ['mnu_code', 'mnu_name', 'mnu_link', 'mnu_access'];
    protected static array $list = ['mnu_menu', 'mnu_code', 'mnu_name', 'mnu_link', 'mnu_access', 'mnu_seq'];
    protected static ?string $order = 'mnu_code';
    protected static array $form = [
        'mnu_menu'   => ['col' => 3],
        'mnu_code'   => ['col' => 3],
        'mnu_name'   => ['col' => 6],
        'mnu_link'   => ['col' => 6],
        'mnu_access' => ['col' => 6],
        'mnu_icon'   => ['col' => 6],
        'mnu_seq'    => ['col' => 3],
        'mnu_target' => ['col' => 3, 'options' => ['_self', '_blank']],
    ];
}
