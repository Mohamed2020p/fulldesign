<?php
/**
 * GodsForum - Application configuration
 *
 * Copy this file's values to match your own environment before going live.
 * Never commit real production credentials to a public repository.
 */

declare(strict_types=1);

// Defence in depth. Apache already denies this directory, but if a server is
// misconfigured these files must still refuse to run as a request target.
if (!defined('GF_ROUTER') && PHP_SAPI !== 'cli' && realpath(__FILE__) === realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    http_response_code(404);
    exit;
}


// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------
define('DB_HOST', getenv('GF_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int) (getenv('GF_DB_PORT') ?: 3306));
define('DB_NAME', getenv('GF_DB_NAME') ?: 'godsforum');
define('DB_USER', getenv('GF_DB_USER') ?: 'root');
define('DB_PASS', getenv('GF_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------------
// Site
// ---------------------------------------------------------------------------
define('SITE_NAME', 'GodsForum');
define('SITE_TAGLINE', 'A meeting hall for mortals with opinions');

/**
 * Work out the URL path the forum is installed under.
 *
 * The project root is the folder holding index.php, one level above this file.
 * The currently running script always lives inside it, so removing the script's
 * path relative to the project root from SCRIPT_NAME leaves the base path.
 */
function gf_detect_base_url(): string
{
    // 1. An explicit override always wins.
    $configured = getenv('GF_BASE_URL');
    if (is_string($configured) && $configured !== '') {
        return $configured === '/' ? '' : '/' . trim(str_replace('\\', '/', $configured), '/');
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';

    if (!is_string($scriptName) || $scriptName === '' || !is_string($scriptFile) || $scriptFile === '') {
        return '';
    }

    $normalise = static fn (string $path): string => rtrim(str_replace('\\', '/', $path), '/');

    $projectRoot = $normalise((string) (realpath(dirname(__DIR__)) ?: dirname(__DIR__)));
    $scriptPath  = $normalise((string) (realpath($scriptFile) ?: $scriptFile));
    $scriptName  = str_replace('\\', '/', $scriptName);

    // 2. Preferred route: strip the script's path inside the project from SCRIPT_NAME.
    if ($projectRoot !== '' && str_starts_with($scriptPath, $projectRoot . '/')) {
        $relative = substr($scriptPath, strlen($projectRoot));      // e.g. /admin/index.php
        if ($relative !== '' && str_ends_with($scriptName, $relative)) {
            return rtrim(substr($scriptName, 0, -strlen($relative)), '/');
        }
    }

    // 3. Fallback for setups where the paths cannot be compared (symlinks, odd
    //    SAPIs): assume this file sits one level below the project root.
    $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    // Scripts inside /admin are one directory deeper than the project root.
    if (str_ends_with($base, '/admin')) {
        $base = substr($base, 0, -strlen('/admin'));
    }

    return $base === '/' ? '' : $base;
}

/**
 * Base URL path of the installation, WITHOUT a trailing slash.
 *
 * You normally do not need to touch this. It is detected automatically, so the
 * forum works unchanged at the domain root:
 *
 *     http://localhost/                       ->  BASE_URL = ''
 *
 * and inside any subfolder, whatever that folder happens to be called:
 *
 *     http://localhost/fulldesign/            ->  BASE_URL = '/fulldesign'
 *     http://localhost/forums/godsforum/      ->  BASE_URL = '/forums/godsforum'
 *
 * Set the GF_BASE_URL environment variable to override the detection, for
 * example when the forum sits behind a reverse proxy on a different path.
 */
define('BASE_URL', gf_detect_base_url());

define('POSTS_PER_PAGE', 10);
define('TOPICS_PER_PAGE', 20);
define('MEMBERS_PER_PAGE', 24);

// Minimum seconds between two posts by the same member (flood control).
define('POST_FLOOD_SECONDS', 15);

// Avatar uploads
define('AVATAR_DIR', dirname(__DIR__) . '/uploads/avatars');
define('AVATAR_MAX_BYTES', 1024 * 1024 * 2); // 2 MB

// ---------------------------------------------------------------------------
// Security
// ---------------------------------------------------------------------------
define('SESSION_NAME', 'GODSFORUMSESSID');
define('SESSION_LIFETIME', 60 * 60 * 8); // 8 hours
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 15 * 60);

// Set to true only when the site is served over HTTPS.
define('COOKIE_SECURE', (bool) (getenv('GF_HTTPS') ?: false));

// Display PHP errors on screen? Keep false in production.
define('DEBUG_MODE', (bool) (getenv('GF_DEBUG') ?: false));
