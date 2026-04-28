<?php
declare(strict_types=1);

/**
 * Dynamic XML sitemap (public pages). Enable pretty URLs via .htaccess (optional).
 */
require_once __DIR__ . '/includes/init.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$origin = vk_site_origin();
$base = rtrim($origin . (BASE_URL === '' ? '' : BASE_URL), '/');

$urls = [
    ['loc' => $base . '/index.php', 'changefreq' => 'weekly', 'priority' => '1.0'],
    ['loc' => $base . '/book.php', 'changefreq' => 'monthly', 'priority' => '0.9'],
    ['loc' => $base . '/track.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => $base . '/portfolio.php', 'changefreq' => 'weekly', 'priority' => '0.8'],
];

$rawLocations = (string) (vk_app_setting('seo_locations', 'jaffna,vavuniya,kilinochchi') ?? 'jaffna,vavuniya,kilinochchi');
$rawServiceSlugs = (string) (vk_app_setting('seo_service_slugs', 'computer-repair,laptop-repair,printer-repair,it-service') ?? 'computer-repair,laptop-repair,printer-repair,it-service');
$localLocations = array_values(array_filter(array_map(
    static fn(string $v): string => strtolower(trim($v)),
    explode(',', $rawLocations)
)));
$localServiceSlugs = array_values(array_filter(array_map(
    static fn(string $v): string => strtolower(trim($v)),
    explode(',', $rawServiceSlugs)
)));
if (!$localLocations) {
    $localLocations = ['jaffna', 'vavuniya', 'kilinochchi'];
}
if (!$localServiceSlugs) {
    $localServiceSlugs = ['computer-repair', 'laptop-repair', 'printer-repair', 'it-service'];
}
foreach ($localLocations as $ll) {
    foreach ($localServiceSlugs as $ls) {
        $urls[] = [
            'loc' => $base . '/service/' . $ls . '-' . $ll,
            'changefreq' => 'weekly',
            'priority' => '0.84',
        ];
    }
}

try {
    $pdo = db();
    if (db_table_exists($pdo, 'web_services')) {
        $svcs = $pdo->query('SELECT id, slug FROM web_services WHERE active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
        foreach ($svcs as $s) {
            $slug = trim((string) ($s['slug'] ?? ''));
            $loc = vk_site_origin() . vk_web_service_public_path($slug, (int) $s['id']);
            $urls[] = [
                'loc' => $loc,
                'changefreq' => 'weekly',
                'priority' => '0.85',
            ];
        }
    }
} catch (Throwable $e) {
    // keep static URLs only
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
    echo '<changefreq>' . htmlspecialchars($u['changefreq'], ENT_XML1, 'UTF-8') . '</changefreq>';
    echo '<priority>' . htmlspecialchars($u['priority'], ENT_XML1, 'UTF-8') . '</priority>';
    echo "</url>\n";
}
echo '</urlset>';
