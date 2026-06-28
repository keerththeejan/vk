<?php
declare(strict_types=1);
/**
 * Session, auth, and DB — no HTML output.
 * Use before any redirect(); then require layout_start.php for the chrome.
 */
require_once __DIR__ . '/init.php';
require_admin();
$pdo = db();
$currentUser = current_user($pdo) ?: vk_auth_cached_user() ?: [];
if (!defined('VK_LAYOUT_BOOTSTRAPPED')) {
    define('VK_LAYOUT_BOOTSTRAPPED', true);
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

/**
 * Finance schema migrations — call only from invoice/payment/account modules.
 */
function vk_ensure_finance_schemas(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    require_once __DIR__ . '/invoices_schema.php';
    require_once __DIR__ . '/accounting_schema.php';
    require_once __DIR__ . '/payments_schema.php';
    vk_ensure_invoice_items_table($pdo);
    vk_ensure_payments_table($pdo);
    vk_ensure_account_ledger_table($pdo);
}
