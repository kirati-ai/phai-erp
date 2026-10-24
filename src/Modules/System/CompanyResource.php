<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\Crud\Resource;

final class CompanyResource extends Resource
{
    public const CODE = 'company';
    protected static string $table = 'co_mstr';
    protected static array $key = ['co_id'];
    protected static bool $single = true;
    protected static ?string $aside = 'system/company-aside';
    protected static ?string $asideScript = 'js/pages/company.js';
    protected static array $form = [
        'co_name'         => ['col' => 8],
        'co_tax_id'       => ['col' => 4],
        'co_branch'       => ['col' => 4],
        'co_contact_name' => ['col' => 6],
        'co_phone'        => ['col' => 6],
        'co_email'        => ['col' => 6],
        'co_base_curr'    => ['col' => 6, 'lookup' => 'currencies'],
        'co_idle_min'     => ['col' => 6], // sign out after this many idle minutes (Auth, shell.js), 0 = never
        'co_remarks'      => ['col' => 12],
    ];
}
