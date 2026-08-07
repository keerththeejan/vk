<?php
declare(strict_types=1);

/**
 * Normalize phone to digits for WhatsApp (default: Sri Lanka mobile patterns).
 */
function vk_whatsapp_normalize_phone(string $raw): ?string
{
    $d = preg_replace('/\D+/', '', $raw);
    if ($d === null || $d === '') {
        return null;
    }
    // 077xxxxxxx → 9477xxxxxxx
    if (strlen($d) === 10 && str_starts_with($d, '07')) {
        $d = '94' . substr($d, 1);
    } elseif (strlen($d) === 9 && str_starts_with($d, '7')) {
        $d = '94' . $d;
    } elseif (strlen($d) === 10 && str_starts_with($d, '7')) {
        $d = '94' . $d;
    }

    if (strlen($d) < 8 || strlen($d) > 15) {
        return null;
    }

    return $d;
}

/**
 * Strip control characters and cap length for safe outbound text.
 */
function vk_whatsapp_sanitize_message(string $text): string
{
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    if (strlen($text) > 4000) {
        $text = mb_substr($text, 0, 3997, 'UTF-8') . '...';
    }

    return $text;
}

/**
 * POST JSON to local Node bridge (whatsapp-web.js / similar). Returns true if HTTP 2xx.
 */
function vk_whatsapp_bridge_send(string $phoneDigits, string $message): bool
{
    $url = defined('VK_WHATSAPP_BRIDGE_URL') ? trim((string) VK_WHATSAPP_BRIDGE_URL) : '';
    if ($url === '') {
        return false;
    }

    $secret = defined('VK_WHATSAPP_BRIDGE_SECRET') ? (string) VK_WHATSAPP_BRIDGE_SECRET : '';
    $message = vk_whatsapp_sanitize_message($message);

    $payload = json_encode(
        [
            'phone' => $phoneDigits,
            'text' => $message,
            'secret' => $secret,
        ],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    if (!function_exists('curl_init')) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 12,
            ],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return $res !== false;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return false;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return $code >= 200 && $code < 300 && $body !== false;
}
