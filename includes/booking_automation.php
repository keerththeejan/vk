<?php
declare(strict_types=1);

require_once __DIR__ . '/assign_technician.php';
if (function_exists('vk_bootstrap_module')) {
    vk_bootstrap_module('whatsapp_bridge');
} else {
    require_once __DIR__ . '/whatsapp_bridge.php';
}

/**
 * @param array<string, string> $serviceTypes
 *
 * @return array{
 *   assigned: bool,
 *   technician_name: ?string,
 *   distance_km: ?float,
 *   user_message: ?string,
 *   whatsapp_sent: bool
 * }
 */
function vk_booking_build_whatsapp_body(
    string $bookingNumber,
    string $customerName,
    string $customerPhone,
    string $serviceLabel,
    string $problem,
    ?string $preferredDate,
    ?float $lat,
    ?float $lng,
    float $distanceKm
): string {
    $map = ($lat !== null && $lng !== null)
        ? 'https://www.google.com/maps?q=' . rawurlencode((string) $lat . ',' . (string) $lng)
        : '—';

    $pref = $preferredDate !== null && $preferredDate !== '' ? $preferredDate : '—';

    $lines = [
        'New Service Booking 🚀',
        '',
        'Customer: ' . $customerName,
        'Phone: ' . $customerPhone,
        'Service: ' . $serviceLabel,
        'Problem: ' . $problem,
        '',
        'Location:',
        $map,
        '',
        'Distance: ' . number_format($distanceKm, 2, '.', '') . ' km',
        '',
        'Date: ' . $pref,
        '',
        'Booking ID: ' . $bookingNumber,
    ];

    return vk_whatsapp_sanitize_message(implode("\n", $lines));
}

/**
 * Run assign + WhatsApp after booking insert (assign inside same transaction).
 *
 * @param array<string, string> $serviceTypes
 *
 * @return array{assign: ?array, user_notice: ?string}
 */
function vk_booking_automation_after_insert(
    PDO $pdo,
    int $bookingDbId,
    string $bookingNumber,
    string $customerName,
    string $customerPhone,
    string $serviceKey,
    string $problem,
    ?string $preferredDate,
    ?float $lat,
    ?float $lng,
    array $serviceTypes
): array {
    $out = ['assign' => null, 'user_notice' => null];

    if ($lat === null || $lng === null) {
        $out['user_notice'] = null;

        return $out;
    }

    if (!db_column_exists($pdo, 'technicians', 'latitude')
        || !db_column_exists($pdo, 'technicians', 'longitude')) {
        return $out;
    }

    $assign = vk_assign_nearest_technician_to_booking($pdo, $bookingDbId, $lat, $lng);
    $out['assign'] = $assign;

    if ($assign === null) {
        $max = defined('VK_ASSIGN_MAX_RADIUS_KM') ? (float) VK_ASSIGN_MAX_RADIUS_KM : 20.0;
        $out['user_notice'] = 'No technician is available within ' . $max . ' km right now — our team will assign someone manually.';

        return $out;
    }

    return $out;
}

/**
 * Fire WhatsApp notifications after DB commit (external IO).
 *
 * @param array<string, string> $serviceTypes
 * @param ?array{technician: array, distance_km: float}|null $assign
 */
function vk_booking_automation_notify_whatsapp(
    string $bookingNumber,
    string $customerName,
    string $customerPhone,
    string $serviceKey,
    string $problem,
    ?string $preferredDate,
    ?float $lat,
    ?float $lng,
    array $serviceTypes,
    ?array $assign
): bool {
    $serviceLabel = $serviceTypes[$serviceKey] ?? $serviceKey;
    $sent = false;

    if ($assign === null || $lat === null || $lng === null) {
        return false;
    }

    $tech = $assign['technician'];
    $dist = (float) $assign['distance_km'];
    $body = vk_booking_build_whatsapp_body(
        $bookingNumber,
        $customerName,
        $customerPhone,
        $serviceLabel,
        $problem,
        $preferredDate,
        $lat,
        $lng,
        $dist
    );

    $techPhone = $tech['phone'] ?? null;
    if ($techPhone) {
        $norm = vk_whatsapp_normalize_phone($techPhone);
        if ($norm) {
            $sent = vk_whatsapp_bridge_send($norm, $body) || $sent;
        }
    }

    $admin = defined('VK_WHATSAPP_ADMIN_PHONE') ? trim((string) VK_WHATSAPP_ADMIN_PHONE) : '';
    if ($admin !== '') {
        $an = vk_whatsapp_normalize_phone($admin);
        if ($an) {
            $sent = vk_whatsapp_bridge_send($an, $body) || $sent;
        }
    }

    return $sent;
}
