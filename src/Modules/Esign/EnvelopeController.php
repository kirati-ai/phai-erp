<?php

declare(strict_types=1);

namespace Phai\Modules\Esign;

use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Http\Response;
use Phai\Core\Lang;
use Phai\Core\Routing\Delete;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\View\View;

/**
 * e-Document Sign (Envelope): the envelope list, the editor of a draft, the signing page, an envelope's page with its
 * progress and evidence, and the check page. No #[Program] on the class: anyone signed in may be asked to sign;
 * Envelope checks who sees what, and making an envelope needs the add right on "envelopes".
 */
final class EnvelopeController
{
    #[Get('/envelopes')]
    public function page(): string
    {
        return $this->view('esign/list', t('esign.page'), 'js/pages/envelopes.js');
    }

    /** ตรวจเอกสาร: ?n= opens with that envelope (the QR code in every page footer). */
    #[Get('/envelopes/verify')]
    public function verifyPage(Request $request): string
    {
        $nbr = (string) ($request->query['n'] ?? '');
        $summary = null;
        if ($nbr !== '') {
            $summary = $this->summary($nbr);
        }
        return $this->view('esign/verify', t('esign.verify'), 'js/pages/envelope-verify.js', ['nbr' => $nbr, 'summary' => $summary]);
    }

    #[Get('/envelopes/{nbr}')]
    public function show(string $nbr): Response|string
    {
        $data = Envelope::load($nbr);
        if ($data['can']['edit']) {
            return Response::redirect(url("/envelopes/$nbr/edit"));
        }
        return $this->view('esign/view', $data['env']['env_title'], 'js/pages/envelope-view.js', ['payload' => $this->out($data, true)], true);
    }

    #[Get('/envelopes/{nbr}/edit')]
    public function editor(string $nbr): string
    {
        $data = Envelope::load($nbr);
        if (!$data['can']['edit']) {
            throw new HttpException(409, t('esign.not_draft'));
        }
        return $this->view('esign/edit', $data['env']['env_title'], 'js/pages/envelope-edit.js', ['payload' => $this->out($data, false)], true);
    }

    #[Get('/envelopes/{nbr}/sign')]
    public function signPage(string $nbr): string
    {
        Envelope::open($nbr);
        $data = Envelope::load($nbr);
        return $this->view('esign/sign', $data['env']['env_title'], 'js/pages/envelope-sign.js', ['payload' => $this->out($data, false)], true);
    }

    /** A clean PDF of the envelope, for the pages that draw it (pdf.js). */
    #[Get('/envelopes/{nbr}/files/{id}')]
    public function file(string $nbr, string $id): Response
    {
        Envelope::load($nbr);
        $f = EnvelopeFile::find($nbr, (int) $id);
        return $this->pdf(EnvelopeFile::bytes($f), preg_replace('/\.[^.]+$/', '', (string) $f['envf_name']) ?: $nbr);
    }

    /** The signed document; ?download=1 saves it instead of showing it. */
    #[Get('/envelopes/{nbr}/final')]
    public function final(string $nbr, Request $request): Response
    {
        $data = Envelope::load($nbr);
        if (!$data['can']['final']) {
            throw HttpException::notFound();
        }
        return $this->pdf(EnvelopeFile::read((string) $data['env']['env_final_file']), $nbr, !empty($request->query['download']));
    }

    // ------------------------------------------------------------------ API

    #[Get('/api/envelopes')]
    public function lists(): array
    {
        return Envelope::lists();
    }

    #[Get('/api/envelopes/people')]
    public function people(): array
    {
        return Envelope::people();
    }

    /** Body {env_title}: a new draft; returns its number. */
    #[Post('/api/envelopes')]
    public function create(Request $request): array
    {
        return ['nbr' => Envelope::create((string) ($request->body['env_title'] ?? ''))];
    }

    /** Body {data: data URL, nbr?}: is this file one that was signed here, and if not, which pages differ? */
    #[Post('/api/envelopes/verify')]
    public function verify(Request $request): array
    {
        $data = (string) ($request->body['data'] ?? '');
        if (!preg_match('#^data:[^;,]*;base64,(.+)$#s', $data, $m) || ($bytes = base64_decode($m[1], true)) === false || $bytes === '') {
            throw HttpException::invalid(['file' => t('esign.file_bad')]);
        }
        $r = EnvelopePdf::verify($bytes, trim((string) ($request->body['nbr'] ?? '')));
        $r['summary'] = $r['nbr'] ? $this->summary($r['nbr']) : null;
        return $r;
    }

    #[Get('/api/envelopes/{nbr}')]
    public function get(string $nbr): array
    {
        return $this->out(Envelope::load($nbr), true);
    }

    #[Put('/api/envelopes/{nbr}')]
    public function save(string $nbr, Request $request): array
    {
        return $this->out(Envelope::save($nbr, $request->body), false);
    }

    #[Delete('/api/envelopes/{nbr}')]
    public function delete(string $nbr): array
    {
        Envelope::deleteDraft($nbr);
        return [];
    }

    /** Body {kind: M|A, name, data: data URL}. */
    #[Post('/api/envelopes/{nbr}/files')]
    public function upload(string $nbr, Request $request): array
    {
        $b = $request->body;
        return $this->out(Envelope::addFile($nbr, (string) ($b['kind'] ?? 'M'), (string) ($b['name'] ?? ''), (string) ($b['data'] ?? '')), false);
    }

