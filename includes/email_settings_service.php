<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email_system.php';

function vk_email_settings_log(string $level, string $message, array $context = []): void
{
    $payload = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    error_log('[email_settings][' . $level . '] ' . $message . $payload);
}

/** @return array{can_access:bool,can_edit:bool,is_super_admin:bool,is_admin_readonly:bool} */
function vk_email_settings_permissions(string $role): array
{
    return match ($role) {
        'super_admin' => ['can_access' => true, 'can_edit' => true, 'is_super_admin' => true, 'is_admin_readonly' => false],
        'admin', 'owner' => ['can_access' => true, 'can_edit' => false, 'is_super_admin' => false, 'is_admin_readonly' => true],
        default => ['can_access' => false, 'can_edit' => false, 'is_super_admin' => false, 'is_admin_readonly' => false],
    };
}

/** @return array{can_access:bool,can_edit:bool,is_super_admin:bool,is_admin_readonly:bool} */
function vk_email_settings_require(): array
{
    require_admin();
    $role = (string) ($_SESSION['user_role'] ?? 'viewer');
    $perms = vk_email_settings_permissions($role);
    if (!$perms['can_access']) {
        flash_set('error', 'Access denied.');
        redirect('/dashboard.php');
    }
    return $perms;
}

/** @return array<string,mixed> */
function vk_email_settings_form_data(PDO $pdo): array
{
    vk_auth_ensure_schema($pdo);
    vk_email_tables_migrate($pdo);
    $smtp = vk_smtp_settings_get($pdo);
    $hasPassword = vk_smtp_get_stored_password_raw($pdo) !== ''
        || vk_smtp_env_value('VK_SMTP_PASS') !== null
        || vk_smtp_env_value('MAIL_PASSWORD') !== null;

    return [
        'smtp_host' => (string) ($smtp['smtp_host'] ?? ''),
        'smtp_port' => (int) ($smtp['smtp_port'] ?? 587),
        'smtp_username' => (string) ($smtp['smtp_user'] ?? ''),
        'smtp_secure' => (string) ($smtp['smtp_secure'] ?? 'tls'),
        'email_from' => (string) ($smtp['from_email'] ?? ''),
        'from_name' => (string) ($smtp['from_name'] ?? ''),
        'reply_to_email' => (string) vk_settings_get($pdo, 'reply_to_email', ''),
        'smtp_timeout' => max(5, min(120, (int) vk_settings_get($pdo, 'smtp_timeout', '30'))),
        'charset' => (string) vk_settings_get($pdo, 'smtp_charset', 'UTF-8'),
        'password_configured' => $hasPassword,
        'configured' => (bool) ($smtp['configured'] ?? false),
    ];
}

/** @param array<string,mixed> $data @return array{ok:bool,error?:string,errors?:array<string,string>} */
function vk_email_settings_validate(array $data): array
{
    $errors = [];
    $host = trim((string) ($data['smtp_host'] ?? ''));
    $port = (int) ($data['smtp_port'] ?? 0);
    $from = trim((string) ($data['email_from'] ?? $data['from_email'] ?? ''));
    $reply = trim((string) ($data['reply_to_email'] ?? ''));
    $user = trim((string) ($data['smtp_username'] ?? $data['smtp_user'] ?? ''));
    $secure = strtolower(trim((string) ($data['smtp_secure'] ?? 'tls')));

    if ($host === '') {
        $errors['smtp_host'] = 'SMTP host is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $host) && !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $errors['smtp_host'] = 'Invalid SMTP host.';
    }
    if ($port < 1 || $port > 65535) {
        $errors['smtp_port'] = 'Port must be between 1 and 65535.';
    }
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $errors['email_from'] = 'Valid sender email is required.';
    }
    if ($reply !== '' && !filter_var($reply, FILTER_VALIDATE_EMAIL)) {
        $errors['reply_to_email'] = 'Invalid reply-to email.';
    }
    if (!in_array($secure, ['tls', 'ssl', 'none'], true)) {
        $errors['smtp_secure'] = 'Encryption must be TLS, SSL, or None.';
    }
    if ($user !== '' && preg_match('/[\x00-\x1F\x7F]/', $user)) {
        $errors['smtp_username'] = 'Invalid characters in username.';
    }

    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors, 'error' => 'Validation failed.'];
    }
    return ['ok' => true];
}

