<?php
declare(strict_types=1);

const VK_AUTH_ROLES = ['super_admin', 'admin', 'manager', 'technician', 'staff', 'viewer'];
const VK_AUTH_STATUSES = ['pending', 'approved', 'active', 'rejected', 'suspended', 'inactive'];

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
                status ENUM('pending','approved','active','rejected','suspended','inactive') NOT NULL DEFAULT 'pending',
                approved TINYINT(1) NOT NULL DEFAULT 0,
                approved_by INT UNSIGNED DEFAULT NULL,
                approved_at DATETIME DEFAULT NULL,
                rejected_at DATETIME DEFAULT NULL,
                last_login_at DATETIME DEFAULT NULL,
                registration_ip VARCHAR(45) DEFAULT NULL,
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
        "CREATE TABLE IF NOT EXISTS email_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED DEFAULT NULL,
            recipient VARCHAR(191) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            template VARCHAR(96) DEFAULT NULL,
            status ENUM('sent','failed','skipped') NOT NULL,
            error_message TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_email_logs_user (user_id, created_at),
            KEY idx_email_logs_status (status, created_at)
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
        'approved' => "ALTER TABLE users ADD COLUMN approved TINYINT(1) NOT NULL DEFAULT 0 AFTER status",
        'approved_by' => "ALTER TABLE users ADD COLUMN approved_by INT UNSIGNED NULL DEFAULT NULL AFTER status",
        'approved_at' => "ALTER TABLE users ADD COLUMN approved_at DATETIME NULL DEFAULT NULL AFTER approved_by",
        'rejected_at' => "ALTER TABLE users ADD COLUMN rejected_at DATETIME NULL DEFAULT NULL AFTER approved_at",
        'last_login_at' => "ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL DEFAULT NULL AFTER rejected_at",
        'registration_ip' => "ALTER TABLE users ADD COLUMN registration_ip VARCHAR(45) NULL DEFAULT NULL AFTER last_login_at",
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
        "ALTER TABLE users MODIFY COLUMN status ENUM('pending','approved','active','rejected','suspended','inactive') NOT NULL DEFAULT 'pending'"
    );
    $pdo->exec("UPDATE users SET approved = 1 WHERE status IN ('approved','active')");

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

function vk_auth_status_is_approved(string $status): bool
{
    return in_array($status, ['approved', 'active'], true);
}

/** Cache authenticated user profile in session to avoid repeated DB reads. */
function vk_auth_cache_user(array $user): void
{
    $uid = (int) ($user['id'] ?? $user['user_id'] ?? 0);
    if ($uid < 1) {
        return;
    }
    $_SESSION['user_id'] = $uid;
    $_SESSION['user_role'] = (string) ($user['role'] ?? 'viewer');
    $_SESSION['user_status'] = (string) ($user['status'] ?? 'approved');
    $_SESSION['technician_id'] = isset($user['technician_id']) && $user['technician_id'] !== null
        ? (int) $user['technician_id']
        : null;
    $_SESSION['_user_cache'] = [
        'id' => $uid,
        'username' => (string) ($user['username'] ?? ''),
        'fullname' => (string) ($user['fullname'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'phone' => (string) ($user['phone'] ?? ''),
        'role' => (string) ($user['role'] ?? 'viewer'),
        'status' => (string) ($user['status'] ?? 'approved'),
        'technician_id' => isset($user['technician_id']) && $user['technician_id'] !== null
            ? (int) $user['technician_id']
            : null,
        'department' => (string) ($user['department'] ?? ''),
        'user_uid' => (string) ($user['user_uid'] ?? ''),
        'last_login_at' => (string) ($user['last_login_at'] ?? ''),
    ];
    $_SESSION['_user_cache_at'] = time();
}

function vk_auth_cached_user(): ?array
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['_user_cache']) || !is_array($_SESSION['_user_cache'])) {
        return null;
    }

    return $_SESSION['_user_cache'];
}

function vk_auth_invalidate_user_cache(): void
{
    unset($_SESSION['_user_cache'], $_SESSION['_user_cache_at'], $_SESSION['user_status']);
}

function vk_auth_user_cache_fresh(int $ttlSeconds = 300): bool
{
    $at = (int) ($_SESSION['_user_cache_at'] ?? 0);

    return !empty($_SESSION['_user_cache'])
        && is_array($_SESSION['_user_cache'])
        && $at > 0
        && (time() - $at) < $ttlSeconds;
}