    /** Body {remove: true} | {kind: M|A} | {dir: -1|1}. */
    #[Put('/api/envelopes/{nbr}/files/{id}')]
    public function changeFile(string $nbr, string $id, Request $request): array
    {
        return $this->out(Envelope::changeFile($nbr, (int) $id, $request->body), false);
    }

    #[Post('/api/envelopes/{nbr}/send')]
    public function send(string $nbr): array
    {
        return $this->out(Envelope::send($nbr), false);
    }

    /** Body {values: {field id: value}, image: PNG data URL or ''}. */
    #[Post('/api/envelopes/{nbr}/sign')]
    public function sign(string $nbr, Request $request): array
    {
        $b = $request->body;
        return $this->out(Envelope::sign($nbr, (array) ($b['values'] ?? []), (string) ($b['image'] ?? '')), false);
    }

    #[Post('/api/envelopes/{nbr}/decline')]
    public function decline(string $nbr, Request $request): array
    {
        return $this->out(Envelope::decline($nbr, (string) ($request->body['note'] ?? '')), false);
    }

    #[Post('/api/envelopes/{nbr}/recall')]
    public function recall(string $nbr): array
    {
        return $this->out(Envelope::recall($nbr), true);
    }

    #[Post('/api/envelopes/{nbr}/remind')]
    public function remind(string $nbr): array
    {
        $r = Envelope::remind($nbr);
        return $this->out($r, true) + ['mailed' => $r['mailed'] ?? 0];
    }

    /** Body {signer: envs_id, userid}. */
    #[Post('/api/envelopes/{nbr}/replace')]
    public function replace(string $nbr, Request $request): array
    {
        $b = $request->body;
        return $this->out(Envelope::replace($nbr, (int) ($b['signer'] ?? 0), (string) ($b['userid'] ?? '')), true);
    }

    // ------------------------------------------------------------------ helpers

    /** What a page gets: the envelope, with events and their chain check on its own page, and problems on a draft. */
    private function out(array $data, bool $events): array
    {
        $nbr = $data['env']['env_nbr'];
        unset($data['env']['env_page_hash']);
        if ($events) {
            $data['events'] = array_map(fn ($h) => [
                'at' => Envelope::when($h['envh_at']), 'event' => $h['envh_event'], 'who' => $h['envs_name'] ?? $h['us_name'] ?? $h['envh_userid'],
                'ip' => $h['envh_ip'], 'detail' => json_decode((string) $h['envh_detail'], true),
            ], EnvelopeLog::all($nbr));
            $data['chainBroken'] = EnvelopeLog::check($nbr);
        }
        foreach ($data['signers'] as &$s) {
            $s['times'] = EnvelopePdf::times($s);
            $s['recv'] = Envelope::when($s['envs_recv_at']);
            $s['opened'] = Envelope::when($s['envs_open_at']);
            $s['signed'] = Envelope::when($s['envs_sign_at']);
        }
        unset($s);
        if ($data['can']['edit']) {
            $data['problems'] = Envelope::problems($nbr);
        }
        $data['pages'] = Envelope::mainPages($nbr);
        $data['done'] = Envelope::when($data['env']['env_done_at']);
        $data['sent'] = Envelope::when($data['env']['env_sent_at']);
        return $data;
    }

    /** Who may see an envelope sees its summary on the check page; anyone else only that it exists and when it was done. */
    private function summary(string $nbr): ?array
    {
        try {
            $d = Envelope::load($nbr);
        } catch (HttpException) {
            $row = \Phai\Core\App::db()->one("SELECT env_nbr, env_done_at, env_pages FROM env_mstr WHERE env_nbr = ? AND env_status = 'C'", [$nbr]);
            return $row ? ['nbr' => $row['env_nbr'], 'done' => Envelope::when($row['env_done_at']), 'pages' => (int) $row['env_pages'], 'full' => false] : null;
        }
        $e = $d['env'];
        return [
            'nbr' => $e['env_nbr'], 'title' => $e['env_title'], 'status' => $e['env_status'], 'done' => Envelope::when($e['env_done_at']),
            'pages' => (int) $e['env_pages'], 'sha256' => $e['env_final_hash'], 'full' => true,
            'signers' => array_map(fn ($s) => ['name' => $s['envs_name'], 'position' => $s['envs_position'], 'status' => $s['envs_status'], 'signed' => Envelope::when($s['envs_sign_at'])],
                array_values(array_filter($d['signers'], fn ($s) => $s['envs_role'] !== 'C' && $s['envs_status'] !== 'C'))),
        ];
    }

    private function view(string $template, string $title, string $script, array $data = [], bool $pdf = false): string
    {
        return View::render($template, $data + [
            'title' => $title, 'help' => 'envelopes', 'scripts' => $pdf ? ['js/esign-pdf.js', $script] : [$script],
            'esignText' => Lang::group(Lang::locale(), 'esign') ?: Lang::group('th', 'esign'),
        ]);
    }

    private function pdf(string $bytes, string $name, bool $download = false): Response
    {
        $file = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) . '.pdf';
        return new Response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline') . '; filename="' . $file . '"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
