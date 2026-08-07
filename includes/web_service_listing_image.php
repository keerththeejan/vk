<?php
declare(strict_types=1);

/**
 * Public service card images: DB override → named assets → keyword fallback → legacy SVG.
 *
 * Drop WebP/JPG/PNG/SVG files into assets/images/services/ using stems like computer-repair.webp
 * for best performance; SVG fallbacks ship with the project.
 */

/**
 * @param array<string, mixed> $row Keys: name, slug; optional listing_image, cover_image
 * @return array{src: string, webp: ?string, alt: string, stem: string}
 */
function vk_web_service_card_image_meta(array $row): array
{
    $name = trim((string) ($row['name'] ?? ''));
    $alt = $name !== '' ? $name : 'Service';

    $try = [];
    if (!empty($row['listing_image'])) {
        $try[] = trim((string) $row['listing_image']);
    }
    if (!empty($row['cover_image'])) {
        $try[] = trim((string) $row['cover_image']);
    }
    foreach ($try as $rel) {
        if ($rel === '') {
            continue;
        }
        if (public_asset_file_exists($rel)) {
            return [
                'src' => $rel,
                'webp' => vk_web_service_try_webp_alternate($rel),
                'alt' => $alt,
                'stem' => 'custom',
            ];
        }
    }

    $stem = vk_web_service_infer_image_stem($row);
    $base = 'assets/images/services/' . $stem;
    foreach (['.webp', '.jpg', '.jpeg', '.png', '.svg'] as $ext) {
        $p = $base . $ext;
        if (public_asset_file_exists($p)) {
            return [
                'src' => $p,
                'webp' => str_ends_with(strtolower($p), '.webp') ? null : vk_web_service_try_webp_alternate($p),
                'alt' => $alt,
                'stem' => $stem,
            ];
        }
    }

    $legacy = vk_web_service_legacy_svg_for_stem($stem);

    return [
        'src' => $legacy,
        'webp' => vk_web_service_try_webp_alternate($legacy),
        'alt' => $alt,
        'stem' => $stem,
    ];
}

function vk_web_service_infer_image_stem(array $row): string
{
    $t = strtolower(trim((string) ($row['name'] ?? '') . ' ' . (string) ($row['slug'] ?? '')));
    if ($t === '') {
        return 'default';
    }
    if (str_contains($t, 'printer')) {
        return 'printer-service';
    }
    if (str_contains($t, 'cctv') || str_contains($t, 'camera') || str_contains($t, 'dvr')) {
        return 'cctv-installation';
    }
    if (str_contains($t, 'maintenance') || str_contains($t, 'amc')) {
        return 'maintenance';
    }
    if (str_contains($t, 'automobile') || str_contains($t, 'breakdown') || str_contains($t, 'vehicle')) {
        return 'automobile';
    }
    if (preg_match('/\bac\b/', $t) || str_contains($t, 'ac repair') || str_contains($t, 'air cond')) {
        return 'ac-repair';
    }
    if (str_contains($t, 'electrical') || str_contains($t, 'wiring') || str_contains($t, 'solar')) {
        return 'electrical';
    }
    if (str_contains($t, 'computer') || str_contains($t, 'laptop') || str_contains($t, 'desktop')) {
        return 'computer-repair';
    }

    return 'default';
}

function vk_web_service_legacy_svg_for_stem(string $stem): string
{
    return match ($stem) {
        'printer-service' => 'assets/images/services/svc-printer.svg',
        'cctv-installation' => 'assets/images/services/svc-cctv.svg',
        'maintenance' => 'assets/images/services/svc-maintenance.svg',
        'automobile' => 'assets/images/services/svc-automobile.svg',
        'ac-repair' => 'assets/images/services/svc-ac.svg',
        'electrical' => 'assets/images/services/svc-electrical.svg',
        'computer-repair', 'default' => 'assets/images/services/svc-computer.svg',
        default => 'assets/images/services/svc-computer.svg',
    };
}

function vk_web_service_try_webp_alternate(string $relativePath): ?string
{
    $lower = strtolower($relativePath);
    if (str_ends_with($lower, '.webp')) {
        return null;
    }
    $webp = preg_replace('/\.(svg|png|jpe?g)$/i', '.webp', $relativePath);
    if ($webp !== null && $webp !== $relativePath && public_asset_file_exists($webp)) {
        return $webp;
    }

    return null;
}
