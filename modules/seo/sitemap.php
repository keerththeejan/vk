<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';
require_admin();
$pdo = db();
vk_marketing_suite_seed($pdo);

$rows = db_table_exists($pdo, 'seo_settings')
    ? $pdo->query("SELECT page_url, updated_at FROM seo_settings WHERE robots_directive NOT LIKE '%noindex%' ORDER BY page_key")->fetchAll(PDO::FETCH_ASSOC)
    : [];
$origin = rtrim(vk_site_origin(), '/');
$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($rows as $row) {
    $loc = (string) $row['page_url'];
    if (!str_starts_with($loc, 'http')) {
        $loc = $origin . '/' . ltrim($loc, '/');
    }
    $lastmod = substr((string) ($row['updated_at'] ?? date('Y-m-d')), 0, 10);
    $xml .= "  <url><loc>" . e($loc) . "</loc><lastmod>" . e($lastmod) . "</lastmod><changefreq>weekly</changefreq><priority>0.80</priority></url>\n";
}
$xml .= "</urlset>\n";

if (($_GET['format'] ?? '') === 'xml') {
    header('Content-Type: application/xml; charset=utf-8');
    echo $xml;
    exit;
}

$pageTitle = 'Sitemap Manager';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="vk-suite-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><span class="vk-suite-kicker"><i class="bi bi-diagram-3"></i> XML Sitemap</span><h1 class="h3 mb-0">Sitemap Manager</h1></div>
        <a class="btn btn-primary" href="?format=xml" target="_blank" rel="noopener"><i class="bi bi-code-slash me-2"></i>View XML</a>
    </div>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="vk-ai-panel h-100">
                <h2>Generator status</h2>
                <p>Sitemap output is generated from managed SEO pages and automatically excludes pages marked noindex.</p>
                <div class="vk-insight-list">
                    <span><i class="bi bi-check2-circle"></i><?= count($rows) ?> crawlable URLs</span>
                    <span><i class="bi bi-check2-circle"></i>XML sitemap protocol ready</span>
                    <span><i class="bi bi-broadcast"></i>Submit to Google Search Console after deployment</span>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card vk-card">
                <div class="card-header bg-transparent fw-semibold">Generated sitemap preview</div>
                <div class="card-body"><pre class="vk-code-preview mb-0"><?= e($xml) ?></pre></div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
