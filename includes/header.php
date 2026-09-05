<?php
/**
 * GodsForum - Public site header.
 *
 * Expects the including page to optionally define:
 *   $pageTitle        string
 *   $pageDescription  string
 *   $breadcrumbs      array<int, array{label: string, url?: string}>
 */

declare(strict_types=1);

// Defence in depth. Apache already denies this directory, but if a server is
// misconfigured these files must still refuse to run as a request target.
if (!defined('GF_ROUTER') && PHP_SAPI !== 'cli' && realpath(__FILE__) === realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    http_response_code(404);
    exit;
}


require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/themes.php';

$gfUser        = current_user();
$pageTitle     = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : SITE_NAME;
$pageDescription = isset($pageDescription) && is_string($pageDescription)
    ? $pageDescription
    : SITE_TAGLINE;
$breadcrumbs   = isset($breadcrumbs) && is_array($breadcrumbs) ? $breadcrumbs : [];
$flashMessages = flash_take();
$gfTheme       = active_theme();
$gfCurrentPath = router_path();

/** @var array<int, array{path: string, label: string, icon: string}> $gfNav */
$gfNav = [
    ['path' => '',        'label' => 'Board',   'icon' => 'dashboard'],
    ['path' => 'recent',  'label' => 'Recent',  'icon' => 'schedule'],
    ['path' => 'members', 'label' => 'Members', 'icon' => 'groups'],
    ['path' => 'search',  'label' => 'Search',  'icon' => 'search'],
    ['path' => 'rules',   'label' => 'Rules',   'icon' => 'gavel'],
];
?>
<!DOCTYPE html>
<html lang="en" class="h-full" data-theme="<?= e($gfTheme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | <?= e(SITE_NAME) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<link rel="icon" href="<?= e(url('assets/img/logo.png')) ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="h-full font-sans antialiased">

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-3">
        <a href="<?= e(url('')) ?>" class="flex items-center gap-3">
            <img src="<?= e(url('assets/img/logo.png')) ?>" alt="" class="h-11 w-11 object-cover" style="border:1px solid var(--accent)">
            <span class="leading-tight">
                <span class="block font-serif text-xl font-semibold tracking-[0.18em]">GODSFORUM</span>
                <span class="block text-[11px] uppercase tracking-[0.24em]" style="color:var(--accent)"><?= e(setting('site_tagline', SITE_TAGLINE)) ?></span>
            </span>
        </a>

        <div class="ml-auto flex items-center gap-2 text-sm">
            <?php if ($gfUser !== null): ?>
                <a href="<?= e(member_url((string) $gfUser['username'])) ?>"
                   class="header-chip flex items-center gap-2 px-3 py-1.5">
                    <img src="<?= e(avatar_url(isset($gfUser['avatar']) ? (string) $gfUser['avatar'] : null)) ?>" alt="" class="h-6 w-6 rounded-sm object-cover">
                    <span class="font-medium"><?= e((string) $gfUser['username']) ?></span>
                </a>
                <?php if (is_admin()): ?>
                    <a href="<?= e(url('admin')) ?>"
                       class="header-chip header-chip-accent hidden items-center gap-1 px-3 py-1.5 font-medium sm:flex">
                        <span class="material-icons-outlined text-[18px]" aria-hidden="true">shield</span>
                        Control room
                    </a>
                <?php endif; ?>
                <form method="post" action="<?= e(url('logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit"
                            class="header-chip flex items-center gap-1 px-3 py-1.5">
                        <span class="material-icons-outlined text-[18px]" aria-hidden="true">logout</span>
                        Sign out
                    </button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('login')) ?>"
                   class="header-chip px-3 py-1.5">Sign in</a>
                <a href="<?= e(url('register')) ?>"
                   class="header-chip header-chip-accent px-3 py-1.5 font-medium">Register</a>
            <?php endif; ?>
        </div>
    </div>

    <nav aria-label="Primary" class="site-header-sub">
        <ul class="mx-auto flex max-w-6xl flex-wrap items-center px-4 text-sm">
            <?php foreach ($gfNav as $item): ?>
                <?php $active = $gfCurrentPath === $item['path']; ?>
                <li>
                    <a href="<?= e(url($item['path'])) ?>"
                       class="nav-link flex items-center gap-1.5 border-b-2 px-3 py-2.5 uppercase tracking-[0.12em]<?= $active ? ' nav-link-active' : '' ?>"
                       <?= $active ? 'aria-current="page"' : '' ?>>
                        <span class="material-icons-outlined text-[18px]" aria-hidden="true"><?= e($item['icon']) ?></span>
                        <?= e($item['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>

<?php if ($breadcrumbs !== []): ?>
<nav aria-label="Breadcrumb" class="border-b border-rule" style="background-color:var(--page-alt)">
    <ol class="mx-auto flex max-w-6xl flex-wrap items-center gap-1 px-4 py-2 text-xs text-soft">
        <li>
            <a href="<?= e(url('')) ?>" class="hover:underline">Board index</a>
        </li>
        <?php foreach ($breadcrumbs as $crumb): ?>
            <li aria-hidden="true" class="px-1 text-soft">/</li>
            <li>
                <?php if (isset($crumb['url'])): ?>
                    <a href="<?= e($crumb['url']) ?>" class="hover:underline"><?= e((string) $crumb['label']) ?></a>
                <?php else: ?>
                    <span class="font-medium"><?= e((string) $crumb['label']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>

<main id="main" class="mx-auto w-full max-w-6xl px-4 py-6">

<?php foreach ($flashMessages as $message): ?>
    <?php
    $tone = match ($message['type']) {
        'success' => 'alert-success',
        'error'   => 'alert-error',
        default   => '',
    };
    $icon = match ($message['type']) {
        'success' => 'check_circle',
        'error'   => 'error_outline',
        default   => 'info',
    };
    ?>
    <div role="status" class="alert <?= $tone ?> mb-4">
        <span class="material-icons-outlined text-[20px]" aria-hidden="true"><?= e($icon) ?></span>
        <span><?= e((string) $message['message']) ?></span>
    </div>
<?php endforeach; ?>
