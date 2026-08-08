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
        'admin', 'owner' => ['can_access' => true, 'can_edit' => true, 'is_super_admin' => false, 'is_admin_readonly' => false],
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
        'company_name' => (string) vk_settings_get($pdo, 'company_name', 'VK Network'),
        'reply_to_email' => (string) vk_settings_get($pdo, 'reply_to_email', ''),
        'smtp_timeout' => max(5, min(120, (int) vk_settings_get($pdo, 'smtp_timeout', '30'))),
        'charset' => (string) vk_settings_get($pdo, 'smtp_charset', 'UTF-8'),
        'smtp_auth' => vk_settings_get($pdo, 'smtp_auth_enabled', '1') !== '0',
        'smtp_debug' => vk_settings_get($pdo, 'smtp_debug_mode', '0') === '1',
        'email_signature' => (string) vk_settings_get($pdo, 'email_default_signature', ''),
        'queue_max_retries' => max(1, min(10, (int) vk_settings_get($pdo, 'email_queue_max_retries', '5'))),
        'queue_retry_interval' => max(30, min(3600, (int) vk_settings_get($pdo, 'email_queue_retry_interval', '60'))),
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
        vk_settings_set($pdo, 'smtp_auth_enabled', empty($data['smtp_auth']) || $data['smtp_auth'] === '0' || $data['smtp_auth'] === false ? '0' : '1');
        vk_settings_set($pdo, 'smtp_debug_mode', !empty($data['smtp_debug']) && $data['smtp_debug'] !== '0' ? '1' : '0');
        vk_settings_set($pdo, 'email_default_signature', trim((string) ($data['email_signature'] ?? '')));
        if (isset($data['company_name']) && trim((string) $data['company_name']) !== '') {
            vk_settings_set($pdo, 'company_name', trim((string) $data['company_name']));
        }
        if (isset($data['queue_max_retries'])) {
            vk_settings_set($pdo, 'email_queue_max_retries', (string) max(1, min(10, (int) $data['queue_max_retries'])));
        }
        if (isset($data['queue_retry_interval'])) {
            vk_settings_set($pdo, 'email_queue_retry_interval', (string) max(30, min(3600, (int) $data['queue_retry_interval'])));
        }

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

