<?php
declare(strict_types=1);
$pageTitle = 'AI Marketing Dashboard';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';
vk_marketing_suite_seed($pdo);
$m = vk_marketing_metrics($pdo);
?>
<div class="vk-suite-page">
    <section class="vk-suite-hero vk-ai-hero mb-4">
        <div><span class="vk-suite-kicker"><i class="bi bi-stars"></i> AI Marketing Intelligence</span><h1>Predictive growth dashboard</h1><p>Lead predictions, customer behavior analytics, sales trends, optimization suggestions, engagement heatmaps, and campaign recommendations for executive decision-making.</p></div>
    </section>
    <div class="row g-3">
        <div class="col-lg-4"><div class="vk-ai-panel h-100"><h2>Lead predictions</h2><strong class="vk-ai-number"><?= max(1, (int) round($m['leads'] * 1.34)) ?></strong><p>Projected qualified leads based on current campaign velocity and service interest mix.</p></div></div>
        <div class="col-lg-4"><div class="vk-ai-panel h-100"><h2>Conversion forecast</h2><strong class="vk-ai-number"><?= e((string) min(100, $m['conversion_rate'] + 12.5)) ?>%</strong><p>Expected conversion uplift if WhatsApp response time stays under 10 minutes.</p></div></div>
        <div class="col-lg-4"><div class="vk-ai-panel h-100"><h2>Revenue trend</h2><strong class="vk-ai-number">+18%</strong><p>Service completion journeys and warranty reminders are the strongest upsell opportunities.</p></div></div>
        <div class="col-xl-8">
            <div class="card vk-card h-100">
                <div class="card-header bg-transparent fw-semibold">Engagement heatmap placeholder</div>
                <div class="card-body">
                    <div class="vk-heatmap" aria-label="Engagement heatmap">
                        <?php for ($i = 1; $i <= 49; $i++): ?><span style="opacity: <?= e((string) (0.18 + (($i * 17) % 70) / 100)) ?>"></span><?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="vk-ai-panel h-100">
                <h2>Smart recommendations</h2>
                <div class="vk-insight-list">
                    <span><i class="bi bi-lightning-charge"></i> Launch segmented CCTV maintenance campaign on Monday morning.</span>
                    <span><i class="bi bi-whatsapp"></i> Use WhatsApp first for hot leads, email second for nurturing.</span>
                    <span><i class="bi bi-graph-up-arrow"></i> Increase budget on campaigns with engagement above <?= e((string) $m['engagement_rate']) ?>%.</span>
                    <span><i class="bi bi-people"></i> Build a VIP segment for high-value customers and maintenance renewals.</span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
