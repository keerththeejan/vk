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
