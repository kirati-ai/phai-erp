<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\View\View;

/** Users can be disabled but not deleted, so audit_log always points to a real user. */
#[Program('users')]
final class UserController
{
    #[Get('/users')]
    public function page(): string
    {
        return View::render('system/users', [
            'title'   => t('system.users'),
            'help'    => 'users',
            'roles'   => App::db()->all('SELECT role_code, role_desc FROM role_mstr WHERE role_active = 1 ORDER BY role_code'),
            'scripts' => ['js/pages/users.js'],
        ]);
    }

    #[Get('/api/users')]
    public function list(Request $request): array
    {
        return User::list($request->query);
    }

    #[Get('/api/users/{us_userid}')]
    public function show(string $us_userid): array
    {
        return User::findOrFail($us_userid);
    }

    #[Post('/api/users')]
    public function create(UserInput $input): array
    {
        if ($input->password === null) {
            throw HttpException::invalid(['password' => t('validation.required')]);
        }
        return User::create(self::row($input));
    }

    #[Put('/api/users/{us_userid}')]
    public function update(string $us_userid, UserInput $input): array
    {
        if ($us_userid === Auth::userId()) {
            $me = User::findOrFail($us_userid);
            if (!$input->us_active || $input->us_role !== $me['us_role']) {
                throw HttpException::invalid(['us_role' => t('system.self_lock')]);
            }
        }
        return User::update($us_userid, self::row($input));
    }

    private static function row(UserInput $input): array
    {
        $row = get_object_vars($input);
        unset($row['password']);
        if ($input->password !== null) {
            $row['us_password_hash'] = Auth::hash($input->password);
        }
        return $row;
    }
}
