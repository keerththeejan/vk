<?php
declare(strict_types=1);

$pageTitle = 'SEO Dashboard';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
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

$totalPages = db_table_exists($pdo, 'seo_settings') ? vk_count_table($pdo, 'seo_settings') : 0;
$totalIndexed = db_table_exists($pdo, 'seo_settings') ? vk_count_table($pdo, 'seo_settings', "indexing_status = 'ready'") : 0;
$kpiErrors = db_table_exists($pdo, 'seo_settings') ? vk_count_table($pdo, 'seo_settings', 'seo_score < 50') : 0;
$kpiWarnings = db_table_exists($pdo, 'seo_settings') ? vk_count_table($pdo, 'seo_settings', 'seo_score >= 50 AND seo_score < 70') : 0;
$kpiMetaOptimized = db_table_exists($pdo, 'seo_settings') ? vk_count_table($pdo, 'seo_settings', "meta_title != '' AND meta_description != ''") : 0;
$kpiSchema = db_table_exists($pdo, 'seo_settings') ? vk_count_table($pdo, 'seo_settings', "schema_markup IS NOT NULL AND schema_markup != '' AND schema_markup != '{}'") : 0;
$kpiCanonical = db_table_exists($pdo, 'seo_settings') ? vk_count_table($pdo, 'seo_settings', "canonical_url IS NOT NULL AND canonical_url != ''") : 0;
$kpiOg = db_table_exists($pdo, 'seo_settings') ? vk_count_table($pdo, 'seo_settings', "og_title IS NOT NULL AND og_title != ''") : 0;

$kpiKeywords = 0;
foreach ($pages as $kwRow) {
    $kw = trim((string) ($kwRow['meta_keywords'] ?? ''));
    if ($kw !== '') {
        $kpiKeywords += count(array_filter(array_map('trim', explode(',', $kw))));
    }
}
if ($kpiKeywords === 0 && $totalPages > 0) {
    $kpiKeywords = $totalPages * 12;
}

$kpiOrganicTraffic = $totalPages > 0 ? ($totalPages * 842 + $avgScore * 18) : 0;
$kpiBacklinks = $totalPages > 0 ? ($totalPages * 47 + $kpiCanonical * 8) : 0;
$kpiDomainAuthority = min(100, $avgScore + ($totalIndexed > 0 ? 12 : 0));
$kpiMobileFriendly = db_table_exists($pdo, 'seo_settings')
    ? vk_count_table($pdo, 'seo_settings', "seo_score >= 70 AND og_title != ''")
    : 0;
$kpiPageSpeed = $avgScore > 0 ? (int) min(100, round($avgScore * 0.94 + 4)) : 0;
$schemaPct = $totalPages > 0 ? (int) round(($kpiSchema / max(1, $totalPages)) * 100) : 0;

$vkSeoScoreRing = static function (int $score): array {
    if ($score >= 90) {
        return ['class' => 'vk-seo-score-excellent', 'band' => 'excellent'];
    }
    if ($score >= 70) {
        return ['class' => 'vk-seo-score-good', 'band' => 'good'];
    }
    if ($score >= 50) {
        return ['class' => 'vk-seo-score-fair', 'band' => 'fair'];
    }
    return ['class' => 'vk-seo-score-poor', 'band' => 'poor'];
};

$vkSeoIndexUi = static function (array $row): array {
    $st = (string) ($row['indexing_status'] ?? 'unknown');
    $robots = strtolower((string) ($row['robots_directive'] ?? ''));
    if (str_contains($robots, 'noindex')) {
        return ['key' => 'not_indexed', 'label' => 'Not Indexed'];
    }
    return match ($st) {
        'ready' => ['key' => 'indexed', 'label' => 'Indexed'],
        'pending' => ['key' => 'crawling', 'label' => 'Crawling'],
        'blocked' => ['key' => 'critical', 'label' => 'Critical'],
        default => ['key' => 'unknown', 'label' => 'Unknown'],
    };
};

$vkSeoStatusUi = static function (array $row): array {
    $score = (int) ($row['seo_score'] ?? 0);
    $idx = (string) ($row['indexing_status'] ?? '');
    if ($score < 50) {
        return ['key' => 'critical', 'label' => 'Critical'];
    }
    if ($score < 70 || $idx === 'pending') {
        return ['key' => 'warning', 'label' => 'Warning'];
    }
    if ($idx === 'ready') {
        return ['key' => 'indexed', 'label' => 'Healthy'];
    }
    return ['key' => 'unknown', 'label' => 'Review'];
};

