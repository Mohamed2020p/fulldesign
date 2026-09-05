<?php
/**
 * GodsForum - Front controller.
 *
 * Every request that is not a real file on disk is sent here by .htaccess.
 * The path is matched against a table of readable routes and dispatched to
 * the page that renders it.
 *
 * Public addresses never expose a .php extension or a numeric identifier.
 * Records are looked up by their slug, and the slug is always bound as a
 * parameter, so a crafted path is simply a slug that matches nothing and
 * produces an ordinary 404.
 */

declare(strict_types=1);

define('GF_ROUTER', true);

require_once __DIR__ . '/includes/functions.php';

/**
 * The path requested, relative to the install folder, with no query string.
 */
function router_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

    // Drop the query string.
    $position = strpos($uri, '?');
    if ($position !== false) {
        $uri = substr($uri, 0, $position);
    }

    $uri = rawurldecode($uri);

    // Drop the install folder prefix so routes are written folder independent.
    if (BASE_URL !== '' && str_starts_with($uri, BASE_URL)) {
        $uri = substr($uri, strlen(BASE_URL));
    }

    $uri = trim($uri, '/');

    // Some servers expand a request for the directory itself into the
    // DirectoryIndex file before PHP sees it. Both entry points mean "the
    // board index", so strip them and treat the request as the root.
    if ($uri === 'index.php' || $uri === 'router.php') {
        return '';
    }

    return $uri;
}

/**
 * Render the 404 page and stop.
 */
function router_not_found(): never
{
    http_response_code(404);

    $pageTitle = 'Page not found';
    $breadcrumbs = [['label' => 'Not found']];

    require __DIR__ . '/includes/header.php';
    echo '<div class="panel px-6 py-16 text-center">'
       . '<span class="material-icons-outlined text-5xl text-soft" aria-hidden="true">explore_off</span>'
       . '<h1 class="mt-3 font-serif text-2xl font-semibold">Page not found</h1>'
       . '<p class="mx-auto mt-2 max-w-md text-sm text-soft">'
       . 'That address does not match anything on the board. It may have been '
       . 'renamed, or the link that brought you here may be out of date.</p>'
       . '<div class="mt-6 flex flex-wrap justify-center gap-2">'
       . '<a class="btn btn-primary" href="' . e(url('')) . '">Board index</a>'
       . '<a class="btn btn-ghost" href="' . e(url('search')) . '">Search the board</a>'
       . '</div></div>';
    require __DIR__ . '/includes/footer.php';

    exit;
}

$path = router_path();

// Reject anything that tries to climb the tree or smuggle a null byte before
// a single route is considered. These can never be part of a real address.
if ($path !== '' && (
        str_contains($path, '..')
        || str_contains($path, '\\')
        || str_contains($path, "\0")
        || str_contains($path, '.php')
    )) {
    router_not_found();
}

/**
 * Static routes: an exact path maps straight to a page file.
 *
 * @var array<string, string> $staticRoutes
 */
$staticRoutes = [
    ''            => 'pages/index.php',
    'recent'      => 'pages/recent.php',
    'members'     => 'pages/members.php',
    'search'      => 'pages/search.php',
    'rules'       => 'pages/rules.php',
    'login'       => 'pages/login.php',
    'register'    => 'pages/register.php',
    'logout'      => 'pages/logout.php',
    'settings'    => 'pages/settings.php',
    'appearance'  => 'pages/appearance.php',
];

if (isset($staticRoutes[$path])) {
    require __DIR__ . '/' . $staticRoutes[$path];
    exit;
}

/**
 * Dynamic routes. Each pattern captures the readable parts of the address and
 * exposes them to the page through $route.
 *
 * @var array<int, array{pattern: string, file: string, keys: array<int, string>}> $routes
 */
$routes = [
    // /board/general-talk  and  /board/general-talk/page/2
    ['pattern' => '#^board/([a-z0-9-]{1,90})$#',                  'file' => 'pages/board.php',     'keys' => ['slug']],
    ['pattern' => '#^board/([a-z0-9-]{1,90})/page/(\d{1,6})$#',   'file' => 'pages/board.php',     'keys' => ['slug', 'page']],

    // /topic/some-topic  and  /topic/some-topic/page/3
    ['pattern' => '#^topic/([a-z0-9-]{1,150})$#',                 'file' => 'pages/topic.php',     'keys' => ['slug']],
    ['pattern' => '#^topic/([a-z0-9-]{1,150})/page/(\d{1,6})$#',  'file' => 'pages/topic.php',     'keys' => ['slug', 'page']],

    // /board/general-talk/new
    ['pattern' => '#^board/([a-z0-9-]{1,90})/new$#',              'file' => 'pages/new_topic.php', 'keys' => ['slug']],

    // /member/hermes
    ['pattern' => '#^member/([A-Za-z0-9_]{1,32})$#',              'file' => 'pages/profile.php',   'keys' => ['username']],

    // Actions that need to address one post. The reference is opaque and is
    // validated against the database before anything is done with it.
    ['pattern' => '#^post/([A-Za-z0-9]{6,64})/edit$#',            'file' => 'pages/edit_post.php', 'keys' => ['ref']],
    ['pattern' => '#^post/([A-Za-z0-9]{6,64})/report$#',          'file' => 'pages/report.php',    'keys' => ['ref']],
];

/** @var array<string, string> $route */
$route = [];

foreach ($routes as $candidate) {
    if (preg_match($candidate['pattern'], $path, $matches) === 1) {
        foreach ($candidate['keys'] as $index => $key) {
            $route[$key] = $matches[$index + 1];
        }

        require __DIR__ . '/' . $candidate['file'];
        exit;
    }
}

// ---------------------------------------------------------------------------
// Admin area: /admin, /admin/members, /admin/appearance and so on.
// ---------------------------------------------------------------------------
if ($path === 'admin' || str_starts_with($path, 'admin/')) {
    $section = $path === 'admin' ? 'index' : substr($path, strlen('admin/'));

    /** @var array<string, string> $adminRoutes */
    $adminRoutes = [
        'index'      => 'pages/admin/index.php',
        'categories' => 'pages/admin/categories.php',
        'boards'     => 'pages/admin/boards.php',
        'topics'     => 'pages/admin/topics.php',
        'posts'      => 'pages/admin/posts.php',
        'reports'    => 'pages/admin/reports.php',
        'members'    => 'pages/admin/users.php',
        'appearance' => 'pages/admin/appearance.php',
        'settings'   => 'pages/admin/settings.php',
        'logs'       => 'pages/admin/logs.php',
    ];

    if (isset($adminRoutes[$section])) {
        require __DIR__ . '/' . $adminRoutes[$section];
        exit;
    }
}

router_not_found();
