<?php
declare(strict_types=1);

/**
 * Email infrastructure: DB tables, logging, queue, sanitization, auto-responder rules.
 */

function vk_email_env_str(string $name, string $default = ''): string
{
    $v = getenv($name);
    if ($v === false || $v === '') {
        return $default;
    }
    return trim((string) $v);
}

function vk_email_env_int(string $name, int $default): int
{
    $v = getenv($name);
    if ($v === false || $v === '') {
        return $default;
    }
    return max(0, (int) $v);
}

function vk_email_tables_migrate(PDO $pdo): void
{
    $sqlFile = ROOT_PATH . '/sql/upgrade_email_system.sql';
    if (!is_readable($sqlFile)) {
        return;
    }
    if (db_table_exists($pdo, 'email_send_log')) {
        return;
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        return;
    }
    $pdo->exec($sql);
}

function vk_email_sanitize_subject(string $subject): string
{
    $s = str_replace(["\r", "\n", "\0"], ' ', $subject);
    return trim(mb_substr($s, 0, 998, 'UTF-8'));
}

function vk_email_sanitize_body_preview(string $body, int $max = 500): string
{
    $oneLine = preg_replace('/\s+/u', ' ', $body) ?? '';
    return mb_substr(trim($oneLine), 0, $max, 'UTF-8');
}

function vk_email_normalize_sender(string $email): string
{
    return strtolower(trim($email));
}

/**
 * @param array<string, string> $headers Lowercase header name => value
 */
function vk_email_should_ignore_autoreply(string $fromEmail, string $subject, array $headers): bool
{
    $from = vk_email_normalize_sender($fromEmail);
    $local = '';
    if (str_contains($from, '@')) {
        $local = explode('@', $from, 2)[0];
    }
    $ignoreLocals = [
        'mailer-daemon', 'mailerdaemon', 'postmaster', 'noreply', 'no-reply', 'donotreply',
        'do-not-reply', 'bounce', 'bounces', 'null', 'root',
    ];
    foreach ($ignoreLocals as $il) {
        if ($local === $il || str_starts_with($local, $il . '+')) {
            return true;
        }
    }
    $subjLower = strtolower($subject);
    if (str_contains($subjLower, 'undeliverable') || str_contains($subjLower, 'delivery status notification')
        || str_contains($subjLower, 'out of office') || str_contains($subjLower, 'automatic reply')) {
        return true;
    }
    $autoSubmitted = strtolower(trim($headers['auto-submitted'] ?? ''));
    if ($autoSubmitted !== '' && $autoSubmitted !== 'no') {
        return true;
    }
    $prec = strtolower(trim($headers['precedence'] ?? ''));
    if ($prec === 'bulk' || $prec === 'junk' || $prec === 'list') {
        return true;
    }
    $suppress = strtolower(trim($headers['x-auto-response-suppress'] ?? ''));
    if ($suppress !== '' && str_contains($suppress, 'all')) {
        return true;
    }
    return false;
}

/**
 * @return array{allowed:bool,reason:?string}
 */
function vk_email_autoresponder_rate_check(PDO $pdo, string $senderEmail): array
{
    vk_email_tables_migrate($pdo);
    if (!db_table_exists($pdo, 'email_autoresponder_rate')) {
        return ['allowed' => true, 'reason' => null];
    }
    $norm = vk_email_normalize_sender($senderEmail);
    if ($norm === '' || !filter_var($norm, FILTER_VALIDATE_EMAIL)) {
        return ['allowed' => false, 'reason' => 'invalid_sender'];
    }
    $st = $pdo->prepare('SELECT last_sent_at FROM email_autoresponder_rate WHERE sender_email = ? LIMIT 1');
    $st->execute([$norm]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['allowed' => true, 'reason' => null];
    }
    $last = strtotime((string) ($row['last_sent_at'] ?? ''));
    if ($last === false) {
        return ['allowed' => true, 'reason' => null];
    }
    if ((time() - $last) < 86400) {
        return ['allowed' => false, 'reason' => 'rate_limit_24h'];
    }
    return ['allowed' => true, 'reason' => null];
}

