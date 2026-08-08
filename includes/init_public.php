<?php
declare(strict_types=1);

/**
 * Lightweight bootstrap for public-facing pages (homepage, book, track, etc.).
 * Skips heavy email/IMAP/WhatsApp modules and defers auth schema until needed.
 */
define('VK_PUBLIC_BOOTSTRAP', true);

require_once __DIR__ . '/bootstrap_core.php';

try {
    if (vk_wants_auth_bootstrap()) {
        $vkAuthPdo = db();
        vk_auth_ensure_schema($vkAuthPdo);
        vk_auth_try_remember($vkAuthPdo);
        vk_auth_enforce_session_timeout();
        unset($vkAuthPdo);
    } elseif (session_status() === PHP_SESSION_ACTIVE) {
        // Anonymous public pages: release session lock immediately for parallel assets/API.
        session_write_close();
    }
} catch (Throwable $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('auth bootstrap (public): ' . $e->getMessage());
    }
}
