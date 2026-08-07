<?php
declare(strict_types=1);

/**
 * Nearest active technician by Haversine (km). No external APIs.
 *
 * @return array{id:int,name:string,phone:?string,latitude:float,longitude:float,distance_km:float}|null
 */
function vk_find_nearest_technician(PDO $pdo, float $userLat, float $userLng, float $maxKm): ?array
{
    if (!db_table_exists($pdo, 'technicians')) {
        return null;
    }
    if (!db_column_exists($pdo, 'technicians', 'latitude')
        || !db_column_exists($pdo, 'technicians', 'longitude')) {
        return null;
    }

    $availClause = '';
    if (db_column_exists($pdo, 'technicians', 'availability')) {
        $availClause = " AND (t.availability IS NULL OR t.availability = 'available') ";
    }

    $sql = "
        SELECT * FROM (
            SELECT
                t.id,
                t.name,
                t.phone,
                t.latitude,
                t.longitude,
                (
                    6371 * ACOS(
                        LEAST(1.0, GREATEST(-1.0,
                            COS(RADIANS(?)) * COS(RADIANS(t.latitude)) * COS(RADIANS(t.longitude) - RADIANS(?))
                            + SIN(RADIANS(?)) * SIN(RADIANS(t.latitude))
                        ))
                    )
                ) AS distance_km
            FROM technicians t
            WHERE t.active = 1
              AND t.latitude IS NOT NULL
              AND t.longitude IS NOT NULL
              {$availClause}
        ) AS ranked
        WHERE ranked.distance_km <= ?
        ORDER BY ranked.distance_km ASC
        LIMIT 1
    ";

    $st = $pdo->prepare($sql);
    $st->execute([$userLat, $userLng, $userLat, $maxKm]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'phone' => $row['phone'] !== null && $row['phone'] !== '' ? (string) $row['phone'] : null,
        'latitude' => (float) $row['latitude'],
        'longitude' => (float) $row['longitude'],
        'distance_km' => (float) $row['distance_km'],
    ];
}

/**
 * Assign nearest technician to a booking row (same transaction as insert).
 *
 * @param array<string, mixed> $ctx Context for callers (not stored)
 *
 * @return array{technician: array, distance_km: float}|null
 */
function vk_assign_nearest_technician_to_booking(
    PDO $pdo,
    int $bookingId,
    float $userLat,
    float $userLng,
    array $ctx = []
): ?array {
    unset($ctx);

    if (!db_column_exists($pdo, 'web_bookings', 'assigned_technician_id')) {
        return null;
    }

    $maxKm = defined('VK_ASSIGN_MAX_RADIUS_KM') ? (float) VK_ASSIGN_MAX_RADIUS_KM : 20.0;
    if ($maxKm <= 0) {
        $maxKm = 20.0;
    }

    $nearest = vk_find_nearest_technician($pdo, $userLat, $userLng, $maxKm);
    if ($nearest === null) {
        return null;
    }

    if (db_column_exists($pdo, 'web_bookings', 'assignment_distance_km')) {
        $pdo->prepare(
            'UPDATE web_bookings SET assigned_technician_id = ?, assignment_distance_km = ? WHERE id = ?'
        )->execute([$nearest['id'], round($nearest['distance_km'], 3), $bookingId]);
    } else {
        $pdo->prepare('UPDATE web_bookings SET assigned_technician_id = ? WHERE id = ?')
            ->execute([$nearest['id'], $bookingId]);
    }

    return [
        'technician' => $nearest,
        'distance_km' => $nearest['distance_km'],
    ];
}