function vk_email_autoresponder_rate_mark(PDO $pdo, string $senderEmail): void
{
    vk_email_tables_migrate($pdo);
    if (!db_table_exists($pdo, 'email_autoresponder_rate')) {
        return;
    }
    $norm = vk_email_normalize_sender($senderEmail);
    if ($norm === '') {
        return;
    }
    $st = $pdo->prepare(
        'INSERT INTO email_autoresponder_rate (sender_email, last_sent_at) VALUES (?, NOW())
         ON DUPLICATE KEY UPDATE last_sent_at = NOW()'
    );
    $st->execute([$norm]);
}

/**
 * @return array{imap_host:string,imap_port:int,imap_user:string,imap_pass:string,imap_enabled:bool,configured:bool}
 */
function vk_imap_settings_get(PDO $pdo): array
{
    $dbHost = (string) vk_settings_get($pdo, 'imap_host', '');
    $host = vk_email_env_str('VK_IMAP_HOST', '');
    if ($host === '' && function_exists('vk_smtp_env_key_set') && !vk_smtp_env_key_set('VK_IMAP_HOST')) {
        $mh = getenv('MAIL_HOST');
        if ($mh !== false && trim((string) $mh) !== '') {
            $host = trim((string) $mh);
        }
    }
    if ($host === '') {
        $host = $dbHost;
    }

    $port = vk_email_env_int('VK_IMAP_PORT', 0);
    if ($port <= 0) {
        $port = (int) (vk_settings_get($pdo, 'imap_port', '993') ?: 993);
    }

    $dbUser = (string) vk_settings_get($pdo, 'imap_username', '');
    $user = vk_email_env_str('VK_IMAP_USER', '');
    if ($user === '' && function_exists('vk_smtp_env_key_set') && !vk_smtp_env_key_set('VK_IMAP_USER')) {
        $mu = getenv('MAIL_USERNAME');
        if ($mu !== false && trim((string) $mu) !== '') {
            $user = trim((string) $mu);
        }
    }
    if ($user === '') {
        $user = $dbUser;
    }

    $pass = vk_email_env_str('VK_IMAP_PASS', '');
    if ($pass === '' && function_exists('vk_smtp_env_key_set') && !vk_smtp_env_key_set('VK_IMAP_PASS')) {
        $mpw = getenv('MAIL_PASSWORD');
        if ($mpw !== false && (string) $mpw !== '') {
            $pass = (string) $mpw;
        }
    }
    if ($pass === '') {
        $sp = getenv('VK_SMTP_PASS');
        if ($sp !== false && (string) $sp !== '') {
            $pass = (string) $sp;
        }
    }
    if ($pass === '') {
        $pass = (string) vk_settings_get($pdo, 'imap_password', '');
    }
    $enabled = vk_settings_get($pdo, 'imap_poll_enabled', '0') === '1'
        || vk_email_env_str('VK_IMAP_ENABLED', '') === '1';
    $configured = $host !== '' && $user !== '' && $pass !== '';
    return [
        'imap_host' => $host,
        'imap_port' => max(1, $port),
        'imap_user' => $user,
        'imap_pass' => $pass,
        'imap_enabled' => $enabled,
        'configured' => $configured,
    ];
}

function vk_imap_settings_save(PDO $pdo, array $in): void
{
    $host = trim((string) ($in['imap_host'] ?? ''));
    $port = max(1, (int) ($in['imap_port'] ?? 993));
    $user = trim((string) ($in['imap_username'] ?? ''));
    $pass = (string) ($in['imap_password'] ?? '');
    $poll = ((string) ($in['imap_poll_enabled'] ?? '0')) === '1' ? '1' : '0';

    if ($pass === '') {
        $pass = (string) vk_settings_get($pdo, 'imap_password', '');
    }

    vk_settings_set($pdo, 'imap_host', $host);
    vk_settings_set($pdo, 'imap_port', (string) $port);
    vk_settings_set($pdo, 'imap_username', $user);
    if ($pass !== '') {
        vk_settings_set($pdo, 'imap_password', $pass);
    }
    vk_settings_set($pdo, 'imap_poll_enabled', $poll);
}