/** @param array<string,mixed> $data @return array{ok:bool,error?:string,message?:string} */
function vk_email_settings_save(PDO $pdo, array $data, int $actorId): array
{
    $validation = vk_email_settings_validate($data);
    if (!$validation['ok']) {
        return $validation;
    }

    $before = vk_email_settings_form_data($pdo);

    try {
        $pdo->beginTransaction();

        vk_smtp_settings_save($pdo, [
            'smtp_host' => trim((string) ($data['smtp_host'] ?? '')),
            'smtp_port' => (int) ($data['smtp_port'] ?? 587),
            'smtp_user' => trim((string) ($data['smtp_username'] ?? '')),
            'smtp_pass' => trim((string) ($data['smtp_password'] ?? '')),
            'smtp_secure' => (string) ($data['smtp_secure'] ?? 'tls'),
            'from_email' => trim((string) ($data['email_from'] ?? '')),
            'from_name' => trim((string) ($data['from_name'] ?? '')),
        ]);

        vk_settings_set($pdo, 'reply_to_email', trim((string) ($data['reply_to_email'] ?? '')));
        vk_settings_set($pdo, 'smtp_timeout', (string) max(5, min(120, (int) ($data['smtp_timeout'] ?? 30))));
        vk_settings_set($pdo, 'smtp_charset', trim((string) ($data['charset'] ?? 'UTF-8')) ?: 'UTF-8');

        vk_settings_audit($pdo, 'email_settings_save', 'smtp', '[smtp bundle]');
        if (function_exists('vk_auth_activity')) {
            vk_auth_activity($pdo, $actorId, $actorId, 'email_settings_updated', 'settings', 1, [
                'host' => $data['smtp_host'] ?? '',
                'ip' => vk_auth_client_ip(),
                'user_agent' => vk_auth_user_agent(),
            ]);
        }

        $pdo->commit();
        vk_email_settings_log('info', 'SMTP settings saved', ['actor' => $actorId]);

        return ['ok' => true, 'message' => 'SMTP settings saved successfully.', 'before' => $before, 'after' => vk_email_settings_form_data($pdo)];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        vk_email_settings_log('error', $e->getMessage(), ['actor' => $actorId]);
        return ['ok' => false, 'error' => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Unable to save SMTP settings.'];
    }
}

/** @return array{ok:bool,steps:array<int,array<string,string>>,error?:string} */
function vk_email_settings_connection_test(PDO $pdo, ?array $override = null): array
{
    require_once __DIR__ . '/email_auto_validation.php';

    $form = $override ?? vk_email_settings_form_data($pdo);
    $pass = trim((string) ($override['smtp_password'] ?? ''));
    if ($pass === '') {
        $pass = vk_smtp_get_stored_password_only($pdo);
    }

    $cfg = [
        'smtp_host' => trim((string) ($form['smtp_host'] ?? '')),
        'smtp_port' => (int) ($form['smtp_port'] ?? 587),
        'smtp_user' => trim((string) ($form['smtp_username'] ?? '')),
        'smtp_pass' => $pass,
        'smtp_secure' => (string) ($form['smtp_secure'] ?? 'tls'),
    ];

    $steps = [];
    $steps[] = ['label' => 'DNS resolution', 'status' => 'running'];
    $resolved = gethostbyname($cfg['smtp_host']);
    if ($resolved === $cfg['smtp_host'] && !filter_var($cfg['smtp_host'], FILTER_VALIDATE_IP)) {
        $steps[0] = ['label' => 'DNS resolution', 'status' => 'failed', 'detail' => 'Host not found'];
        return ['ok' => false, 'steps' => $steps, 'error' => 'Host not found'];
    }
    $steps[0] = ['label' => 'DNS resolution', 'status' => 'success', 'detail' => $resolved];

    $steps[] = ['label' => 'Connecting', 'status' => 'running'];
    $probe = vk_email_probe_smtp($cfg);
    if (!($probe['ok'] ?? false)) {
        $steps[1] = ['label' => 'Connecting', 'status' => 'failed', 'detail' => (string) ($probe['error'] ?? 'Connection failed')];
        return ['ok' => false, 'steps' => $steps, 'error' => vk_email_settings_friendly_error((string) ($probe['error'] ?? ''))];
    }
    $steps[1] = ['label' => 'Authenticating', 'status' => 'success', 'detail' => (string) ($probe['profile'] ?? 'OK')];

    return ['ok' => true, 'steps' => $steps, 'message' => 'Connection successful'];
}

function vk_email_settings_friendly_error(string $raw): string
{
    $l = strtolower($raw);
    if (str_contains($l, 'authenticate') || str_contains($l, 'authentication')) {
        return 'SMTP authentication failed. Check username and password.';
    }
    if (str_contains($l, 'timed out') || str_contains($l, 'timeout')) {
        return 'Connection timeout. Verify host, port, and firewall.';
    }
    if (str_contains($l, 'certificate') || str_contains($l, 'ssl')) {
        return 'SSL/TLS certificate error. Try TLS on port 587 or enable relaxed SSL.';
    }
    if (str_contains($l, 'connect')) {
        return 'Could not connect to SMTP server.';
    }
    return $raw !== '' ? $raw : 'SMTP connection failed';
}

/** @return array<int,array<string,string>> */
function vk_email_settings_templates(): array
{
    return [
        ['key' => 'registration', 'name' => 'User Registration', 'subject' => 'Welcome to VK Network'],
        ['key' => 'account_approved', 'name' => 'Account Approval', 'subject' => 'Your account has been approved'],
        ['key' => 'password_reset', 'name' => 'Password Reset', 'subject' => 'Reset your password'],
        ['key' => 'otp', 'name' => 'OTP Verification', 'subject' => 'Your verification code'],
        ['key' => 'invoice', 'name' => 'Invoice', 'subject' => 'Invoice from VK Network'],
        ['key' => 'order_confirmation', 'name' => 'Order Confirmation', 'subject' => 'Order confirmed'],
        ['key' => 'contact_form', 'name' => 'Contact Form', 'subject' => 'New contact message'],
        ['key' => 'newsletter', 'name' => 'Newsletter', 'subject' => 'VK Network Newsletter'],
    ];
}

function vk_email_settings_template_html(string $key, string $company = 'VK Network'): string
{
    $titles = [
        'registration' => 'Welcome aboard',
        'account_approved' => 'Account approved',
        'password_reset' => 'Password reset',
        'otp' => 'Verification code',
        'invoice' => 'Your invoice',
        'order_confirmation' => 'Order confirmed',
        'contact_form' => 'Message received',
        'newsletter' => 'Latest updates',
    ];
    $title = $titles[$key] ?? 'Notification';
    $body = '<p>This is a preview of the <strong>' . e($title) . '</strong> template.</p>'
        . '<p style="margin:24px 0;"><a href="#" style="display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;">Call to action</a></p>'
        . '<p style="color:#64748b;font-size:14px;">Responsive layout · UTF-8 · ' . e($company) . '</p>';

    if (function_exists('vk_auth_email_shell')) {
        return vk_auth_email_shell($title, $body);
    }
    return '<!doctype html><html><body style="font-family:sans-serif;padding:24px;">' . $body . '</body></html>';
}

/** @return array{ok:bool,error?:string,data?:array<string,mixed>} */
function vk_email_settings_export(PDO $pdo): array
{
    $data = vk_email_settings_form_data($pdo);
    unset($data['password_configured']);
    $data['exported_at'] = date(DATE_ATOM);
    $data['smtp_password'] = '[redacted]';
    return ['ok' => true, 'data' => $data];
}

/** @return array{ok:bool,error?:string,message?:string} */
function vk_email_settings_import(PDO $pdo, array $data, int $actorId): array
{
    unset($data['exported_at'], $data['password_configured'], $data['smtp_password']);
    return vk_email_settings_save($pdo, $data, $actorId);
}

/** @return array{ok:bool,message?:string} */
function vk_email_settings_restore_defaults(PDO $pdo, int $actorId): array
{
    $defaults = [
        'smtp_host' => (string) vk_settings_get($pdo, 'smtp_host', ''),
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_secure' => 'tls',
        'email_from' => (string) vk_settings_get($pdo, 'support_email', ''),
        'from_name' => (string) vk_settings_get($pdo, 'company_name', 'VK Network'),
        'reply_to_email' => '',
        'smtp_timeout' => 30,
        'charset' => 'UTF-8',
    ];
    vk_settings_audit($pdo, 'email_settings_restore', 'smtp', 'defaults');
    return vk_email_settings_save($pdo, $defaults, $actorId);
}

/** @return list<array<string,mixed>> */
function vk_email_settings_send_log(PDO $pdo, int $limit = 75): array
{
    vk_email_tables_migrate($pdo);
    if (!db_table_exists($pdo, 'email_send_log')) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT id, template_type, to_email, subject, status, attempts, error_message, created_at, sent_at
         FROM email_send_log ORDER BY id DESC LIMIT ?'
    );
    $st->bindValue(1, max(1, min(200, $limit)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
