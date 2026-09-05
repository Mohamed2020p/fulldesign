<?php
/**
 * GodsForum - Sign out. POST only, CSRF protected.
 */

declare(strict_types=1);

// This page is a route target, not a public address. It is reached only
// through the front controller, which has already resolved the request.
if (!defined('GF_ROUTER')) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/functions.php';

require_post_csrf();
logout_user();

gf_start_session();
flash('success', 'You have been signed out.');
redirect(url(''));