/**
 * @return array{enabled:bool,subject:string,body:string}
 */
function vk_autoresponder_settings_get(PDO $pdo): array
{
    $enabled = vk_settings_get($pdo, 'email_autoresponder_enabled', '0') === '1';
    $subject = (string) vk_settings_get($pdo, 'email_autoresponder_subject', 'Thank you for contacting VK IT');
    $body = (string) vk_settings_get(
        $pdo,
        'email_autoresponder_body',
        "Hello,\n\nThank you for contacting us. We have received your email and will respond shortly.\n\nRegards,\nVK IT Team"
    );
    return ['enabled' => $enabled, 'subject' => $subject, 'body' => $body];
}

function vk_autoresponder_settings_save(PDO $pdo, array $in): void
{
    $en = ((string) ($in['email_autoresponder_enabled'] ?? '0')) === '1' ? '1' : '0';
    $sub = vk_email_sanitize_subject((string) ($in['email_autoresponder_subject'] ?? ''));
    $body = (string) ($in['email_autoresponder_body'] ?? '');
    if (mb_strlen($body, 'UTF-8') > 20000) {
        $body = mb_substr($body, 0, 20000, 'UTF-8');
    }
    vk_settings_set($pdo, 'email_autoresponder_enabled', $en);
    vk_settings_set($pdo, 'email_autoresponder_subject', $sub);
    vk_settings_set($pdo, 'email_autoresponder_body', $body);
}

/**
 * @return int log row id (0 if logging skipped)
 */
