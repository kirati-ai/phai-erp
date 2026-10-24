<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\Ai\AiError;
use Phai\Core\Ai\Chat;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\View\View;

/** Ask the built-in AI about the company's data. It reads only what the user may see. */
#[Program('ai-chat')]
final class AiController
{
    #[Get('/ai')]
    public function page(): string
    {
        return View::render('system/ai', [
            'title'   => t('ai.title'),
            'config'  => ['enabled' => Chat::enabled(), 'canSetup' => Auth::can('settings-ai', 'view')],
            'scripts' => ['js/pages/ai.js'],
        ]);
    }

    /** Body: {question, history: [{role, content}]} — asking is part of viewing. */
    #[Post('/api/ai/chat'), Action('view')]
    public function ask(Request $request): array
    {
        if (!Chat::enabled()) {
            throw new HttpException(409, t('ai.disabled'));
        }
        $question = trim((string) ($request->body['question'] ?? ''));
        if ($question === '') {
            throw HttpException::invalid(['question' => t('validation.required')]);
        }
        if (mb_strlen($question) > 2000) {
            throw HttpException::invalid(['question' => t('validation.max', ['max' => 2000])]);
        }
        $history = is_array($request->body['history'] ?? null) ? $request->body['history'] : [];
        set_time_limit(300);
        try {
            return Chat::ask($question, $history);
        } catch (AiError $e) {
            throw new HttpException(502, $e->getMessage());
        }
    }
}
