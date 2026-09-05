<?php
/**
 * GodsForum - Directory index entry point.
 *
 * The .htaccess file sets "DirectoryIndex router.php", but many shared hosts
 * pin DirectoryIndex in the server configuration where a .htaccess file
 * cannot override it. This file exists so the board also starts correctly on
 * those hosts: it simply hands the request to the front controller.
 *
 * Visitors never see this address. Any request that literally names a .php
 * file is redirected to its readable address by .htaccess before it is run.
 */

declare(strict_types=1);

require __DIR__ . '/router.php';
