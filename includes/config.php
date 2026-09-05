<?php
/**
 * GodsForum - Application configuration
 *
 * Copy this file's values to match your own environment before going live.
 * Never commit real production credentials to a public repository.
 */

declare(strict_types=1);

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
 * Base URL path of the installation, WITHOUT trailing slash.
 * Use '' when the forum lives at the domain root, or '/godsforum' in a subfolder.
 */
define('BASE_URL', rtrim(getenv('GF_BASE_URL') ?: '', '/'));

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
