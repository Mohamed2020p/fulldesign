<?php
/**
 * GodsForum - Shared helpers: sessions, CSRF, escaping, auth, pagination.
 */

declare(strict_types=1);

// Defence in depth. Apache already denies this directory, but if a server is
// misconfigured these files must still refuse to run as a request target.
if (!defined('GF_ROUTER') && PHP_SAPI !== 'cli' && realpath(__FILE__) === realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    http_response_code(404);
    exit;
}


require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------------------
// Error reporting
// ---------------------------------------------------------------------------
if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

// ---------------------------------------------------------------------------
// Session bootstrap
// ---------------------------------------------------------------------------
function gf_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
        'domain'   => '',
        'secure'   => COOKIE_SECURE,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Rotate the identifier periodically to blunt session fixation.
    if (!isset($_SESSION['created_at'])) {
        $_SESSION['created_at'] = time();
    } elseif (time() - (int) $_SESSION['created_at'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }
}

function gf_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // Over HTTPS, pin the browser to HTTPS for a year. Sent only on a secure
    // connection, because a browser must ignore it on plain HTTP and sending
    // it there would lock out a site that is not yet fully on TLS.
    if (COOKIE_SECURE
        || (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ---------------------------------------------------------------------------
// Output escaping
// ---------------------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escaped, paragraph-formatted user text. No HTML from users is ever trusted.
 */
function format_post(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", trim($body));
    $paragraphs = preg_split('/\n{2,}/', $body) ?: [];

    $html = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }
        $html .= '<p>' . nl2br(e($paragraph)) . '</p>';
    }

    return $html !== '' ? $html : '<p></p>';
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Send a Location header and stop.
 *
 * Accepts an absolute URL, a path already containing BASE_URL (as returned by
 * url() or topic_url()), or a plain path such as 'login.php'. The base path is
 * never applied twice, so passing a value straight from url() is safe.
 */
function redirect(string $path): never
{
    header('Location: ' . absolute_path($path));
    exit;
}

/**
 * Turn any of the accepted path forms into one rooted at the install folder.
 */
function absolute_path(string $path): string
{
    // Full URLs are handed back untouched.
    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }

    // Already prefixed with the install folder, for example from url().
    if (BASE_URL !== '' && ($path === BASE_URL || str_starts_with($path, BASE_URL . '/'))) {
        return $path;
    }

    // At the domain root a leading slash already means the right thing.
    if (BASE_URL === '' && str_starts_with($path, '/')) {
        return $path;
    }

    return url($path);
}

// ---------------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------------
function csrf_token(): string
{
    gf_start_session();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_validate(?string $token): bool
{
    gf_start_session();

    $stored = $_SESSION['csrf_token'] ?? '';

    return is_string($stored)
        && $stored !== ''
        && is_string($token)
        && hash_equals($stored, $token);
}

/**
 * Guard for every state-changing request: POST only + valid token.
 */
function require_post_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Security token expired or invalid. Please go back, reload the page and try again.');
    }
}

// ---------------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------------
function flash(string $type, string $message): void
{
    gf_start_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * @return array<int, array{type: string, message: string}>
 */
function flash_take(): array
{
    gf_start_session();
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($items) ? $items : [];
}

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------
/**
 * @return array<string, mixed>|null
 */
function current_user(): ?array
{
    static $cached = false;
    static $user = null;

    if ($cached) {
        return $user;
    }
    $cached = true;

    gf_start_session();
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string) $id)) {
        return null;
    }

    $user = db_one(
        'SELECT id, username, email, role, status, theme, signature, avatar, post_count,
                ban_reason, banned_until, created_at, last_seen_at
         FROM users WHERE id = :id LIMIT 1',
        ['id' => (int) $id]
    );

    if ($user === null) {
        unset($_SESSION['user_id']);

        return null;
    }

    // A timed suspension that has run its course lifts itself, so staff do not
    // have to come back and undo every temporary ban by hand.
    if ($user['status'] === 'banned' && $user['banned_until'] !== null
        && strtotime((string) $user['banned_until']) <= time()) {
        db_query(
            'UPDATE users SET status = "active", ban_reason = "", banned_until = NULL, banned_by = NULL
              WHERE id = :id',
            ['id' => (int) $user['id']]
        );
        db_query(
            'UPDATE bans SET lifted_at = NOW() WHERE user_id = :id AND lifted_at IS NULL',
            ['id' => (int) $user['id']]
        );

        $user['status']       = 'active';
        $user['ban_reason']   = '';
        $user['banned_until'] = null;
    }

    if ($user['status'] !== 'active') {
        $gfBanned = $user;
        $user = null;
        unset($_SESSION['user_id']);
        gf_banned_notice($gfBanned);

        return null;
    }

    // Keep "last seen" fresh without hammering the database.
    if ($user['last_seen_at'] === null || strtotime((string) $user['last_seen_at']) < time() - 300) {
        db_query('UPDATE users SET last_seen_at = NOW() WHERE id = :id', ['id' => (int) $user['id']]);
    }

    return $user;
}

