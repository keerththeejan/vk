<?php
declare(strict_types=1);

/**
 * IMAP polling (SSL/TLS), inbound persistence, and auto-responder.
 * Requires PHP ext-imap and openssl.
 */

/**
 * @return array<string, string>
 */
function vk_imap_header_lines_to_map(string $headerRaw): array
{
    $out = [];
    $current = '';
    foreach (preg_split("/\r\n|\n|\r/", $headerRaw) ?: [] as $line) {
        if ($line === '') {
            continue;
        }
        if (preg_match('/^[ \t]/', $line) && $current !== '') {
            $out[$current] .= ' ' . trim($line);
            continue;
        }
        if (preg_match('/^([^:]+):\s*(.*)$/', $line, $m)) {
            $current = strtolower(trim($m[1]));
            $out[$current] = trim($m[2]);
        }
    }
    return $out;
}

function vk_imap_extract_from_email(string $fromHeader): string
{
    $fromHeader = trim($fromHeader);
    if ($fromHeader === '') {
        return '';
    }
    if (function_exists('imap_rfc822_parse_adrlist')) {
        $list = @imap_rfc822_parse_adrlist($fromHeader, 'localhost');
        if (is_array($list) && isset($list[0])) {
            $o = $list[0];
            $mb = (string) ($o->mailbox ?? '');
            $host = (string) ($o->host ?? '');
            if ($mb !== '' && $host !== '' && $host !== 'MISSING_DOMAIN' && str_contains($host, '.')) {
                return vk_email_normalize_sender($mb . '@' . $host);
            }
        }
    }
    if (preg_match('/<([^>]+@[^>]+)>/', $fromHeader, $m)) {
        return vk_email_normalize_sender($m[1]);
    }
    if (preg_match('/([\w\.\-\+]+@[\w\.\-]+\.[a-z]{2,})/i', $fromHeader, $m)) {
        return vk_email_normalize_sender($m[1]);
    }
    return '';
}

function vk_imap_extract_from_name(string $fromHeader): string
{
    $fromHeader = trim($fromHeader);
    if (preg_match('/^(.+?)\s*<[^>]+>$/', $fromHeader, $m)) {
        return trim($m[1], " \t\"'");
    }
    return '';
}

/**
 * @param object|array<mixed> $part
 */
function vk_imap_collect_plain($mb, int $uid, $part, string $section, bool $preferPlain, string &$plain, string &$html): void
{
    if (is_object($part)) {
        $type = (int) ($part->type ?? 0);
        $subtype = strtolower((string) ($part->subtype ?? ''));
        if ($type === 0) {
            $body = imap_fetchbody($mb, $uid, $section === '' ? '1' : $section, FT_UID);
            if ($body === false || $body === '') {
                return;
            }
            $enc = (int) ($part->encoding ?? 0);
            if ($enc === 3) {
                $dec = base64_decode($body, true);
                $body = $dec !== false ? $dec : $body;
            } elseif ($enc === 4) {
                $body = quoted_printable_decode($body);
            }
            if ($subtype === 'plain' && $plain === '') {
                $plain = (string) $body;
            } elseif ($subtype === 'html' && $html === '') {
                $html = (string) $body;
            }
        }
        if (!empty($part->parts) && is_array($part->parts)) {
            foreach ($part->parts as $i => $sub) {
                $num = $i + 1;
                $next = $section === '' ? (string) $num : $section . '.' . $num;
                vk_imap_collect_plain($mb, $uid, $sub, $next, $preferPlain, $plain, $html);
            }
        }
    }
}

function vk_imap_fetch_best_body($mb, int $uid): string
{
    $plain = '';
    $html = '';
    $st = @imap_fetchstructure($mb, $uid, FT_UID);
    if ($st) {
        if (!empty($st->parts)) {
            foreach ($st->parts as $i => $sub) {
                vk_imap_collect_plain($mb, $uid, $sub, (string) ($i + 1), true, $plain, $html);
            }
        } else {
            vk_imap_collect_plain($mb, $uid, $st, '1', true, $plain, $html);
        }
    }
    if ($plain !== '') {
        return $plain;
    }
    if ($html !== '') {
        return trim(strip_tags($html));
    }
    $fallback = imap_body($mb, $uid, FT_UID | FT_PEEK);
    return is_string($fallback) ? trim($fallback) : '';
}

/**
 * @return array{fetched:int,stored:int,autoreplies:int,skipped:int,errors:array<int,string>,notices:array<int,string>}
 */
