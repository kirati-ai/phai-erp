<?php
/**
 * Main layout.
 * @var string $content  page HTML
 * @var string $title    page title
 * @var array  $scripts  extra JS files under public/, e.g. ['js/pages/uoms.js']
 */

use Phai\Core\Auth\Auth;
use Phai\Core\Http\Request;
use Phai\Core\Lang;
use Phai\Modules\System\Menu;

$current = Request::current()?->path ?? '/';
$query = Request::current()?->query ?? [];
// The current menu item: the longest link that is this page or a page below it
// (/stock-counts/PI000001 -> /stock-counts, while /stock/moves does not light up /stock).
// A link with a filter (/stock?type=RM) needs the same filter in the address; the first such item wins.
$activeItem = null;
$activeLen = 0;
$user = Auth::user();
$menu = Menu::tree();
foreach ($menu as $group) {
    foreach ($group['items'] as $item) {
        [$path, $qs] = explode('?', $item['mnu_link'], 2) + [1 => ''];
        parse_str($qs, $want);
        if ($path !== '#' && ($path === $current || str_starts_with($current, $path . '/'))
            && array_intersect_assoc($want, $query) === $want && strlen($item['mnu_link']) > $activeLen) {
            $activeItem = $item['mnu_menu'] . '|' . $item['mnu_code'];
            $activeLen = strlen($item['mnu_link']);
        }
    }
}
$isCurrent = fn (array $item) => $item['mnu_menu'] . '|' . $item['mnu_code'] === $activeItem;
// menus this user pinned (us_pins) sit under เรียนรู้ ERP, in pinned order; only ones still in the menu they may see
$pins = array_filter(explode(',', (string) ($user['us_pins'] ?? '')));
$pinned = [];
foreach ($menu as $group) {
    foreach ($group['items'] as $item) {
        if (in_array($item['mnu_code'], $pins, true) && !Menu::isLabel($item)) {
            $pinned[$item['mnu_code']] = $item;
        }
    }
}
$pinned = array_replace(array_intersect_key(array_flip($pins), $pinned), $pinned);
$pinLabel = ['on' => t('common.pin'), 'off' => t('common.unpin')];
$company = \Phai\Core\App::db()->value('SELECT co_name FROM co_mstr WHERE co_id = 1') ?? 'PhaiERP';
?>
<!doctype html>
<?php $theme = in_array($user['us_theme'] ?? '', \Phai\Modules\System\AuthController::THEMES, true) ? $user['us_theme'] : 'light'; // us_theme ?>
<html lang="<?= e(Lang::locale()) ?>" data-bs-theme="<?= $theme === 'dark' ? 'dark' : 'light' ?>"<?= in_array($theme, ['light', 'dark'], true) ? '' : ' data-phai-theme="' . e($theme) . '"' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="phai-base" content="<?= e(url('')) ?>">
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' : '') ?>PhaiERP</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/phaierp-logo.svg')) ?>">
    <link href="<?= e(asset('fonts/ibm-plex-sans-thai/ibm-plex-sans-thai.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/tabler/tabler.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/tabler-icons/tabler-icons.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('css/phai.css')) ?>" rel="stylesheet">
    <script>try { if (localStorage.getItem('phai-side-mini') === '1') document.documentElement.classList.add('side-mini'); } catch (e) {}</script>
