<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
$origin = rtrim(vk_site_origin(), '/');
$robots = "User-agent: *\nAllow: /\nDisallow: /modules/\nDisallow: /api/\nDisallow: /includes/\nSitemap: {$origin}" . BASE_URL . "/modules/seo/sitemap.php?format=xml\n";
if (($_GET['format'] ?? '') === 'txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo $robots;
    exit;
}
$pageTitle = 'Robots.txt Editor';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="vk-suite-page">
    <section class="vk-suite-hero mb-4">
        <div><span class="vk-suite-kicker"><i class="bi bi-robot"></i> Crawl Control</span><h1>Robots.txt Editor</h1><p>Generate crawler rules for public discovery while protecting internal enterprise modules and APIs.</p></div>
        <div class="vk-suite-hero-actions"><a class="btn btn-primary" href="?format=txt" target="_blank" rel="noopener">View robots.txt</a></div>
    </section>
    <div class="card vk-card">
        <div class="card-header bg-transparent fw-semibold">Generated robots.txt</div>
        <div class="card-body"><textarea class="form-control font-monospace" rows="12"><?= e($robots) ?></textarea><p class="small text-muted mt-3 mb-0">Deploy these rules to the web root robots endpoint when ready. This generator keeps admin routes private and sitemap discovery enabled.</p></div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