/** @return array{ok:bool,steps:array<int,array<string,mixed>>,error?:string,message?:string,reasons?:list<string>,ms?:int,greeting?:string,transcript?:string,report?:array<string,mixed>} */
function vk_email_settings_connection_test(PDO $pdo, ?array $override = null): array
{
    require_once __DIR__ . '/email_auto_validation.php';

    $effective = vk_smtp_settings_get($pdo);
    $form = $override ?? vk_email_settings_form_data($pdo);

    // Same credential source as vk_mailer_send, unless the admin typed a new password in the form.
    $pass = trim((string) ($override['smtp_password'] ?? ''));
    if ($pass === '') {
        $pass = (string) ($effective['smtp_pass'] ?? '');
    }

    $host = trim((string) ($override['smtp_host'] ?? $form['smtp_host'] ?? $effective['smtp_host'] ?? ''));
    $port = (int) ($override['smtp_port'] ?? $form['smtp_port'] ?? $effective['smtp_port'] ?? 587);
    $user = trim((string) ($override['smtp_username'] ?? $form['smtp_username'] ?? $effective['smtp_user'] ?? ''));
    $secure = strtolower((string) ($override['smtp_secure'] ?? $form['smtp_secure'] ?? $effective['smtp_secure'] ?? 'tls'));
    $authEnabled = !isset($form['smtp_auth']) || $form['smtp_auth'] === true || $form['smtp_auth'] === '1' || $form['smtp_auth'] === 1;
    if (isset($override['smtp_auth'])) {
        $authEnabled = $override['smtp_auth'] === true || $override['smtp_auth'] === '1' || $override['smtp_auth'] === 1;
    }
    if ($user === '') {
        $authEnabled = false;
    }
    $from = trim((string) ($override['email_from'] ?? $form['email_from'] ?? $effective['from_email'] ?? ''));
    $reply = trim((string) ($override['reply_to_email'] ?? $form['reply_to_email'] ?? ''));
    $timeout = max(5, min(60, (int) ($override['smtp_timeout'] ?? $form['smtp_timeout'] ?? 30)));

    // Auto-correct common Hostinger/cPanel misconfig: apex host works for TCP but MX recommends mail.
    if ($host === 'vkitnet.info') {
        $host = 'mail.vkitnet.info';
    }
    if ($secure === 'tls' && $port === 465) {
        $secure = 'ssl';
    } elseif ($secure === 'ssl' && $port === 587) {
        $secure = 'tls';
    }

    $steps = [];
    $t0 = microtime(true);

    // 1) Validate From / Reply-To
    $steps[] = ['label' => 'Sender / Reply-To validation', 'status' => 'running'];
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $steps[count($steps) - 1] = ['label' => 'Sender / Reply-To validation', 'status' => 'failed', 'detail' => 'Invalid From email'];
        return vk_email_settings_diag_fail($steps, 'Invalid From email address.', ['Sender Email is missing or invalid'], $t0);
    }
    if ($reply !== '' && !filter_var($reply, FILTER_VALIDATE_EMAIL)) {
        $steps[count($steps) - 1] = ['label' => 'Sender / Reply-To validation', 'status' => 'failed', 'detail' => 'Invalid Reply-To'];
        return vk_email_settings_diag_fail($steps, 'Invalid Reply-To email address.', ['Reply-To must be a valid email or left blank'], $t0);
    }
    $steps[count($steps) - 1] = ['label' => 'Sender / Reply-To validation', 'status' => 'success', 'detail' => $from];

    // 2) DNS
    $steps[] = ['label' => 'DNS Lookup', 'status' => 'running'];
    if ($host === '') {
        $steps[count($steps) - 1] = ['label' => 'DNS Lookup', 'status' => 'failed', 'detail' => 'Host empty'];
        return vk_email_settings_diag_fail($steps, 'SMTP host is required.', ['Wrong SMTP Host'], $t0);
    }
    $resolved = @gethostbyname($host);
    if ($resolved === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
        $steps[count($steps) - 1] = ['label' => 'DNS Lookup', 'status' => 'failed', 'detail' => 'Host not found'];
        return vk_email_settings_diag_fail($steps, 'DNS lookup failed for SMTP host.', ['Wrong SMTP Host', 'DNS problems'], $t0);
    }
    $steps[count($steps) - 1] = ['label' => 'DNS Lookup', 'status' => 'success', 'detail' => $resolved];

    // 3) TCP / port connectivity
    $steps[] = ['label' => 'SMTP Connection (TCP)', 'status' => 'running'];
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen(($secure === 'ssl' ? 'ssl://' : '') . (filter_var($host, FILTER_VALIDATE_IP) ? $host : $resolved), $port, $errno, $errstr, min(12, $timeout));
    if ($fp === false && $secure === 'ssl') {
        // Plain TCP probe when SSL wrapper fails (common on local WAMP)
        $fp = @fsockopen($host, $port, $errno, $errstr, min(12, $timeout));
    }
    if ($fp === false) {
        $steps[count($steps) - 1] = ['label' => 'SMTP Connection (TCP)', 'status' => 'failed', 'detail' => trim($errstr !== '' ? $errstr : 'Port ' . $port . ' unreachable')];
        return vk_email_settings_diag_fail(
            $steps,
            'Cannot open TCP connection to SMTP server.',
            ['Wrong SMTP Port', 'Firewall blocking SMTP', 'Internet Connection / Port Connectivity', 'SSL/TLS mismatch'],
            $t0
        );
    }
    stream_set_timeout($fp, min(8, $timeout));
    $greeting = (string) @fgets($fp, 512);
    @fclose($fp);
    $steps[count($steps) - 1] = [
        'label' => 'SMTP Connection (TCP)',
        'status' => 'success',
        'detail' => 'Port ' . $port . ' open',
    ];

    // 4) Server greeting
    $steps[] = ['label' => 'Server Greeting', 'status' => 'running'];
    $greetOk = $greeting !== '' && (str_starts_with(trim($greeting), '220') || str_contains(strtolower($greeting), 'smtp') || str_contains(strtolower($greeting), 'esmtp'));
    $steps[count($steps) - 1] = [
        'label' => 'Server Greeting',
        'status' => $greetOk || $greeting === '' ? 'success' : 'failed',
        'detail' => $greeting !== '' ? trim($greeting) : 'Connected (greeting captured during AUTH probe)',
    ];

    // 5) Encryption profile
    $steps[] = ['label' => 'Encryption', 'status' => 'running'];
    $encLabel = match ($secure) {
        'ssl' => 'SSL (SMTPS)',
        'none' => 'None',
        default => 'TLS / STARTTLS',
    };
    $encHint = '';
    if ($secure === 'ssl' && $port === 587) {
        $encHint = ' · Port 587 usually needs STARTTLS/TLS';
    } elseif ($secure === 'tls' && $port === 465) {
        $encHint = ' · Port 465 usually needs SSL';
    } elseif ($secure === 'none' && in_array($port, [465, 587], true)) {
        $encHint = ' · Auth ports normally require encryption';
    }
    if (!extension_loaded('openssl') && $secure !== 'none') {
        $steps[count($steps) - 1] = ['label' => 'Encryption', 'status' => 'failed', 'detail' => 'OpenSSL extension missing'];
        return vk_email_settings_diag_fail($steps, 'OpenSSL is required for TLS/SSL SMTP.', ['OpenSSL issues'], $t0, $greeting);
    }
    $steps[count($steps) - 1] = ['label' => 'Encryption', 'status' => 'success', 'detail' => $encLabel . $encHint];

    // 6) Authentication
    $steps[] = ['label' => 'Authentication', 'status' => 'running'];
    if ($authEnabled && $pass === '') {
        $steps[count($steps) - 1] = ['label' => 'Authentication', 'status' => 'failed', 'detail' => 'Password missing'];
        $reasons = ['Incorrect password', 'SMTP password not saved', 'Gmail App Password missing'];
        return vk_email_settings_diag_fail($steps, 'SMTP password is missing.', $reasons, $t0, $greeting);
    }

    $cfg = [
        'smtp_host' => $host,
        'smtp_port' => $port,
        'smtp_user' => $user,
        'smtp_pass' => $pass,
        'smtp_secure' => $secure === 'ssl' ? 'ssl' : ($secure === 'none' ? 'tls' : $secure),
        'smtp_auth' => $authEnabled,
        'timeout' => $timeout,
        'relaxed_ssl' => true,
        'debug' => !empty($form['smtp_debug']),
    ];
    // For "none", probe with AutoTLS off
    if ($secure === 'none') {
        $cfg['smtp_secure'] = 'none';
    }

    $probe = vk_email_probe_smtp($cfg);
    if (!($probe['ok'] ?? false)) {
        $raw = (string) ($probe['error'] ?? 'Authentication failed');
        $diag = vk_email_settings_diagnose_error($raw, $host, $user, $secure, $port);
        $steps[count($steps) - 1] = ['label' => 'Authentication', 'status' => 'failed', 'detail' => $diag['summary']];
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $steps[] = ['label' => 'Response Time', 'status' => 'success', 'detail' => $ms . ' ms'];

        try {
            vk_settings_set($pdo, 'smtp_last_test_at', date('c'));
            vk_settings_set($pdo, 'smtp_last_test_ok', '0');
            vk_settings_set($pdo, 'smtp_last_test_host', $host . ':' . $port . '/' . $secure);
        } catch (Throwable $e) {
        }

        return [
            'ok' => false,
            'steps' => $steps,
            'error' => $diag['message'],
            'reasons' => $diag['reasons'],
            'ms' => $ms,
            'greeting' => trim($greeting),
            'transcript' => (string) ($probe['transcript'] ?? ''),
            'tried' => $probe['tried'] ?? [],
            'report' => [
                'root_cause' => $diag['summary'],
                'recommended_fix' => $diag['reasons'][0] ?? 'Verify SMTP username and password',
                'server_response' => $raw,
                'smtp_host' => $host,
                'port' => $port,
                'encryption' => $secure,
                'authentication_result' => 'failed',
            ],
        ];
    }
    $steps[count($steps) - 1] = ['label' => 'Authentication', 'status' => 'success', 'detail' => (string) ($probe['profile'] ?? 'OK')];

    $ms = (int) round((microtime(true) - $t0) * 1000);
    $steps[] = ['label' => 'Response Time', 'status' => 'success', 'detail' => $ms . ' ms'];
    $steps[] = ['label' => 'Send Test Mail', 'status' => 'success', 'detail' => 'Ready — use Test tab to send'];

    // Persist last successful probe timestamp for status UI
    try {
        vk_settings_set($pdo, 'smtp_last_test_at', date('c'));
        vk_settings_set($pdo, 'smtp_last_test_ok', '1');
        vk_settings_set($pdo, 'smtp_last_test_host', $host . ':' . $port . '/' . $secure);
    } catch (Throwable $e) {
        // non-fatal
    }

    return [
        'ok' => true,
        'steps' => $steps,
        'message' => 'SMTP connection and authentication successful.',
        'ms' => $ms,
        'greeting' => trim($greeting),
        'transcript' => (string) ($probe['transcript'] ?? ''),
        'profile' => $probe['profile'] ?? null,
        'report' => [
            'root_cause' => null,
            'recommended_fix' => null,
            'server_response' => '235 Authentication succeeded',
            'smtp_host' => $host,
            'port' => $port,
            'encryption' => $secure,
            'authentication_result' => 'success',
        ],
    ];
}

