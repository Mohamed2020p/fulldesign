<?php
/**
 * GodsForum - Admin control room layout.
 *
 * Include admin_header() output by requiring this file, then call
 * admin_header($title) and admin_footer() around the page body.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

/**
 * Render the opening markup of an admin page.
 */
function admin_header(string $title, string $subtitle = ''): void
{
    $user        = require_admin();
    $currentFile = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $flashes     = flash_take();

    $openReports = (int) db_value('SELECT COUNT(*) FROM reports WHERE status = "open"', [], 0);

    /** @var array<int, array{file: string, label: string, icon: string, badge?: int, admin_only?: bool}> $nav */
    $nav = [
        ['file' => 'index.php',      'label' => 'Dashboard',  'icon' => 'dashboard'],
        ['file' => 'categories.php', 'label' => 'Categories', 'icon' => 'folder'],
        ['file' => 'boards.php',     'label' => 'Boards',     'icon' => 'view_list'],
        ['file' => 'topics.php',     'label' => 'Topics',     'icon' => 'forum'],
        ['file' => 'posts.php',      'label' => 'Posts',      'icon' => 'article'],
        ['file' => 'reports.php',    'label' => 'Reports',    'icon' => 'flag', 'badge' => $openReports],
        ['file' => 'users.php',      'label' => 'Members',    'icon' => 'group'],
        ['file' => 'settings.php',   'label' => 'Settings',   'icon' => 'settings', 'admin_only' => true],
        ['file' => 'logs.php',       'label' => 'Staff log',  'icon' => 'history',  'admin_only' => true],
    ];
    ?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | Control room</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e(url('assets/img/logo.png')) ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="h-full bg-parchment font-sans text-ink antialiased">

<a class="skip-link" href="#admin-main">Skip to content</a>

<div class="flex min-h-full flex-col lg:flex-row">
    <aside class="w-full shrink-0 border-b-2 border-ink bg-ink text-parchment lg:min-h-screen lg:w-64 lg:border-b-0 lg:border-r-2">
        <div class="flex items-center gap-3 border-b border-parchment/15 px-4 py-4">
            <img src="<?= e(url('assets/img/logo.png')) ?>" alt="" class="h-10 w-10 border border-gold/60 bg-parchment object-cover">
            <span class="leading-tight">
                <span class="block font-serif text-sm font-semibold tracking-[0.18em]">CONTROL ROOM</span>
                <span class="block text-[10px] uppercase tracking-[0.22em] text-gold">GodsForum staff</span>
            </span>
        </div>

        <nav aria-label="Control room" class="p-2">
            <ul class="space-y-0.5 text-sm">
                <?php foreach ($nav as $item): ?>
                    <?php if (($item['admin_only'] ?? false) === true && !is_super_admin()) { continue; } ?>
                    <?php $active = $currentFile === $item['file']; ?>
                    <li>
                        <a href="<?= e(url('admin/' . $item['file'])) ?>"
                           class="flex items-center gap-2 border-l-2 px-3 py-2 transition-colors <?= $active ? 'border-gold bg-parchment/10 text-gold' : 'border-transparent text-parchment/80 hover:bg-parchment/5 hover:text-parchment' ?>">
                            <span class="material-icons-outlined text-[18px]" aria-hidden="true"><?= e($item['icon']) ?></span>
                            <span class="flex-1"><?= e($item['label']) ?></span>
                            <?php if (($item['badge'] ?? 0) > 0): ?>
                                <span class="border border-crimson bg-crimson px-1.5 text-[10px] font-semibold text-parchment"><?= e((string) $item['badge']) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="mt-auto border-t border-parchment/15 p-3 text-xs">
            <p class="text-parchment/70">Signed in as</p>
            <p class="font-semibold text-parchment"><?= e((string) $user['username']) ?> &middot; <?= e(role_label((string) $user['role'])) ?></p>
            <a href="<?= e(url('index.php')) ?>" class="mt-2 flex items-center gap-1 text-parchment/80 hover:text-gold">
                <span class="material-icons-outlined text-[16px]" aria-hidden="true">arrow_back</span>
                Back to the forum
            </a>
        </div>
    </aside>

    <main id="admin-main" class="min-w-0 flex-1 px-4 py-6 lg:px-8">
        <header class="mb-6 border-b border-rule pb-4">
            <h1 class="font-serif text-2xl font-semibold text-ink"><?= e($title) ?></h1>
            <?php if ($subtitle !== ''): ?>
                <p class="mt-1 text-sm text-ink-soft"><?= e($subtitle) ?></p>
            <?php endif; ?>
        </header>

        <?php foreach ($flashes as $flash): ?>
            <?php
            $tone = match ($flash['type']) {
                'success' => 'border-forest bg-forest/10 text-forest',
                'error'   => 'border-crimson bg-crimson/10 text-crimson',
                default   => 'border-ink bg-ink/5 text-ink',
            };
            ?>
            <div role="status" class="mb-4 border-l-4 <?= $tone ?> px-4 py-3 text-sm"><?= e((string) $flash['message']) ?></div>
        <?php endforeach; ?>
    <?php
}

/**
 * Render the closing markup of an admin page.
 */
function admin_footer(): void
{
    ?>
        <p class="mt-10 border-t border-rule pt-4 text-center text-xs text-ink-soft">
            <?= e(SITE_NAME) ?> control room &copy; <?= e(date('Y')) ?>. All staff actions are logged.
        </p>
    </main>
</div>

</body>
</html>
    <?php
}
