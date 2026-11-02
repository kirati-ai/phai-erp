<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Delete;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Put;

/** The company stamp (SignImage), changed on the company screen beside the logo; printed beside signed boxes. */
#[Program('company')]
final class StampController
{
    #[Get('/api/company/stamp')]
    public function show(): array
    {
        return ['image' => SignImage::dataUrl(null)];
    }

    /** Body {image: PNG data URL}, trimmed and scaled by js/sign-pad.js. */
    #[Put('/api/company/stamp')]
    public function update(Request $request): array
    {
        $name = SignImage::store(null, (string) ($request->body['image'] ?? ''));
        App::db()->update('co_mstr', ['co_stamp_path' => $name, 'updated_by' => Auth::userId()], ['co_id' => 1]);
        Audit::updated('co_mstr', 'co_id=1', ['co_stamp_path' => null], ['co_stamp_path' => $name . ' ' . date('c')]);
        return ['image' => SignImage::dataUrl(null)];
    }

    #[Delete('/api/company/stamp')]
    public function delete(): array
    {
        SignImage::remove(null);
        App::db()->update('co_mstr', ['co_stamp_path' => null, 'updated_by' => Auth::userId()], ['co_id' => 1]);
        Audit::updated('co_mstr', 'co_id=1', ['co_stamp_path' => 'stamp'], ['co_stamp_path' => null]);
        return ['image' => null];
    }
}
