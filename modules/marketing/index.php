<?php
declare(strict_types=1);

$pageTitle = 'Marketing Dashboard';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';
vk_marketing_suite_seed($pdo);

$m = vk_marketing_metrics($pdo);
$campaigns = $pdo->query('SELECT * FROM marketing_campaigns ORDER BY FIELD(status, "active", "scheduled", "draft", "paused", "completed"), id DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);
$leads = $pdo->query('SELECT * FROM marketing_leads ORDER BY score DESC, id DESC LIMIT 6')->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');
$kpiEmailCamp = vk_count_table($pdo, 'marketing_campaigns', "channel = 'email'");
$kpiWaCamp = vk_count_table($pdo, 'marketing_campaigns', "channel = 'whatsapp'");
$kpiSmsCamp = vk_count_table($pdo, 'marketing_campaigns', "channel = 'sms'");
$kpiFailedMsgs = vk_count_table($pdo, 'whatsapp_logs', "status = 'failed'");
$kpiTodayMsgs = vk_count_table($pdo, 'whatsapp_logs', "DATE(created_at) = " . $pdo->quote($today));

$revenuePipeline = 0.0;
foreach ($leads as $leadSum) {
    $revenuePipeline += (float) ($leadSum['estimated_value'] ?? 0);
}

$vkMktInitials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(substr((string) ($parts[0] ?? 'L'), 0, 1) . substr((string) ($parts[1] ?? ''), 0, 1));
};

$vkMktStatusUi = static function (string $status): array {
    return match ($status) {
        'active' => ['key' => 'active', 'label' => 'Running'],
        'scheduled' => ['key' => 'scheduled', 'label' => 'Scheduled'],
        'paused' => ['key' => 'paused', 'label' => 'Paused'],
        'completed' => ['key' => 'completed', 'label' => 'Completed'],
        default => ['key' => 'draft', 'label' => 'Draft'],
    };
};

$vkMktChannelUi = static function (string $channel): array {
    $key = str_replace('-', '_', strtolower($channel));
    $label = ucwords(str_replace('_', ' ', $channel));
    return ['key' => $key, 'label' => $label];
};

$vkMktCampaignType = static function (array $c): string {
    $obj = strtolower((string) ($c['objective'] ?? ''));
    if (str_contains($obj, 'renewal') || str_contains($obj, 'warranty')) {
        return 'retention';
    }
    if (str_contains($obj, 'lead') || str_contains($obj, 'awareness')) {
        return 'acquisition';
    }
    return 'engagement';
};

$vkMktFormatMoney = static function ($n): string {
    return formatCurrency((float) $n);
};