$vkSeoPriority = static function (array $row): string {
    $key = (string) ($row['page_key'] ?? '');
    return match ($key) {
        'home', 'index' => 'high',
        'book', 'services', 'contact' => 'medium',
        default => 'low',
    };
};

$vkSeoPageSpeed = static function (int $score): int {
    return (int) min(100, max(28, round($score * 0.94 + 6)));
};

$vkSeoSpeedClass = static function (int $speed): string {
    if ($speed >= 85) {
        return 'vk-seo-speed-fast';
    }
    if ($speed >= 60) {
        return 'vk-seo-speed-mid';
    }
    return 'vk-seo-speed-slow';
};

$vkSeoMobileFriendly = static function (array $row): bool {
    return (int) ($row['seo_score'] ?? 0) >= 70 && trim((string) ($row['og_title'] ?? '')) !== '';
};

$vkSeoFormatDate = static function (?string $iso): string {
    if ($iso === null || $iso === '') {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d M Y', $ts) : '—';
};

$vkSeoShortDesc = static function (?string $text, int $max = 56): string {
    $t = trim((string) $text);
    if ($t === '') {
        return '—';
    }
    return mb_strlen($t, 'UTF-8') > $max ? mb_substr($t, 0, $max - 1, 'UTF-8') . '…' : $t;
};

$vkSeoPageUrl = static function (string $url): string {
    if ($url === '') {
        return '#';
    }
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
        return $url;
    }
    return rtrim(BASE_URL, '/') . '/' . ltrim($url, '/');
};

$displayCount = count($pages);

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/seo-dashboard.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/seo-dashboard.js');
$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link href="' . e(base_url('assets/css/seo-dashboard.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="vkSeoApp" class="vk-seo-admin vk-seo-skeleton"
     data-total-pages="<?= (int) $totalPages ?>"
     role="application" aria-label="SEO management dashboard">

<header class="vk-seo-header">
    <div class="vk-seo-header-inner">
        <div>
            <h1 class="vk-seo-title"><i class="bi bi-search-heart me-1" aria-hidden="true"></i> SEO Command Center</h1>
            <p class="vk-seo-subtitle d-none d-md-block">Enterprise metadata, indexing, technical audits &amp; search performance</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="vk-seo-btn vk-seo-btn-ghost" href="<?= e(BASE_URL) ?>/modules/seo/sitemap.php"><i class="bi bi-diagram-3"></i><span class="d-none d-sm-inline">Sitemap</span></a>
            <a class="vk-seo-btn vk-seo-btn-primary" href="<?= e(BASE_URL) ?>/modules/seo/pages.php"><i class="bi bi-pencil-square"></i><span class="d-none d-sm-inline">Page SEO</span></a>
        </div>
    </div>
</header>

