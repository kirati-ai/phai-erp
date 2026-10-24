<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\Routing\Get;
use Phai\Core\View\View;

/**
 * "เรียนรู้ ERP": what an ERP is, how its modules hand work to each other, every document of each chain with its
 * official Thai name and accounting entry, and a glossary. Teaching material like the help pages, so any logged-in
 * user may read it (no program); the text lives in views/learn/<lang>.php.
 */
final class LearnController
{
    #[Get('/learn')]
    public function index(): string
    {
        return View::render('learn/index', ['title' => t('common.learn'), 'help' => 'home']);
    }
}
