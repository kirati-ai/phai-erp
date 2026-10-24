<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\App;
use Phai\Core\Auth\Program;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;

/** Active employees for the name fields of the screens that ask for a person (Phai.pick source "people"). */
#[Program('employees', 'purchase-requisitions', 'qc', 'work-orders', 'stock-counts', 'fixed-assets')]
final class PeopleController
{
    /** ?q= */
    #[Get('/api/people')]
    public function list(Request $request): array
    {
        $like = '%' . addcslashes(trim((string) $request->input('q', '')), '%_\\') . '%';
        return App::db()->all(
            "SELECT emp_nbr, emp_name, COALESCE(emp_dept, '') AS emp_dept FROM emp_mstr
              WHERE emp_active = 1 AND (emp_nbr LIKE ? OR emp_name LIKE ? OR emp_dept LIKE ?)
              ORDER BY emp_name LIMIT 50",
            [$like, $like, $like]
        );
    }
}
