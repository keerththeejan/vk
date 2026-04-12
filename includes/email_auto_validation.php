<?php
declare(strict_types=1);

/**
 * Automated email setup: env → DB sync, SMTP/IMAP/POP3 probes, Laravel-style MAIL_* aliases.
 * Passwords are never returned; use vk_email_mask_secret() in logs/API output.
 */

function vk_email_mask_secret(string $s): string
{
    if ($s === '') {
        return '';
    }
    $len = strlen($s);
    if ($len <= 4) {
        return '****';
    }
    return substr($s, 0, 2) . str_repeat('*', min(12, $len - 4)) . substr($s, -2);
}

function vk_email_env_nonempty(string $name): bool
{
    $v = getenv($name);
    return $v !== false && trim((string) $v) !== '';
}

/**
 * Build SMTP/IMAP settings purely from environment (VK_* with MAIL_* fallback).
 *
 * @return array{smtp_host:string,smtp_port:int,smtp_user:string,smtp_pass:string,smtp_secure:string,from_email:string,from_name:string,imap_host:string,imap_port:int,imap_user:string,imap_pass:string}
 */
function vk_email_resolve_from_environment(): array
{
    $preset = function_exists('vk_mail_preset_vkitnet') ? vk_mail_preset_vkitnet() : [
        'smtp_host' => '', 'smtp_port' => 465, 'smtp_secure' => 'ssl',
        'imap_host' => '', 'imap_port' => 993, 'pop3_port' => 995,
    ];

    $host = vk_email_env_str('VK_SMTP_HOST', '');
    if ($host === '') {
        $host = vk_email_env_str('MAIL_HOST', '');
    }

    $port = 0;
    if (vk_email_env_nonempty('VK_SMTP_PORT')) {
        $port = max(1, (int) (string) getenv('VK_SMTP_PORT'));
    } else {
        $mp = getenv('MAIL_PORT');
        if ($mp !== false && trim((string) $mp) !== '') {
            $port = max(1, (int) $mp);
        }
    }
    if ($port <= 0) {
        $port = 465;
    }

    $user = vk_email_env_str('VK_SMTP_USER', '');
    if ($user === '') {
        $user = vk_email_env_str('MAIL_USERNAME', '');
    }

    $pass = '';
    if (vk_email_env_nonempty('VK_SMTP_PASS')) {
        $pass = (string) getenv('VK_SMTP_PASS');
    } elseif (getenv('MAIL_PASSWORD') !== false && (string) getenv('MAIL_PASSWORD') !== '') {
        $pass = (string) getenv('MAIL_PASSWORD');
    }

    $secure = strtolower(vk_email_env_str('VK_SMTP_SECURE', ''));
    if ($secure === '') {
        $me = getenv('MAIL_ENCRYPTION');
        if ($me !== false && trim((string) $me) !== '') {
            $secure = strtolower(trim((string) $me));
        }
    }
    if ($secure !== 'ssl' && $secure !== 'tls') {
        $secure = 'ssl';
    }

    $from = vk_email_env_str('VK_MAIL_FROM', '');
    if ($from === '') {
        $from = vk_email_env_str('MAIL_FROM_ADDRESS', '');
    }
    if ($from === '' && $user !== '' && filter_var($user, FILTER_VALIDATE_EMAIL)) {
        $from = $user;
    }

    $fromName = vk_email_env_str('VK_MAIL_FROM_NAME', '');
    if ($fromName === '') {
        $fromName = vk_email_env_str('MAIL_FROM_NAME', 'VK IT');
    }

    $imapHost = vk_email_env_str('VK_IMAP_HOST', '');
    if ($imapHost === '') {
        $imapHost = $host !== '' ? $host : (string) ($preset['imap_host'] ?? '');
    }

    $imapPort = 0;
    if (vk_email_env_nonempty('VK_IMAP_PORT')) {
        $imapPort = max(1, (int) (string) getenv('VK_IMAP_PORT'));
    }
    if ($imapPort <= 0) {
        $imapPort = (int) ($preset['imap_port'] ?? 993);
    }

    $imapUser = vk_email_env_str('VK_IMAP_USER', '');
    if ($imapUser === '') {
        $imapUser = $user;
    }

    $imapPass = '';
    if (vk_email_env_nonempty('VK_IMAP_PASS')) {
        $imapPass = (string) getenv('VK_IMAP_PASS');
    } elseif ($pass !== '') {
        $imapPass = $pass;
    }

    $lowerUser = strtolower($user);
    if (str_ends_with($lowerUser, '@vkitnet.info') || str_ends_with(strtolower($from), '@vkitnet.info')) {
        if ($host === '') {
            $host = (string) ($preset['smtp_host'] ?? 'vkitnet.info');
        }
        if (!vk_email_env_nonempty('VK_SMTP_PORT') && getenv('MAIL_PORT') === false) {
            $port = (int) ($preset['smtp_port'] ?? 465);
        }
        if (!vk_email_env_nonempty('VK_SMTP_SECURE') && getenv('MAIL_ENCRYPTION') === false) {
            $secure = (string) ($preset['smtp_secure'] ?? 'ssl');
        }
        if (!vk_email_env_nonempty('VK_IMAP_HOST') && getenv('MAIL_HOST') === false) {
            $imapHost = (string) ($preset['imap_host'] ?? 'vkitnet.info');
        }
    }

    return [
        'smtp_host' => $host,
        'smtp_port' => max(1, $port),
        'smtp_user' => $user,
        'smtp_pass' => $pass,
        'smtp_secure' => $secure === 'tls' ? 'tls' : 'ssl',
        'from_email' => $from,
        'from_name' => $fromName,
        'imap_host' => $imapHost,
        'imap_port' => max(1, $imapPort),
        'imap_user' => $imapUser,
        'imap_pass' => $imapPass,
        'pop3_port' => (int) ($preset['pop3_port'] ?? 995),
    ];
}