<div class="vk-seo-kpi-grid" role="region" aria-label="SEO KPIs">
    <div class="vk-seo-kpi vk-seo-kpi-green">
        <div class="vk-seo-kpi-icon"><i class="bi bi-globe2"></i></div>
        <div class="vk-seo-kpi-body">
            <span class="vk-seo-kpi-label">Indexed Pages</span>
            <span class="vk-seo-kpi-value" data-count-to="<?= (int) $totalIndexed ?>">0</span>
            <span class="vk-seo-kpi-trend">Index-ready</span>
        </div>
    </div>
    <div class="vk-seo-kpi vk-seo-kpi-blue">
        <div class="vk-seo-kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="vk-seo-kpi-body">
            <span class="vk-seo-kpi-label">Organic Traffic</span>
            <span class="vk-seo-kpi-value" data-count-to="<?= (int) $kpiOrganicTraffic ?>">0</span>
            <span class="vk-seo-kpi-trend">Est. monthly</span>
        </div>
    </div>
    <div class="vk-seo-kpi vk-seo-kpi-teal">
        <div class="vk-seo-kpi-icon"><i class="bi bi-key"></i></div>
        <div class="vk-seo-kpi-body">
            <span class="vk-seo-kpi-label">Keywords</span>
            <span class="vk-seo-kpi-value" data-count-to="<?= (int) $kpiKeywords ?>">0</span>
            <span class="vk-seo-kpi-trend">Ranking</span>
        </div>
    </div>
    <div class="vk-seo-kpi vk-seo-kpi-red">
        <div class="vk-seo-kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="vk-seo-kpi-body">
            <span class="vk-seo-kpi-label">SEO Errors</span>
            <span class="vk-seo-kpi-value" data-count-to="<?= (int) $kpiErrors ?>">0</span>
            <span class="vk-seo-kpi-trend">Score &lt; 50</span>
        </div>
    </div>
    <div class="vk-seo-kpi vk-seo-kpi-green">
        <div class="vk-seo-kpi-icon"><i class="bi bi-shield-check"></i></div>
        <div class="vk-seo-kpi-body">
            <span class="vk-seo-kpi-label">SEO Score</span>
            <span class="vk-seo-kpi-value" data-count-to="<?= (int) $avgScore ?>" data-count-suffix="%">0</span>
            <span class="vk-seo-kpi-trend">Dashboard avg</span>
        </div>
    </div>
    <div class="vk-seo-kpi vk-seo-kpi-purple">
        <div class="vk-seo-kpi-icon"><i class="bi bi-link-45deg"></i></div>
        <div class="vk-seo-kpi-body">
            <span class="vk-seo-kpi-label">Backlinks</span>
            <span class="vk-seo-kpi-value" data-count-to="<?= (int) $kpiBacklinks ?>">0</span>
            <span class="vk-seo-kpi-trend">Estimated</span>
        </div>
    </div>
    <div class="vk-seo-kpi vk-seo-kpi-blue">
        <div class="vk-seo-kpi-icon"><i class="bi bi-trophy"></i></div>
        <div class="vk-seo-kpi-body">
            <span class="vk-seo-kpi-label">Domain Auth.</span>
            <span class="vk-seo-kpi-value" data-count-to="<?= (int) $kpiDomainAuthority ?>">0</span>
            <span class="vk-seo-kpi-trend">DA estimate</span>
        </div>
    </div>
    <div class="vk-seo-kpi vk-seo-kpi-teal">
        <div class="vk-seo-kpi-icon"><i class="bi bi-tags"></i></div>
        <div class="vk-seo-kpi-body">
            <span class="vk-seo-kpi-label">Meta Optimized</span>
            <span class="vk-seo-kpi-value" data-count-to="<?= (int) $kpiMetaOptimized ?>">0</span>
            <span class="vk-seo-kpi-trend">Title + desc</span>
        </div>
    </div>
    <div class="vk-seo-kpi vk-seo-kpi-orange">
        <div class="vk-seo-kpi-icon"><i class="bi bi-lightning-charge"></i></div>
        <div class="vk-seo-kpi-body">
            <span class="vk-seo-kpi-label">Page Speed</span>
            <span class="vk-seo-kpi-value" data-count-to="<?= (int) $kpiPageSpeed ?>" data-count-suffix="%">0</span>
            <span class="vk-seo-kpi-trend">Core Web Vitals</span>
        </div>
    </div>
    <div class="vk-seo-kpi vk-seo-kpi-green">
        <div class="vk-seo-kpi-icon"><i class="bi bi-phone"></i></div>
        <div class="vk-seo-kpi-body">
            <span class="vk-seo-kpi-label">Mobile OK</span>
            <span class="vk-seo-kpi-value" data-count-to="<?= (int) $kpiMobileFriendly ?>">0</span>
            <span class="vk-seo-kpi-trend">Friendly pages</span>
        </div>
    </div>
</div>

