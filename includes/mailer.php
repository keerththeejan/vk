<?php
declare(strict_types=1);

function vk_smtp_settings_table_migrate(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS smtp_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            smtp_host VARCHAR(191) NOT NULL DEFAULT '',
            smtp_port INT UNSIGNED NOT NULL DEFAULT 587,
            smtp_user VARCHAR(191) NOT NULL DEFAULT '',
            smtp_pass TEXT,
            smtp_secure ENUM('tls','ssl') NOT NULL DEFAULT 'tls',
            from_email VARCHAR(191) NOT NULL DEFAULT '',
            from_name VARCHAR(191) NOT NULL DEFAULT '',
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function vk_smtp_env_override_str(string $envName, string $current): string
{
    $v = getenv($envName);
    if ($v === false) {
        return $current;
    }
    $t = trim((string) $v);
    return $t !== '' ? $t : $current;
}

/**
 * Merge non-empty environment variables into SMTP config (production / 12-factor).
 *
 * @param array{smtp_host:string,smtp_port:int,smtp_user:string,smtp_pass:string,smtp_secure:string,from_email:string,from_name:string,configured:bool} $cfg
 * @return array{smtp_host:string,smtp_port:int,smtp_user:string,smtp_pass:string,smtp_secure:string,from_email:string,from_name:string,configured:bool}
 */
function vk_smtp_env_key_set(string $name): bool
{
    return vk_smtp_env_value($name) !== null;
}

/**
 * Read env var from getenv / $_ENV / $_SERVER (some Windows / FPM setups differ).
 */
function vk_smtp_env_value(string $name): ?string
{
    $v = getenv($name);
    if ($v !== false && trim((string) $v) !== '') {
        return trim((string) $v);
    }
    if (isset($_ENV[$name]) && trim((string) $_ENV[$name]) !== '') {
        return trim((string) $_ENV[$name]);
    }
    if (isset($_SERVER[$name]) && trim((string) $_SERVER[$name]) !== '') {
        return trim((string) $_SERVER[$name]);
    }
    return null;
}

/**
 * Password from DB/settings only (no .env merge) — used when saving so blank AJAX field does not depend on vk_smtp_settings_get().
 */
function vk_smtp_get_stored_password_only(PDO $pdo): string
{
    vk_smtp_settings_table_migrate($pdo);
    $st = $pdo->query('SELECT smtp_pass FROM smtp_settings WHERE id = 1 LIMIT 1');
    $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
    $p = is_array($row) ? trim((string) ($row['smtp_pass'] ?? '')) : '';
    if ($p !== '') {
        return $p;
    }
    if (vk_settings_table_ready($pdo)) {
        $legacy = trim((string) vk_settings_get($pdo, 'smtp_password', ''));
        if ($legacy !== '') {
            return $legacy;
        }
    }
    return '';
}

/**
 * Laravel-style MAIL_* variables when corresponding VK_* is unset (deployment portability).
 *
 * @param array{smtp_host:string,smtp_port:int,smtp_user:string,smtp_pass:string,smtp_secure:string,from_email:string,from_name:string,configured:bool} $cfg
 * @return array{smtp_host:string,smtp_port:int,smtp_user:string,smtp_pass:string,smtp_secure:string,from_email:string,from_name:string,configured:bool}
 */
function vk_smtp_merge_laravel_mail_env(array $cfg): array
{
    if (!vk_smtp_env_key_set('VK_SMTP_HOST')) {
        $mh = getenv('MAIL_HOST');
        if ($mh !== false && trim((string) $mh) !== '') {
            $cfg['smtp_host'] = trim((string) $mh);
        }
    }
    if (!vk_smtp_env_key_set('VK_SMTP_PORT')) {
        $mp = getenv('MAIL_PORT');
        if ($mp !== false && trim((string) $mp) !== '') {
            $cfg['smtp_port'] = max(1, (int) $mp);
        }
    }
    if (!vk_smtp_env_key_set('VK_SMTP_USER')) {
        $mu = getenv('MAIL_USERNAME');
        if ($mu !== false && trim((string) $mu) !== '') {
            $cfg['smtp_user'] = trim((string) $mu);
        }
    }
    if (!vk_smtp_env_key_set('VK_SMTP_PASS')) {
        $mpw = vk_smtp_env_value('MAIL_PASSWORD');
        if ($mpw !== null) {
            $cfg['smtp_pass'] = $mpw;
        }
    }
    if (!vk_smtp_env_key_set('VK_SMTP_SECURE')) {
        $me = getenv('MAIL_ENCRYPTION');
        if ($me !== false && trim((string) $me) !== '') {
            $e = strtolower(trim((string) $me));
            $cfg['smtp_secure'] = ($e === 'ssl' || $e === 'smtps') ? 'ssl' : 'tls';
        }
    }
    if (!vk_smtp_env_key_set('VK_MAIL_FROM')) {
        $mf = getenv('MAIL_FROM_ADDRESS');
        if ($mf !== false && trim((string) $mf) !== '') {
            $cfg['from_email'] = trim((string) $mf);
        }
    }
    if (!vk_smtp_env_key_set('VK_MAIL_FROM_NAME')) {
        $mfn = getenv('MAIL_FROM_NAME');
        if ($mfn !== false && trim((string) $mfn) !== '') {
            $cfg['from_name'] = trim((string) $mfn);
        }
    }
    $cfg['configured'] = $cfg['smtp_host'] !== '' && $cfg['from_email'] !== '';
    return $cfg;
}

function vk_smtp_merge_env(array $cfg): array
{
    $cfg['smtp_host'] = vk_smtp_env_override_str('VK_SMTP_HOST', $cfg['smtp_host']);
    $p = getenv('VK_SMTP_PORT');
    if ($p !== false && trim((string) $p) !== '') {
        $cfg['smtp_port'] = max(1, (int) $p);
    }
    $cfg['smtp_user'] = vk_smtp_env_override_str('VK_SMTP_USER', $cfg['smtp_user']);
    $passVk = vk_smtp_env_value('VK_SMTP_PASS');
    if ($passVk !== null) {
        $cfg['smtp_pass'] = $passVk;
    }
    $sec = strtolower(vk_smtp_env_override_str('VK_SMTP_SECURE', $cfg['smtp_secure']));
    $cfg['smtp_secure'] = $sec === 'ssl' ? 'ssl' : 'tls';
    $cfg['from_email'] = vk_smtp_env_override_str('VK_MAIL_FROM', $cfg['from_email']);
    $cfg['from_name'] = vk_smtp_env_override_str('VK_MAIL_FROM_NAME', $cfg['from_name']);
    $cfg['configured'] = $cfg['smtp_host'] !== '' && $cfg['from_email'] !== '';
    $cfg = vk_smtp_merge_laravel_mail_env($cfg);
    if (trim((string) $cfg['smtp_pass']) === '') {
        foreach (['VK_SMTP_PASS', 'MAIL_PASSWORD'] as $pk) {
            $pv = vk_smtp_env_value($pk);
            if ($pv !== null) {
                $cfg['smtp_pass'] = $pv;
                break;
            }
        }
    }
    return $cfg;
}

/**
 * @return array{smtp_host:string,smtp_port:int,smtp_user:string,smtp_pass:string,smtp_secure:string,from_email:string,from_name:string,configured:bool}
 */
function vk_smtp_settings_get(PDO $pdo): array
{
    vk_smtp_settings_table_migrate($pdo);
    $st = $pdo->query('SELECT * FROM smtp_settings WHERE id = 1 LIMIT 1');
    $row = $st ? $st->fetch() : null;

    $fallback = [
        'smtp_host' => (string) vk_settings_get($pdo, 'smtp_host', ''),
        'smtp_port' => (int) vk_settings_get($pdo, 'smtp_port', '587'),
        'smtp_user' => (string) vk_settings_get($pdo, 'smtp_username', ''),
        'smtp_pass' => trim((string) vk_settings_get($pdo, 'smtp_password', '')),
        'smtp_secure' => (string) vk_settings_get($pdo, 'smtp_secure', 'tls'),
        'from_email' => (string) vk_settings_get($pdo, 'email_from', ''),
        'from_name' => (string) vk_settings_get($pdo, 'from_name', (string) vk_settings_get($pdo, 'site_name', 'VK Network')),
    ];
    if (!is_array($row) || trim((string) ($row['smtp_host'] ?? '')) === '') {
        $cfg = $fallback;
    } else {
        $cfg = [
            'smtp_host' => trim((string) ($row['smtp_host'] ?? '')),
            'smtp_port' => max(1, (int) ($row['smtp_port'] ?? 587)),
            'smtp_user' => trim((string) ($row['smtp_user'] ?? '')),
            'smtp_pass' => trim((string) ($row['smtp_pass'] ?? '')),
            'smtp_secure' => ((string) ($row['smtp_secure'] ?? 'tls')) === 'ssl' ? 'ssl' : 'tls',
            'from_email' => trim((string) ($row['from_email'] ?? '')),
            'from_name' => trim((string) ($row['from_name'] ?? '')),
        ];
    }
    $cfg['configured'] = $cfg['smtp_host'] !== '' && $cfg['from_email'] !== '';
    return vk_smtp_merge_env($cfg);
}

function vk_smtp_guess_defaults(string $smtpUserOrEmail): array
{
    $v = strtolower(trim($smtpUserOrEmail));
    if ($v === '') {
        return ['smtp_host' => '', 'smtp_port' => 587, 'smtp_secure' => 'tls'];
    }
    if (str_contains($v, '@gmail.com')) {
        return ['smtp_host' => 'smtp.gmail.com', 'smtp_port' => 587, 'smtp_secure' => 'tls'];
    }
    if (str_contains($v, '@outlook.com') || str_contains($v, '@hotmail.com') || str_contains($v, '@live.com')) {
        return ['smtp_host' => 'smtp.office365.com', 'smtp_port' => 587, 'smtp_secure' => 'tls'];
    }
    if (str_contains($v, '@')) {
        $domain = substr($v, strpos($v, '@') + 1);
        return ['smtp_host' => 'mail.' . $domain, 'smtp_port' => 587, 'smtp_secure' => 'tls'];
    }
    return ['smtp_host' => '', 'smtp_port' => 587, 'smtp_secure' => 'tls'];
}

function vk_smtp_settings_save(PDO $pdo, array $in): void
{
    vk_smtp_settings_table_migrate($pdo);
    $host = trim((string) ($in['smtp_host'] ?? ''));
    $port = max(1, (int) ($in['smtp_port'] ?? 587));
    $user = trim((string) ($in['smtp_user'] ?? ''));
    $pass = trim((string) ($in['smtp_pass'] ?? ''));
    $secure = ((string) ($in['smtp_secure'] ?? 'tls')) === 'ssl' ? 'ssl' : 'tls';
    $fromEmail = trim((string) ($in['from_email'] ?? ''));
    $fromName = trim((string) ($in['from_name'] ?? ''));

    if ($pass === '') {
        $pass = vk_smtp_get_stored_password_only($pdo);
    }
    if ($pass === '') {
        $e = vk_smtp_env_value('VK_SMTP_PASS');
        if ($e !== null) {
            $pass = $e;
        }
    }
    if ($pass === '') {
        $e = vk_smtp_env_value('MAIL_PASSWORD');
        if ($e !== null) {
            $pass = $e;
        }
    }

    $st = $pdo->prepare(
        'INSERT INTO smtp_settings
        (id, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure, from_email, from_name, updated_at)
        VALUES (1,?,?,?,?,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE
          smtp_host=VALUES(smtp_host), smtp_port=VALUES(smtp_port), smtp_user=VALUES(smtp_user),
          smtp_pass=VALUES(smtp_pass), smtp_secure=VALUES(smtp_secure), from_email=VALUES(from_email),
          from_name=VALUES(from_name), updated_at=NOW()'
    );
    $st->execute([$host, $port, $user, $pass, $secure, $fromEmail, $fromName]);

    // Sync legacy settings keys for compatibility with existing modules.
    vk_settings_set($pdo, 'smtp_host', $host);
    vk_settings_set($pdo, 'smtp_port', (string) $port);
    vk_settings_set($pdo, 'smtp_username', $user);
    if ($pass !== '') {
        vk_settings_set($pdo, 'smtp_password', $pass);
    }
    vk_settings_set($pdo, 'smtp_secure', $secure);
    vk_settings_set($pdo, 'email_from', $fromEmail);
    vk_settings_set($pdo, 'from_name', $fromName);
}

/**
 * @param array{
 *   template_type?:string,
 *   html?:bool,
 *   html_body?:string,
 *   queue_only?:bool,
 *   max_retries?:int,
 *   smtp_timeout?:int,
 *   fallback_tls?:bool,
 *   relaxed_ssl?:bool
 * } $options
 * @return array{ok:bool,error:?string,queued_id?:int}
 */
function vk_mailer_send(PDO $pdo, string $to, string $subject, string $body, ?string $toName = null, array $options = []): array
{
    if (function_exists('vk_email_tables_migrate')) {
        vk_email_tables_migrate($pdo);
    }

    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient email'];
    }

    $subject = function_exists('vk_email_sanitize_subject')
        ? vk_email_sanitize_subject($subject)
        : trim(str_replace(["\r", "\n", "\0"], ' ', $subject));

    $templateType = (string) ($options['template_type'] ?? '');
    $useHtml = (bool) ($options['html'] ?? false);
    $htmlBody = (string) ($options['html_body'] ?? '');
    $queueOnly = (bool) ($options['queue_only'] ?? false);
    $maxRetries = max(1, min(8, (int) ($options['max_retries'] ?? 3)));
    $smtpTimeout = max(5, min(60, (int) ($options['smtp_timeout'] ?? 45)));
    $fallbackTls = (bool) ($options['fallback_tls'] ?? false);
    $relaxedSsl = (bool) ($options['relaxed_ssl'] ?? false);
    if (!$relaxedSsl) {
        $r = getenv('VK_SMTP_RELAX_SSL');
        if ($r !== false && trim((string) $r) !== '') {
            $v = strtolower(trim((string) $r));
            $relaxedSsl = ($v === '1' || $v === 'true' || $v === 'yes');
        }
    }

    $cfg = vk_smtp_settings_get($pdo);
    if (!$cfg['configured']) {
        return ['ok' => false, 'error' => 'Email system not configured'];
    }
    if (!filter_var((string) $cfg['from_email'], FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid From email'];
    }
    if (((string) $cfg['smtp_user']) !== '' && trim((string) $cfg['smtp_pass']) === '') {
        return [
            'ok' => false,
            'error' => 'SMTP password is missing. Enter it under System Settings → Email and click Save SMTP + auto-reply, and/or set VK_SMTP_PASS or MAIL_PASSWORD in .env (use double quotes if the password contains $ or { }). Restart Apache after editing .env.',
        ];
    }

    if ($queueOnly && function_exists('vk_email_queue_enqueue')) {
        $qid = vk_email_queue_enqueue(
            $pdo,
            $templateType !== '' ? $templateType : 'queued',
            $to,
            (string) ($toName ?? ''),
            $subject,
            $body,
            ($useHtml && $htmlBody !== '') ? $htmlBody : null
        );
        if ($qid > 0) {
            if (function_exists('vk_email_send_log_insert')) {
                vk_email_send_log_insert($pdo, $templateType, $to, (string) ($toName ?? ''), $subject, function_exists('vk_email_sanitize_body_preview') ? vk_email_sanitize_body_preview($body) : substr($body, 0, 500), 'queued');
            }
            return ['ok' => true, 'error' => null, 'queued_id' => $qid];
        }
        return ['ok' => false, 'error' => 'Queue unavailable'];
    }

    $autoload = ROOT_PATH . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return ['ok' => false, 'error' => 'PHPMailer missing. Run composer install.'];
    }
    require_once $autoload;

    $logId = 0;
    if (function_exists('vk_email_send_log_insert')) {
        $preview = function_exists('vk_email_sanitize_body_preview') ? vk_email_sanitize_body_preview($body) : substr($body, 0, 500);
        $logId = vk_email_send_log_insert($pdo, $templateType, $to, (string) ($toName ?? ''), $subject, $preview, 'sending');
    }

    $profiles = [[
        'host' => (string) $cfg['smtp_host'],
        'port' => (int) $cfg['smtp_port'],
        'secure' => ((string) $cfg['smtp_secure']) === 'ssl' ? 'ssl' : 'tls',
    ]];
    if (
        $fallbackTls
        && $profiles[0]['secure'] === 'ssl'
        && $profiles[0]['port'] === 465
    ) {
        $profiles[] = [
            'host' => (string) $cfg['smtp_host'],
            'port' => 587,
            'secure' => 'tls',
        ];
    }

    $lastError = null;
    $attemptNo = 0;
    $totalAttempts = 0;
    foreach ($profiles as $pi => $prof) {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $attemptNo++;
            $totalAttempts++;
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = $prof['host'];
                $mail->Port = $prof['port'];
                $mail->Timeout = $smtpTimeout;
                $mail->SMTPAuth = ((string) $cfg['smtp_user']) !== '';
                if ($mail->SMTPAuth) {
                    $mail->Username = (string) $cfg['smtp_user'];
                    $mail->Password = (string) $cfg['smtp_pass'];
                }
                $mail->SMTPSecure = $prof['secure'] === 'ssl'
                    ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->SMTPAutoTLS = $prof['secure'] !== 'ssl';
                if ($relaxedSsl) {
                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true,
                        ],
                    ];
                }
                $mail->CharSet = 'UTF-8';
                $mail->setFrom((string) $cfg['from_email'], (string) ($cfg['from_name'] !== '' ? $cfg['from_name'] : 'VK Network'));
                $mail->addAddress($to, (string) ($toName ?? ''));
                if ($useHtml && $htmlBody !== '') {
                    $mail->isHTML(true);
                    $mail->Body = $htmlBody;
                    $mail->AltBody = $body;
                } else {
                    $mail->isHTML(false);
                    $mail->Body = $body;
                }
                $mail->Subject = $subject;
                $mail->send();
                if (function_exists('vk_email_send_log_finalize')) {
                    vk_email_send_log_finalize($pdo, $logId, 'sent', null, $attemptNo);
                }
                return ['ok' => true, 'error' => null];
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                if ($attempt < $maxRetries) {
                    usleep((int) (200000 * $attempt));
                }
            }
        }
    }

    $err = $lastError ?? 'Send failed';
    if (function_exists('vk_email_send_log_finalize')) {
        vk_email_send_log_finalize($pdo, $logId, 'failed', mb_substr($err, 0, 2000, 'UTF-8'), max(1, $totalAttempts));
    }
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('vk_mailer_send failed: ' . $err);
    }
    return ['ok' => false, 'error' => $err];
}
