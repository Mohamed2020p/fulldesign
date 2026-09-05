<?php
/**
 * GodsForum - Sign out. POST only, CSRF protected.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

require_post_csrf();
logout_user();

gf_start_session();
flash('success', 'You have been signed out.');
redirect('index.php');
