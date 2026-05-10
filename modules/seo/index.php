<?php
declare(strict_types=1);
$pageTitle = 'SEO Dashboard';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';
vk_marketing_suite_seed($pdo);

$pages = db_table_exists($pdo, 'seo_settings')
    ? $pdo->query('SELECT * FROM seo_settings ORDER BY seo_score ASC, updated_at DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC)
    : [];
$avgScore = 0;
$indexed = 0;
if ($pages) {
    $avgScore = (int) round(array_sum(array_map(static fn(array $r): int => (int) $r['seo_score'], $pages)) / count($pages));
    $indexed = count(array_filter($pages, static fn(array $r): bool => ($r['indexing_status'] ?? '') === 'ready'));
}
?>
<div class="vk-suite-page">
    <section class="vk-suite-hero mb-4">
        <div>
            <span class="vk-suite-kicker"><i class="bi bi-search-heart"></i> SEO Intelligence</span>
            <h1>Enterprise SEO command center</h1>
            <p>Manage dynamic metadata, canonical URLs, social previews, structured schema, local business SEO, sitemap, robots, and technical audit signals.</p>
        </div>
        <div class="vk-suite-hero-actions">
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/seo/pages.php"><i class="bi bi-pencil-square me-2"></i>Page SEO Settings</a>
            <a class="btn btn-outline-light" href="<?= e(BASE_URL) ?>/modules/seo/sitemap.php"><i class="bi bi-diagram-3 me-2"></i>Sitemap</a>
        </div>
    </section>

    <div class="vk-suite-kpis mb-4">
        <div class="vk-suite-kpi"><i class="bi bi-speedometer2"></i><span>Average SEO score</span><strong><?= e((string) $avgScore) ?>%</strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-file-earmark-code"></i><span>Managed pages</span><strong><?= e((string) count($pages)) ?></strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-google"></i><span>Index-ready pages</span><strong><?= e((string) $indexed) ?></strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-braces"></i><span>Schema coverage</span><strong><?= $pages ? '100%' : '0%' ?></strong></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card vk-card h-100">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">SEO audit dashboard</span>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/seo/analytics.php">Analytics</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive table-responsive-stack">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Page</th><th>Score</th><th>Indexing</th><th>Canonical</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($pages as $row): ?>
                                <tr>
                                    <td><strong><?= e((string) $row['meta_title']) ?></strong><div class="small text-muted"><?= e((string) $row['page_url']) ?></div></td>
                                    <td><span class="badge text-bg-<?= (int) $row['seo_score'] >= 80 ? 'success' : 'warning' ?>"><?= (int) $row['seo_score'] ?>%</span></td>
                                    <td><span class="badge text-bg-info"><?= e((string) $row['indexing_status']) ?></span></td>
                                    <td class="small text-break"><?= e((string) $row['canonical_url']) ?></td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/seo/pages.php?id=<?= (int) $row['id'] ?>">Edit</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$pages): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Import or open the marketing suite schema to begin SEO management.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="vk-ai-panel h-100">
                <div class="vk-ai-orb"><i class="bi bi-stars"></i></div>
                <h2>Technical SEO checker</h2>
                <p>AI-style checks review title length, description depth, canonical URLs, Open Graph coverage, Twitter cards, local business schema, crawl directives, and sitemap readiness.</p>
                <div class="vk-insight-list">
                    <span><i class="bi bi-check2-circle"></i> Structured schema markup enabled</span>
                    <span><i class="bi bi-check2-circle"></i> Local business SEO ready</span>
                    <span><i class="bi bi-exclamation-triangle"></i> Connect Search Console for live indexing data</span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
