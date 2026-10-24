<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Guest;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Http\Response;
use Phai\Core\Routing\Delete;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Put;

/** Company logo: shown on the login page and printed documents, changed on the company screen. */
#[Program('company')]
final class LogoController
{
    /** Public: the login page shows it before anyone signs in. */
    #[Get('/logo'), Guest]
    public function show(): Response
    {
        $file = Logo::file() ?? throw HttpException::notFound();
        return new Response((string) file_get_contents($file), 200, [
            'Content-Type'  => Logo::mime($file),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** Body: {logo: data URL} */
    #[Put('/api/company/logo')]
    public function update(Request $request): array
    {
        $name = Logo::store((string) ($request->body['logo'] ?? ''));
        $old = (string) App::db()->value('SELECT co_logo_path FROM co_mstr WHERE co_id = 1');
        App::db()->update('co_mstr', ['co_logo_path' => $name, 'updated_by' => Auth::userId()], ['co_id' => 1]);
        Audit::updated('co_mstr', 'co_id=1', ['co_logo_path' => $old], ['co_logo_path' => $name . ' ' . date('c')]);
        return ['url' => Logo::url()];
    }

    #[Delete('/api/company/logo')]
    public function delete(): array
    {
        Logo::removeFiles();
        App::db()->update('co_mstr', ['co_logo_path' => null, 'updated_by' => Auth::userId()], ['co_id' => 1]);
        Audit::updated('co_mstr', 'co_id=1', ['co_logo_path' => 'logo'], ['co_logo_path' => null]);
        return ['url' => null];
    }
}