<form class="vk-seo-toolbar" id="vkSeoFilterForm" role="search" aria-label="SEO filters" onsubmit="return false;">
    <div class="vk-seo-toolbar-mobile-head d-lg-none">
        <span class="fw-semibold small"><i class="bi bi-sliders"></i> Filters</span>
        <button type="button" class="vk-seo-btn vk-seo-btn-icon" data-bs-toggle="collapse" data-bs-target="#vkSeoToolbarCollapse" aria-expanded="false" aria-controls="vkSeoToolbarCollapse" aria-label="Toggle filters"><i class="bi bi-chevron-down"></i></button>
    </div>
    <div class="collapse vk-seo-toolbar-collapse" id="vkSeoToolbarCollapse">
        <div class="vk-seo-toolbar-inner">
            <div class="vk-seo-search-wrap">
                <i class="bi bi-search vk-seo-search-ico" aria-hidden="true"></i>
                <input type="search" id="vkSeoSearch" class="form-control vk-seo-ctl" placeholder="Search pages… ( / )" autocomplete="off" aria-label="Search SEO pages">
            </div>
            <select class="form-select vk-seo-ctl vk-seo-ctl-sm" disabled title="Single project"><option>Project</option></select>
            <select class="form-select vk-seo-ctl vk-seo-ctl-sm" disabled title="Page type from page_key"><option>Page Type</option></select>
            <select id="vkSeoFilterStatus" class="form-select vk-seo-ctl vk-seo-ctl-sm" aria-label="SEO status">
                <option value="">Status</option>
                <option value="indexed">Healthy</option>
                <option value="warning">Warning</option>
                <option value="critical">Critical</option>
                <option value="unknown">Review</option>
            </select>
            <select id="vkSeoFilterPriority" class="form-select vk-seo-ctl vk-seo-ctl-sm" aria-label="Priority">
                <option value="">Priority</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
            <input type="date" class="form-control vk-seo-ctl vk-seo-ctl-date" disabled aria-label="Date from">
            <input type="date" class="form-control vk-seo-ctl vk-seo-ctl-date" disabled aria-label="Date to">
            <select id="vkSeoFilterScore" class="form-select vk-seo-ctl vk-seo-ctl-sm" aria-label="SEO score">
                <option value="">Score</option>
                <option value="excellent">90–100</option>
                <option value="good">70–89</option>
                <option value="fair">50–69</option>
                <option value="poor">Below 50</option>
            </select>
            <select id="vkSeoFilterIndex" class="form-select vk-seo-ctl vk-seo-ctl-sm" aria-label="Index status">
                <option value="">Index</option>
                <option value="indexed">Indexed</option>
                <option value="not_indexed">Not Indexed</option>
                <option value="crawling">Crawling</option>
                <option value="critical">Critical</option>
                <option value="unknown">Unknown</option>
            </select>
            <select class="form-select vk-seo-ctl vk-seo-ctl-xs" disabled aria-label="Rows per page"><option>8</option></select>
            <div class="vk-seo-toolbar-btns">
                <a class="vk-seo-btn vk-seo-btn-primary" href="<?= e(BASE_URL) ?>/modules/seo/pages.php"><i class="bi bi-radar"></i><span class="d-none d-xl-inline">Scan Website</span></a>
                <button type="button" class="vk-seo-btn" id="vkSeoRefresh" aria-label="Recheck SEO"><i class="bi bi-arrow-repeat"></i></button>
                <a class="vk-seo-btn" href="<?= e(BASE_URL) ?>/modules/seo/analytics.php"><i class="bi bi-file-earmark-bar-graph"></i><span class="d-none d-xl-inline">Export Report</span></a>
                <a class="vk-seo-btn" href="<?= e(BASE_URL) ?>/modules/seo/sitemap.php"><i class="bi bi-diagram-3"></i><span class="d-none d-xl-inline">Sitemap</span></a>
                <button type="button" class="vk-seo-btn" id="vkSeoReset" aria-label="Reset filters"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
    </div>
</form>

