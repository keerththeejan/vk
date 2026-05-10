<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
$pdo = db();
vk_auth_log_login($pdo, !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null, (string) ($_SESSION['user_role'] ?? ''), 'logout');
vk_auth_clear_remember_cookie($pdo);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
}
session_destroy();
header('Location: ' . BASE_URL . '/index.php');
exit;