/**
 * @return array{saved:bool,detail:array<string,mixed>}
 */
function vk_email_sync_environment_to_database(PDO $pdo, bool $enableImapPoll, bool $applyVkitnetPreset): array
{
    $r = vk_email_resolve_from_environment();
    if ($applyVkitnetPreset) {
        $p = vk_mail_preset_vkitnet();
        $email = strtolower($r['smtp_user'] ?: $r['from_email']);
        if (str_ends_with($email, '@vkitnet.info')) {
            if ($r['smtp_host'] === '') {
                $r['smtp_host'] = $p['smtp_host'];
            }
            if ($r['smtp_port'] === 587 || $r['smtp_port'] === 0) {
                $r['smtp_port'] = $p['smtp_port'];
            }
            $r['smtp_secure'] = 'ssl';
            if ($r['imap_host'] === '') {
                $r['imap_host'] = $p['imap_host'];
            }
            $r['imap_port'] = $p['imap_port'];
        }
    }

    $detail = [
        'smtp_host' => $r['smtp_host'],
        'smtp_port' => $r['smtp_port'],
        'smtp_user' => $r['smtp_user'],
        'smtp_pass_masked' => vk_email_mask_secret($r['smtp_pass']),
        'from_email' => $r['from_email'],
        'imap_host' => $r['imap_host'],
        'imap_user' => $r['imap_user'],
    ];

    if ($r['smtp_host'] === '' || $r['from_email'] === '' || $r['smtp_user'] === '' || $r['smtp_pass'] === '') {
        return [
            'saved' => false,
            'detail' => array_merge($detail, [
                'error' => 'Incomplete SMTP env: need host, user, password, from (set VK_* or Laravel MAIL_* vars).',
            ]),
        ];
    }

    vk_smtp_settings_save($pdo, [
        'smtp_host' => $r['smtp_host'],
        'smtp_port' => $r['smtp_port'],
        'smtp_user' => $r['smtp_user'],
        'smtp_pass' => $r['smtp_pass'],
        'smtp_secure' => $r['smtp_secure'],
        'from_email' => $r['from_email'],
        'from_name' => $r['from_name'],
    ]);

    vk_imap_settings_save($pdo, [
        'imap_host' => $r['imap_host'],
        'imap_port' => $r['imap_port'],
        'imap_username' => $r['imap_user'],
        'imap_password' => $r['imap_pass'],
        'imap_poll_enabled' => $enableImapPoll ? '1' : '0',
    ]);

    return ['saved' => true, 'detail' => $detail];
}

/**
 * Try SMTP connect (PHPMailer); on failure retry TLS :587 if first was SSL :465.
 *
 * @return array{ok:bool,profile:?string,error:?string,tried:array<int,array{profile:string,ok:bool,error:?string}>}
 */
