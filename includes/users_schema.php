<?php
declare(strict_types=1);

/**
 * Extend users table for management UI (idempotent).
 */
function vk_ensure_users_management_schema(PDO $pdo): void
{
    if (function_exists('vk_auth_ensure_schema')) {
        vk_auth_ensure_schema($pdo);
        return;
    }

    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (!db_table_exists($pdo, 'users')) {
        return;
    }

    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($db) || $db === '') {
            return;
        }

        if (!db_column_exists($pdo, 'users', 'email')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL DEFAULT NULL AFTER username');
        }
        if (!db_column_exists($pdo, 'users', 'phone')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN phone VARCHAR(32) NULL DEFAULT NULL AFTER email');
        }
        if (!db_column_exists($pdo, 'users', 'status')) {
            $pdo->exec(
                "ALTER TABLE users ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER technician_id"
            );
        }

        $ct = $pdo->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $ct->execute([$db, 'users', 'role']);
        $colType = (string) $ct->fetchColumn();
        if ($colType !== '' && stripos($colType, 'staff') === false) {
            $pdo->exec(
                "ALTER TABLE users MODIFY COLUMN role ENUM('admin','staff','technician') NOT NULL DEFAULT 'admin'"
            );
        }

        $idx = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $idx->execute([$db, 'users', 'uq_users_email']);
        if ((int) $idx->fetchColumn() === 0) {
            try {
                $pdo->exec('CREATE UNIQUE INDEX uq_users_email ON users (email)');
            } catch (Throwable $e) {
                // duplicate emails from bad data: skip
            }
        }
    } catch (Throwable $e) {
        error_log('vk_ensure_users_management_schema: ' . $e->getMessage());
    }
}

function vk_users_count_active_admins(PDO $pdo): int
{
    if (!db_table_exists($pdo, 'users')) {
        return 0;
    }
    vk_ensure_users_management_schema($pdo);
    $hasStatus = db_column_exists($pdo, 'users', 'status');
    $sql = $hasStatus
        ? "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'"
        : "SELECT COUNT(*) FROM users WHERE role = 'admin'";
    return (int) $pdo->query($sql)->fetchColumn();
}
