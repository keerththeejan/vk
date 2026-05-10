<?php
declare(strict_types=1);
$pageTitle = 'Marketing Dashboard';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';
vk_marketing_suite_seed($pdo);
$m = vk_marketing_metrics($pdo);
$campaigns = $pdo->query('SELECT * FROM marketing_campaigns ORDER BY FIELD(status, "active", "scheduled", "draft", "paused", "completed"), id DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);
$leads = $pdo->query('SELECT * FROM marketing_leads ORDER BY score DESC, id DESC LIMIT 6')->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="vk-suite-page">
    <section class="vk-suite-hero mb-4">
        <div>
            <span class="vk-suite-kicker"><i class="bi bi-megaphone"></i> Digital Marketing OS</span>
            <h1>AI-ready campaign command center</h1>
            <p>Coordinate Facebook, Instagram, WhatsApp, email, SMS, lead generation, customer segmentation, conversion analytics, and campaign performance from one premium workspace.</p>
        </div>
        <div class="vk-suite-hero-actions">
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/marketing/campaigns.php"><i class="bi bi-plus-lg me-2"></i>Campaigns</a>
            <a class="btn btn-outline-light" href="<?= e(BASE_URL) ?>/modules/marketing/leads.php"><i class="bi bi-funnel me-2"></i>Lead pipeline</a>
        </div>
    </section>
    <div class="vk-suite-kpis mb-4">
        <div class="vk-suite-kpi"><i class="bi bi-person-plus"></i><span>Total leads</span><strong><?= e((string) $m['leads']) ?></strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-bullseye"></i><span>Conversion rate</span><strong><?= e((string) $m['conversion_rate']) ?>%</strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-broadcast"></i><span>Campaign reach</span><strong><?= e(number_format((int) $m['reach'])) ?></strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-heart-pulse"></i><span>Engagement</span><strong><?= e((string) $m['engagement_rate']) ?>%</strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-whatsapp"></i><span>WhatsApp clicks</span><strong><?= e((string) $m['whatsapp_clicks']) ?></strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-envelope-open"></i><span>Email open rate</span><strong><?= e((string) $m['email_open_rate']) ?>%</strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-lightning-charge"></i><span>Active campaigns</span><strong><?= e((string) $m['active_campaigns']) ?></strong></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card vk-card h-100">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-semibold">Campaign analytics dashboard</span><a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/marketing/campaigns.php">Manage</a></div>
                <div class="card-body p-0">
                    <div class="table-responsive table-responsive-stack">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Campaign</th><th>Channel</th><th>Status</th><th>Reach</th><th>Conversions</th></tr></thead>
                            <tbody>
                            <?php foreach ($campaigns as $c): ?>
                                <tr>
                                    <td><strong><?= e((string) $c['campaign_name']) ?></strong><div class="small text-muted"><?= e((string) $c['objective']) ?></div></td>
                                    <td><span class="badge text-bg-info"><?= e(str_replace('_', ' ', (string) $c['channel'])) ?></span></td>
                                    <td><span class="badge text-bg-<?= vk_marketing_status_badge((string) $c['status']) ?>"><?= e((string) $c['status']) ?></span></td>
                                    <td><?= e(number_format((int) $c['reach'])) ?></td>
                                    <td><?= e((string) (int) $c['conversions']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="vk-ai-panel h-100">
                <div class="vk-ai-orb"><i class="bi bi-stars"></i></div>
                <h2>AI campaign insights</h2>
                <div class="vk-insight-list">
                    <span><i class="bi bi-arrow-up-right-circle"></i> Warranty audiences are the highest intent segment this week.</span>
                    <span><i class="bi bi-whatsapp"></i> WhatsApp follow-ups should be scheduled within 8 minutes of web inquiry.</span>
                    <span><i class="bi bi-envelope-heart"></i> Use service-completion email journeys to request reviews and referrals.</span>
                    <span><i class="bi bi-graph-up"></i> CCTV maintenance offers show strong B2B conversion potential.</span>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card vk-card">
                <div class="card-header bg-transparent fw-semibold">Lead tracking</div>
                <div class="card-body">
                    <div class="vk-pipeline">
                        <?php foreach ($leads as $lead): ?>
                            <a class="vk-pipeline-card" href="<?= e(BASE_URL) ?>/modules/marketing/leads.php">
                                <span class="badge text-bg-<?= vk_lead_stage_badge((string) $lead['stage']) ?>"><?= e((string) $lead['stage']) ?></span>
                                <strong><?= e((string) $lead['lead_name']) ?></strong>
                                <small><?= e((string) $lead['service_interest']) ?> · Score <?= (int) $lead['score'] ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
