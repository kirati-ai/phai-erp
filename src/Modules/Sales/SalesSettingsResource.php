<?php

declare(strict_types=1);

namespace Phai\Modules\Sales;

use Phai\Core\Crud\Resource;

final class SalesSettingsResource extends Resource
{
    public const CODE = 'settings-sales';
    public const PATH = '/settings/sales';
    protected static string $table = 'so_ctrl';
    protected static array $key = ['ctrl_id'];
    protected static bool $single = true;
    protected static array $form = [
        'so_cr_chk'  => ['col' => 12],
    ];
}
