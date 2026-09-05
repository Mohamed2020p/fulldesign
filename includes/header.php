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

require_once __DIR__ . '/functions.php';

$gfUser        = current_user();
$pageTitle     = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : SITE_NAME;
$pageDescription = isset($pageDescription) && is_string($pageDescription)
    ? $pageDescription
    : SITE_TAGLINE;
$breadcrumbs   = isset($breadcrumbs) && is_array($breadcrumbs) ? $breadcrumbs : [];
$flashMessages = flash_take();
$currentFile   = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

/** @var array<int, array{file: string, label: string, icon: string}> $gfNav */
$gfNav = [
    ['file' => 'index.php',   'label' => 'Board',   'icon' => 'dashboard'],
    ['file' => 'recent.php',  'label' => 'Recent',  'icon' => 'schedule'],
    ['file' => 'members.php', 'label' => 'Members', 'icon' => 'groups'],
    ['file' => 'search.php',  'label' => 'Search',  'icon' => 'search'],
    ['file' => 'rules.php',   'label' => 'Rules',   'icon' => 'gavel'],
];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
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
<body class="h-full bg-parchment font-sans text-ink antialiased">

<a class="skip-link" href="#main">Skip to content</a>

<header class="border-b-2 border-ink bg-ink text-parchment">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-3">
        <a href="<?= e(url('index.php')) ?>" class="flex items-center gap-3">
            <img src="<?= e(url('assets/img/logo.png')) ?>" alt="" class="h-11 w-11 rounded-sm border border-gold/60 bg-parchment object-cover">
            <span class="leading-tight">
                <span class="block font-serif text-xl font-semibold tracking-[0.18em] text-parchment">GODSFORUM</span>
                <span class="block text-[11px] uppercase tracking-[0.24em] text-gold"><?= e(SITE_TAGLINE) ?></span>
            </span>
        </a>

        <div class="ml-auto flex items-center gap-2 text-sm">
            <?php if ($gfUser !== null): ?>
                <a href="<?= e(url('profile.php?id=' . (int) $gfUser['id'])) ?>"
                   class="flex items-center gap-2 rounded-sm border border-parchment/25 px-3 py-1.5 transition-colors hover:border-gold hover:text-gold">
                    <img src="<?= e(avatar_url(isset($gfUser['avatar']) ? (string) $gfUser['avatar'] : null)) ?>" alt="" class="h-6 w-6 rounded-sm object-cover">
                    <span class="font-medium"><?= e((string) $gfUser['username']) ?></span>
                </a>
                <?php if (is_admin()): ?>
                    <a href="<?= e(url('admin/index.php')) ?>"
                       class="hidden items-center gap-1 rounded-sm border border-gold bg-gold px-3 py-1.5 font-medium text-ink transition-colors hover:bg-transparent hover:text-gold sm:flex">
                        <span class="material-icons-outlined text-[18px]" aria-hidden="true">shield</span>
                        Control room
                    </a>
                <?php endif; ?>
                <form method="post" action="<?= e(url('logout.php')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit"
                            class="flex items-center gap-1 rounded-sm border border-parchment/25 px-3 py-1.5 transition-colors hover:border-crimson hover:text-crimson">
                        <span class="material-icons-outlined text-[18px]" aria-hidden="true">logout</span>
                        Sign out
                    </button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('login.php')) ?>"
                   class="rounded-sm border border-parchment/25 px-3 py-1.5 transition-colors hover:border-gold hover:text-gold">Sign in</a>
                <a href="<?= e(url('register.php')) ?>"
                   class="rounded-sm border border-gold bg-gold px-3 py-1.5 font-medium text-ink transition-colors hover:bg-transparent hover:text-gold">Register</a>
            <?php endif; ?>
        </div>
    </div>

    <nav aria-label="Primary" class="border-t border-parchment/15 bg-ink-dark">
        <ul class="mx-auto flex max-w-6xl flex-wrap items-center px-4 text-sm">
            <?php foreach ($gfNav as $item): ?>
                <?php $active = $currentFile === $item['file']; ?>
                <li>
                    <a href="<?= e(url($item['file'])) ?>"
                       class="flex items-center gap-1.5 border-b-2 px-3 py-2.5 uppercase tracking-[0.12em] transition-colors <?= $active ? 'border-gold text-gold' : 'border-transparent text-parchment/75 hover:border-parchment/40 hover:text-parchment' ?>"
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
<nav aria-label="Breadcrumb" class="border-b border-rule bg-parchment-dark">
    <ol class="mx-auto flex max-w-6xl flex-wrap items-center gap-1 px-4 py-2 text-xs text-ink-soft">
        <li>
            <a href="<?= e(url('index.php')) ?>" class="hover:text-crimson hover:underline">Board index</a>
        </li>
        <?php foreach ($breadcrumbs as $crumb): ?>
            <li aria-hidden="true" class="px-1 text-ink-soft/60">/</li>
            <li>
                <?php if (isset($crumb['url'])): ?>
                    <a href="<?= e($crumb['url']) ?>" class="hover:text-crimson hover:underline"><?= e((string) $crumb['label']) ?></a>
                <?php else: ?>
                    <span class="font-medium text-ink"><?= e((string) $crumb['label']) ?></span>
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
        'success' => 'border-forest bg-forest/10 text-forest',
        'error'   => 'border-crimson bg-crimson/10 text-crimson',
        default   => 'border-ink bg-ink/5 text-ink',
    };
    $icon = match ($message['type']) {
        'success' => 'check_circle',
        'error'   => 'error_outline',
        default   => 'info',
    };
    ?>
    <div role="status" class="mb-4 flex items-start gap-2 border-l-4 <?= $tone ?> px-4 py-3 text-sm">
        <span class="material-icons-outlined text-[20px]" aria-hidden="true"><?= e($icon) ?></span>
        <span><?= e((string) $message['message']) ?></span>
    </div>
<?php endforeach; ?>
