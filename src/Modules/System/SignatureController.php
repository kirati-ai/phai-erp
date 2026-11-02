<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\Request;
use Phai\Core\Http\Response;
use Phai\Core\Routing\Delete;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\View\View;

/**
 * Signing printed documents (System\Signature) and each user's own signature image. No #[Program]: like /api/me, every
 * user has their own list and signature; what they may sign is checked on the document itself, through its print route
 * and its program's print right.
 */
final class SignatureController
{
    /** รอเซ็น: what waits for my signature, and what I asked others to sign. */
    #[Get('/signatures')]
    public function page(): string
    {
        return View::render('system/signatures', ['title' => t('sign.page'), 'help' => 'signatures', 'scripts' => ['js/pages/signatures.js']]);
    }

    /** The link in a request e-mail: after logging in, straight to the document's page with its boxes. */
    #[Get('/signatures/{id}')]
    public function open(string $id): Response
    {
        $r = Signature::find((int) $id);
        return Response::redirect(url($r['sig_path']) . '?html=1');
    }

    #[Get('/api/signatures')]
    public function mine(): array
    {
        return Signature::mine();
    }

    /** ?path=: who may be asked to sign that document (those whose role may open it). */
    #[Get('/api/signatures/people')]
    public function people(Request $request): array
    {
        return Signature::people((string) ($request->query['path'] ?? ''));
    }

    /** Body {path, slot}: sign that box with my signature. */
    #[Post('/api/signatures/sign')]
    public function sign(Request $request): array
    {
        return Signature::sign((string) ($request->body['path'] ?? ''), (int) ($request->body['slot'] ?? -1));
    }

    /** Body {path, slot, userid, note}: ask someone to sign that box. */
    #[Post('/api/signatures/request')]
    public function ask(Request $request): array
    {
        $b = $request->body;
        return Signature::request((string) ($b['path'] ?? ''), (int) ($b['slot'] ?? -1), (string) ($b['userid'] ?? ''), trim(mb_substr((string) ($b['note'] ?? ''), 0, 255)));
    }

    /** Body {path, slot, note}: turn down a request to me, saying why. */
    #[Post('/api/signatures/reject')]
    public function reject(Request $request): array
    {
        $b = $request->body;
        return Signature::reject((string) ($b['path'] ?? ''), (int) ($b['slot'] ?? -1), trim((string) ($b['note'] ?? '')));
    }

    /** Body {path, slot}: take back my signature, or my request that still waits. */
    #[Post('/api/signatures/withdraw')]
    public function withdraw(Request $request): array
    {
        return Signature::withdraw((string) ($request->body['path'] ?? ''), (int) ($request->body['slot'] ?? -1));
    }

    /** My signature as a data URL (the profile dialog shows it), or null. */
    #[Get('/api/me/sign')]
    public function show(): array
    {
        return ['image' => SignImage::dataUrl(Auth::userId())];
    }

    /** Body {image: PNG data URL}, already trimmed and scaled by js/sign-pad.js. */
    #[Put('/api/me/sign')]
    public function update(Request $request): array
    {
        $me = (string) Auth::userId();
        $name = SignImage::store($me, (string) ($request->body['image'] ?? ''));
        User::update($me, ['us_sign' => $name]);
        return ['image' => SignImage::dataUrl($me)];
    }

    /** Documents already signed keep their copy of the signature (sig_det.sig_image). */
    #[Delete('/api/me/sign')]
    public function remove(): array
    {
        $me = (string) Auth::userId();
        SignImage::remove($me);
        User::update($me, ['us_sign' => null]);
        return ['image' => null];
    }
}
