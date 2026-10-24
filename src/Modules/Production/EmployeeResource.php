<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\App;
use Phai\Core\Crud\Resource;
use Phai\Core\Http\HttpException;

/**
 * Employees: who works the processes. One who signs in is linked to a user (emp_userid), so the screens put
 * their name in "requested by" / "inspector" and their number on labour. Pay is not handled here.
 */
final class EmployeeResource extends Resource
{
    public const CODE = 'employees';
    protected static string $table = 'emp_mstr';
    protected static array $key = ['emp_nbr'];
    protected static array $search = ['emp_nbr', 'emp_name', 'emp_dept'];
    protected static array $list = ['emp_nbr', 'emp_name', 'emp_dept', 'emp_userid', 'emp_wkctr', 'emp_rate', 'emp_active'];
    // Resource narrows each width by one: these fill the rows as 4+8, 6+6, 4+4+4, 12
    protected static array $form = [
        'emp_nbr'    => ['col' => 5, 'upper' => true],
        'emp_name'   => ['col' => 9],
        'emp_dept'   => ['col' => 7],
        'emp_userid' => ['col' => 7, 'lookup' => 'users'],
        'emp_wkctr'  => ['col' => 5, 'lookup' => 'work-centers'],
        'emp_type'   => [
            'col'     => 5,
            'options' => ['FULL_TIME', 'PART_TIME', 'SUBCONTRACT'],
        ],
        'emp_rate'   => ['col' => 5],
        'emp_active' => ['col' => 13],
    ];
    protected static array $usedBy = [
        'wo_labor' => 'wol_emp_nbr',
    ];

    /** A user is one employee at most. */
    public static function validate(array $data, bool $creating): array
    {
        $row = parent::validate($data, $creating);
        $user = $row['emp_userid'] ?? null;
        if ($user !== null && $user !== '') {
            $other = App::db()->value('SELECT emp_nbr FROM emp_mstr WHERE emp_userid = ? AND emp_nbr <> ?', [$user, (string) ($row['emp_nbr'] ?? $data['emp_nbr'] ?? '')]);
            if ($other) {
                throw HttpException::invalid(['emp_userid' => t('production.user_taken', ['emp' => $other])]);
            }
        } elseif (array_key_exists('emp_userid', $row)) {
            $row['emp_userid'] = null;
        }
        return $row;
    }
}
