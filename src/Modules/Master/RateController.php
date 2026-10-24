<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\Ai\Describe;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;

final class RateController
{
    /** ?curr=USD&date=2026-10-03 — baht per unit, or null when no rate is set yet. */
    #[Describe('Exchange rate to baht on a date', ['curr' => 'currency code', 'date' => 'YYYY-MM-DD'])]
    #[Get('/api/rate')]
    public function rate(Request $request): array
    {
        return ['rate' => Money::rate((string) $request->input('curr', ''), (string) $request->input('date', date('Y-m-d')))];
    }
}
