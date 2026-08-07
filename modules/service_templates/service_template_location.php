<?php
declare(strict_types=1);

/**
 * Parse POST fields for service template map location.
 *
 * @return array{lat: ?float, lng: ?float, address: ?string, error: ?string}
 */
function st_service_template_parse_location(array $post): array
{
    $out = ['lat' => null, 'lng' => null, 'address' => null, 'error' => null];

    if (!empty($post['clear_service_location']) && (string) $post['clear_service_location'] === '1') {
        return $out;
    }

    $addr = trim((string) ($post['service_address'] ?? ''));
    if (strlen($addr) > 2000) {
        $out['error'] = 'Address must be 2000 characters or less.';

        return $out;
    }
    if ($addr !== '') {
        $out['address'] = $addr;
    }

    $latS = trim((string) ($post['service_latitude'] ?? ''));
    $lngS = trim((string) ($post['service_longitude'] ?? ''));

    if ($latS === '' && $lngS === '') {
        return $out;
    }

    if ($latS === '' || $lngS === '') {
        $out['error'] = 'Set both latitude and longitude, or leave both empty.';

        return $out;
    }

    if (!is_numeric($latS) || !is_numeric($lngS)) {
        $out['error'] = 'Invalid coordinate format.';

        return $out;
    }

    $lat = (float) $latS;
    $lng = (float) $lngS;

    if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
        $out['error'] = 'Coordinates out of valid range.';

        return $out;
    }

    $out['lat'] = round($lat, 8);
    $out['lng'] = round($lng, 8);

    return $out;
}

function st_service_template_has_coordinates(?array $row): bool
{
    if ($row === null) {
        return false;
    }
    $lat = $row['latitude'] ?? null;
    $lng = $row['longitude'] ?? null;
    if ($lat === null || $lng === null || $lat === '' || $lng === '') {
        return false;
    }

    return is_numeric($lat) && is_numeric($lng);
}

/** Format stored coordinate for HTML input display. */
/** @param mixed $v */
function st_format_coord_display($v): string
{
    if ($v === null || $v === '') {
        return '';
    }
    if (!is_numeric($v)) {
        return '';
    }

    return rtrim(rtrim(sprintf('%.8F', (float) $v), '0'), '.');
}