</head>
<?php $embed = ($_GET['embed'] ?? '') === '1'; // shown inside a window on another page (a queue's document): no menu, no top bar ?>
<body<?= $embed ? ' class="phai-embed"' : '' ?>>
<aside class="phai-side" id="phai-side">
    <div class="phai-side-head">
        <a href="<?= e(url('/')) ?>" class="phai-side-brand"><img src="<?= e(asset('img/phaierp-logo-light.svg')) ?>" alt="" class="phai-mark"><span class="phai-word">Phai<b>ERP</b></span><span class="phai-ver">v<?= e(\Phai\Core\App::VERSION) ?></span></a>
    </div>
    <nav class="phai-side-nav">
        <a class="phai-nav-link<?= $current === '/' ? ' active' : '' ?>" href="<?= e(url('/')) ?>">
            <i class="ti ti-home"></i><span><?= e(t('common.home')) ?></span>
        </a>
        <a class="phai-nav-link<?= $current === '/learn' ? ' active' : '' ?>" href="<?= e(url('/learn')) ?>">
            <i class="ti ti-school"></i><span><?= e(t('common.learn')) ?></span>
        </a>
        <div class="phai-nav-pins" id="phai-nav-pins" data-pin="<?= e($pinLabel['on']) ?>" data-unpin="<?= e($pinLabel['off']) ?>"<?= $pinned ? '' : ' hidden' ?>><?php // ปักหมุด: shell.js moves links in and out ?>
            <?php foreach ($pinned as $item): ?>
                <a class="phai-nav-sublink<?= $isCurrent($item) ? ' active' : '' ?>" href="<?= e(url($item['mnu_link'])) ?>" data-code="<?= e($item['mnu_code']) ?>"
                   <?= $item['mnu_target'] === '_blank' ? 'target="_blank" rel="noopener"' : '' ?>>
                    <i class="<?= e($item['mnu_icon'] ?: 'ti ti-point') ?>"></i><span><?= e($item['mnu_name']) ?></span><i class="ti ti-pinned phai-pin on" role="button" aria-label="<?= e($pinLabel['off']) ?>"></i>
                </a>
            <?php endforeach ?>
        </div>
        <?php if (Auth::can('ai-chat', 'view') && \Phai\Core\Ai\Chat::enabled()): ?>
            <a class="phai-nav-link<?= $current === '/ai' ? ' active' : '' ?>" href="<?= e(url('/ai')) ?>">
                <i class="ti ti-sparkles"></i><span><?= e(t('ai.title')) ?></span>
            </a>
        <?php endif ?>
        <?php foreach ($menu as $i => $group):
            $open = false;
            foreach ($group['items'] as $item) {
                $open = $open || $isCurrent($item);
            }
            ?>
            <div class="phai-nav-group<?= $open ? ' open has-active' : '' ?>" data-group="<?= $i ?>">
                <button type="button" class="phai-nav-link phai-nav-toggle" aria-expanded="<?= $open ? 'true' : 'false' ?>">
                    <i class="<?= e($group['icon']) ?>"></i><span><?= e($group['name']) ?></span><i class="ti ti-chevron-right phai-nav-chevron"></i>
                </button>
                <div class="phai-nav-sub">
                    <div>
                        <?php $fold = false;
                        foreach ($group['items'] as $j => $item): ?>
                            <?php if (Menu::isLabel($item)): ?>
                                <?php if ($fold): $fold = false ?></div></div><?php endif ?>
                                <?php if (Menu::isFold($item)):
                                    // ตั้งค่า folds away: open when the current page is in it (shell.js remembers the rest)
                                    $fold = true;
                                    $here = false;
                                    for ($k = $j + 1; isset($group['items'][$k]) && !Menu::isLabel($group['items'][$k]); $k++) {
                                        $here = $here || $isCurrent($group['items'][$k]);
                                    }
                                    ?>
                                    <button type="button" class="phai-nav-label phai-nav-fold-toggle" data-fold="<?= e($item['mnu_code']) ?>" aria-expanded="<?= $here ? 'true' : 'false' ?>">
                                        <span><?= e($item['mnu_name']) ?></span><i class="ti ti-chevron-right"></i>
                                    </button>
                                    <div class="phai-nav-fold<?= $here ? ' open has-active' : '' ?>"><div>
                                <?php else: ?>
                                    <div class="phai-nav-label"><?= e($item['mnu_name']) ?></div>
                                <?php endif ?>
                                <?php continue ?>
                            <?php endif ?>
                            <a class="phai-nav-sublink<?= $isCurrent($item) ? ' active' : '' ?>"
                               href="<?= e(url($item['mnu_link'])) ?>" data-code="<?= e($item['mnu_code']) ?>"
                               data-search="<?= e(mb_strtolower($item['mnu_name'] . ' ' . $group['name'])) ?>"
                               <?= $item['mnu_target'] === '_blank' ? 'target="_blank" rel="noopener"' : '' ?>>
                                <i class="<?= e($item['mnu_icon'] ?: 'ti ti-point') ?>"></i><span><?= e($item['mnu_name']) ?></span><?php $on = isset($pinned[$item['mnu_code']]) ?><i class="ti <?= $on ? 'ti-pinned on' : 'ti-pin' ?> phai-pin" role="button" aria-label="<?= e($pinLabel[$on ? 'off' : 'on']) ?>"></i>
                            </a>
                        <?php endforeach ?>
                        <?php if ($fold): ?></div></div><?php endif ?>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </nav>
    <div class="phai-side-foot">
        <time id="phai-side-clock" class="phai-side-clock" aria-label="<?= e(t('common.clock')) ?>" data-idle="<?= \Phai\Core\Auth\Auth::idleMinutes() ?>"></time><?php // minutes to sign-out when idle (shell.js) ?>
    </div>
    <div id="phai-side-resize" class="phai-side-resize" role="separator" aria-orientation="vertical" aria-label="<?= e(t('common.menu_width')) ?>" tabindex="0"></div>
