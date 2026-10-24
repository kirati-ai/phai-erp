<?php

declare(strict_types=1);

namespace Phai\Install;

use Phai\Core\Auth\Guest;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\View\View;
use Phai\Modules\System\Logo;

/** Loaded only while config.php does not exist (see App::run). */
#[Guest]
final class InstallController
{
    #[Get('/install')]
    public function page(): string
    {
        return View::render('install/index', [
            'title'        => t('install.title'),
            'requirements' => Installer::requirements(),
            'ready'        => Installer::requirementsMet(),
            'defaults'     => Installer::defaults(),
            'scripts'      => ['js/pages/install.js'],
        ], 'blank');
    }

    /** Step 1: can we reach the server, and is the database empty? */
    #[Post('/api/install/db')]
    public function checkDb(InstallInput $input): array
    {
        $pdo = Installer::connect($input);
        $tables = Installer::tableCount($pdo, $input->db_name);
        if ($tables > 0) {
            throw HttpException::invalid(['db_name' => t('install.db_not_empty')]);
        }
        return [
            'version' => $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),
            'exists'  => $tables !== null,
        ];
    }

    /** Wizard "next": check one step (company, admin) before moving on. Body: all fields + step. */
    #[Post('/api/install/check')]
    public function check(InstallInput $input, Request $request): array
    {
        $step = (string) ($request->body['step'] ?? '');
        if ($missing = $input->missingForInstall($step)) {
            throw HttpException::invalid($missing);
        }
        if ($step === 'company' && $input->co_logo !== null) {
            Logo::decode($input->co_logo, 'co_logo');
        }
        return ['step' => $step];
    }

    /** Address pickers before the database exists: read from the geography seed. */
    #[Get('/api/install/geo/provinces')]
    public function provinces(): array
    {
        return GeoSeed::provinces();
    }

    #[Get('/api/install/geo/districts/{id}')]
    public function districts(int $id): array
    {
        return GeoSeed::districts($id);
    }

    #[Get('/api/install/geo/subdistricts/{id}')]
    public function subdistricts(int $id): array
    {
        return GeoSeed::subdistricts($id);
    }

    /** Last step: install everything. */
    #[Post('/api/install/run')]
    public function run(InstallInput $input, Request $request): array
    {
        if ($missing = $input->missingForInstall()) {
            throw HttpException::invalid($missing);
        }
        if ($input->co_logo !== null) {
            Logo::decode($input->co_logo, 'co_logo'); // refuse before anything is created
        }
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host = preg_replace('/[^A-Za-z0-9.:\[\]-]/', '', $request->header('host') ?? 'localhost');
        $appUrl = ($https ? 'https' : 'http') . '://' . $host . $request->basePath;

        return Installer::install($input, $appUrl) + ['login' => url('/login')];
    }
}