/**
 * Show a suspended member why they cannot sign in, then stop the request.
 *
 * @param array<string, mixed> $user
 */
function gf_banned_notice(array $user): void
{
    // Only interrupt normal page views; a background asset request just ends.
    if (defined('GF_SUPPRESS_BAN_NOTICE')) {
        return;
    }

    $reason = trim((string) ($user['ban_reason'] ?? ''));
    $until  = $user['banned_until'] !== null ? (string) $user['banned_until'] : null;

    $_SESSION['gf_ban_notice'] = [
        'reason' => $reason,
        'until'  => $until,
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();

    return $user !== null && in_array($user['role'], ['admin', 'moderator'], true);
}

function is_super_admin(): bool
{
    $user = current_user();

    return $user !== null && $user['role'] === 'admin';
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('error', 'Please sign in to continue.');
        redirect(url('login'));
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('You do not have permission to view the control room.');
    }

    return $user;
}

/**
 * Require a full administrator, not merely a moderator.
 *
 * @return array<string, mixed>
 */
function require_super_admin(): array
{
    $user = require_admin();
    if (!is_super_admin()) {
        http_response_code(403);
        exit('Only an administrator may use this page.');
    }

    return $user;
}

function login_user(int $userId): void
{
    gf_start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['created_at'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    db_query('UPDATE users SET last_login_at = NOW(), last_seen_at = NOW() WHERE id = :id', ['id' => $userId]);
}

function logout_user(): void
{
    gf_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------------
// Brute force throttling
// ---------------------------------------------------------------------------
function login_attempts_recent(string $identifier, string $ip): int
{
    // LOGIN_LOCKOUT_SECONDS is an application constant cast to int, never user input.
    $window = (int) LOGIN_LOCKOUT_SECONDS;

    return (int) db_value(
        'SELECT COUNT(*) FROM login_attempts
         WHERE (identifier = :identifier OR ip_address = :ip)
           AND success = 0
           AND attempted_at > (NOW() - INTERVAL ' . $window . ' SECOND)',
        [
            'identifier' => mb_strtolower($identifier),
            'ip'         => $ip,
        ],
        0
    );
}

function login_attempt_record(string $identifier, string $ip, bool $success): void
{
    db_query(
        'INSERT INTO login_attempts (identifier, ip_address, success) VALUES (:identifier, :ip, :success)',
        [
            'identifier' => mb_substr(mb_strtolower($identifier), 0, 190),
            'ip'         => $ip,
            'success'    => $success ? 1 : 0,
        ]
    );
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

// ---------------------------------------------------------------------------
// Formatting helpers
// ---------------------------------------------------------------------------
function time_ago(?string $datetime): string
{
    if ($datetime === null) {
        return 'never';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'unknown';
    }

    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'moments ago';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);

        return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);

        return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 2592000) {
        $d = (int) floor($diff / 86400);

        return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    }

    return date('j M Y', $timestamp);
}

function full_date(?string $datetime): string
{
    if ($datetime === null) {
        return '-';
    }
    $timestamp = strtotime($datetime);

    return $timestamp === false ? '-' : date('j M Y, H:i', $timestamp);
}

function number_short(int $number): string
{
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    }
    if ($number >= 1000) {
        return round($number / 1000, 1) . 'k';
    }

    return (string) $number;
}

function slugify(string $text): string
{
    $text = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $text) ?? '';
    $text = trim(mb_strtolower($text), '-');
    $text = preg_replace('/-+/', '-', $text) ?? '';

    return $text === '' ? 'topic' : mb_substr($text, 0, 80);
}

function excerpt(string $text, int $length = 140): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length - 1) . '...';
}

function avatar_url(?string $avatar): string
{
    if ($avatar !== null && $avatar !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $avatar) === 1) {
        return url('uploads/avatars/' . $avatar);
    }

    return url('assets/img/avatar-default.png');
}

function role_label(string $role): string
{
    return match ($role) {
        'admin'     => 'Administrator',
        'moderator' => 'Moderator',
        default     => 'Member',
    };
}

/**
 * Build a page-number list for pagination widgets.
 *
 * @return array<int, int>
 */
