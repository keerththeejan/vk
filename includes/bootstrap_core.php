<?php
declare(strict_types=1);

/**
 * Shared bootstrap: config, database, helpers, settings, SEO.
 * Used by init.php (full) and init_public.php (lightweight public pages).
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/performance.php';
require_once __DIR__ . '/cache.php';

vk_perf_bootstrap();

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
