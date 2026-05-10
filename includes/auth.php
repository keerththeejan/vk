<?php
declare(strict_types=1);

const VK_AUTH_ROLES = ['super_admin', 'admin', 'manager', 'technician', 'staff', 'viewer'];
const VK_AUTH_STATUSES = ['pending', 'active', 'rejected', 'suspended', 'inactive'];

function vk_auth_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS roles (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role_key VARCHAR(64) NOT NULL UNIQUE,
            role_name VARCHAR(96) NOT NULL,
            priority TINYINT UNSIGNED NOT NULL DEFAULT 50,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    foreach ([
        ['super_admin', 'Super Admin', 100],
        ['admin', 'Admin', 90],
        ['manager', 'Manager', 70],
        ['technician', 'Technician', 50],
        ['staff', 'Staff', 40],
        ['viewer', 'Viewer', 10],
    ] as $role) {
        $st = $pdo->prepare('INSERT IGNORE INTO roles (role_key, role_name, priority) VALUES (?, ?, ?)');
        $st->execute($role);
    }

    if (!db_table_exists($pdo, 'users')) {
        $pdo->exec(
            "CREATE TABLE users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(64) NOT NULL UNIQUE,
                email VARCHAR(150) DEFAULT NULL,
                phone VARCHAR(32) DEFAULT NULL,
                password_hash VARCHAR(255) NOT NULL,
                fullname VARCHAR(128) DEFAULT NULL,
                department VARCHAR(128) DEFAULT NULL,
                user_uid VARCHAR(32) DEFAULT NULL UNIQUE,
                role ENUM('super_admin','admin','manager','technician','staff','viewer') NOT NULL DEFAULT 'viewer',
                technician_id INT UNSIGNED DEFAULT NULL,
                status ENUM('pending','active','rejected','suspended','inactive') NOT NULL DEFAULT 'pending',
                approved_by INT UNSIGNED DEFAULT NULL,
                approved_at DATETIME DEFAULT NULL,
                rejected_at DATETIME DEFAULT NULL,
                last_login_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_users_email (email),
                KEY idx_users_status_role (status, role),
                KEY idx_users_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } else {
        vk_auth_upgrade_users_table($pdo);
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS login_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED DEFAULT NULL,
            username VARCHAR(150) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            status ENUM('success','failed','blocked','logout') NOT NULL,
            failure_reason VARCHAR(160) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_login_logs_user (user_id, created_at),
            KEY idx_login_logs_lookup (username, ip_address, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS approvals (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            action ENUM('registered','approved','rejected','suspended','reactivated','role_changed','password_reset') NOT NULL,
            actor_id INT UNSIGNED DEFAULT NULL,
            from_status VARCHAR(32) DEFAULT NULL,
            to_status VARCHAR(32) DEFAULT NULL,
            from_role VARCHAR(64) DEFAULT NULL,
            to_role VARCHAR(64) DEFAULT NULL,
            note VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_approvals_user (user_id, created_at),
            KEY idx_approvals_actor (actor_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_resets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash VARCHAR(255) DEFAULT NULL,
            requested_by INT UNSIGNED DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_password_resets_user (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS activity_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED DEFAULT NULL,
            actor_id INT UNSIGNED DEFAULT NULL,
            action VARCHAR(96) NOT NULL,
            entity_type VARCHAR(64) DEFAULT NULL,
            entity_id BIGINT UNSIGNED DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_activity_actor (actor_id, created_at),
            KEY idx_activity_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS remember_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            selector CHAR(24) NOT NULL UNIQUE,
            validator_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME DEFAULT NULL,
            KEY idx_remember_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function vk_auth_upgrade_users_table(PDO $pdo): void
{
    foreach ([
        'email' => "ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL DEFAULT NULL AFTER username",
        'phone' => "ALTER TABLE users ADD COLUMN phone VARCHAR(32) NULL DEFAULT NULL AFTER email",
        'department' => "ALTER TABLE users ADD COLUMN department VARCHAR(128) NULL DEFAULT NULL AFTER fullname",
        'user_uid' => "ALTER TABLE users ADD COLUMN user_uid VARCHAR(32) NULL DEFAULT NULL AFTER department",
        'approved_by' => "ALTER TABLE users ADD COLUMN approved_by INT UNSIGNED NULL DEFAULT NULL AFTER status",
        'approved_at' => "ALTER TABLE users ADD COLUMN approved_at DATETIME NULL DEFAULT NULL AFTER approved_by",
        'rejected_at' => "ALTER TABLE users ADD COLUMN rejected_at DATETIME NULL DEFAULT NULL AFTER approved_at",
        'last_login_at' => "ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL DEFAULT NULL AFTER rejected_at",
        'updated_at' => "ALTER TABLE users ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    ] as $column => $sql) {
        if (!db_column_exists($pdo, 'users', $column)) {
            $pdo->exec($sql);
        }
    }

    $pdo->exec(
        "ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin','manager','technician','staff','viewer') NOT NULL DEFAULT 'viewer'"
    );
    $pdo->exec(
        "ALTER TABLE users MODIFY COLUMN status ENUM('pending','active','rejected','suspended','inactive') NOT NULL DEFAULT 'pending'"
    );

    try {
        $pdo->exec('CREATE UNIQUE INDEX uq_users_uid ON users (user_uid)');
    } catch (Throwable $e) {
        // Already exists, or duplicate legacy null handling on old MySQL.
    }
    try {
        $pdo->exec('CREATE INDEX idx_users_status_role ON users (status, role)');
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec('CREATE UNIQUE INDEX uq_users_email ON users (email)');
    } catch (Throwable $e) {
    }
}

function vk_auth_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function vk_auth_user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function vk_auth_generate_password(int $length = 16): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*-_=+';
    $password = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }
    return $password;
}

function vk_auth_generate_user_uid(PDO $pdo): string
{
    do {
        $uid = 'VK-' . date('ym') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $st = $pdo->prepare('SELECT 1 FROM users WHERE user_uid = ? LIMIT 1');
        $st->execute([$uid]);
    } while ($st->fetchColumn());
    return $uid;
}

function vk_auth_generate_username(PDO $pdo, string $fullName): string
{
    $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '.', $fullName), '.'));
    if ($base === '') {
        $base = 'vk.user';
    }
    $base = substr($base, 0, 36);
    do {
        $username = $base . '.' . random_int(1000, 9999);
        $st = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $st->execute([$username]);
    } while ($st->fetchColumn());
    return $username;
}

function vk_auth_create_pending_user(PDO $pdo, array $data): array
{
    vk_auth_ensure_schema($pdo);
    $fullName = trim((string) ($data['fullname'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $phone = trim((string) ($data['phone'] ?? ''));
    $department = trim((string) ($data['department'] ?? ''));

    if ($fullName === '' || strlen($fullName) < 3) {
        throw new InvalidArgumentException('Enter your full name.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }
    if ($phone === '' || strlen($phone) < 6) {
        throw new InvalidArgumentException('Enter a valid phone number.');
    }
    if ($department === '') {
        throw new InvalidArgumentException('Select or enter your department.');
    }

    $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    $exists->execute([$email]);
    if ($exists->fetchColumn()) {
        throw new RuntimeException('A user with this email already exists.');
    }

    $username = vk_auth_generate_username($pdo, $fullName);
    $password = vk_auth_generate_password();
    $uid = vk_auth_generate_user_uid($pdo);
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $st = $pdo->prepare(
        "INSERT INTO users (username, email, phone, password_hash, fullname, department, user_uid, role, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'viewer', 'pending')"
    );
    $st->execute([$username, $email, $phone, $hash, $fullName, $department, $uid]);
    $userId = (int) $pdo->lastInsertId();

    vk_auth_record_approval($pdo, $userId, 'registered', null, null, 'pending', null, 'viewer', 'Self-service registration');
    vk_auth_activity($pdo, $userId, null, 'user_registered', 'user', $userId, ['email' => $email, 'department' => $department]);

    return [
        'id' => $userId,
        'user_uid' => $uid,
        'username' => $username,
        'password' => $password,
        'status' => 'pending',
    ];
}

function vk_auth_log_login(PDO $pdo, ?int $userId, string $username, string $status, ?string $reason = null): void
{
    vk_auth_ensure_schema($pdo);
    $st = $pdo->prepare(
        'INSERT INTO login_logs (user_id, username, ip_address, user_agent, status, failure_reason)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $st->execute([$userId, substr($username, 0, 150), vk_auth_client_ip(), vk_auth_user_agent(), $status, $reason]);
}

function vk_auth_too_many_attempts(PDO $pdo, string $username): bool
{
    vk_auth_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM login_logs
         WHERE status IN ('failed','blocked')
           AND created_at >= (NOW() - INTERVAL 15 MINUTE)
           AND (ip_address = ? OR username = ?)"
    );
    $st->execute([vk_auth_client_ip(), substr($username, 0, 150)]);
    return (int) $st->fetchColumn() >= 6;
}

function vk_auth_attempt_login(PDO $pdo, string $identity, string $password, bool $remember = false): array
{
    vk_auth_ensure_schema($pdo);
    $identity = trim($identity);
    if ($identity === '' || $password === '') {
        return ['ok' => false, 'message' => 'Enter username or email and password.'];
    }
    if (vk_auth_too_many_attempts($pdo, $identity)) {
        vk_auth_log_login($pdo, null, $identity, 'blocked', 'rate_limited');
        return ['ok' => false, 'message' => 'Too many sign-in attempts. Try again in 15 minutes.'];
    }

    $st = $pdo->prepare(
        'SELECT id, username, email, password_hash, fullname, role, technician_id, status
         FROM users WHERE username = ? OR email = ? LIMIT 1'
    );
    $st->execute([$identity, $identity]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($password, (string) $row['password_hash'])) {
        vk_auth_log_login($pdo, $row ? (int) $row['id'] : null, $identity, 'failed', 'invalid_credentials');
        return ['ok' => false, 'message' => 'Invalid credentials.'];
    }

    $status = (string) ($row['status'] ?? 'pending');
    if ($status !== 'active') {
        vk_auth_log_login($pdo, (int) $row['id'], (string) $row['username'], 'failed', 'status_' . $status);
        $message = match ($status) {
            'pending' => 'Your account is waiting for administrator approval.',
            'rejected' => 'This registration was not approved. Contact an administrator.',
            'suspended', 'inactive' => 'This account is not active. Contact an administrator.',
            default => 'This account is not available for sign-in.',
        };
        return ['ok' => false, 'message' => $message, 'status' => $status];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['user_role'] = (string) ($row['role'] ?? 'viewer');
    $_SESSION['technician_id'] = isset($row['technician_id']) && $row['technician_id'] !== null ? (int) $row['technician_id'] : null;
    $_SESSION['auth_last_seen'] = time();
    $_SESSION['auth_login_at'] = time();

    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);
    vk_auth_log_login($pdo, (int) $row['id'], (string) $row['username'], 'success');
    vk_auth_activity($pdo, (int) $row['id'], (int) $row['id'], 'login_success', 'user', (int) $row['id']);
    if ($remember) {
        vk_auth_set_remember_cookie($pdo, (int) $row['id']);
    }

    return ['ok' => true, 'user' => $row];
}

function vk_auth_set_remember_cookie(PDO $pdo, int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + (86400 * 30);
    $st = $pdo->prepare('INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, FROM_UNIXTIME(?))');
    $st->execute([$userId, $selector, password_hash($validator, PASSWORD_DEFAULT), $expires]);
    setcookie('vk_remember', $selector . ':' . $validator, [
        'expires' => $expires,
        'path' => BASE_URL !== '' ? BASE_URL : '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function vk_auth_clear_remember_cookie(PDO $pdo): void
{
    if (!empty($_COOKIE['vk_remember']) && is_string($_COOKIE['vk_remember'])) {
        [$selector] = array_pad(explode(':', $_COOKIE['vk_remember'], 2), 2, '');
        if ($selector !== '') {
            $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$selector]);
        }
    }
    setcookie('vk_remember', '', [
        'expires' => time() - 3600,
        'path' => BASE_URL !== '' ? BASE_URL : '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function vk_auth_try_remember(PDO $pdo): void
{
    if (!empty($_SESSION['user_id']) || empty($_COOKIE['vk_remember']) || !is_string($_COOKIE['vk_remember'])) {
        return;
    }
    vk_auth_ensure_schema($pdo);
    [$selector, $validator] = array_pad(explode(':', $_COOKIE['vk_remember'], 2), 2, '');
    if ($selector === '' || $validator === '') {
        return;
    }
    $st = $pdo->prepare(
        "SELECT rt.id, rt.user_id, rt.validator_hash, u.role, u.technician_id, u.status
         FROM remember_tokens rt JOIN users u ON u.id = rt.user_id
         WHERE rt.selector = ? AND rt.expires_at > NOW() LIMIT 1"
    );
    $st->execute([$selector]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify($validator, (string) $row['validator_hash']) || (string) $row['status'] !== 'active') {
        vk_auth_clear_remember_cookie($pdo);
        return;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['user_id'];
    $_SESSION['user_role'] = (string) $row['role'];
    $_SESSION['technician_id'] = $row['technician_id'] !== null ? (int) $row['technician_id'] : null;
    $_SESSION['auth_last_seen'] = time();
    $pdo->prepare('UPDATE remember_tokens SET last_used_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);
}

function vk_auth_enforce_session_timeout(int $seconds = 7200): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    $lastSeen = (int) ($_SESSION['auth_last_seen'] ?? time());
    if (time() - $lastSeen > $seconds) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_name(SESSION_NAME);
        session_start();
        flash_set('warning', 'Your session timed out. Please sign in again.');
        redirect('/login.php?timeout=1');
    }
    $_SESSION['auth_last_seen'] = time();
}

function vk_auth_role_label(string $role): string
{
    return match ($role) {
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'manager' => 'Manager',
        'technician' => 'Technician',
        'staff' => 'Staff',
        'viewer' => 'Viewer',
        default => ucfirst(str_replace('_', ' ', $role)),
    };
}

function vk_auth_status_label(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
}

function vk_auth_role_can_manage(string $role): bool
{
    return in_array($role, ['super_admin', 'admin'], true);
}

function vk_auth_record_approval(
    PDO $pdo,
    int $userId,
    string $action,
    ?int $actorId,
    ?string $fromStatus = null,
    ?string $toStatus = null,
    ?string $fromRole = null,
    ?string $toRole = null,
    ?string $note = null
): void {
    $st = $pdo->prepare(
        'INSERT INTO approvals (user_id, action, actor_id, from_status, to_status, from_role, to_role, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute([$userId, $action, $actorId, $fromStatus, $toStatus, $fromRole, $toRole, $note]);
}

function vk_auth_activity(PDO $pdo, ?int $userId, ?int $actorId, string $action, ?string $entityType = null, ?int $entityId = null, ?array $metadata = null): void
{
    $st = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, actor_id, action, entity_type, entity_id, ip_address, metadata)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute([
        $userId,
        $actorId,
        $action,
        $entityType,
        $entityId,
        vk_auth_client_ip(),
        $metadata ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
    ]);
}

function vk_auth_update_user_status(PDO $pdo, int $userId, string $status, ?int $actorId, ?string $note = null): void
{
    if (!in_array($status, VK_AUTH_STATUSES, true)) {
        throw new InvalidArgumentException('Invalid account status.');
    }
    $st = $pdo->prepare('SELECT status, role FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('User not found.');
    }
    $updates = 'status = ?, updated_at = NOW()';
    $params = [$status];
    if ($status === 'active') {
        $updates .= ', approved_by = ?, approved_at = NOW(), rejected_at = NULL';
        $params[] = $actorId;
    } elseif ($status === 'rejected') {
        $updates .= ', rejected_at = NOW()';
    }
    $params[] = $userId;
    $pdo->prepare("UPDATE users SET {$updates} WHERE id = ?")->execute($params);

    $action = match ($status) {
        'active' => 'approved',
        'rejected' => 'rejected',
        'suspended' => 'suspended',
        default => 'reactivated',
    };
    vk_auth_record_approval($pdo, $userId, $action, $actorId, (string) $user['status'], $status, (string) $user['role'], (string) $user['role'], $note);
    vk_auth_activity($pdo, $userId, $actorId, 'user_' . $action, 'user', $userId, ['status' => $status]);
}

function vk_auth_change_role(PDO $pdo, int $userId, string $role, ?int $actorId): void
{
    if (!in_array($role, VK_AUTH_ROLES, true)) {
        throw new InvalidArgumentException('Invalid role.');
    }
    $st = $pdo->prepare('SELECT role, status FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('User not found.');
    }
    $pdo->prepare('UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?')->execute([$role, $userId]);
    vk_auth_record_approval($pdo, $userId, 'role_changed', $actorId, (string) $user['status'], (string) $user['status'], (string) $user['role'], $role);
    vk_auth_activity($pdo, $userId, $actorId, 'user_role_changed', 'user', $userId, ['role' => $role]);
}

function vk_auth_admin_reset_password(PDO $pdo, int $userId, ?int $actorId): string
{
    $password = vk_auth_generate_password();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')->execute([$hash, $userId]);
    $pdo->prepare(
        'INSERT INTO password_resets (user_id, requested_by, expires_at, used_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY), NOW())'
    )->execute([$userId, $actorId]);
    vk_auth_record_approval($pdo, $userId, 'password_reset', $actorId);
    vk_auth_activity($pdo, $userId, $actorId, 'user_password_reset', 'user', $userId);
    return $password;
}