</aside>
<div class="phai-side-backdrop" id="phai-side-backdrop"></div>

<div class="phai-main">
    <header class="phai-topbar">
        <div class="d-flex align-items-center gap-2 min-w-0">
            <button type="button" class="phai-burger" id="phai-side-toggle" aria-label="Menu"><span></span><span></span><span></span></button>
            <span class="phai-company text-secondary small d-none d-md-inline-flex align-items-center text-nowrap">
                <?php if ($logo = \Phai\Modules\System\Logo::url()): ?><img src="<?= e($logo) ?>" alt="" class="phai-logo-top me-1"><?php endif ?><?= e($company) ?>
            </span>
            <h1 class="phai-title text-truncate"><?= e($title ?? '') ?></h1>
            <?php
            // how-to for the screen: views/help/<lang>/<help>.php, opened from a small ? beside the title (no text on the screen itself)
            $helpFile = null;
            if (!empty($help) && preg_match('/^[a-z0-9-]+$/', $help)) {
                foreach ([Lang::locale(), 'th'] as $l) {
                    if (is_file($f = PHAI_ROOT . "/views/help/$l/$help.php")) {
                        $helpFile = $f;
                        break;
                    }
                }
            }
            ?>
            <?php if (!empty($docNbr)): /* the number a new document gets when saved (Numbering::peek) */ ?>
                <span class="badge bg-secondary-lt text-secondary fw-normal phai-doc-nbr" title="<?= e(t('common.next_nbr')) ?>"><?= e($docNbr) ?></span>
            <?php endif ?>
        </div>
        <div class="d-flex align-items-center gap-1">
            <?php if ($helpFile): ?>
                <button type="button" class="phai-help-btn" data-bs-toggle="offcanvas" data-bs-target="#phai-help" title="<?= e(t('common.help')) ?>" aria-label="<?= e(t('common.help')) ?>"><i class="ti ti-help-circle"></i></button>
            <?php endif ?>
            <?php $signWaiting = \Phai\Modules\System\Signature::waitingCount(); // documents asking for my signature ?>
            <?php if ($signWaiting): ?>
                <a href="<?= e(url('/signatures')) ?>" class="phai-help-btn" title="<?= e(t('sign.waiting')) ?>" aria-label="<?= e(t('sign.waiting')) ?>"><i class="ti ti-writing-sign"></i><span class="badge bg-red text-white ms-1"><?= $signWaiting ?></span></a>
            <?php endif ?>
            <?php $envWaiting = \Phai\Modules\Esign\Envelope::waitingCount(); // envelopes waiting for my signature ?>
            <?php if ($envWaiting): ?>
                <a href="<?= e(url('/envelopes')) ?>" class="phai-help-btn" title="<?= e(t('esign.tab_waiting')) ?>" aria-label="<?= e(t('esign.tab_waiting')) ?>"><i class="ti ti-mail-opened"></i><span class="badge bg-red text-white ms-1"><?= $envWaiting ?></span></a>
            <?php endif ?>
            <div class="dropdown"><?php // ธีม on the bar itself: a dot per theme, a click switches at once and keeps it with the user ?>
                <button type="button" class="phai-help-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside"  title="<?= e(t('auth.theme')) ?>" aria-label="<?= e(t('auth.theme')) ?>"><i class="ti ti-palette"></i></button>
                <div class="dropdown-menu dropdown-menu-end p-0">
                    <div class="phai-theme-pick phai-theme-pick-bar" id="phai-theme">
                        <div class="phai-theme-pick-head"><i class="ti ti-palette me-2"></i><?= e(t('auth.theme')) ?><span class="ms-auto ps-3" data-theme-name><?= e(t('auth.theme_' . $theme)) ?></span></div>
                        <div class="phai-theme-dots">
                            <?php foreach (\Phai\Modules\System\AuthController::THEMES as $key): ?>
                                <button type="button" class="phai-theme-dot<?= $key === $theme ? ' active' : '' ?>" data-theme="<?= e($key) ?>" title="<?= e(t('auth.theme_' . $key)) ?>" aria-label="<?= e(t('auth.theme_' . $key)) ?>" aria-pressed="<?= $key === $theme ? 'true' : 'false' ?>"><i class="ti ti-check"></i></button>
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php $other = Lang::locale() === 'th' ? 'en' : 'th'; // the flag of the language a click switches to ?>
            <button type="button" class="phai-lang-btn" id="phai-lang" data-lang="<?= $other ?>" title="<?= e(t('common.lang_' . $other)) ?>" aria-label="<?= e(t('common.lang_' . $other)) ?>"><?= \Phai\Core\View\View::partial('partials/flag', ['lang' => $other]) ?></button>
            <?php
            // the user: picture (or initials) and name; the menu opens on a card with the same picture
            $avatarUrl = \Phai\Modules\System\Avatar::url($user ?? []);
            $initials = \Phai\Modules\System\Avatar::initials((string) ($user['us_name'] ?? ''));
            $avatar = fn (string $cls) => $avatarUrl
                ? '<img src="' . e($avatarUrl) . '" alt="" class="phai-avatar ' . $cls . '" data-avatar>'
                : '<span class="phai-avatar ' . $cls . '" data-avatar data-initials>' . e($initials) . '</span>';
            ?>
            <div class="dropdown ms-2">
                <a href="#" class="btn btn-sm btn-ghost-secondary phai-user-btn" data-bs-toggle="dropdown">
                    <?= $avatar('phai-avatar-sm') ?><span data-user-name><?= e($user['us_name'] ?? '') ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end phai-user-menu">
                    <div class="phai-user-card">
                        <?= $avatar('phai-avatar-lg') ?>
                        <div class="min-w-0">
                            <div class="phai-user-card-name" data-user-name><?= e($user['us_name'] ?? '') ?></div>
                            <div class="phai-user-card-sub"><?= e($user['role_desc'] ?? $user['us_role'] ?? '') ?></div>
                            <div class="phai-user-card-sub text-truncate" data-user-email><?= e($user['us_email'] ?? '') ?></div>
                        </div>
                    </div>
                    <a href="#" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#phai-profile">
                        <i class="ti ti-user-edit me-2"></i><?= e(t('auth.profile')) ?>
                    </a>
                    <a href="#" class="dropdown-item" id="phai-my-sign">
                        <i class="ti ti-signature me-2"></i><?= e(t('sign.my_sign')) ?>
                    </a>
                    <a href="<?= e(url('/signatures')) ?>" class="dropdown-item">
                        <i class="ti ti-writing-sign me-2"></i><?= e(t('sign.page')) ?>
                        <?php if ($signWaiting): ?><span class="badge bg-red text-white ms-auto"><?= $signWaiting ?></span><?php endif ?>
                    </a>
                    <a href="#" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#phai-password">
                        <i class="ti ti-key me-2"></i><?= e(t('auth.change_password')) ?>
                    </a>
                    <a href="#" class="dropdown-item" id="phai-logout">
                        <i class="ti ti-logout me-2"></i><?= e(t('auth.logout')) ?>
                    </a>
                    <div class="phai-user-menu-foot">PhaiERP <?= e(\Phai\Core\App::VERSION) ?></div>
                </div>
            </div>
        </div>
    </header>
    <main class="phai-body">
        <div class="container-fluid">
            <?php
            $printParts = explode('/', trim($current, '/'));
            if (empty($ownToolbar) && count($printParts) === 2 && $printParts[1] !== 'new'
                && in_array($printParts[0], \Phai\Modules\System\DocumentPrintController::DOCUMENTS, true)
                && Auth::can($printParts[0], 'print')): ?>
                <div class="phai-toolbar justify-content-end">
                    <a class="btn btn-sm" href="<?= e(url('/' . $printParts[0])) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
                    <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url('/documents/' . $printParts[0] . '/' . rawurlencode($printParts[1]) . '/print')) ?>"><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
                    <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url('/documents/' . $printParts[0] . '/' . rawurlencode($printParts[1]) . '/print')) ?>?html=1"><i class="ti ti-writing-sign me-1"></i><?= e(t('js.sign_doc')) ?></a>
                </div>
            <?php endif ?>
            <?php if (!$embed) foreach ($queues ?? [] as $queueCfg) echo \Phai\Core\View\View::partial('partials/queue', ['q' => $queueCfg]); // work waiting from the step before ?>
            <?= $content ?>
        </div>
    </main>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="phai-toasts"></div>

