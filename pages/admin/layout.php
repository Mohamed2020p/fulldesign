<?php
/**
 * GodsForum - Admin control room layout.
 *
 * The public board is deliberately old school. The control room is not: it
 * uses a modern interface with a fixed sidebar, soft surfaces, rounded cards
 * and a clear visual hierarchy, so the people who run the board get a tool
 * that is comfortable to work in all day.
 *
 * Call admin_header($title) and admin_footer() around the page body.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/themes.php';

/**
 * The control room section currently being viewed, taken from the clean URL.
 */
function admin_section(): string
{
    $path = router_path();

    if ($path === 'admin' || $path === '') {
        return 'index';
    }

    if (str_starts_with($path, 'admin/')) {
        return substr($path, strlen('admin/'));
    }

    return 'index';
}

/**
 * Render the opening markup of an admin page.
 */
function admin_header(string $title, string $subtitle = ''): void
{
    $user    = require_admin();
    $section = admin_section();
    $flashes = flash_take();

    $openReports = (int) db_value('SELECT COUNT(*) FROM reports WHERE status = "open"', [], 0);

    /** @var array<int, array{path: string, label: string, icon: string, badge?: int, admin_only?: bool, group: string}> $nav */
    $nav = [
        ['path' => 'index',      'label' => 'Dashboard',  'icon' => 'space_dashboard', 'group' => 'Overview'],
        ['path' => 'reports',    'label' => 'Reports',    'icon' => 'flag',            'group' => 'Overview', 'badge' => $openReports],

        ['path' => 'categories', 'label' => 'Categories', 'icon' => 'folder_open',     'group' => 'Structure'],
        ['path' => 'boards',     'label' => 'Boards',     'icon' => 'view_list',       'group' => 'Structure'],

        ['path' => 'topics',     'label' => 'Topics',     'icon' => 'forum',           'group' => 'Content'],
        ['path' => 'posts',      'label' => 'Posts',      'icon' => 'article',         'group' => 'Content'],

        ['path' => 'members',    'label' => 'Members',    'icon' => 'group',           'group' => 'People'],

        ['path' => 'appearance', 'label' => 'Appearance', 'icon' => 'palette',         'group' => 'Board', 'admin_only' => true],
        ['path' => 'settings',   'label' => 'Settings',   'icon' => 'tune',            'group' => 'Board', 'admin_only' => true],
        ['path' => 'logs',       'label' => 'Staff log',  'icon' => 'history',         'group' => 'Board', 'admin_only' => true],
    ];

    /** @var array<string, array<int, array<string, mixed>>> $groups */
    $groups = [];
    foreach ($nav as $item) {
        if (($item['admin_only'] ?? false) === true && !is_super_admin()) {
            continue;
        }
        $groups[$item['group']][] = $item;
    }
    ?>
<!DOCTYPE html>
<html lang="en" class="h-full" data-theme="admin">
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
<body class="admin-body h-full font-sans antialiased">

<a class="skip-link" href="#admin-main">Skip to content</a>

<div class="flex min-h-full flex-col lg:flex-row">
    <aside class="admin-sidebar w-full shrink-0 lg:min-h-screen lg:w-64">
        <div class="admin-brand">
            <img src="<?= e(url('assets/img/logo.png')) ?>" alt="" class="h-9 w-9 rounded-lg object-cover">
            <span class="leading-tight">
                <span class="block text-sm font-semibold tracking-wide">Control room</span>
                <span class="admin-brand-sub block text-[11px]">GodsForum staff</span>
            </span>
        </div>

        <nav aria-label="Control room" class="p-3">
            <?php foreach ($groups as $groupName => $items): ?>
                <p class="admin-nav-group"><?= e($groupName) ?></p>
                <ul class="mb-4 space-y-1 text-sm">
                    <?php foreach ($items as $item): ?>
                        <?php $active = $section === (string) $item['path']; ?>
                        <li>
                            <a href="<?= e(url('admin/' . ($item['path'] === 'index' ? '' : (string) $item['path']))) ?>"
                               class="admin-nav-link<?= $active ? ' admin-nav-link-active' : '' ?>"
                               <?= $active ? 'aria-current="page"' : '' ?>>
                                <span class="material-icons-outlined text-[19px]" aria-hidden="true"><?= e((string) $item['icon']) ?></span>
                                <span class="flex-1"><?= e((string) $item['label']) ?></span>
                                <?php if ((int) ($item['badge'] ?? 0) > 0): ?>
                                    <span class="admin-badge"><?= e((string) (int) $item['badge']) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar-foot">
            <p class="admin-brand-sub text-[11px]">Signed in as</p>
            <p class="text-sm font-semibold"><?= e((string) $user['username']) ?></p>
            <p class="admin-brand-sub text-[11px]"><?= e(role_label((string) $user['role'])) ?></p>
            <a href="<?= e(url('')) ?>" class="admin-nav-link mt-3">
                <span class="material-icons-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                Back to the forum
            </a>
        </div>
    </aside>

    <main id="admin-main" class="min-w-0 flex-1 px-4 py-7 lg:px-9">
        <header class="mb-7">
            <h1 class="text-2xl font-semibold tracking-tight"><?= e($title) ?></h1>
            <?php if ($subtitle !== ''): ?>
                <p class="mt-1 text-sm text-soft"><?= e($subtitle) ?></p>
            <?php endif; ?>
        </header>

        <?php foreach ($flashes as $flash): ?>
            <?php
            $tone = match ($flash['type']) {
                'success' => 'alert-success',
                'error'   => 'alert-error',
                default   => '',
            };
            ?>
            <div role="status" class="alert <?= $tone ?> mb-4"><?= e((string) $flash['message']) ?></div>
        <?php endforeach; ?>
    <?php
}

/**
 * Render the closing markup of an admin page.
 */
function admin_footer(): void
{
    ?>
        <p class="mt-10 border-t border-rule pt-5 text-center text-xs text-soft">
            <?= e(SITE_NAME) ?> control room &copy; <?= e(date('Y')) ?>. Every staff action is written to the log.
        </p>
    </main>
</div>

</body>
</html>
    <?php
}
