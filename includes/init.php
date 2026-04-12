<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/functions.php';
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