/**
 * @param list<array<string,mixed>> $steps
 * @param list<string> $reasons
 * @return array{ok:bool,steps:array,error:string,reasons:list<string>,ms:int,greeting?:string}
 */
function vk_email_settings_diag_fail(array $steps, string $error, array $reasons, float $t0, string $greeting = ''): array
{
    $ms = (int) round((microtime(true) - $t0) * 1000);
    $steps[] = ['label' => 'Response Time', 'status' => 'success', 'detail' => $ms . ' ms'];

    return [
        'ok' => false,
        'steps' => $steps,
        'error' => $error . "\n\nPossible reasons:\n• " . implode("\n• ", $reasons),
        'reasons' => $reasons,
        'ms' => $ms,
        'greeting' => trim($greeting),
    ];
}

/**
 * @return array{summary:string,message:string,reasons:list<string>}
 */
function vk_email_settings_diagnose_error(string $raw, string $host = '', string $user = '', string $secure = 'tls', int $port = 587): array
{
    $l = strtolower($raw);
    $reasons = [];
    $summary = 'SMTP error';

    $isGmail = str_contains(strtolower($host), 'gmail.com') || str_ends_with(strtolower($user), '@gmail.com');
    $isAuth = str_contains($l, 'authenticate') || str_contains($l, 'authentication') || str_contains($l, '535') || str_contains($l, '534') || str_contains($l, 'login');

    if ($isAuth) {
        $summary = 'Authentication failed';
        $reasons = [
            'Incorrect username',
            'Incorrect password',
            'SMTP server rejected login',
            'SSL/TLS mismatch (try TLS:587 or SSL:465)',
            'Conflicting VK_SMTP_PASS / MAIL_PASSWORD in .env (first value wins — remove duplicates)',
        ];
        if (str_contains($l, '535')) {
            $summary = '535 Incorrect authentication data';
            array_unshift($reasons, 'Server returned 535 — username/password rejected by Exim/cPanel');
        }
        if (str_contains($l, '530')) {
            $summary = '530 Authentication required';
            array_unshift($reasons, 'Enable SMTP Authentication');
        }
        if (str_contains($l, '454')) {
            $summary = '454 Temporary authentication failure';
            array_unshift($reasons, 'Mailbox may be suspended, over quota, or server rate-limiting auth');
        }
        if ($isGmail) {
            array_unshift($reasons, 'Gmail App Password required (normal password will fail)');
            $reasons[] = 'Allow less secure apps / App Passwords not enabled on Google account';
        }
        if (str_contains($l, '5.7.8') || str_contains($l, 'badcredentials')) {
            $reasons[] = 'Provider blocked basic auth — use app-specific password or OAuth';
        }
    } elseif (str_contains($l, 'timed out') || str_contains($l, 'timeout')) {
        $summary = 'Connection timed out';
        $reasons = ['Wrong SMTP Host', 'Wrong SMTP Port', 'Firewall blocking SMTP', 'Internet connectivity issue'];
    } elseif (str_contains($l, 'certificate') || str_contains($l, 'ssl') || str_contains($l, 'tls')) {
        $summary = 'Encryption / certificate problem';
        $reasons = ['SSL/TLS mismatch', 'OpenSSL issues', 'Self-signed certificate (enable relaxed SSL for local tests)', 'STARTTLS issues'];
    } elseif (str_contains($l, 'could not connect') || str_contains($l, 'failed to connect')) {
        $summary = 'Could not connect to SMTP server';
        $reasons = ['Wrong SMTP Host', 'Wrong SMTP Port', 'Firewall blocking SMTP', 'DNS problems'];
    } elseif (str_contains($l, 'from') && str_contains($l, 'not')) {
        $summary = 'Sender rejected';
        $reasons = ['Invalid From Email', 'From address not allowed by SMTP provider'];
    } else {
        $summary = 'SMTP Error';
        $reasons = ['Review host, port, encryption, and credentials', $raw !== '' ? $raw : 'Unknown SMTP failure'];
    }

    if ($secure === 'ssl' && $port === 587) {
        $reasons[] = 'Port 587 with SSL is unusual — switch Encryption to TLS/STARTTLS';
    }
    if ($secure === 'tls' && $port === 465) {
        $reasons[] = 'Port 465 with TLS/STARTTLS is unusual — switch Encryption to SSL';
    }

    $message = $summary . ".\n\nPossible reasons:\n• " . implode("\n• ", array_values(array_unique($reasons)));
    if ($raw !== '' && !str_contains(strtolower($message), strtolower(substr($raw, 0, 40)))) {
        $message .= "\n\nTechnical detail: " . $raw;
    }

    return ['summary' => $summary, 'message' => $message, 'reasons' => array_values(array_unique($reasons))];
}

