<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Guest;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Http\Response;
use Phai\Core\Routing\Delete;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;

final class AuthController
{
    /**
     * Themes (us_mstr.us_theme), in the order the user menu shows them: ไผ่ (green, the default), หมอก (white and grey),
     * คราม (blue), อัญชัน (purple), ดินเผา (terracotta) — light, <html data-phai-theme> — and ไผ่ยามค่ำ (dark, data-bs-theme).
     */
    public const THEMES = ['light', 'mist', 'indigo', 'grape', 'clay', 'dark'];

    #[Get('/login'), Guest]
    public function page(): Response|string
    {
        if (Auth::check()) {
            return Response::redirect(url('/'));
        }
        return View::render('auth/login', ['scripts' => ['js/pages/login.js'], 'themed' => true], 'blank');
    }

    #[Post('/api/login'), Guest]
    public function login(LoginInput $input): array
    {
        Auth::attempt($input->login, $input->password);
        return ['next' => url(self::safeNext($input->next))];
    }

    #[Post('/api/logout')]
    public function logout(): array
    {
        Auth::logout();
        return ['next' => url('/login')];
    }

    /** The page saw the user busy (mouse, keys): the session counts its idle time again from now. */
    #[Get('/api/me/alive')]
    public function alive(): array
    {
        return ['idle' => Auth::idleMinutes()];
    }

    /** The user's theme (THEMES), kept with the user so every device shows it. */
    #[Put('/api/me/theme')]
    public function theme(Request $request): array
    {
        $theme = (string) ($request->body['theme'] ?? '');
        if (!in_array($theme, self::THEMES, true)) {
            throw HttpException::invalid(['theme' => t('validation.in')]);
        }
        App::db()->update('us_mstr', ['us_theme' => $theme, 'updated_by' => Auth::userId()], ['us_userid' => Auth::userId()]);
        return ['theme' => $theme];
    }

    /** The user's language (Lang::LOCALES: th, en), kept with the user like the theme; the page reloads to show it. */
    #[Put('/api/me/lang')]
    public function lang(Request $request): array
    {
        $lang = (string) ($request->body['lang'] ?? '');
        if (!in_array($lang, \Phai\Core\Lang::LOCALES, true)) {
            throw HttpException::invalid(['lang' => t('validation.in')]);
        }
        App::db()->update('us_mstr', ['us_lang' => $lang, 'updated_by' => Auth::userId()], ['us_userid' => Auth::userId()]);
        return ['lang' => $lang];
    }

    /** Menus I pinned under เรียนรู้ ERP (mnu_code in pinned order), kept with the user like the theme. */
    #[Put('/api/me/pins')]
    public function pins(Request $request): array
    {
        $codes = array_values(array_unique(array_map('strval', (array) ($request->body['pins'] ?? []))));
        $known = array_column(App::db()->all("SELECT mnu_code FROM mnu_det WHERE mnu_link <> '#'"), 'mnu_code');
        $codes = array_slice(array_values(array_intersect($codes, $known)), 0, 30);
        App::db()->update('us_mstr', ['us_pins' => $codes ? implode(',', $codes) : null, 'updated_by' => Auth::userId()], ['us_userid' => Auth::userId()]);
        return ['pins' => $codes];
    }

    /** My profile (the menu under my name): name, e-mail and phones; role and active stay with the user screen. */
    #[Put('/api/me/profile')]
    public function profile(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $row = [
            'us_name'   => $c->text('us_name', $b['us_name'] ?? '', true, 255),
            'us_email'  => $c->text('us_email', $b['us_email'] ?? '', true, 255),
            'us_phone'  => $c->text('us_phone', $b['us_phone'] ?? '', false, 50, false),
            'us_mobile' => $c->text('us_mobile', $b['us_mobile'] ?? '', false, 50, false),
        ];
        if ($row['us_email'] !== null && !filter_var($row['us_email'], FILTER_VALIDATE_EMAIL)) {
            $c->fail('us_email', t('validation.email'));
        } elseif ($row['us_email'] !== null && App::db()->value('SELECT 1 FROM us_mstr WHERE us_email = ? AND us_userid <> ?', [$row['us_email'], Auth::userId()])) {
            $c->fail('us_email', t('validation.duplicate'));
        }
        $c->done();
        User::update(Auth::userId(), $row);
        return $row;
    }

    /** My picture, body {avatar: data URL} (PNG, JPEG or WebP up to 1 MB); DELETE takes it off. */
    #[Put('/api/me/avatar')]
    public function avatar(Request $request): array
    {
        $name = Avatar::store(Auth::userId(), (string) ($request->body['avatar'] ?? ''));
        User::update(Auth::userId(), ['us_avatar' => $name]);
        return ['url' => Avatar::url(['us_userid' => Auth::userId(), 'us_avatar' => $name])];
    }

    #[Delete('/api/me/avatar')]
    public function avatarRemove(): array
    {
        Avatar::remove(Auth::userId());
        User::update(Auth::userId(), ['us_avatar' => null]);
        return ['url' => null];
    }

    /** A user's picture, for anyone signed in (it shows beside their name). */
    #[Get('/avatar/{userid}')]
    public function avatarShow(string $userid): Response
    {
        $file = Avatar::file($userid) ?? throw HttpException::notFound();
        return new Response((string) file_get_contents($file), 200, ['Content-Type' => Logo::mime($file), 'Cache-Control' => 'private, max-age=86400']);
    }

    #[Put('/api/me/password')]
    public function password(PasswordInput $input): null
    {
        $hash = App::db()->value('SELECT us_password_hash FROM us_mstr WHERE us_userid = ?', [Auth::userId()]);
        if (!password_verify($input->current_password, (string) $hash)) {
            throw HttpException::invalid(['current_password' => t('auth.wrong_password')]);
        }
        User::update(Auth::userId(), ['us_password_hash' => Auth::hash($input->new_password)]);
        return null;
    }

    /** Only paths inside this app, never another site. */
    public static function safeNext(?string $next): string
    {
        if ($next === null || !preg_match('#^/[^/\\\\]#', $next) || str_starts_with($next, '/api/')) {
            return '/';
        }
        return $next;
    }
}