function vk_email_probe_smtp(array $cfg): array
{
    $autoload = ROOT_PATH . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return ['ok' => false, 'profile' => null, 'error' => 'PHPMailer not installed (composer install)', 'tried' => []];
    }
    require_once $autoload;

    $tries = [
        ['host' => $cfg['smtp_host'], 'port' => (int) $cfg['smtp_port'], 'secure' => $cfg['smtp_secure']],
    ];
    if ($cfg['smtp_secure'] === 'ssl' && (int) $cfg['smtp_port'] === 465) {
        $tries[] = ['host' => $cfg['smtp_host'], 'port' => 587, 'secure' => 'tls'];
    }
    if ($cfg['smtp_secure'] === 'tls' && (int) $cfg['smtp_port'] === 587) {
        $tries[] = ['host' => $cfg['smtp_host'], 'port' => 465, 'secure' => 'ssl'];
    }

    $tried = [];
    foreach ($tries as $try) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $profile = $try['secure'] . ':' . $try['port'];
        try {
            $mail->isSMTP();
            $mail->Host = (string) $try['host'];
            $mail->Port = (int) $try['port'];
            $mail->SMTPAuth = true;
            $mail->Username = (string) $cfg['smtp_user'];
            $mail->Password = (string) $cfg['smtp_pass'];
            $mail->SMTPSecure = $try['secure'] === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Timeout = 20;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ];
            if (!$mail->smtpConnect()) {
                throw new RuntimeException('smtpConnect() returned false');
            }
            $mail->smtpClose();
            $tried[] = ['profile' => $profile, 'ok' => true, 'error' => null];
            return ['ok' => true, 'profile' => $profile, 'error' => null, 'tried' => $tried];
        } catch (Throwable $e) {
            $err = $e->getMessage();
            $tried[] = ['profile' => $profile, 'ok' => false, 'error' => $err];
            try {
                $mail->smtpClose();
            } catch (Throwable) {
            }
        }
    }

    $last = $tried[count($tried) - 1] ?? null;

    return [
        'ok' => false,
        'profile' => null,
        'error' => $last['error'] ?? 'SMTP failed',
        'tried' => $tried,
    ];
}

/**
 * @return array{ok:bool,method:?string,message_count:int,error:?string,tried:array<int,array<string,mixed>>}
 */
function vk_email_probe_inbound(string $host, string $user, string $pass, int $pop3Port = 995): array
{
    $tried = [];
    if (!function_exists('imap_open')) {
        return [
            'ok' => false,
            'method' => null,
            'message_count' => 0,
            'error' => 'PHP imap extension not loaded — enable extension=imap in php.ini',
            'tried' => [],
        ];
    }

    $attempts = [
        ['label' => 'imap_ssl', 'mailbox' => sprintf('{%s:993/imap/ssl}INBOX', $host)],
        ['label' => 'imap_ssl_novalidate', 'mailbox' => sprintf('{%s:993/imap/ssl/novalidate-cert}INBOX', $host)],
        ['label' => 'pop3_ssl', 'mailbox' => sprintf('{%s:%d/pop3/ssl}INBOX', $host, max(1, $pop3Port))],
    ];

    foreach ($attempts as $a) {
        imap_errors();
        imap_alerts();
        $mb = @imap_open($a['mailbox'], $user, $pass, OP_READONLY);
        if ($mb !== false) {
            $n = imap_num_msg($mb);
            imap_close($mb);
            $tried[] = ['method' => $a['label'], 'ok' => true, 'error' => null];
            return [
                'ok' => true,
                'method' => $a['label'],
                'message_count' => (int) $n,
                'error' => null,
                'tried' => $tried,
            ];
        }
        $err = imap_last_error() ?: 'imap_open failed';
        $tried[] = ['method' => $a['label'], 'ok' => false, 'error' => $err];
    }

    return [
        'ok' => false,
        'method' => null,
        'message_count' => 0,
        'error' => 'IMAP and POP3 SSL attempts failed',
        'tried' => $tried,
    ];
}

function vk_email_setup_secret_ok(?string $token): bool
{
    $secret = vk_email_env_str('VK_SETUP_SECRET', '');
    if ($secret === '') {
        return false;
    }
    return hash_equals($secret, (string) $token);
}

function vk_email_auto_setup_completed(PDO $pdo): bool
{
    if (!vk_settings_table_ready($pdo)) {
        return false;
    }
    return vk_settings_get($pdo, 'email_auto_setup_done', '0') === '1';
}

