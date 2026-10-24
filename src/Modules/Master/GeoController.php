<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\App;
use Phai\Core\Routing\Get;

/** Thai provinces, districts and sub-districts for address pickers. Any logged-in user may read them. */
final class GeoController
{
    #[Get('/api/geo/provinces')]
    public function provinces(): array
    {
        return App::db()->all('SELECT pv_id AS id, pv_name_th AS name FROM pv_mstr ORDER BY pv_name_th');
    }

    #[Get('/api/geo/districts/{pv_id}')]
    public function districts(int $pv_id): array
    {
        return App::db()->all('SELECT am_id AS id, am_name_th AS name FROM am_mstr WHERE am_pv_id = ? ORDER BY am_name_th', [$pv_id]);
    }

    #[Get('/api/geo/subdistricts/{am_id}')]
    public function subdistricts(int $am_id): array
    {
        return App::db()->all(
            'SELECT tb_id AS id, tb_name_th AS name, tb_zip_code AS zip FROM tb_mstr WHERE tb_am_id = ? ORDER BY tb_name_th',
            [$am_id]
        );
    }
}
