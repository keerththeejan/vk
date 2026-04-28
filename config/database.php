<?php
/**
 * PDO database connection (singleton).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $isProduction = defined('APP_ENV') && APP_ENV === 'production';

    $host = getenv('VK_DB_HOST') ?: ($isProduction ? 'localhost' : '127.0.0.1');
    $name = getenv('VK_DB_NAME') ?: ($isProduction ? '' : 'vk_billing');
    $user = getenv('VK_DB_USER') ?: ($isProduction ? '' : 'root');
    $pass = getenv('VK_DB_PASS') ?: ($isProduction ? '' : '1234');
    $charset = 'utf8mb4';

    if ($name === '' || $user === '') {
        throw new RuntimeException('Database is not configured. Set VK_DB_NAME, VK_DB_USER, and VK_DB_PASS.');
    }

    $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}
