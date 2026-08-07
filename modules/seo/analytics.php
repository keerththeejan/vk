<?php
declare(strict_types=1);
$pageTitle = 'SEO Analytics';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';
vk_marketing_suite_seed($pdo);
$rows = $pdo->query('SELECT * FROM seo_settings ORDER BY seo_score DESC')->fetchAll(PDO::FETCH_ASSOC);
$avg = $rows ? (int) round(array_sum(array_column($rows, 'seo_score')) / count($rows)) : 0;
?>
<div class="vk-suite-page">
    <section class="vk-suite-hero mb-4">
        <div><span class="vk-suite-kicker"><i class="bi bi-graph-up-arrow"></i> Search Analytics</span><h1>SEO Analytics</h1><p>Executive SEO scorecards, indexing readiness, structured data coverage, and technical SEO recommendations.</p></div>
    </section>
    <div class="vk-suite-kpis mb-4">
        <div class="vk-suite-kpi"><i class="bi bi-trophy"></i><span>SEO health</span><strong><?= $avg ?>%</strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-link-45deg"></i><span>Canonical coverage</span><strong><?= count(array_filter($rows, fn($r) => trim((string) $r['canonical_url']) !== '')) ?></strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-share"></i><span>Social cards</span><strong><?= count(array_filter($rows, fn($r) => trim((string) $r['og_title']) !== '')) ?></strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-google"></i><span>Index-ready</span><strong><?= count(array_filter($rows, fn($r) => ($r['indexing_status'] ?? '') === 'ready')) ?></strong></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card vk-card h-100">
                <div class="card-header bg-transparent fw-semibold">SEO score analytics</div>
                <div class="card-body">
                    <div class="vk-chart-placeholder">
                        <?php foreach ($rows as $row): ?>
                            <div class="vk-chart-row"><span><?= e((string) $row['page_key']) ?></span><div><b style="width: <?= (int) $row['seo_score'] ?>%"></b></div><strong><?= (int) $row['seo_score'] ?>%</strong></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="vk-ai-panel h-100">
                <h2>Smart recommendations</h2>
                <div class="vk-insight-list">
                    <span><i class="bi bi-stars"></i> Add Search Console credentials to replace simulated indexing states.</span>
                    <span><i class="bi bi-stars"></i> Keep meta descriptions between 90 and 170 characters for maximum SERP clarity.</span>
                    <span><i class="bi bi-stars"></i> Use LocalBusiness schema on service pages for regional discovery.</span>
                    <span><i class="bi bi-stars"></i> Refresh sitemap after publishing service templates or portfolio pages.</span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
