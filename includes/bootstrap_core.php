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
    // Prevent PHP from injecting Cache-Control: no-store (breaks public HTML caching).
    session_cache_limiter('');
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $shouldStartSession = true;
    if (defined('VK_PUBLIC_BOOTSTRAP') && VK_PUBLIC_BOOTSTRAP) {
        // Anonymous public GET/HEAD: skip session entirely (faster TTFB, no lock, no cookie).
        $shouldStartSession = ($method !== 'GET' && $method !== 'HEAD')
            || vk_wants_auth_bootstrap();
    }
    if ($shouldStartSession) {
        session_start();
    }
}
