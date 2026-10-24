<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\App;
use Phai\Core\Auth\Program;
use Phai\Core\Routing\Get;

/** Stock type of each product line, so the item form knows whether pt_ad_code is a vendor (RM) or a customer. */
#[Program('parts')]
final class PartLineController
{
    #[Get('/api/parts/line-types')]
    public function types(): array
    {
        return array_column(App::db()->all('SELECT pl_prod_line, pl_inventory_type FROM pl_mstr'), 'pl_inventory_type', 'pl_prod_line');
    }
}