function vk_email_auto_setup_set_flag(PDO $pdo, bool $done): void
{
    vk_settings_set($pdo, 'email_auto_setup_done', $done ? '1' : '0');
}

/**
 * @return array{smtp:array,messages:array<int,string>,overall_ok:bool}
 */
function vk_email_run_setup_validation(PDO $pdo, ?string $testSendTo, bool $sendTest, bool $skipInboundCheck = false): array
{
    vk_email_tables_migrate($pdo);
    $messages = [];
    $cfg = vk_smtp_settings_get($pdo);
    $smtpResult = ['configured' => $cfg['configured'], 'probe' => null, 'send_test' => null];

    if (!$cfg['configured'] || $cfg['smtp_pass'] === '') {
        $smtpResult['probe'] = ['ok' => false, 'error' => 'SMTP not configured'];
        return [
            'smtp' => $smtpResult,
            'inbound' => ['skipped' => true, 'reason' => 'smtp not ready'],
            'messages' => ['SMTP incomplete after sync'],
            'overall_ok' => false,
        ];
    }

    $probe = vk_email_probe_smtp([
        'smtp_host' => $cfg['smtp_host'],
        'smtp_port' => $cfg['smtp_port'],
        'smtp_user' => $cfg['smtp_user'],
        'smtp_pass' => $cfg['smtp_pass'],
        'smtp_secure' => $cfg['smtp_secure'],
    ]);
    $smtpResult['probe'] = $probe;
    if ($probe['ok']) {
        $messages[] = 'SMTP OK (' . ($probe['profile'] ?? '') . ')';
    } else {
        $messages[] = 'SMTP failed: ' . ($probe['error'] ?? '');
    }

    if ($probe['ok'] && $sendTest && $testSendTo !== null && filter_var($testSendTo, FILTER_VALIDATE_EMAIL)) {
        $smtpResult['send_test'] = vk_mailer_send(
            $pdo,
            $testSendTo,
            'VK IT — automated setup test',
            "This message confirms SMTP from the automated email setup.\r\n\r\nTime: " . date('c'),
            null,
            ['template_type' => 'email_auto_setup_test']
        );
        if ($smtpResult['send_test']['ok']) {
            $messages[] = 'Test email sent to ' . $testSendTo;
        } else {
            $messages[] = 'Test send failed: ' . ($smtpResult['send_test']['error'] ?? '');
        }
    }

    $imapCfg = vk_imap_settings_get($pdo);
    $inbound = ['configured' => $imapCfg['configured'], 'probe' => null, 'skipped' => false, 'reason' => null];
    if ($skipInboundCheck) {
        $inbound['skipped'] = true;
        $inbound['reason'] = 'skip_inbound_check requested';
        $messages[] = 'Inbound: skipped (skip_inbound_check)';
    } elseif (!$imapCfg['configured']) {
        $inbound['skipped'] = true;
        $inbound['reason'] = 'IMAP env incomplete';
        $messages[] = 'Inbound: skipped (no IMAP user/pass/host)';
    } else {
        $preset = vk_mail_preset_vkitnet();
        $popPort = (int) ($preset['pop3_port'] ?? 995);
        $inbound['probe'] = vk_email_probe_inbound(
            $imapCfg['imap_host'],
            $imapCfg['imap_user'],
            $imapCfg['imap_pass'],
            $popPort
        );
        if ($inbound['probe']['ok']) {
            $messages[] = 'Inbound OK via ' . ($inbound['probe']['method'] ?? '') . ' (messages: ' . ($inbound['probe']['message_count'] ?? 0) . ')';
        } else {
            $messages[] = 'Inbound failed: ' . ($inbound['probe']['error'] ?? '');
        }
    }

    $smtpOk = (bool) ($probe['ok'] ?? false);
    $sendOk = !$sendTest || !filter_var((string) $testSendTo, FILTER_VALIDATE_EMAIL)
        || (bool) ($smtpResult['send_test']['ok'] ?? true);
    $inOk = $skipInboundCheck
        || !($inbound['configured'] ?? false)
        || (bool) ($inbound['probe']['ok'] ?? false);

    $overall = $smtpOk && $sendOk && $inOk;

    return [
        'smtp' => $smtpResult,
        'inbound' => $inbound,
        'messages' => $messages,
        'overall_ok' => $overall,
    ];
}
