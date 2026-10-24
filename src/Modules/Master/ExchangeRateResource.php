<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\Crud\Resource;

/** Rate = baht per 1 unit of the currency on that date. */
final class ExchangeRateResource extends Resource
{
    public const CODE = 'exchange-rates';
    protected static string $table = 'ex_mstr';
    protected static array $key = ['ex_curr', 'ex_date'];
    protected static ?string $order = 'ex_date';
    protected static array $search = ['ex_curr'];
    protected static array $list = ['ex_curr', 'ex_date', 'ex_rate'];
    protected static array $form = [
        'ex_curr' => ['col' => 4, 'lookup' => 'currencies'],
        'ex_date' => ['col' => 4],
        'ex_rate' => ['col' => 4],
    ];
}