<div class="modal" id="phai-confirm" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content phai-confirm">
            <div class="modal-body">
                <div class="phai-confirm-icon"><i class="ti ti-alert-triangle"></i></div>
                <div class="phai-confirm-title"><?= e(t('common.confirm_title')) ?></div>
                <div class="phai-confirm-text" data-message></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
                <button type="button" class="btn btn-sm btn-danger" data-ok><i class="ti ti-check me-1"></i><?= e(t('common.confirm')) ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="phai-profile" tabindex="-1" data-saved="<?= e(t('auth.profile_saved')) ?>" data-bad-image="<?= e(t('system.logo_type')) ?>">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" novalidate autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title"><?= e(t('auth.profile')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="phai-avatar-edit"><?= $avatar('phai-avatar-xl') ?></div>
                    <div class="d-flex flex-column gap-1 align-items-start">
                        <input type="file" id="phai-avatar-file" accept="image/png,image/jpeg,image/webp" hidden>
                        <button type="button" class="btn btn-sm" id="phai-avatar-change"><i class="ti ti-camera me-1"></i><?= e(t('auth.avatar_change')) ?></button>
                        <button type="button" class="btn btn-sm btn-ghost-danger" id="phai-avatar-remove"<?= $avatarUrl ? '' : ' hidden' ?>><i class="ti ti-trash me-1"></i><?= e(t('auth.avatar_remove')) ?></button>
                        <span class="small text-secondary"><?= e(t('auth.avatar_hint')) ?></span>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6"><label class="form-label"><?= e(t('auth.userid')) ?></label>
                        <input type="text" class="form-control form-control-sm" value="<?= e($user['us_userid'] ?? '') ?>" disabled></div>
                    <div class="col-sm-6"><label class="form-label"><?= e(t('auth.role')) ?></label>
                        <input type="text" class="form-control form-control-sm" value="<?= e($user['role_desc'] ?? $user['us_role'] ?? '') ?>" disabled></div>
                    <div class="col-12"><label class="form-label"><?= e(t('auth.full_name')) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="us_name" maxlength="255" value="<?= e($user['us_name'] ?? '') ?>"></div>
                    <div class="col-12"><label class="form-label"><?= e(t('auth.email')) ?> <span class="text-danger">*</span></label>
                        <input type="email" class="form-control form-control-sm" name="us_email" maxlength="255" value="<?= e($user['us_email'] ?? '') ?>"></div>
                    <div class="col-sm-6"><label class="form-label"><?= e(t('auth.phone')) ?></label>
                        <input type="text" class="form-control form-control-sm" name="us_phone" maxlength="50" value="<?= e($user['us_phone'] ?? '') ?>"></div>
                    <div class="col-sm-6"><label class="form-label"><?= e(t('auth.mobile')) ?></label>
                        <input type="text" class="form-control form-control-sm" name="us_mobile" maxlength="50" value="<?= e($user['us_mobile'] ?? '') ?>"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
                <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="phai-password" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form class="modal-content" novalidate>
            <div class="modal-header">
                <h5 class="modal-title"><?= e(t('auth.change_password')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label"><?= e(t('auth.current_password')) ?></label>
                    <input type="password" class="form-control form-control-sm" name="current_password" autocomplete="current-password">
                </div>
                <div>
                    <label class="form-label"><?= e(t('auth.new_password')) ?></label>
                    <input type="password" class="form-control form-control-sm" name="new_password" autocomplete="new-password" minlength="8">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
                <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php if ($helpFile): ?>
<div class="offcanvas offcanvas-end phai-help" tabindex="-1" id="phai-help" aria-labelledby="phai-help-title">
    <div class="offcanvas-header py-2">
        <h2 class="offcanvas-title h4" id="phai-help-title"><i class="ti ti-help-circle me-1"></i><?= e(t('common.help')) ?> · <?= e($title ?? '') ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body pt-0"><?php require $helpFile ?></div>
</div>
<?php endif ?>

<script>window.PHAI_LANG = <?= json_encode(Lang::group(Lang::locale(), 'js') ?: Lang::group('th', 'js'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset('vendor/tabler/tabler.min.js')) ?>"></script>
<script src="<?= e(asset('js/phai.js')) ?>"></script>
<script src="<?= e(asset('js/xlsx.js')) ?>"></script>
<script src="<?= e(asset('js/datepicker.js')) ?>"></script>
<script id="phai-sign-text" type="application/json"><?= json_encode(Lang::group(Lang::locale(), 'sign') ?: Lang::group('th', 'sign'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('js/sign-pad.js')) ?>"></script>
<script src="<?= e(asset('js/account.js')) ?>"></script>
<script src="<?= e(asset('js/shell.js')) ?>"></script>
<?php if (!empty($queues)): ?><script src="<?= e(asset('js/queue.js')) ?>"></script><?php endif ?>
<?php foreach ($scripts ?? [] as $script): ?>
<script src="<?= e(asset($script)) ?>"></script>
<?php endforeach ?>
</body>
</html>
