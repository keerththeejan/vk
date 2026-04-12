<?php
declare(strict_types=1);
/**
 * Session, auth, and DB — no HTML output.
 * Use before any redirect(); then require layout_start.php for the chrome.
 */
require_once __DIR__ . '/init.php';
require_admin();
$pdo = db();
require_once __DIR__ . '/payments_schema.php';
vk_ensure_payments_table($pdo);
$currentUser = current_user($pdo);
if (!defined('VK_LAYOUT_BOOTSTRAPPED')) {
    define('VK_LAYOUT_BOOTSTRAPPED', true);
}
