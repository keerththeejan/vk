<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap_core.php';

vk_bootstrap_module('service_gallery');
vk_bootstrap_module('vehicle_booking');
vk_bootstrap_module('whatsapp_bridge');
vk_bootstrap_module('mailer');
vk_bootstrap_module('email_system');
vk_bootstrap_module('email_imap_poll');

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
