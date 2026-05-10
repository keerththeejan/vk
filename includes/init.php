<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/service_gallery.php';
require_once __DIR__ . '/vehicle_booking.php';
require_once __DIR__ . '/whatsapp_bridge.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email_system.php';
require_once __DIR__ . '/email_imap_poll.php';
require_once __DIR__ . '/seo.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

try {
    $vkAuthPdo = db();
    vk_auth_ensure_schema($vkAuthPdo);
    vk_auth_try_remember($vkAuthPdo);
    vk_auth_enforce_session_timeout();
    unset($vkAuthPdo);
} catch (Throwable $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('auth bootstrap: ' . $e->getMessage());
    }
}