function vk_email_send_log_insert(
    PDO $pdo,
    string $templateType,
    string $toEmail,
    string $toName,
    string $subject,
    string $bodyPreview,
    string $status = 'sending'
): int {
    vk_email_tables_migrate($pdo);
    if (!db_table_exists($pdo, 'email_send_log')) {
        return 0;
    }
    $st = $pdo->prepare(
        'INSERT INTO email_send_log
        (direction, template_type, to_email, to_name, subject, body_preview, status, attempts, created_at)
        VALUES (\'outbound\',?,?,?,?,?,?,1,NOW())'
    );
    $st->execute([$templateType, $toEmail, $toName, $subject, $bodyPreview, $status]);
    return (int) $pdo->lastInsertId();
}

function vk_email_send_log_finalize(PDO $pdo, int $logId, string $status, ?string $error, int $attempts): void
{
    if ($logId <= 0 || !db_table_exists($pdo, 'email_send_log')) {
        return;
    }
    if ($status === 'sent') {
        $st = $pdo->prepare('UPDATE email_send_log SET status = ?, error_message = ?, attempts = ?, sent_at = NOW() WHERE id = ?');
    } else {
        $st = $pdo->prepare('UPDATE email_send_log SET status = ?, error_message = ?, attempts = ?, sent_at = NULL WHERE id = ?');
    }
    $st->execute([$status, $error, $attempts, $logId]);
}

function vk_email_queue_enqueue(
    PDO $pdo,
    string $templateType,
    string $toEmail,
    string $toName,
    string $subject,
    string $bodyText,
    ?string $bodyHtml = null,
    int $maxAttempts = 5
): int {
    vk_email_tables_migrate($pdo);
    if (!db_table_exists($pdo, 'email_outbound_queue')) {
        return 0;
    }
    $st = $pdo->prepare(
        'INSERT INTO email_outbound_queue
        (template_type, to_email, to_name, subject, body_text, body_html, status, attempts, max_attempts, next_attempt_at)
        VALUES (?,?,?,?,?,?,\'pending\',0,?,NOW())'
    );
    $st->execute([$templateType, $toEmail, $toName, $subject, $bodyText, $bodyHtml, max(1, $maxAttempts)]);

    return (int) $pdo->lastInsertId();
}

/**
 * Process pending outbound queue rows (called from cron).
 *
 * @return array{processed:int,sent:int,failed:int}
 */
function vk_email_queue_process(PDO $pdo, int $limit = 25): array
{
    vk_email_tables_migrate($pdo);
    $out = ['processed' => 0, 'sent' => 0, 'failed' => 0];
    if (!db_table_exists($pdo, 'email_outbound_queue')) {
        return $out;
    }
    $st = $pdo->prepare(
        'SELECT * FROM email_outbound_queue WHERE status = \'pending\' AND next_attempt_at <= NOW() ORDER BY id ASC LIMIT ' . (int) max(1, min(100, $limit))
    );
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $out['processed']++;
        $id = (int) ($row['id'] ?? 0);
        $pdo->prepare('UPDATE email_outbound_queue SET status = \'processing\' WHERE id = ?')->execute([$id]);
        $opts = [
            'template_type' => (string) ($row['template_type'] ?? ''),
            'html' => ((string) ($row['body_html'] ?? '')) !== '',
            'html_body' => (string) ($row['body_html'] ?? ''),
        ];
        $res = vk_mailer_send(
            $pdo,
            (string) ($row['to_email'] ?? ''),
            (string) ($row['subject'] ?? ''),
            (string) ($row['body_text'] ?? ''),
            (string) ($row['to_name'] ?? '') ?: null,
            $opts
        );
        $attempts = (int) ($row['attempts'] ?? 0) + 1;
        $max = max(1, (int) ($row['max_attempts'] ?? 5));
        if ($res['ok']) {
            $out['sent']++;
            $pdo->prepare('UPDATE email_outbound_queue SET status = \'sent\', attempts = ?, sent_at = NOW(), last_error = NULL WHERE id = ?')
                ->execute([$attempts, $id]);
        } else {
            $err = (string) ($res['error'] ?? 'failed');
            if ($attempts >= $max) {
                $out['failed']++;
                $pdo->prepare('UPDATE email_outbound_queue SET status = \'failed\', attempts = ?, last_error = ? WHERE id = ?')
                    ->execute([$attempts, mb_substr($err, 0, 2000, 'UTF-8'), $id]);
            } else {
                $delay = min(3600, (int) (60 * pow(2, min(6, $attempts))));
                $pdo->prepare(
                    'UPDATE email_outbound_queue SET status = \'pending\', attempts = ?, last_error = ?, next_attempt_at = DATE_ADD(NOW(), INTERVAL ' . $delay . ' SECOND) WHERE id = ?'
                )->execute([$attempts, mb_substr($err, 0, 2000, 'UTF-8'), $id]);
            }
        }
    }
    return $out;
}

/**
 * Transactional templates (registration, password reset, system).
 *
 * @return array{ok:bool,error:?string}
 */
function vk_email_send_transactional(PDO $pdo, string $type, string $to, array $vars = []): array
{
    $to = vk_email_normalize_sender($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient'];
    }
    $site = (string) vk_settings_get($pdo, 'site_name', 'VK IT');
    $subject = '';
    $body = '';
    if ($type === 'registration_confirm') {
        $name = (string) ($vars['name'] ?? 'there');
        $subject = 'Welcome to ' . $site;
        $body = "Hello {$name},\n\nYour registration was received.\n\nThank you,\n{$site}";
    } elseif ($type === 'password_reset') {
        $name = (string) ($vars['name'] ?? 'there');
        $link = (string) ($vars['reset_link'] ?? '');
        $subject = 'Password reset — ' . $site;
        $body = "Hello {$name},\n\nWe received a request to reset your password.\n";
        if ($link !== '') {
            $body .= "Use this link (expires soon):\n{$link}\n\n";
        }
        $body .= "If you did not request this, you can ignore this email.\n\n— {$site}";
    } elseif ($type === 'system_notification') {
        $subject = (string) ($vars['subject'] ?? 'Notification');
        $body = (string) ($vars['body'] ?? '');
    } else {
        return ['ok' => false, 'error' => 'Unknown template type'];
    }
    $subject = vk_email_sanitize_subject($subject);
    return vk_mailer_send($pdo, $to, $subject, $body, (string) ($vars['to_name'] ?? '') ?: null, ['template_type' => $type]);
}