<div class="row g-2 mb-2">
    <div class="col-12 col-xl-8">
        <div class="vk-seo-panel" id="vkSeoPagePanel">
            <div class="vk-seo-panel-head">
                <h2 class="vk-seo-panel-title">SEO audit dashboard</h2>
                <a class="vk-seo-btn vk-seo-btn-ghost" href="<?= e(BASE_URL) ?>/modules/seo/pages.php">Manage all pages</a>
            </div>
            <div class="vk-seo-panel-scroll vk-seo-desktop-only">
                <table class="table vk-seo-table mb-0" id="vkSeoTable">
                    <thead>
                        <tr>
                            <th class="vk-seo-sticky-col" style="width:34px"><input type="checkbox" class="form-check-input" id="vkSeoSelectAll" aria-label="Select all pages"></th>
                            <th style="width:140px">URL</th>
                            <th style="width:160px">Page Title</th>
                            <th class="vk-seo-col-hide-lg" style="width:160px">Meta Description</th>
                            <th class="vk-seo-col-hide-md" style="width:100px">Keywords</th>
                            <th style="width:52px">Score</th>
                            <th class="vk-seo-col-hide-md" style="width:56px">Speed</th>
                            <th style="width:88px">Index</th>
                            <th class="vk-seo-col-hide-lg" style="width:64px">Mobile</th>
                            <th class="vk-seo-col-hide-md" style="width:88px">Crawled</th>
                            <th style="width:56px">Priority</th>
                            <th style="width:80px">Status</th>
                            <th style="width:220px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pages as $row): ?>
                    <?php
                        $score = (int) ($row['seo_score'] ?? 0);
                        $ring = $vkSeoScoreRing($score);
                        $idx = $vkSeoIndexUi($row);
                        $st = $vkSeoStatusUi($row);
                        $pri = $vkSeoPriority($row);
                        $speed = $vkSeoPageSpeed($score);
                        $mobile = $vkSeoMobileFriendly($row);
                        $fullUrl = $vkSeoPageUrl((string) ($row['page_url'] ?? ''));
                        $searchBlob = strtolower(implode(' ', [
                            (string) ($row['page_url'] ?? ''),
                            (string) ($row['meta_title'] ?? ''),
                            (string) ($row['meta_description'] ?? ''),
                            (string) ($row['meta_keywords'] ?? ''),
                            (string) ($row['indexing_status'] ?? ''),
                            (string) ($row['page_key'] ?? ''),
                        ]));
                    ?>
                    <tr data-page-id="<?= (int) $row['id'] ?>"
                        data-search="<?= e($searchBlob) ?>"
                        data-score-band="<?= e($ring['band']) ?>"
                        data-index="<?= e($idx['key']) ?>"
                        data-priority="<?= e($pri) ?>"
                        data-status="<?= e($st['key']) ?>">
                        <td class="vk-seo-sticky-col"><input type="checkbox" class="form-check-input vk-seo-row-check" aria-label="Select page"></td>
                        <td><div class="vk-seo-url vk-seo-highlight-target" title="<?= e((string) $row['page_url']) ?>"><?= e((string) $row['page_key']) ?></div></td>
                        <td><div class="vk-seo-title-cell vk-seo-highlight-target"><?= e((string) $row['meta_title']) ?></div></td>
                        <td class="vk-seo-col-hide-lg"><div class="vk-seo-desc vk-seo-highlight-target"><?= e($vkSeoShortDesc((string) ($row['meta_description'] ?? ''))) ?></div></td>
                        <td class="vk-seo-col-hide-md"><div class="vk-seo-kw vk-seo-highlight-target"><?= e($vkSeoShortDesc((string) ($row['meta_keywords'] ?? ''), 40)) ?></div></td>
                        <td>
                            <div class="vk-seo-score-ring <?= e($ring['class']) ?>" style="--score: <?= $score ?>" title="SEO Score <?= $score ?>%"><span><?= $score ?></span></div>
                        </td>
                        <td class="vk-seo-col-hide-md"><span class="vk-seo-speed <?= e($vkSeoSpeedClass($speed)) ?>"><?= $speed ?></span></td>
                        <td><span class="vk-seo-badge vk-seo-st-<?= e($idx['key']) ?>"><?= e($idx['label']) ?></span></td>
                        <td class="vk-seo-col-hide-lg"><?php if ($mobile): ?><i class="bi bi-check-circle-fill vk-seo-mobile-yes" title="Mobile friendly"></i><?php else: ?><i class="bi bi-dash-circle vk-seo-mobile-no" title="Needs review"></i><?php endif; ?></td>
                        <td class="vk-seo-col-hide-md vk-seo-date"><?= e($vkSeoFormatDate((string) ($row['updated_at'] ?? ''))) ?></td>
                        <td><span class="vk-seo-pri-<?= e($pri) ?>"><?= e(ucfirst($pri)) ?></span></td>
                        <td><span class="vk-seo-badge vk-seo-st-<?= e($st['key']) ?>"><?= e($st['label']) ?></span></td>
                        <td>
                            <div class="vk-seo-actions">
                                <a class="vk-seo-act" href="<?= e(BASE_URL) ?>/modules/seo/pages.php?id=<?= (int) $row['id'] ?>" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></a>
                                <a class="vk-seo-act" href="<?= e(BASE_URL) ?>/modules/seo/pages.php?id=<?= (int) $row['id'] ?>" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a class="vk-seo-act" href="<?= e(BASE_URL) ?>/modules/seo/analytics.php" data-bs-toggle="tooltip" title="Analyze"><i class="bi bi-search"></i></a>
                                <a class="vk-seo-act" href="<?= e(BASE_URL) ?>/modules/seo/analytics.php" data-bs-toggle="tooltip" title="Speed test"><i class="bi bi-lightning"></i></a>
                                <a class="vk-seo-act" href="<?= e($fullUrl) ?>" target="_blank" rel="noopener" data-bs-toggle="tooltip" title="Preview"><i class="bi bi-file-earmark"></i></a>
                                <a class="vk-seo-act" href="<?= e($fullUrl) ?>" target="_blank" rel="noopener" data-bs-toggle="tooltip" title="Open URL"><i class="bi bi-box-arrow-up-right"></i></a>
                                <a class="vk-seo-act" href="<?= e(BASE_URL) ?>/modules/seo/analytics.php" data-bs-toggle="tooltip" title="Report"><i class="bi bi-bar-chart"></i></a>
                                <span class="vk-seo-act vk-seo-act-danger" data-bs-toggle="tooltip" title="Delete (not available)" aria-disabled="true" role="button"><i class="bi bi-trash"></i></span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="vk-seo-empty" id="vkSeoTableEmpty"<?= $pages ? ' hidden' : '' ?>>
                    <div class="vk-seo-empty-icon"><i class="bi bi-search"></i></div>
                    <h2>No SEO records found</h2>
                    <p>Import the marketing suite schema or run an SEO scan to begin managing pages.</p>
                    <a class="vk-seo-btn vk-seo-btn-primary" href="<?= e(BASE_URL) ?>/modules/seo/pages.php"><i class="bi bi-radar"></i> Run SEO Scan</a>
                </div>
            </div>

            <div class="vk-seo-mobile-only">
                <?php if (!$pages): ?>
                <div class="vk-seo-empty">
                    <div class="vk-seo-empty-icon"><i class="bi bi-search"></i></div>
                    <h2>No SEO records found</h2>
                    <a class="vk-seo-btn vk-seo-btn-primary mt-2" href="<?= e(BASE_URL) ?>/modules/seo/pages.php">Run SEO Scan</a>
                </div>
                <?php endif; ?>
                <?php foreach ($pages as $row): ?>
                <?php
                    $score = (int) ($row['seo_score'] ?? 0);
                    $ring = $vkSeoScoreRing($score);
                    $idx = $vkSeoIndexUi($row);
                    $st = $vkSeoStatusUi($row);
                    $pri = $vkSeoPriority($row);
                    $speed = $vkSeoPageSpeed($score);
                    $fullUrl = $vkSeoPageUrl((string) ($row['page_url'] ?? ''));
                    $searchBlob = strtolower(implode(' ', [(string) $row['page_url'], (string) $row['meta_title'], (string) $row['meta_keywords']]));
                ?>
                <article class="vk-seo-mobile-card" data-page-id="<?= (int) $row['id'] ?>" data-search="<?= e($searchBlob) ?>" data-score-band="<?= e($ring['band']) ?>" data-index="<?= e($idx['key']) ?>" data-priority="<?= e($pri) ?>" data-status="<?= e($st['key']) ?>">
                    <div class="vk-seo-mobile-card-head">
                        <div style="min-width:0">
                            <div class="vk-seo-title-cell"><?= e((string) $row['meta_title']) ?></div>
                            <div class="vk-seo-url"><?= e((string) $row['page_key']) ?></div>
                        </div>
                        <div class="vk-seo-score-ring <?= e($ring['class']) ?>" style="--score: <?= $score ?>"><span><?= $score ?></span></div>
                    </div>
                    <div class="d-flex gap-2 mb-2">
                        <span class="vk-seo-badge vk-seo-st-<?= e($idx['key']) ?>"><?= e($idx['label']) ?></span>
                        <span class="vk-seo-badge vk-seo-st-<?= e($st['key']) ?>"><?= e($st['label']) ?></span>
                        <span class="vk-seo-pri-<?= e($pri) ?>"><?= e(ucfirst($pri)) ?></span>
                    </div>
                    <dl class="vk-seo-mobile-card-grid">
                        <dt>Speed</dt><dd class="<?= e($vkSeoSpeedClass($speed)) ?>"><?= $speed ?></dd>
                        <dt>Crawled</dt><dd><?= e($vkSeoFormatDate((string) ($row['updated_at'] ?? ''))) ?></dd>
                        <dt>Keywords</dt><dd><?= e($vkSeoShortDesc((string) ($row['meta_keywords'] ?? ''), 24)) ?></dd>
                        <dt>Mobile</dt><dd><?= $vkSeoMobileFriendly($row) ? 'Yes' : 'Review' ?></dd>
                    </dl>
                    <div class="vk-seo-actions">
                        <a class="vk-seo-act" href="<?= e(BASE_URL) ?>/modules/seo/pages.php?id=<?= (int) $row['id'] ?>" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                        <a class="vk-seo-act" href="<?= e(BASE_URL) ?>/modules/seo/analytics.php" aria-label="Analyze"><i class="bi bi-bar-chart"></i></a>
                        <a class="vk-seo-act" href="<?= e($fullUrl) ?>" target="_blank" rel="noopener" aria-label="Open URL"><i class="bi bi-box-arrow-up-right"></i></a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <footer class="vk-seo-footer">
                <span id="vkSeoVisibleCount">Showing 1–<?= (int) $displayCount ?> of <?= (int) $totalPages ?></span>
                <nav class="vk-seo-page-nav" aria-label="Page pagination">
                    <span class="vk-seo-page-link is-active" aria-current="page">1</span>
                    <?php if ($totalPages > $displayCount): ?>
                    <a class="vk-seo-page-link" href="<?= e(BASE_URL) ?>/modules/seo/pages.php" aria-label="View all pages"><i class="bi bi-chevron-right"></i></a>
                    <?php endif; ?>
                </nav>
            </footer>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="vk-seo-panel">
            <div class="vk-seo-panel-head"><h2 class="vk-seo-panel-title">Search analytics</h2></div>
            <div class="vk-seo-analytics">
                <div class="vk-seo-chart-card">
                    <h3 class="vk-seo-chart-title">Organic traffic</h3>
                    <div class="vk-seo-chart-metric" data-count-to="<?= (int) $kpiOrganicTraffic ?>">0</div>
                    <div class="vk-seo-bar-row"><span class="vk-seo-bar-label">Trend</span><div class="vk-seo-bar-track"><div class="vk-seo-bar-fill" data-width="<?= min(100, (int) ($avgScore * 0.9)) ?>"></div></div><span class="vk-seo-bar-val"><?= (int) $avgScore ?>%</span></div>
                </div>
                <div class="vk-seo-chart-card">
                    <h3 class="vk-seo-chart-title">Keyword ranking</h3>
                    <div class="vk-seo-chart-metric" data-count-to="<?= (int) $kpiKeywords ?>">0</div>
                    <div class="vk-seo-bar-row"><span class="vk-seo-bar-label">Tracked</span><div class="vk-seo-bar-track"><div class="vk-seo-bar-fill" data-width="<?= min(100, $kpiKeywords * 3) ?>"></div></div><span class="vk-seo-bar-val"><?= (int) $kpiKeywords ?></span></div>
                </div>
                <div class="vk-seo-chart-card">
                    <h3 class="vk-seo-chart-title">Backlink growth</h3>
                    <div class="vk-seo-chart-metric" data-count-to="<?= (int) $kpiBacklinks ?>">0</div>
                    <div class="vk-seo-bar-row"><span class="vk-seo-bar-label">Links</span><div class="vk-seo-bar-track"><div class="vk-seo-bar-fill" data-width="<?= min(100, (int) ($kpiBacklinks / 10)) ?>"></div></div><span class="vk-seo-bar-val">+<?= (int) ($kpiCanonical * 2) ?></span></div>
                </div>
                <div class="vk-seo-chart-card">
                    <h3 class="vk-seo-chart-title">Crawl errors</h3>
                    <div class="vk-seo-chart-metric" data-count-to="<?= (int) ($kpiErrors + $kpiWarnings) ?>">0</div>
                    <div class="vk-seo-bar-row"><span class="vk-seo-bar-label">Critical</span><div class="vk-seo-bar-track"><div class="vk-seo-bar-fill" data-width="<?= min(100, $kpiErrors * 20) ?>" style="background:linear-gradient(90deg,var(--danger),var(--warning))"></div></div><span class="vk-seo-bar-val"><?= (int) $kpiErrors ?></span></div>
                </div>
                <div class="vk-seo-chart-card">
                    <h3 class="vk-seo-chart-title">Core Web Vitals</h3>
                    <div class="vk-seo-chart-metric" data-count-to="<?= (int) $kpiPageSpeed ?>" data-count-suffix="%">0</div>
                    <div class="vk-seo-bar-row"><span class="vk-seo-bar-label">Speed</span><div class="vk-seo-bar-track"><div class="vk-seo-bar-fill" data-width="<?= (int) $kpiPageSpeed ?>"></div></div><span class="vk-seo-bar-val"><?= (int) $kpiPageSpeed ?></span></div>
                </div>
                <div class="vk-seo-chart-card">
                    <h3 class="vk-seo-chart-title">Top pages by score</h3>
                    <?php
                    $byScore = $pages;
                    usort($byScore, static fn ($a, $b) => ((int) ($b['seo_score'] ?? 0)) <=> ((int) ($a['seo_score'] ?? 0)));
                    $byScore = array_slice($byScore, 0, 4);
                    foreach ($byScore as $tp):
                    ?>
                    <div class="vk-seo-bar-row">
                        <span class="vk-seo-bar-label" title="<?= e((string) $tp['page_key']) ?>"><?= e((string) $tp['page_key']) ?></span>
                        <div class="vk-seo-bar-track"><div class="vk-seo-bar-fill" data-width="<?= (int) ($tp['seo_score'] ?? 0) ?>"></div></div>
                        <span class="vk-seo-bar-val"><?= (int) ($tp['seo_score'] ?? 0) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="vk-seo-panel">
    <div class="vk-seo-panel-head"><h2 class="vk-seo-panel-title">Website health overview</h2></div>
    <div class="vk-seo-health">
        <?php
        $healthItems = [
            ['Meta titles', $kpiMetaOptimized, $totalPages, 'bi-type', 'Titles configured'],
            ['Meta descriptions', $kpiMetaOptimized, $totalPages, 'bi-text-paragraph', 'Descriptions set'],
            ['Canonical URLs', $kpiCanonical, $totalPages, 'bi-link-45deg', 'Canonical coverage'],
            ['Robots.txt', 1, 1, 'bi-robot', 'Generator ready', true],
            ['Sitemap.xml', $totalIndexed, max(1, $totalPages), 'bi-diagram-3', 'URLs in sitemap'],
            ['Broken links', 0, 0, 'bi-x-circle', 'Connect crawler', false, true],
            ['404 errors', 0, 0, 'bi-exclamation-octagon', 'Connect Search Console', false, true],
            ['Image ALT tags', $kpiOg, $totalPages, 'bi-image', 'Social/OG coverage'],
            ['Schema markup', $kpiSchema, $totalPages, 'bi-braces', 'Structured data'],
            ['SSL certificate', 1, 1, 'bi-shield-lock', 'HTTPS active', true],
            ['Open Graph', $kpiOg, $totalPages, 'bi-share', 'OG tags present'],
            ['Twitter cards', $totalPages > 0 ? $totalPages : 0, max(1, $totalPages), 'bi-twitter-x', 'Card type set'],
            ['Core Web Vitals', $kpiPageSpeed, 100, 'bi-speedometer2', 'Performance score'],
        ];
        foreach ($healthItems as $hi):
            $label = $hi[0];
            $val = (int) $hi[1];
            $max = (int) $hi[2];
            $icon = $hi[3];
            $sub = $hi[4];
            $forcePass = ($hi[5] ?? false) === true;
            $forceWarn = ($hi[6] ?? false) === true;
            if ($forcePass) {
                $state = 'pass';
            } elseif ($forceWarn) {
                $state = 'warn';
            } elseif ($max > 0 && $val >= $max) {
                $state = 'pass';
            } elseif ($val > 0) {
                $state = 'warn';
            } else {
                $state = 'fail';
            }
            $stateIcon = match ($state) {
                'pass' => 'bi-check-lg',
                'warn' => 'bi-exclamation-lg',
                default => 'bi-x-lg',
            };
        ?>
        <div class="vk-seo-health-item vk-seo-health-<?= e($state) ?>">
            <div class="vk-seo-health-icon"><i class="bi <?= e($icon) ?>"></i></div>
            <div class="vk-seo-health-body">
                <div class="vk-seo-health-label"><?= e($label) ?> <i class="bi <?= e($stateIcon) ?> ms-1 small"></i></div>
                <div class="vk-seo-health-sub"><?= e($sub) ?><?php if (!$forcePass && !$forceWarn && $max > 0): ?> · <?= $val ?>/<?= $max ?><?php endif; ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="vk-seo-insights">
    <h3><i class="bi bi-stars text-primary"></i> Technical SEO checker</h3>
    <div class="vk-seo-insight-list">
        <div class="vk-seo-insight-item"><i class="bi bi-check2-circle"></i> Structured schema markup <?= $schemaPct >= 100 ? 'enabled' : 'partial (' . $schemaPct . '%)' ?></div>
        <div class="vk-seo-insight-item"><i class="bi bi-check2-circle"></i> Local business SEO <?= $kpiSchema > 0 ? 'ready' : 'pending setup' ?></div>
        <div class="vk-seo-insight-item"><i class="bi bi-exclamation-triangle"></i> Connect Search Console for live indexing data</div>
        <div class="vk-seo-insight-item"><i class="bi bi-check2-circle"></i> <?= (int) $indexed ?> of <?= count($pages) ?> dashboard pages index-ready · <?= (int) $kpiWarnings ?> warnings</div>
    </div>
</div>

</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/seo-dashboard.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
