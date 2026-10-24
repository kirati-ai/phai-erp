<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Put;
use Phai\Core\View\View;

/**
 * Prefix, number format and next running number of every document type — all on /settings/numbering, and each module's
 * own documents at the foot of its settings page (MODULES), which may change them with the edit right on that page.
 */
#[Program('settings-numbering', 'settings-purchase', 'settings-inventory', 'settings-production', 'settings-sales', 'settings-gl')]
final class NumberingController
{
    /** Settings page (program) => the documents numbered on it. */
    public const MODULES = [
        'settings-purchase'   => ['PR', 'PO', 'RC', 'RT'],
        'settings-inventory'  => ['IC', 'TO', 'PI'],
        'settings-production' => ['WO', 'MI', 'QC'],
        'settings-sales'      => ['QT', 'SO', 'SH', 'SR', 'INV', 'CN', 'DN', 'BL'],
        'settings-gl'         => ['CR', 'DP', 'AP', 'VO', 'WHT', 'PV', 'JE', 'PC'],
    ];

    /** May the user see / change ('view' / 'edit') the numbering of $doc: on the numbering page, or on its module's settings. */
    public static function can(string $doc, string $action = 'view'): bool
    {
        if (Auth::can('settings-numbering', $action)) {
            return true;
        }
        foreach (self::MODULES as $prog => $docs) {
            if (in_array($doc, $docs, true)) {
                return Auth::can($prog, $action);
            }
        }
        return false;
    }

    /** The numbering table of one settings page (views/system/numbering-table.php), for $prog's documents. */
    public static function table(string $prog, string $width = '50rem'): string
    {
        $docs = self::MODULES[$prog] ?? [];
        return View::partial('system/numbering-table', [
            'docs'    => array_values(array_filter(Numbering::all(), fn ($d) => in_array($d['doc'], $docs, true))),
            'canEdit' => Auth::can($prog, 'edit') || Auth::can('settings-numbering', 'edit'),
            'title'   => t('numbering.title'),
            'width'   => $width,
        ]);
    }

    #[Get('/settings/numbering')]
    public function page(): string
    {
        return View::render('system/numbering', [
            'title'   => t('numbering.title'),
            'help'    => 'settings-numbering',
            'docs'    => array_values(array_filter(Numbering::all(), fn ($d) => self::can($d['doc']))),
            'canEdit' => Auth::can('settings-numbering', 'edit'),
        ]);
    }

    /** Body: prefix_PO, format_PO, next_PO, prefix_SO, ... (the form fields as they are; a settings page sends only its own). */
    #[Put('/api/settings/numbering')]
    public function save(Request $request): null
    {
        $errors = [];
        $set = [];
        foreach (Numbering::all() as $cur) {
            $doc = $cur['doc'];
            if (!isset($request->body["prefix_$doc"]) && !isset($request->body["next_$doc"]) && !isset($request->body["format_$doc"])) {
                continue; // not on the page that saved
            }
            if (!self::can($doc, 'edit')) {
                throw new HttpException(403, t('auth.forbidden'));
            }
            $prefix = trim((string) ($request->body["prefix_$doc"] ?? $cur['prefix']));
            $next = trim((string) ($request->body["next_$doc"] ?? $cur['next']));
            $format = (string) ($request->body["format_$doc"] ?? $cur['format']);
            if (!isset(Numbering::FORMATS[$format])) {
                $errors["format_$doc"] = t('validation.in');
            }

            if (!preg_match('/^[A-Za-z]{2}$/', $prefix)) { // one standard: two letters, e.g. PO, IV
                $errors["prefix_$doc"] = t('numbering.prefix_format');
            }
            if (!preg_match('/^\d{1,9}$/', $next) || (int) $next < 1) {
                $errors["next_$doc"] = t('validation.min', ['min' => 1]);
            }
            if ($prefix !== $cur['prefix'] || (int) $next !== $cur['next'] || $format !== $cur['format']) {
                $set[$doc] = [strtoupper($prefix), (int) $next, $format];
            }
        }
        if ($errors) {
            throw HttpException::invalid($errors);
        }

        App::db()->transaction(function () use ($set) {
            foreach ($set as $doc => [$prefix, $next, $format]) {
                Numbering::set($doc, $prefix, $next, $format);
            }
        });
        return null;
    }
}
