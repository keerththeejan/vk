<?php
declare(strict_types=1);

/**
 * Request timing and optional debug headers (APP_DEBUG or VK_PERF_HEADERS=1).
 */

function vk_perf_bootstrap(): void
{
    if (isset($GLOBALS['_vk_perf'])) {
        return;
    }
    $GLOBALS['_vk_perf'] = [
        'start' => hrtime(true),
        'memory' => memory_get_usage(true),
        'queries' => 0,
    ];
}

function vk_perf_mark_query(): void
{
    if (isset($GLOBALS['_vk_perf'])) {
        $GLOBALS['_vk_perf']['queries']++;
    }
}

function vk_perf_shutdown_headers(): void
{
    if (!isset($GLOBALS['_vk_perf']) || headers_sent()) {
        return;
    }
    $enabled = (defined('APP_DEBUG') && APP_DEBUG)
        || (getenv('VK_PERF_HEADERS') && getenv('VK_PERF_HEADERS') !== '0');
    if (!$enabled) {
        return;
    }
    $elapsedMs = (hrtime(true) - (int) $GLOBALS['_vk_perf']['start']) / 1_000_000;
    $memoryMb = memory_get_peak_usage(true) / 1048576;
    header('X-VK-Exec-Ms: ' . number_format($elapsedMs, 2, '.', ''));
    header('X-VK-Memory-MB: ' . number_format($memoryMb, 2, '.', ''));
    header('X-VK-Query-Count: ' . (int) $GLOBALS['_vk_perf']['queries']);
}

register_shutdown_function('vk_perf_shutdown_headers');
register_shutdown_function(static function (): void {
    // Probabilistic housekeeping (~2% of requests) keeps pages fast.
    if (random_int(1, 50) !== 1) {
        return;
    }
    if (function_exists('vk_maintenance_cleanup')) {
        try {
            vk_maintenance_cleanup();
        } catch (Throwable) {
        }
    }
});

/**
 * Send no-store headers for authenticated HTML responses (overrides public PHP caching).
 */
function vk_perf_send_private_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
