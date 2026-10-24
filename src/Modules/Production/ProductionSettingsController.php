<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\Auth\Program;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;
use Phai\Modules\System\NumberingController;

/** Production settings: wo_ctrl holds the numbering of work orders and QC (and when MRP last ran), so the page is that table. */
#[Program('settings-production')]
final class ProductionSettingsController
{
    #[Get('/settings/production')]
    public function page(): string
    {
        return View::render('production/settings', [
            'title' => t('production.settings_title'),
            'help'  => 'settings-production',
        ]);
    }
}