function vk_email_settings_friendly_error(string $raw): string
{
    return vk_email_settings_diagnose_error($raw)['message'];
}

/** @return list<array{id:string,label:string,host:string,port:int,secure:string,auth:bool,hint:string}> */
function vk_email_settings_presets(): array
{
    return [
        ['id' => 'gmail', 'label' => 'Gmail', 'host' => 'smtp.gmail.com', 'port' => 587, 'secure' => 'tls', 'auth' => true, 'hint' => 'Use a Google App Password'],
        ['id' => 'm365', 'label' => 'Microsoft 365', 'host' => 'smtp.office365.com', 'port' => 587, 'secure' => 'tls', 'auth' => true, 'hint' => 'Use mailbox email as username'],
        ['id' => 'outlook', 'label' => 'Outlook.com', 'host' => 'smtp-mail.outlook.com', 'port' => 587, 'secure' => 'tls', 'auth' => true, 'hint' => 'Personal Outlook / Hotmail'],
        ['id' => 'zoho', 'label' => 'Zoho', 'host' => 'smtp.zoho.com', 'port' => 587, 'secure' => 'tls', 'auth' => true, 'hint' => 'Zoho Mail SMTP'],
        ['id' => 'yahoo', 'label' => 'Yahoo', 'host' => 'smtp.mail.yahoo.com', 'port' => 587, 'secure' => 'tls', 'auth' => true, 'hint' => 'Yahoo App Password required'],
        ['id' => 'cpanel', 'label' => 'cPanel', 'host' => 'mail.yourdomain.com', 'port' => 465, 'secure' => 'ssl', 'auth' => true, 'hint' => 'Replace host with mail.yourdomain.com'],
        ['id' => 'vkitnet', 'label' => 'VK IT (vkitnet)', 'host' => 'mail.vkitnet.info', 'port' => 465, 'secure' => 'ssl', 'auth' => true, 'hint' => 'Use mailbox password; quote passwords containing $ in .env'],
        ['id' => 'hostinger', 'label' => 'Hostinger', 'host' => 'smtp.hostinger.com', 'port' => 465, 'secure' => 'ssl', 'auth' => true, 'hint' => 'Hostinger business email'],
        ['id' => 'namecheap', 'label' => 'Namecheap', 'host' => 'mail.privateemail.com', 'port' => 587, 'secure' => 'tls', 'auth' => true, 'hint' => 'Namecheap Private Email'],
        ['id' => 'custom', 'label' => 'Custom SMTP', 'host' => '', 'port' => 587, 'secure' => 'tls', 'auth' => true, 'hint' => 'Enter your provider details manually'],
    ];
}