$vkMktFormatDate = static function (?string $iso): string {
    if ($iso === null || $iso === '') {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d M Y', $ts) : '—';
};

$vkMktMetrics = static function (array $c, float $openRate): array {
    $reach = max(0, (int) ($c['reach'] ?? 0));
    $engagement = max(0, (int) ($c['engagement'] ?? 0));
    $conversions = max(0, (int) ($c['conversions'] ?? 0));
    $budget = (float) ($c['budget'] ?? 0);
    $spent = $reach > 0 ? min($budget, round($budget * ($engagement / max($reach, 1)), 2)) : 0.0;
    $opened = (string) ($c['channel'] ?? '') === 'email'
        ? (int) round($engagement * ($openRate / 100))
        : (int) round($engagement * 0.35);
    $clicked = max($conversions, (int) round($engagement * 0.08));
    return [
        'spent' => $spent,
        'sent' => $reach,
        'delivered' => $engagement,
        'opened' => $opened,
        'clicked' => $clicked,
        'conversion' => $conversions,
    ];
};

$topCampaigns = $campaigns;
usort($topCampaigns, static fn ($a, $b) => ((int) ($b['conversions'] ?? 0)) <=> ((int) ($a['conversions'] ?? 0)));
$topCampaigns = array_slice($topCampaigns, 0, 5);
$maxConv = max(1, ...array_map(static fn ($c) => (int) ($c['conversions'] ?? 0), $topCampaigns ?: [['conversions' => 1]]));

$displayCount = count($campaigns);
$totalCampaigns = (int) ($m['campaigns'] ?? 0);

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/marketing-dashboard.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/marketing-dashboard.js');
$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link href="' . e(base_url('assets/css/marketing-dashboard.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="vkMktApp" class="vk-mkt-admin vk-mkt-skeleton"
     data-total-campaigns="<?= (int) $totalCampaigns ?>"
     role="application" aria-label="Marketing automation dashboard">

<header class="vk-mkt-header">
    <div class="vk-mkt-header-inner">
        <div>
            <h1 class="vk-mkt-title"><i class="bi bi-megaphone me-1" aria-hidden="true"></i> Marketing Hub</h1>
            <p class="vk-mkt-subtitle d-none d-md-block">Enterprise campaign automation · email, WhatsApp, SMS &amp; multi-channel CRM</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="vk-mkt-btn vk-mkt-btn-ghost" href="<?= e(BASE_URL) ?>/modules/marketing/leads.php"><i class="bi bi-funnel"></i><span class="d-none d-sm-inline">Leads</span></a>
            <a class="vk-mkt-btn vk-mkt-btn-ghost" href="<?= e(BASE_URL) ?>/modules/marketing/ai.php"><i class="bi bi-stars"></i><span class="d-none d-sm-inline">AI Insights</span></a>
        </div>
    </div>
</header>

<div class="vk-mkt-kpi-grid" role="region" aria-label="Marketing KPIs">
    <div class="vk-mkt-kpi vk-mkt-kpi-blue">
        <div class="vk-mkt-kpi-icon"><i class="bi bi-broadcast"></i></div>
        <div class="vk-mkt-kpi-body">
            <span class="vk-mkt-kpi-label">Total Campaigns</span>
            <span class="vk-mkt-kpi-value" data-count-to="<?= (int) $m['campaigns'] ?>">0</span>
            <span class="vk-mkt-kpi-trend">All channels</span>
        </div>
    </div>
    <div class="vk-mkt-kpi vk-mkt-kpi-blue">
        <div class="vk-mkt-kpi-icon"><i class="bi bi-envelope"></i></div>
        <div class="vk-mkt-kpi-body">
            <span class="vk-mkt-kpi-label">Email</span>
            <span class="vk-mkt-kpi-value" data-count-to="<?= (int) $kpiEmailCamp ?>">0</span>
            <span class="vk-mkt-kpi-trend">Campaigns</span>
        </div>
    </div>
    <div class="vk-mkt-kpi vk-mkt-kpi-green">
        <div class="vk-mkt-kpi-icon"><i class="bi bi-whatsapp"></i></div>
        <div class="vk-mkt-kpi-body">
            <span class="vk-mkt-kpi-label">WhatsApp</span>
            <span class="vk-mkt-kpi-value" data-count-to="<?= (int) $kpiWaCamp ?>">0</span>
            <span class="vk-mkt-kpi-trend">Campaigns</span>
        </div>
    </div>
    <div class="vk-mkt-kpi vk-mkt-kpi-orange">
        <div class="vk-mkt-kpi-icon"><i class="bi bi-chat-dots"></i></div>
        <div class="vk-mkt-kpi-body">
            <span class="vk-mkt-kpi-label">SMS</span>
            <span class="vk-mkt-kpi-value" data-count-to="<?= (int) $kpiSmsCamp ?>">0</span>
            <span class="vk-mkt-kpi-trend">Campaigns</span>
        </div>
    </div>
    <div class="vk-mkt-kpi vk-mkt-kpi-teal">
        <div class="vk-mkt-kpi-icon"><i class="bi bi-people"></i></div>
        <div class="vk-mkt-kpi-body">
            <span class="vk-mkt-kpi-label">Contacts</span>
            <span class="vk-mkt-kpi-value" data-count-to="<?= (int) $m['leads'] ?>">0</span>
            <span class="vk-mkt-kpi-trend">Total leads</span>
        </div>
    </div>
    <div class="vk-mkt-kpi vk-mkt-kpi-green">
        <div class="vk-mkt-kpi-icon"><i class="bi bi-lightning-charge"></i></div>
        <div class="vk-mkt-kpi-body">
            <span class="vk-mkt-kpi-label">Active</span>
            <span class="vk-mkt-kpi-value" data-count-to="<?= (int) $m['active_campaigns'] ?>">0</span>
            <span class="vk-mkt-kpi-trend">Scheduled + running</span>
        </div>
    </div>
    <div class="vk-mkt-kpi vk-mkt-kpi-purple">
        <div class="vk-mkt-kpi-icon"><i class="bi bi-bullseye"></i></div>
        <div class="vk-mkt-kpi-body">
            <span class="vk-mkt-kpi-label">Conversion</span>
            <span class="vk-mkt-kpi-value" data-count-to="<?= (float) $m['conversion_rate'] ?>" data-count-float="1" data-count-suffix="%">0</span>
            <span class="vk-mkt-kpi-trend">Campaign avg</span>
        </div>
    </div>
    <div class="vk-mkt-kpi vk-mkt-kpi-green">
        <div class="vk-mkt-kpi-icon"><i class="bi bi-currency-rupee"></i></div>
        <div class="vk-mkt-kpi-body">
            <span class="vk-mkt-kpi-label">Revenue</span>
            <span class="vk-mkt-kpi-value"><?= e($vkMktFormatMoney($revenuePipeline)) ?></span>
            <span class="vk-mkt-kpi-trend">Top leads pipeline</span>
        </div>
    </div>
    <div class="vk-mkt-kpi vk-mkt-kpi-blue">
        <div class="vk-mkt-kpi-icon"><i class="bi bi-send"></i></div>
        <div class="vk-mkt-kpi-body">
            <span class="vk-mkt-kpi-label">Sent Today</span>
            <span class="vk-mkt-kpi-value" data-count-to="<?= (int) $kpiTodayMsgs ?>">0</span>
            <span class="vk-mkt-kpi-trend">WhatsApp logs</span>
        </div>
    </div>
    <div class="vk-mkt-kpi vk-mkt-kpi-red">
        <div class="vk-mkt-kpi-icon"><i class="bi bi-x-octagon"></i></div>
        <div class="vk-mkt-kpi-body">
            <span class="vk-mkt-kpi-label">Failed</span>
            <span class="vk-mkt-kpi-value" data-count-to="<?= (int) $kpiFailedMsgs ?>">0</span>
            <span class="vk-mkt-kpi-trend">Delivery errors</span>
        </div>
    </div>
</div>

<form class="vk-mkt-toolbar" id="vkMktFilterForm" role="search" aria-label="Campaign filters" onsubmit="return false;">
    <div class="vk-mkt-toolbar-mobile-head d-lg-none">
        <span class="fw-semibold small"><i class="bi bi-sliders"></i> Filters</span>
        <button type="button" class="vk-mkt-btn vk-mkt-btn-icon" data-bs-toggle="collapse" data-bs-target="#vkMktToolbarCollapse" aria-expanded="false" aria-controls="vkMktToolbarCollapse" aria-label="Toggle filters"><i class="bi bi-chevron-down"></i></button>
    </div>
    <div class="collapse vk-mkt-toolbar-collapse" id="vkMktToolbarCollapse">
        <div class="vk-mkt-toolbar-inner">
            <div class="vk-mkt-search-wrap">
                <i class="bi bi-search vk-mkt-search-ico" aria-hidden="true"></i>
                <input type="search" id="vkMktSearch" class="form-control vk-mkt-ctl" placeholder="Search campaigns… ( / )" autocomplete="off" aria-label="Search campaigns">
            </div>
            <select id="vkMktFilterType" class="form-select vk-mkt-ctl vk-mkt-ctl-sm" aria-label="Campaign type">
                <option value="">Type</option>
                <option value="acquisition">Acquisition</option>
                <option value="retention">Retention</option>
                <option value="engagement">Engagement</option>
            </select>
            <select id="vkMktFilterStatus" class="form-select vk-mkt-ctl vk-mkt-ctl-sm" aria-label="Campaign status">
                <option value="">Status</option>
                <option value="draft">Draft</option>
                <option value="scheduled">Scheduled</option>
                <option value="active">Running</option>
                <option value="paused">Paused</option>
                <option value="completed">Completed</option>
            </select>
            <select id="vkMktFilterChannel" class="form-select vk-mkt-ctl vk-mkt-ctl-sm" aria-label="Channel">
                <option value="">Channel</option>
                <option value="email">Email</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="sms">SMS</option>
                <option value="facebook">Facebook</option>
                <option value="instagram">Instagram</option>
                <option value="multi_channel">Multi channel</option>
            </select>
            <input type="date" class="form-control vk-mkt-ctl vk-mkt-ctl-date" disabled title="Date filter via campaigns page" aria-label="Date from">
            <input type="date" class="form-control vk-mkt-ctl vk-mkt-ctl-date" disabled aria-label="Date to">
            <select class="form-select vk-mkt-ctl vk-mkt-ctl-sm" disabled title="Assign user in campaigns"><option>Assigned</option></select>
            <select class="form-select vk-mkt-ctl vk-mkt-ctl-sm" disabled title="Branch not in schema"><option>Branch</option></select>
            <select class="form-select vk-mkt-ctl vk-mkt-ctl-xs" disabled aria-label="Rows per page"><option>8</option></select>
            <div class="vk-mkt-toolbar-btns">
                <a class="vk-mkt-btn vk-mkt-btn-primary" href="<?= e(BASE_URL) ?>/modules/marketing/campaigns.php"><i class="bi bi-plus-lg"></i><span class="d-none d-xl-inline">New Campaign</span></a>
                <a class="vk-mkt-btn" href="<?= e(BASE_URL) ?>/modules/marketing/leads.php"><i class="bi bi-upload"></i><span class="d-none d-xl-inline">Import</span></a>
                <a class="vk-mkt-btn" href="<?= e(BASE_URL) ?>/modules/marketing/export.php?type=campaigns"><i class="bi bi-download"></i><span class="d-none d-xl-inline">Export</span></a>
                <button type="button" class="vk-mkt-btn" id="vkMktRefresh" aria-label="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
                <button type="button" class="vk-mkt-btn" id="vkMktReset" aria-label="Reset filters"><i class="bi bi-x-lg"></i></button>
                <a class="vk-mkt-btn" href="<?= e(BASE_URL) ?>/modules/marketing/ai.php"><i class="bi bi-graph-up"></i><span class="d-none d-xl-inline">Analytics</span></a>
            </div>
        </div>
    </div>
</form>

<div class="vk-mkt-insights">
    <h3><i class="bi bi-stars text-primary"></i> AI campaign insights</h3>
    <div class="vk-mkt-insight-list">
        <div class="vk-mkt-insight-item"><i class="bi bi-arrow-up-right-circle"></i> Warranty audiences are the highest intent segment this week.</div>
        <div class="vk-mkt-insight-item"><i class="bi bi-whatsapp"></i> WhatsApp follow-ups should be scheduled within 8 minutes of web inquiry.</div>
        <div class="vk-mkt-insight-item"><i class="bi bi-envelope-heart"></i> Use service-completion email journeys to request reviews and referrals.</div>
        <div class="vk-mkt-insight-item"><i class="bi bi-graph-up"></i> CCTV maintenance offers show strong B2B conversion potential.</div>
    </div>
</div>

<div class="row g-2 mb-2">
    <div class="col-12 col-xl-8">
        <div class="vk-mkt-panel" id="vkMktCampaignPanel">
            <div class="vk-mkt-panel-head">
                <h2 class="vk-mkt-panel-title">Campaign performance</h2>
                <a class="vk-mkt-btn vk-mkt-btn-ghost" href="<?= e(BASE_URL) ?>/modules/marketing/campaigns.php">Manage all</a>
            </div>
            <div class="vk-mkt-panel-scroll vk-mkt-desktop-only">
                <table class="table vk-mkt-table mb-0" id="vkMktTable">
                    <thead>
                        <tr>
                            <th class="vk-mkt-sticky-col" style="width:34px"><input type="checkbox" class="form-check-input" id="vkMktSelectAll" aria-label="Select all campaigns"></th>
                            <th style="width:160px">Campaign</th>
                            <th class="vk-mkt-col-hide-lg" style="width:88px">Type</th>
                            <th style="width:96px">Channel</th>
                            <th style="width:120px">Audience</th>
                            <th style="width:88px">Status</th>
                            <th class="vk-mkt-col-hide-md" style="width:72px">Budget</th>
                            <th class="vk-mkt-col-hide-md" style="width:72px">Spent</th>
                            <th style="width:56px">Sent</th>
                            <th class="vk-mkt-col-hide-lg" style="width:64px">Delivered</th>
                            <th class="vk-mkt-col-hide-lg" style="width:56px">Opened</th>
                            <th class="vk-mkt-col-hide-md" style="width:56px">Clicked</th>
                            <th style="width:64px">Conv.</th>
                            <th class="vk-mkt-col-hide-md" style="width:88px">Created</th>
                            <th style="width:200px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($campaigns as $c): ?>
                    <?php
                        $st = $vkMktStatusUi((string) ($c['status'] ?? 'draft'));
                        $ch = $vkMktChannelUi((string) ($c['channel'] ?? 'multi_channel'));
                        $type = $vkMktCampaignType($c);
                        $met = $vkMktMetrics($c, (float) ($m['email_open_rate'] ?? 0));
                        $searchBlob = strtolower(implode(' ', [
                            (string) ($c['campaign_name'] ?? ''),
                            (string) ($c['objective'] ?? ''),
                            (string) ($c['segment'] ?? ''),
                            (string) ($c['channel'] ?? ''),
                            (string) ($c['status'] ?? ''),
                            $type,
                        ]));
                    ?>
                    <tr data-campaign-id="<?= (int) $c['id'] ?>"
                        data-search="<?= e($searchBlob) ?>"
                        data-status="<?= e((string) ($c['status'] ?? '')) ?>"
                        data-channel="<?= e((string) ($c['channel'] ?? '')) ?>"
                        data-type="<?= e($type) ?>">
                        <td class="vk-mkt-sticky-col"><input type="checkbox" class="form-check-input vk-mkt-row-check" aria-label="Select campaign"></td>
                        <td>
                            <div class="vk-mkt-campaign-name vk-mkt-highlight-target"><?= e((string) $c['campaign_name']) ?></div>
                            <div class="vk-mkt-campaign-sub"><?= e((string) $c['objective']) ?></div>
                        </td>
                        <td class="vk-mkt-col-hide-lg"><span class="vk-mkt-tag"><?= e(ucfirst($type)) ?></span></td>
                        <td><span class="vk-mkt-badge vk-mkt-ch-<?= e($ch['key']) ?>"><?= e($ch['label']) ?></span></td>
                        <td><span class="vk-mkt-campaign-sub vk-mkt-highlight-target"><?= e((string) $c['segment']) ?></span></td>
                        <td><span class="vk-mkt-badge vk-mkt-st-<?= e($st['key']) ?>"><?= e($st['label']) ?></span></td>
                        <td class="vk-mkt-col-hide-md vk-mkt-num"><?= e($vkMktFormatMoney($c['budget'] ?? 0)) ?></td>
                        <td class="vk-mkt-col-hide-md vk-mkt-num vk-mkt-amt"><?= e($vkMktFormatMoney($met['spent'])) ?></td>
                        <td class="vk-mkt-num"><?= e(number_format($met['sent'])) ?></td>
                        <td class="vk-mkt-col-hide-lg vk-mkt-num"><?= e(number_format($met['delivered'])) ?></td>
                        <td class="vk-mkt-col-hide-lg vk-mkt-num"><?= e(number_format($met['opened'])) ?></td>
                        <td class="vk-mkt-col-hide-md vk-mkt-num"><?= e(number_format($met['clicked'])) ?></td>
                        <td class="vk-mkt-num"><strong><?= e(number_format($met['conversion'])) ?></strong></td>
                        <td class="vk-mkt-col-hide-md vk-mkt-date"><?= e($vkMktFormatDate((string) ($c['created_at'] ?? ''))) ?></td>
                        <td>
                            <div class="vk-mkt-actions">
                                <a class="vk-mkt-act" href="<?= e(BASE_URL) ?>/modules/marketing/campaigns.php" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></a>
                                <a class="vk-mkt-act" href="<?= e(BASE_URL) ?>/modules/marketing/campaigns.php" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a class="vk-mkt-act" href="<?= e(BASE_URL) ?>/modules/marketing/campaigns.php" data-bs-toggle="tooltip" title="Launch"><i class="bi bi-play-fill"></i></a>
                                <span class="vk-mkt-act" data-bs-toggle="tooltip" title="Pause (manage in campaigns)" tabindex="0" role="button" aria-disabled="true"><i class="bi bi-pause-fill"></i></span>
                                <a class="vk-mkt-act" href="<?= e(BASE_URL) ?>/modules/marketing/ai.php" data-bs-toggle="tooltip" title="Analytics"><i class="bi bi-bar-chart"></i></a>
                                <a class="vk-mkt-act" href="<?= e(BASE_URL) ?>/modules/marketing/leads.php" data-bs-toggle="tooltip" title="Audience"><i class="bi bi-people"></i></a>
                                <span class="vk-mkt-act" data-bs-toggle="tooltip" title="Duplicate (coming soon)" tabindex="0" role="button" aria-disabled="true"><i class="bi bi-copy"></i></span>
                                <span class="vk-mkt-act vk-mkt-act-danger" data-bs-toggle="tooltip" title="Delete (not available)" tabindex="0" role="button" aria-disabled="true"><i class="bi bi-trash"></i></span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="vk-mkt-empty" id="vkMktTableEmpty"<?= $campaigns ? ' hidden' : '' ?>>
                    <div class="vk-mkt-empty-icon"><i class="bi bi-megaphone"></i></div>
                    <h2>No marketing campaigns found</h2>
                    <p>Adjust filters or create a new campaign to get started.</p>
                    <a class="vk-mkt-btn vk-mkt-btn-primary" href="<?= e(BASE_URL) ?>/modules/marketing/campaigns.php"><i class="bi bi-plus-lg"></i> Create Campaign</a>
                </div>
            </div>

            <div class="vk-mkt-mobile-only">
                <?php if (!$campaigns): ?>
                <div class="vk-mkt-empty">
                    <div class="vk-mkt-empty-icon"><i class="bi bi-megaphone"></i></div>
                    <h2>No marketing campaigns found</h2>
                    <a class="vk-mkt-btn vk-mkt-btn-primary mt-2" href="<?= e(BASE_URL) ?>/modules/marketing/campaigns.php">Create Campaign</a>
                </div>
                <?php endif; ?>
                <?php foreach ($campaigns as $c): ?>
                <?php
                    $st = $vkMktStatusUi((string) ($c['status'] ?? 'draft'));
                    $ch = $vkMktChannelUi((string) ($c['channel'] ?? 'multi_channel'));
                    $type = $vkMktCampaignType($c);
                    $met = $vkMktMetrics($c, (float) ($m['email_open_rate'] ?? 0));
                    $searchBlob = strtolower(implode(' ', [(string) $c['campaign_name'], (string) $c['channel'], (string) $c['status'], $type]));
                ?>
                <article class="vk-mkt-mobile-card" data-campaign-id="<?= (int) $c['id'] ?>" data-search="<?= e($searchBlob) ?>" data-status="<?= e((string) ($c['status'] ?? '')) ?>" data-channel="<?= e((string) ($c['channel'] ?? '')) ?>" data-type="<?= e($type) ?>">
                    <div class="vk-mkt-mobile-card-head">
                        <div>
                            <div class="vk-mkt-campaign-name"><?= e((string) $c['campaign_name']) ?></div>
                            <div class="vk-mkt-campaign-sub"><?= e((string) $c['segment']) ?></div>
                        </div>
                        <span class="vk-mkt-badge vk-mkt-st-<?= e($st['key']) ?>"><?= e($st['label']) ?></span>
                    </div>
                    <div class="d-flex gap-2 mb-2">
                        <span class="vk-mkt-badge vk-mkt-ch-<?= e($ch['key']) ?>"><?= e($ch['label']) ?></span>
                        <span class="vk-mkt-tag"><?= e(ucfirst($type)) ?></span>
                    </div>
                    <dl class="vk-mkt-mobile-card-grid">
                        <dt>Sent</dt><dd><?= e(number_format($met['sent'])) ?></dd>
                        <dt>Delivered</dt><dd><?= e(number_format($met['delivered'])) ?></dd>
                        <dt>Clicked</dt><dd><?= e(number_format($met['clicked'])) ?></dd>
                        <dt>Conversions</dt><dd><?= e(number_format($met['conversion'])) ?></dd>
                        <dt>Budget</dt><dd><?= e($vkMktFormatMoney($c['budget'] ?? 0)) ?></dd>
                        <dt>Spent</dt><dd class="vk-mkt-amt"><?= e($vkMktFormatMoney($met['spent'])) ?></dd>
                    </dl>
                    <div class="vk-mkt-actions">
                        <a class="vk-mkt-act" href="<?= e(BASE_URL) ?>/modules/marketing/campaigns.php" aria-label="View"><i class="bi bi-eye"></i></a>
                        <a class="vk-mkt-act" href="<?= e(BASE_URL) ?>/modules/marketing/ai.php" aria-label="Analytics"><i class="bi bi-bar-chart"></i></a>
                        <a class="vk-mkt-act" href="<?= e(BASE_URL) ?>/modules/marketing/leads.php" aria-label="Audience"><i class="bi bi-people"></i></a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <footer class="vk-mkt-footer">
                <span id="vkMktVisibleCount">Showing 1–<?= (int) $displayCount ?> of <?= (int) $totalCampaigns ?></span>
                <nav class="vk-mkt-page-nav" aria-label="Campaign pagination">
                    <span class="vk-mkt-page-link is-active" aria-current="page">1</span>
                    <?php if ($totalCampaigns > $displayCount): ?>
                    <a class="vk-mkt-page-link" href="<?= e(BASE_URL) ?>/modules/marketing/campaigns.php" aria-label="View all campaigns"><i class="bi bi-chevron-right"></i></a>
                    <?php endif; ?>
                </nav>
            </footer>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="vk-mkt-panel">
            <div class="vk-mkt-panel-head"><h2 class="vk-mkt-panel-title">Analytics snapshot</h2></div>
            <div class="vk-mkt-analytics">
                <div class="vk-mkt-chart-card">
                    <h3 class="vk-mkt-chart-title">Campaign reach</h3>
                    <div class="vk-mkt-chart-metric" data-count-to="<?= (int) $m['reach'] ?>">0</div>
                    <div class="vk-mkt-bar-row"><span class="vk-mkt-bar-label">Engagement</span><div class="vk-mkt-bar-track"><div class="vk-mkt-bar-fill" data-width="<?= min(100, (int) $m['engagement_rate']) ?>"></div></div><span class="vk-mkt-bar-val"><?= e((string) $m['engagement_rate']) ?>%</span></div>
                </div>
                <div class="vk-mkt-chart-card">
                    <h3 class="vk-mkt-chart-title">Open rate</h3>
                    <div class="vk-mkt-chart-metric" data-count-to="<?= (float) $m['email_open_rate'] ?>" data-count-float="1" data-count-suffix="%">0</div>
                    <div class="vk-mkt-bar-row"><span class="vk-mkt-bar-label">Email</span><div class="vk-mkt-bar-track"><div class="vk-mkt-bar-fill" data-width="<?= min(100, (float) $m['email_open_rate']) ?>"></div></div><span class="vk-mkt-bar-val"><?= e((string) $m['email_open_rate']) ?>%</span></div>
                </div>
                <div class="vk-mkt-chart-card">
                    <h3 class="vk-mkt-chart-title">Conversions</h3>
                    <div class="vk-mkt-chart-metric" data-count-to="<?= (int) $m['conversions'] ?>">0</div>
                    <div class="vk-mkt-bar-row"><span class="vk-mkt-bar-label">Rate</span><div class="vk-mkt-bar-track"><div class="vk-mkt-bar-fill" data-width="<?= min(100, (float) $m['conversion_rate']) ?>"></div></div><span class="vk-mkt-bar-val"><?= e((string) $m['conversion_rate']) ?>%</span></div>
                </div>
                <div class="vk-mkt-chart-card">
                    <h3 class="vk-mkt-chart-title">Top campaigns</h3>
                    <?php foreach ($topCampaigns as $tc): ?>
                    <?php $pct = round(((int) ($tc['conversions'] ?? 0) / $maxConv) * 100); ?>
                    <div class="vk-mkt-bar-row">
                        <span class="vk-mkt-bar-label" title="<?= e((string) $tc['campaign_name']) ?>"><?= e(mb_strlen((string) $tc['campaign_name']) > 12 ? mb_substr((string) $tc['campaign_name'], 0, 10, 'UTF-8') . '…' : (string) $tc['campaign_name']) ?></span>
                        <div class="vk-mkt-bar-track"><div class="vk-mkt-bar-fill" data-width="<?= (int) $pct ?>"></div></div>
                        <span class="vk-mkt-bar-val"><?= (int) ($tc['conversions'] ?? 0) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="vk-mkt-chart-card">
                    <h3 class="vk-mkt-chart-title">WhatsApp delivery</h3>
                    <div class="vk-mkt-chart-metric" data-count-to="<?= (float) ($m['whatsapp_delivery_rate'] ?? 0) ?>" data-count-float="1" data-count-suffix="%">0</div>
                    <div class="vk-mkt-bar-row"><span class="vk-mkt-bar-label">Delivered</span><div class="vk-mkt-bar-track"><div class="vk-mkt-bar-fill" data-width="<?= min(100, (float) ($m['whatsapp_delivery_rate'] ?? 0)) ?>"></div></div><span class="vk-mkt-bar-val"><?= e((string) ($m['whatsapp_delivery_rate'] ?? 0)) ?>%</span></div>
                </div>
                <div class="vk-mkt-chart-card">
                    <h3 class="vk-mkt-chart-title">ROI estimate</h3>
                    <div class="vk-mkt-chart-metric"><?= e($vkMktFormatMoney($revenuePipeline)) ?></div>
                    <div class="vk-mkt-bar-row"><span class="vk-mkt-bar-label">Pipeline</span><div class="vk-mkt-bar-track"><div class="vk-mkt-bar-fill" data-width="<?= min(100, (int) ($m['conversion_rate'] * 2)) ?>"></div></div><span class="vk-mkt-bar-val"><?= e((string) $m['conversion_rate']) ?>%</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="vk-mkt-panel">
    <div class="vk-mkt-panel-head">
        <h2 class="vk-mkt-panel-title">Contact management</h2>
        <a class="vk-mkt-btn vk-mkt-btn-ghost" href="<?= e(BASE_URL) ?>/modules/marketing/leads.php">View pipeline</a>
    </div>
    <div class="vk-mkt-contacts">
        <?php if (!$leads): ?>
        <div class="vk-mkt-empty py-4">
            <p class="mb-2">No contacts in pipeline yet.</p>
            <a class="vk-mkt-btn vk-mkt-btn-primary" href="<?= e(BASE_URL) ?>/modules/marketing/leads.php">Add lead</a>
        </div>
        <?php endif; ?>
        <?php foreach ($leads as $lead): ?>
        <?php $stage = (string) ($lead['stage'] ?? 'new'); ?>
        <a class="vk-mkt-contact-row text-decoration-none text-reset" href="<?= e(BASE_URL) ?>/modules/marketing/leads.php">
            <div class="vk-mkt-avatar" aria-hidden="true"><?= e($vkMktInitials((string) $lead['lead_name'])) ?></div>
            <div class="vk-mkt-contact-body">
                <div class="d-flex justify-content-between gap-2">
                    <span class="vk-mkt-contact-name"><?= e((string) $lead['lead_name']) ?></span>
                    <span class="vk-mkt-score"><?= (int) ($lead['score'] ?? 0) ?></span>
                </div>
                <div class="vk-mkt-contact-sub">
                    <?= e((string) ($lead['phone'] ?: '—')) ?> · <?= e((string) ($lead['email'] ?: '—')) ?>
                </div>
                <div class="vk-mkt-contact-meta mt-1">
                    <span class="vk-mkt-badge vk-mkt-lead-st-<?= e($stage) ?>"><?= e($stage) ?></span>
                    <span class="vk-mkt-tag"><?= e((string) $lead['source']) ?></span>
                    <?php if (!empty($lead['service_interest'])): ?>
                    <span class="vk-mkt-tag"><?= e((string) $lead['service_interest']) ?></span>
                    <?php endif; ?>
                    <span class="vk-mkt-amt ms-auto"><?= e($vkMktFormatMoney($lead['estimated_value'] ?? 0)) ?></span>
                </div>
                <div class="vk-mkt-contact-sub mt-1">
                    Last contact: <?= e($vkMktFormatDate((string) ($lead['last_touch_at'] ?? $lead['created_at'] ?? ''))) ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/marketing-dashboard.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
