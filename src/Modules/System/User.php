<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\Db\Model;

final class User extends Model
{
    protected static string $table = 'us_mstr';
    protected static array $key = ['us_userid'];
    protected static array $search = ['us_userid', 'us_name', 'us_email'];
    protected static array $hidden = ['us_password_hash', 'us_last_state', 'us_last_state_date'];
    protected static array $unique = ['idx_us_email' => 'us_email'];
}