function vk_auth_load_user_cache(PDO $pdo, int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT id, username, fullname, email, phone, role, technician_id, status, department, user_uid, last_login_at
         FROM users WHERE id = ? LIMIT 1'
    );
    $st->execute([$userId]);
    vk_perf_mark_query();
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    vk_auth_cache_user($row);

    return $_SESSION['_user_cache'];
}

function vk_auth_create_pending_user(PDO $pdo, array $data): array
{
    vk_auth_ensure_schema($pdo);
    $fullName = trim(strip_tags((string) ($data['fullname'] ?? '')));
    $email = strtolower(trim((string) filter_var((string) ($data['email'] ?? ''), FILTER_SANITIZE_EMAIL)));
    $phone = trim(preg_replace('/[^\d+\-\s()]/', '', (string) ($data['phone'] ?? '')));
    $department = trim(strip_tags((string) ($data['department'] ?? '')));

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

    $pdo->beginTransaction();
    try {
        $username = vk_auth_generate_username($pdo, $fullName);
        $password = vk_auth_generate_password();
        $uid = vk_auth_generate_user_uid($pdo);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $st = $pdo->prepare(
            "INSERT INTO users
                (username, email, phone, password_hash, fullname, department, user_uid, role, status, approved, registration_ip)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, 'viewer', 'pending', 0, ?)"
        );
        $st->execute([$username, $email, $phone, $hash, $fullName, $department, $uid, vk_auth_client_ip()]);
        $userId = (int) $pdo->lastInsertId();

        vk_auth_record_approval($pdo, $userId, 'registered', null, null, 'pending', null, 'viewer', 'Self-service registration');
        vk_auth_activity($pdo, $userId, null, 'user_registered', 'user', $userId, [
            'email' => $email,
            'department' => $department,
            'ip' => vk_auth_client_ip(),
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    vk_auth_notify_admin_registration($pdo, [
        'id' => $userId,
        'fullname' => $fullName,
        'email' => $email,
        'username' => $username,
        'department' => $department,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

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
        'SELECT id, username, email, phone, password_hash, fullname, role, technician_id, status, department, user_uid, last_login_at
         FROM users WHERE username = ? OR email = ? LIMIT 1'
    );
    $st->execute([$identity, $identity]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($password, (string) $row['password_hash'])) {
        vk_auth_log_login($pdo, $row ? (int) $row['id'] : null, $identity, 'failed', 'invalid_credentials');
        return ['ok' => false, 'message' => 'Invalid credentials.'];
    }

    $status = (string) ($row['status'] ?? 'pending');
    if (!vk_auth_status_is_approved($status)) {
        vk_auth_log_login($pdo, (int) $row['id'], (string) $row['username'], 'failed', 'status_' . $status);
        $message = match ($status) {
            'pending' => 'Your account is awaiting administrator approval.',
            'rejected' => 'This registration was not approved. Contact an administrator.',
            'suspended', 'inactive' => 'This account is not active. Contact an administrator.',
            default => 'This account is not available for sign-in.',
        };
        return ['ok' => false, 'message' => $message, 'status' => $status];
    }

    session_regenerate_id(true);
    vk_auth_cache_user($row);
    $_SESSION['auth_last_seen'] = time();
    $_SESSION['auth_login_at'] = time();

    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);
    vk_perf_mark_query();
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
        "SELECT rt.id, rt.user_id, rt.validator_hash, u.id, u.username, u.fullname, u.email, u.phone,
                u.role, u.technician_id, u.status, u.department, u.user_uid, u.last_login_at
         FROM remember_tokens rt JOIN users u ON u.id = rt.user_id
         WHERE rt.selector = ? AND rt.expires_at > NOW() LIMIT 1"
    );
    $st->execute([$selector]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify($validator, (string) $row['validator_hash']) || !vk_auth_status_is_approved((string) $row['status'])) {
        vk_auth_clear_remember_cookie($pdo);
        return;
    }
    session_regenerate_id(true);
    vk_auth_cache_user($row);
    $_SESSION['auth_last_seen'] = time();
    $pdo->prepare('UPDATE remember_tokens SET last_used_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);
    vk_perf_mark_query();
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
    if (vk_auth_status_is_approved($status)) {
        return 'Approved';
    }
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
    if (vk_auth_status_is_approved($status)) {
        $updates .= ', approved = 1, approved_by = ?, approved_at = NOW(), rejected_at = NULL';
        $params[] = $actorId;
    } elseif ($status === 'rejected') {
        $updates .= ', approved = 0, rejected_at = NOW()';
    } else {
        $updates .= ', approved = 0';
    }
    $params[] = $userId;
    $pdo->prepare("UPDATE users SET {$updates} WHERE id = ?")->execute($params);

    $action = match ($status) {
        'approved', 'active' => 'approved',
        'rejected' => 'rejected',
        'suspended' => 'suspended',
        default => 'reactivated',
    };
    vk_auth_record_approval($pdo, $userId, $action, $actorId, (string) $user['status'], $status, (string) $user['role'], (string) $user['role'], $note);
    vk_auth_activity($pdo, $userId, $actorId, 'user_' . $action, 'user', $userId, ['status' => $status]);
    if (vk_auth_status_is_approved($status)) {
        vk_auth_notify_user_approved($pdo, $userId);
    }
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

function vk_auth_email_log(PDO $pdo, ?int $userId, string $recipient, string $subject, string $template, string $status, ?string $error = null): void
{
    try {
        $st = $pdo->prepare(
            'INSERT INTO email_logs (user_id, recipient, subject, template, status, error_message)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$userId, $recipient, $subject, $template, $status, $error]);
    } catch (Throwable $e) {
        error_log('email log failed: ' . $e->getMessage());
    }
}

function vk_auth_admin_recipients(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT email, fullname FROM users
         WHERE role IN ('super_admin','admin') AND status IN ('approved','active') AND approved = 1
           AND email IS NOT NULL AND email <> ''
         ORDER BY role = 'super_admin' DESC, id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    $recipients = [];
    foreach ($rows as $row) {
        if (filter_var((string) $row['email'], FILTER_VALIDATE_EMAIL)) {
            $recipients[] = ['email' => (string) $row['email'], 'name' => (string) ($row['fullname'] ?? '')];
        }
    }
    $fallback = getenv('VK_ADMIN_EMAIL') ?: getenv('MAIL_ADMIN_EMAIL') ?: '';
    if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
        $recipients[] = ['email' => $fallback, 'name' => 'VK Network Admin'];
    }
    if (!$recipients && function_exists('vk_smtp_settings_get')) {
        try {
            $cfg = vk_smtp_settings_get($pdo);
            if (!empty($cfg['from_email']) && filter_var((string) $cfg['from_email'], FILTER_VALIDATE_EMAIL)) {
                $recipients[] = ['email' => (string) $cfg['from_email'], 'name' => (string) ($cfg['from_name'] ?: 'VK Network Admin')];
            }
        } catch (Throwable $e) {
        }
    }
    return $recipients;
}

function vk_auth_email_shell(string $title, string $body): string
{
    return '<!doctype html><html><body style="margin:0;background:#07111f;font-family:Inter,Segoe UI,Arial,sans-serif;color:#eaf2ff;">'
        . '<div style="padding:28px;background:linear-gradient(135deg,#07111f,#0d1f3a);">'
        . '<div style="max-width:640px;margin:auto;border:1px solid rgba(103,232,249,.25);border-radius:22px;background:rgba(13,22,42,.94);overflow:hidden;">'
        . '<div style="padding:24px 28px;border-bottom:1px solid rgba(103,232,249,.18);"><div style="font-size:13px;color:#67e8f9;text-transform:uppercase;font-weight:700;">VK Network Security</div>'
        . '<h1 style="margin:8px 0 0;font-size:24px;color:#fff;">' . e($title) . '</h1></div>'
        . '<div style="padding:28px;color:#c8d6f3;line-height:1.65;">' . $body . '</div>'
        . '<div style="padding:18px 28px;color:#7890b8;font-size:12px;border-top:1px solid rgba(103,232,249,.14);">This is an automated enterprise authentication notice.</div>'
        . '</div></div></body></html>';
}

function vk_auth_notify_admin_registration(PDO $pdo, array $user): void
{
    $subject = 'New User Registration Pending Approval';
    $approvalUrl = base_url('approve_users.php');
    $bodyText = "New user registration pending approval\n\n"
        . "Full Name: {$user['fullname']}\nEmail: {$user['email']}\nUsername: {$user['username']}\nDepartment: {$user['department']}\nRegistration Time: {$user['created_at']}\nApproval URL: {$approvalUrl}\n";
    $html = vk_auth_email_shell($subject,
        '<p>A new user registration is waiting for administrator approval.</p>'
        . '<table style="width:100%;border-collapse:collapse;">'
        . '<tr><td style="padding:8px;color:#8fb9ff;">Full Name</td><td style="padding:8px;color:#fff;">' . e((string) $user['fullname']) . '</td></tr>'
        . '<tr><td style="padding:8px;color:#8fb9ff;">Email</td><td style="padding:8px;color:#fff;">' . e((string) $user['email']) . '</td></tr>'
        . '<tr><td style="padding:8px;color:#8fb9ff;">Username</td><td style="padding:8px;color:#fff;">' . e((string) $user['username']) . '</td></tr>'
        . '<tr><td style="padding:8px;color:#8fb9ff;">Department</td><td style="padding:8px;color:#fff;">' . e((string) $user['department']) . '</td></tr>'
        . '<tr><td style="padding:8px;color:#8fb9ff;">Registration Time</td><td style="padding:8px;color:#fff;">' . e((string) $user['created_at']) . '</td></tr>'
        . '</table><p><a href="' . e($approvalUrl) . '" style="display:inline-block;padding:12px 18px;border-radius:14px;background:#2f80ff;color:#fff;text-decoration:none;font-weight:700;">Open Approval Center</a></p>'
    );
    $recipients = vk_auth_admin_recipients($pdo);
    if (!$recipients) {
        vk_auth_email_log($pdo, (int) $user['id'], 'admin', $subject, 'registration_admin', 'skipped', 'No admin recipient configured');
        return;
    }
    foreach ($recipients as $recipient) {
        $result = function_exists('vk_mailer_send')
            ? vk_mailer_send($pdo, $recipient['email'], $subject, $bodyText, $recipient['name'], ['html' => true, 'html_body' => $html, 'template_type' => 'registration_admin', 'max_retries' => 1, 'smtp_timeout' => 8])
            : ['ok' => false, 'error' => 'Mailer unavailable'];
        vk_auth_email_log($pdo, (int) $user['id'], $recipient['email'], $subject, 'registration_admin', !empty($result['ok']) ? 'sent' : 'failed', $result['error'] ?? null);
    }
}

function vk_auth_notify_user_approved(PDO $pdo, int $userId): void
{
    $st = $pdo->prepare('SELECT username, email, fullname FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    if (!$user || !filter_var((string) ($user['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $subject = 'Your VK Network Account Has Been Approved';
    $loginUrl = base_url('login.php');
    $bodyText = "Your VK Network account has been approved.\n\nUsername: {$user['username']}\nLogin URL: {$loginUrl}\n\nUse the temporary password shown during registration or request a reset from an administrator. Change your password after signing in.";
    $html = vk_auth_email_shell($subject,
        '<p>Your VK Network account has been approved and is ready for secure sign-in.</p>'
        . '<p><strong style="color:#8fb9ff;">Username:</strong> <span style="color:#fff;">' . e((string) $user['username']) . '</span></p>'
        . '<p>Use the temporary password shown during registration. If you did not save it, request a reset from an administrator.</p>'
        . '<p>For security, change your password after your first successful login and never share credentials.</p>'
        . '<p><a href="' . e($loginUrl) . '" style="display:inline-block;padding:12px 18px;border-radius:14px;background:#2f80ff;color:#fff;text-decoration:none;font-weight:700;">Open Secure Login</a></p>'
    );
    $result = function_exists('vk_mailer_send')
        ? vk_mailer_send($pdo, (string) $user['email'], $subject, $bodyText, (string) ($user['fullname'] ?? ''), ['html' => true, 'html_body' => $html, 'template_type' => 'account_approved', 'max_retries' => 1, 'smtp_timeout' => 8])
        : ['ok' => false, 'error' => 'Mailer unavailable'];
    vk_auth_email_log($pdo, $userId, (string) $user['email'], $subject, 'account_approved', !empty($result['ok']) ? 'sent' : 'failed', $result['error'] ?? null);
}