function page_window(int $current, int $total, int $radius = 2): array
{
    $pages = [];
    for ($i = max(1, $current - $radius); $i <= min($total, $current + $radius); $i++) {
        $pages[] = $i;
    }

    return $pages;
}

function param_int(string $key, int $default = 0): int
{
    $value = $_GET[$key] ?? null;

    return is_string($value) && preg_match('/^\d+$/', $value) === 1 ? (int) $value : $default;
}

function param_string(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? null;

    return is_string($value) ? trim($value) : $default;
}

function post_string(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? null;

    return is_string($value) ? trim($value) : $default;
}

function post_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? null;

    return is_string($value) && preg_match('/^\d+$/', $value) === 1 ? (int) $value : $default;
}

// ---------------------------------------------------------------------------
// Domain helpers
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
// Clean URL builders
//
// Every public address is a readable path. No .php extension and no numeric
// query string is ever shown, so the addresses reveal nothing about the
// storage layer.
//
//   /board/general-talk
//   /board/general-talk/page/2
//   /topic/what-does-everyone-drink-while-posting
//   /topic/what-does-everyone-drink-while-posting/page/3
//   /member/hermes
// ---------------------------------------------------------------------------

function topic_url(string $slug, int $page = 1): string
{
    $path = 'topic/' . rawurlencode($slug);
    if ($page > 1) {
        $path .= '/page/' . $page;
    }

    return url($path);
}

function board_url(string $slug, int $page = 1): string
{
    $path = 'board/' . rawurlencode($slug);
    if ($page > 1) {
        $path .= '/page/' . $page;
    }

    return url($path);
}

/**
 * A random, unguessable public reference for a post.
 *
 * Public addresses use this instead of the auto increment id so the address
 * space cannot be walked and no internal numbering is disclosed.
 */
function generate_ref(int $length = 12): string
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $max = strlen($alphabet) - 1;
    $ref = '';

    for ($i = 0; $i < $length; $i++) {
        $ref .= $alphabet[random_int(0, $max)];
    }

    return $ref;
}

function member_url(string $username): string
{
    return url('member/' . rawurlencode($username));
}

function post_url(string $topicSlug, int $postId, int $page = 1): string
{
    return topic_url($topicSlug, $page) . '#post-' . $postId;
}

/**
 * Build a unique slug for a table, appending -2, -3 and so on when needed.
 *
 * $table is never user input: it is a literal chosen by the calling code.
 */
function unique_slug(string $table, string $text, ?int $ignoreId = null): string
{
    $allowed = ['topics', 'boards', 'categories'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Unknown table for slug generation.');
    }

    $base = slugify($text);
    $slug = $base;
    $suffix = 1;

    while (true) {
        $sql = 'SELECT COUNT(*) FROM `' . $table . '` WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        if ((int) db_value($sql, $params, 0) === 0) {
            return $slug;
        }

        $suffix++;
        $slug = $base . '-' . $suffix;
    }
}

function recount_topic(int $topicId): void
{
    $stats = db_one(
        'SELECT COUNT(*) AS total, MAX(created_at) AS last_at FROM posts WHERE topic_id = :id',
        ['id' => $topicId]
    ) ?? ['total' => 0, 'last_at' => null];

    $replies = max(0, (int) $stats['total'] - 1);

    if ($stats['last_at'] === null) {
        db_query('UPDATE topics SET reply_count = 0 WHERE id = :id', ['id' => $topicId]);

        return;
    }

    db_query(
        'UPDATE topics SET reply_count = :replies, last_post_at = :last_at WHERE id = :id',
        ['replies' => $replies, 'last_at' => (string) $stats['last_at'], 'id' => $topicId]
    );
}

function recount_user_posts(int $userId): void
{
    $count = (int) db_value('SELECT COUNT(*) FROM posts WHERE user_id = :id', ['id' => $userId], 0);

    db_query('UPDATE users SET post_count = :count WHERE id = :id', ['count' => $count, 'id' => $userId]);
}

function log_admin_action(int $adminId, string $action, string $details = ''): void
{
    db_query(
        'INSERT INTO admin_log (admin_id, action, details, ip_address) VALUES (:admin, :action, :details, :ip)',
        [
            'admin'   => $adminId,
            'action'  => mb_substr($action, 0, 100),
            'details' => mb_substr($details, 0, 500),
            'ip'      => client_ip(),
        ]
    );
}

/**
 * Store a board setting, creating the row if it is not there yet.
 *
 * The key and the value are both bound, so neither can influence the query.
 */
function setting_put(string $key, string $value): void
{
    db_query(
        'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        ['k' => $key, 'v' => $value]
    );
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
    }

    return $cache[$key] ?? $default;
}

gf_start_session();
gf_send_security_headers();
