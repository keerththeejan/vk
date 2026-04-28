<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$origin = vk_site_origin();
$base = rtrim($origin . (BASE_URL === '' ? '' : BASE_URL), '/');
$sitemap = $base . '/sitemap.php';

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Crawl-delay: 2\n";
echo "\n";
echo 'Sitemap: ' . $sitemap . "\n";