function vk_email_imap_poll(PDO $pdo, int $limit = 40): array
{
    $stats = ['fetched' => 0, 'stored' => 0, 'autoreplies' => 0, 'skipped' => 0, 'errors' => [], 'notices' => []];
    if (!function_exists('imap_open')) {
        $stats['errors'][] = 'PHP IMAP extension not enabled';
        return $stats;
    }

    vk_email_tables_migrate($pdo);
    $imap = vk_imap_settings_get($pdo);
    if (!$imap['configured']) {
        $stats['errors'][] = 'IMAP not configured';
        return $stats;
    }
    if (!$imap['imap_enabled']) {
        $stats['errors'][] = 'IMAP polling disabled (enable in admin or set VK_IMAP_ENABLED=1)';
        return $stats;
    }

    $host = $imap['imap_host'];
    $port = $imap['imap_port'];
    $user = $imap['imap_user'];
    $pass = $imap['imap_pass'];
    $folder = 'INBOX';
    $mbStr = sprintf('{%s:%d/imap/ssl}%s', $host, $port, $folder);

    $mb = @imap_open($mbStr, $user, $pass, OP_READONLY);
    if ($mb === false) {
        $stats['errors'][] = 'IMAP connect failed: ' . (imap_last_error() ?: 'unknown');
        return $stats;
    }
    imap_errors();
    imap_alerts();

    $lastUid = (int) (vk_settings_get($pdo, 'imap_last_uid', '0') ?: 0);
    $uids = imap_search($mb, 'ALL', SE_UID);
    if ($uids === false) {
        imap_close($mb);
        return $stats;
    }

    sort($uids, SORT_NUMERIC);
    if ($lastUid === 0 && $uids !== []) {
        $maxExisting = max($uids);
        vk_settings_set($pdo, 'imap_last_uid', (string) $maxExisting);
        imap_close($mb);
        $stats['notices'][] = 'First run: IMAP UID watermark set to avoid replying to old mail. New messages will be processed from the next run.';

        return $stats;
    }

    $processed = 0;
    foreach ($uids as $uid) {
        $uid = (int) $uid;
        if ($uid <= $lastUid) {
            continue;
        }
        if ($processed >= $limit) {
            break;
        }
        $processed++;
        $stats['fetched']++;

        $hdrRaw = imap_fetchheader($mb, $uid, FT_UID);
        if (!is_string($hdrRaw)) {
            $hdrRaw = '';
        }
        $hdrMap = vk_imap_header_lines_to_map($hdrRaw);
        $fromLine = (string) ($hdrMap['from'] ?? '');
        $fromEmail = vk_imap_extract_from_email($fromLine);
        $fromName = vk_imap_extract_from_name($fromLine) ?: $fromEmail;
        $ov = imap_fetch_overview($mb, (string) $uid, FT_UID);
        $rawSubj = '';
        if (is_array($ov) && isset($ov[0]) && is_object($ov[0])) {
            $rawSubj = (string) ($ov[0]->subject ?? '');
        }
        $subject = function_exists('imap_utf8') ? (string) imap_utf8($rawSubj) : $rawSubj;
        $subject = function_exists('vk_email_sanitize_subject') ? vk_email_sanitize_subject($subject) : $subject;
        $toLine = (string) ($hdrMap['to'] ?? '');
        $toEmail = vk_imap_extract_from_email($toLine) ?: (string) vk_smtp_settings_get($pdo)['from_email'];

        $dateHdr = (string) ($hdrMap['date'] ?? '');
        $msgDate = $dateHdr !== '' ? date('Y-m-d H:i:s', strtotime($dateHdr) ?: time()) : date('Y-m-d H:i:s');

        $bodyText = vk_imap_fetch_best_body($mb, $uid);
        if (mb_strlen($bodyText, 'UTF-8') > 65535) {
            $bodyText = mb_substr($bodyText, 0, 65535, 'UTF-8');
        }

        try {
            $st = $pdo->prepare(
                'INSERT INTO email_inbound
                (imap_folder, imap_uid, from_email, from_name, to_email, subject, body_text, message_date)
                VALUES (?,?,?,?,?,?,?,?)'
            );
            $st->execute([$folder, $uid, $fromEmail, $fromName, $toEmail, $subject, $bodyText, $msgDate]);
            $stats['stored']++;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                $stats['skipped']++;
            } else {
                $stats['errors'][] = 'UID ' . $uid . ': ' . $e->getMessage();
            }
            vk_settings_set($pdo, 'imap_last_uid', (string) max($lastUid, $uid));
            $lastUid = max($lastUid, $uid);
            continue;
        }

        $skipReason = null;
        $ar = vk_autoresponder_settings_get($pdo);
        if (!$ar['enabled']) {
            $skipReason = 'autoresponder_disabled';
        } elseif ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $skipReason = 'invalid_from';
        } elseif (vk_email_should_ignore_autoreply($fromEmail, $subject, $hdrMap)) {
            $skipReason = 'system_or_bulk';
        } else {
            $ourBox = vk_email_normalize_sender((string) vk_smtp_settings_get($pdo)['from_email']);
            if ($ourBox !== '' && $fromEmail === $ourBox) {
                $skipReason = 'self_address';
            }
        }
        if ($skipReason === null) {
            $rate = vk_email_autoresponder_rate_check($pdo, $fromEmail);
            if (!$rate['allowed']) {
                $skipReason = (string) ($rate['reason'] ?? 'rate_limited');
            }
        }

        if ($skipReason !== null) {
            $pdo->prepare('UPDATE email_inbound SET autoresponder_skip_reason = ? WHERE imap_folder = ? AND imap_uid = ?')
                ->execute([$skipReason, $folder, $uid]);
            $stats['skipped']++;
        } else {
            $subj = function_exists('vk_email_sanitize_subject') ? vk_email_sanitize_subject($ar['subject']) : $ar['subject'];
            $send = vk_mailer_send($pdo, $fromEmail, $subj, $ar['body'], $fromName ?: null, ['template_type' => 'autoresponder']);
            if ($send['ok']) {
                vk_email_autoresponder_rate_mark($pdo, $fromEmail);
                $pdo->prepare('UPDATE email_inbound SET autoresponder_sent = 1 WHERE imap_folder = ? AND imap_uid = ?')
                    ->execute([$folder, $uid]);
                $stats['autoreplies']++;
            } else {
                $err = (string) ($send['error'] ?? 'send_failed');
                $pdo->prepare('UPDATE email_inbound SET autoresponder_skip_reason = ? WHERE imap_folder = ? AND imap_uid = ?')
                    ->execute(['send_failed:' . mb_substr($err, 0, 180, 'UTF-8'), $folder, $uid]);
                $stats['errors'][] = 'Auto-reply UID ' . $uid . ': ' . $err;
            }
        }

        vk_settings_set($pdo, 'imap_last_uid', (string) $uid);
        $lastUid = $uid;
    }

    imap_close($mb);
    return $stats;
}