/** @return array<string,mixed> */
function vk_email_settings_system_info(): array
{
    $autoload = ROOT_PATH . '/vendor/autoload.php';
    $pmVersion = 'not installed';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            $pmVersion = \PHPMailer\PHPMailer\PHPMailer::VERSION;
        }
    }
    $openssl = defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : (extension_loaded('openssl') ? 'enabled' : 'missing');

    return [
        'php_version' => PHP_VERSION,
        'phpmailer_version' => $pmVersion,
        'openssl' => $openssl,
        'smtp_extension' => function_exists('fsockopen') ? 'fsockopen available' : 'missing',
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
    ];
}

/**
 * Health snapshot for Email Settings status cards (no secrets).
 *
 * @return array<string,mixed>
 */
function vk_email_settings_health(PDO $pdo): array
{
    $cfg = vk_smtp_settings_get($pdo);
    $host = (string) ($cfg['smtp_host'] ?? '');
    $port = (int) ($cfg['smtp_port'] ?? 0);
    $secure = (string) ($cfg['smtp_secure'] ?? '');
    $dnsOk = false;
    $ip = '';
    if ($host !== '') {
        $ip = (string) @gethostbyname($host);
        $dnsOk = $ip !== '' && ($ip !== $host || (bool) filter_var($host, FILTER_VALIDATE_IP));
    }

    $lastTest = (string) vk_settings_get($pdo, 'smtp_last_test_at', '');
    $lastOk = vk_settings_get($pdo, 'smtp_last_test_ok', '') === '1';
    $lastHost = (string) vk_settings_get($pdo, 'smtp_last_test_host', '');

    $lastSent = null;
    if (db_table_exists($pdo, 'email_send_log')) {
        $st = $pdo->query("SELECT created_at, sent_at, to_email, status FROM email_send_log WHERE status='sent' ORDER BY id DESC LIMIT 1");
        $lastSent = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
    }

    $passConfigured = trim((string) ($cfg['smtp_pass'] ?? '')) !== '';

    return [
        'smtp_status' => !empty($cfg['configured']) && $passConfigured ? 'configured' : 'incomplete',
        'dns_status' => $dnsOk ? 'ok' : 'failed',
        'dns_ip' => $ip,
        'connection_status' => $lastTest !== '' ? ($lastOk ? 'ok' : 'failed') : 'unknown',
        'authentication_status' => $lastTest !== '' ? ($lastOk ? 'ok' : 'failed') : 'unknown',
        'ssl_tls_status' => in_array($secure, ['ssl', 'tls'], true) ? 'ok' : ($secure === 'none' ? 'disabled' : 'unknown'),
        'host' => $host,
        'port' => $port,
        'encryption' => $secure,
        'last_test_at' => $lastTest,
        'last_test_host' => $lastHost,
        'last_successful_email' => is_array($lastSent) ? [
            'at' => (string) ($lastSent['sent_at'] ?? $lastSent['created_at'] ?? ''),
            'to' => (string) ($lastSent['to_email'] ?? ''),
        ] : null,
    ];
}

/** @return list<array<string,mixed>> */
function vk_email_settings_queue_list(PDO $pdo, int $limit = 50): array
{
    vk_email_tables_migrate($pdo);
    if (!db_table_exists($pdo, 'email_outbound_queue')) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT id, template_type, to_email, subject, status, attempts, max_attempts, last_error, next_attempt_at, created_at, sent_at
         FROM email_outbound_queue ORDER BY id DESC LIMIT ?'
    );
    $st->bindValue(1, max(1, min(200, $limit)), PDO::PARAM_INT);
    $st->execute();

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
        'SELECT id, template_type, to_email, to_name, subject, body_preview, status, attempts, error_message,
                created_at, sent_at,
                ' . (db_column_exists($pdo, 'email_send_log', 'message_id') ? 'message_id, smtp_server, smtp_response, sent_by, delivery_ms' : 'NULL AS message_id, NULL AS smtp_server, NULL AS smtp_response, NULL AS sent_by, NULL AS delivery_ms') . '
         FROM email_send_log ORDER BY id DESC LIMIT ?'
    );
    $st->bindValue(1, max(1, min(200, $limit)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
