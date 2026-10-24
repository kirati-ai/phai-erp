<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\Db\Model;

final class Role extends Model
{
    protected static string $table = 'role_mstr';
    protected static array $key = ['role_code'];
    protected static array $search = ['role_code', 'role_desc'];
}
