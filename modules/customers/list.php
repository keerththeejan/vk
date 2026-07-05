<?php
declare(strict_types=1);

$pageTitle = 'Customers';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/customers_kpi_service.php';

$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPageOptions = [10, 15, 25, 50, 100];
$perPageReq = (int) ($_GET['per_page'] ?? 15);
$perPage = in_array($perPageReq, $perPageOptions, true) ? $perPageReq : 15;

$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$countSt = $pdo->prepare("SELECT COUNT(*) FROM customers c WHERE $where");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();
$pg = paginate($total, $page, $perPage);

$sql = "SELECT c.*, a.code AS account_code, a.current_balance
        FROM customers c
        JOIN accounts a ON a.customer_id = c.id
        WHERE $where
        ORDER BY c.id DESC
        LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$vkCustBatchCounts = static function (PDO $pdo, array $ids, string $table): array {
    if ($ids === [] || !db_table_exists($pdo, $table)) {
        return [];
    }
    $allowed = ['repair_jobs', 'cctv_installations', 'maintenance_contracts', 'invoices'];
    if (!in_array($table, $allowed, true)) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT customer_id, COUNT(*) AS cnt FROM {$table} WHERE customer_id IN ($ph) GROUP BY customer_id");
    $st->execute($ids);
    $out = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $out[(int) $r['customer_id']] = (int) $r['cnt'];
    }
    return $out;
};

$vkCustLastService = static function (PDO $pdo, array $ids): array {
    if ($ids === [] || !db_table_exists($pdo, 'repair_jobs')) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT customer_id, MAX(created_at) AS last_at FROM repair_jobs WHERE customer_id IN ($ph) GROUP BY customer_id");
    $st->execute($ids);
    $out = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $out[(int) $r['customer_id']] = (string) $r['last_at'];
    }
    return $out;
};

$customerIds = array_map(static fn ($r) => (int) $r['id'], $rows);
$repairCounts = $vkCustBatchCounts($pdo, $customerIds, 'repair_jobs');
$cctvCounts = $vkCustBatchCounts($pdo, $customerIds, 'cctv_installations');
$maintCounts = $vkCustBatchCounts($pdo, $customerIds, 'maintenance_contracts');
$invoiceCounts = $vkCustBatchCounts($pdo, $customerIds, 'invoices');
$lastServices = $vkCustLastService($pdo, $customerIds);

$vkCustKpis = vk_customers_list_kpis($pdo);
$kpiTotal = $vkCustKpis['total'];
$kpiNewMonth = $vkCustKpis['new_month'];
$kpiNewLastMonth = $vkCustKpis['new_last_month'];
$kpiActiveRepairs = $vkCustKpis['active_repairs'];
$kpiMaint = $vkCustKpis['maint'];
$kpiCctv = $vkCustKpis['cctv'];
$kpiWhatsapp = $vkCustKpis['whatsapp'];
$kpiOutstanding = $vkCustKpis['outstanding'];
$kpiRevenue = $vkCustKpis['revenue'];
$kpiVip = $vkCustKpis['vip'];
$kpiQuotations = $vkCustKpis['quotations'];
$growthPct = $vkCustKpis['growth_pct'];

$pageFrom = $total > 0 ? $pg['offset'] + 1 : 0;
$pageTo = min($pg['offset'] + count($rows), $total);

$queryBase = static function (array $extra = []) use ($q, $perPage): string {
    return http_build_query(array_merge(['q' => $q, 'per_page' => $perPage], $extra));
};

$vkCustInitials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(substr((string) ($parts[0] ?? 'C'), 0, 1) . substr((string) ($parts[1] ?? ''), 0, 1));
};

$vkCustType = static function (array $r, float $balance): array {
    $name = strtoupper((string) ($r['name'] ?? ''));
    if (str_contains($name, 'VIP')) {
        return ['key' => 'vip', 'label' => 'VIP'];
    }
    if (preg_match('/\b(LTD|LIMITED|PLC|PVT|INC|COMPANY|CO\.)\b/', $name)) {
        return ['key' => 'business', 'label' => 'Business'];
    }
    if (preg_match('/\b(GOV|GOVERNMENT|MUNICIPAL|COUNCIL)\b/', $name)) {
        return ['key' => 'government', 'label' => 'Government'];
    }
    if (preg_match('/\b(SCHOOL|COLLEGE|UNIVERSITY|ACADEMY)\b/', $name)) {
        return ['key' => 'school', 'label' => 'School'];
    }
    if (preg_match('/\b(NGO|FOUNDATION|TRUST)\b/', $name)) {
        return ['key' => 'ngo', 'label' => 'NGO'];
    }
    if ($balance > 50000) {
        return ['key' => 'vip', 'label' => 'VIP'];
    }
    return ['key' => 'individual', 'label' => 'Individual'];
};

$vkCustStatus = static function (array $r, int $repairCount): array {
    $created = strtotime((string) ($r['created_at'] ?? ''));
    if ($repairCount === 0 && $created && $created >= strtotime('-14 days')) {
        return ['key' => 'lead', 'label' => 'Lead'];
    }
    return ['key' => 'active', 'label' => 'Active'];
};

$vkCustCompany = static function (string $name): string {
    if (preg_match('/\b(LTD|LIMITED|PLC|PVT|INC|COMPANY)\b/i', $name)) {
        return $name;
    }
    return '—';
};

$vkCustCity = static function (?string $address): string {
    if ($address === null || trim($address) === '') {
        return '—';
    }
    $line = trim(strtok($address, "\n"));
    $parts = array_map('trim', explode(',', $line));
    return $parts !== [] ? (string) end($parts) : '—';
};

$vkCustBalanceUi = static function (float $bal): array {
    if ($bal <= 0.001) {
        return ['class' => 'vk-cust-amt-paid', 'label' => 'Paid'];
    }
    if ($bal < 5000) {
        return ['class' => 'vk-cust-amt-partial', 'label' => 'Partial'];
    }
    return ['class' => 'vk-cust-amt-overdue', 'label' => 'Due'];
};

$vkCustFormatDate = static function (?string $iso): string {
    if ($iso === null || $iso === '') {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d M Y', $ts) : '—';
};

$vkCustWaLink = static function (?string $phone): string {
    $d = preg_replace('/\D+/', '', (string) $phone);
    return $d !== '' ? 'https://wa.me/' . $d : '';
};

$typeDistribution = ['individual' => 0, 'business' => 0, 'vip' => 0, 'other' => 0];
foreach ($rows as $rowDist) {
    $t = $vkCustType($rowDist, (float) ($rowDist['current_balance'] ?? 0));
    if (isset($typeDistribution[$t['key']])) {
        $typeDistribution[$t['key']]++;
    } else {
        $typeDistribution['other']++;
    }
}

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/customers-list.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/customers-list.js');
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/customers-list.css')) . '?v=' . e($cssV) . '" media="print" onload="this.media=\'all\'">'
    . '<noscript><link rel="stylesheet" href="' . e(base_url('assets/css/customers-list.css')) . '?v=' . e($cssV) . '"></noscript>';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="vkCustApp" class="vk-cust-admin vk-cust-skeleton"
     data-filtered-total="<?= (int) $total ?>"
     data-search-query="<?= e($q) ?>"
     role="application" aria-label="Customer CRM">

<header class="vk-cust-header">
    <div class="vk-cust-header-inner">
        <div>
            <h1 class="vk-cust-title"><i class="bi bi-people-fill me-1" aria-hidden="true"></i> Customer CRM</h1>
            <p class="vk-cust-subtitle d-none d-md-block">Enterprise relationship hub · repairs, billing, maintenance &amp; WhatsApp</p>
        </div>
        <a class="vk-cust-btn vk-cust-btn-primary" href="<?= e(BASE_URL) ?>/modules/customers/add.php">
            <i class="bi bi-person-plus" aria-hidden="true"></i><span>New Customer</span>
        </a>
    </div>
</header>

<div class="vk-cust-kpi-grid" role="region" aria-label="Customer KPIs">
    <div class="vk-cust-kpi vk-cust-kpi-blue">
        <div class="vk-cust-kpi-icon"><i class="bi bi-people"></i></div>
        <div class="vk-cust-kpi-body">
            <span class="vk-cust-kpi-label">Total</span>
            <span class="vk-cust-kpi-value" data-count-to="<?= (int) $kpiTotal ?>">0</span>
            <span class="vk-cust-kpi-trend">All customers</span>
        </div>
    </div>
    <div class="vk-cust-kpi vk-cust-kpi-green">
        <div class="vk-cust-kpi-icon"><i class="bi bi-person-check"></i></div>
        <div class="vk-cust-kpi-body">
            <span class="vk-cust-kpi-label">Active</span>
            <span class="vk-cust-kpi-value" data-count-to="<?= (int) $kpiTotal ?>">0</span>
            <span class="vk-cust-kpi-trend">In directory</span>
        </div>
    </div>
    <div class="vk-cust-kpi vk-cust-kpi-teal">
        <div class="vk-cust-kpi-icon"><i class="bi bi-person-plus"></i></div>
        <div class="vk-cust-kpi-body">
            <span class="vk-cust-kpi-label">New Month</span>
            <span class="vk-cust-kpi-value" data-count-to="<?= (int) $kpiNewMonth ?>">0</span>
            <span class="vk-cust-kpi-trend"><?= e((string) $growthPct) ?>% vs last</span>
        </div>
    </div>
    <div class="vk-cust-kpi vk-cust-kpi-green">
        <div class="vk-cust-kpi-icon"><i class="bi bi-currency-rupee"></i></div>
        <div class="vk-cust-kpi-body">
            <span class="vk-cust-kpi-label">Revenue</span>
            <span class="vk-cust-kpi-value" data-count-to="<?= (int) $kpiRevenue ?>" data-count-money="1">0</span>
            <span class="vk-cust-kpi-trend">Paid invoices</span>
        </div>
    </div>
    <div class="vk-cust-kpi vk-cust-kpi-blue">
        <div class="vk-cust-kpi-icon"><i class="bi bi-file-earmark-text"></i></div>
        <div class="vk-cust-kpi-body">
            <span class="vk-cust-kpi-label">Quotations</span>
            <span class="vk-cust-kpi-value" data-count-to="<?= (int) $kpiQuotations ?>">0</span>
            <span class="vk-cust-kpi-trend">Draft/sent</span>
        </div>
    </div>
    <div class="vk-cust-kpi vk-cust-kpi-orange">
        <div class="vk-cust-kpi-icon"><i class="bi bi-tools"></i></div>
        <div class="vk-cust-kpi-body">
            <span class="vk-cust-kpi-label">Repairs</span>
            <span class="vk-cust-kpi-value" data-count-to="<?= (int) $kpiActiveRepairs ?>">0</span>
            <span class="vk-cust-kpi-trend">Open jobs</span>
        </div>
    </div>
    <div class="vk-cust-kpi vk-cust-kpi-purple">
        <div class="vk-cust-kpi-icon"><i class="bi bi-wrench"></i></div>
        <div class="vk-cust-kpi-body">
            <span class="vk-cust-kpi-label">Maintenance</span>
            <span class="vk-cust-kpi-value" data-count-to="<?= (int) $kpiMaint ?>">0</span>
            <span class="vk-cust-kpi-trend">Active contracts</span>
        </div>
    </div>
    <div class="vk-cust-kpi vk-cust-kpi-teal">
        <div class="vk-cust-kpi-icon"><i class="bi bi-camera-video"></i></div>
        <div class="vk-cust-kpi-body">
            <span class="vk-cust-kpi-label">CCTV</span>
            <span class="vk-cust-kpi-value" data-count-to="<?= (int) $kpiCctv ?>">0</span>
            <span class="vk-cust-kpi-trend">Projects</span>
        </div>
    </div>
    <div class="vk-cust-kpi vk-cust-kpi-purple">
        <div class="vk-cust-kpi-icon"><i class="bi bi-star-fill"></i></div>
        <div class="vk-cust-kpi-body">
            <span class="vk-cust-kpi-label">VIP</span>
            <span class="vk-cust-kpi-value" data-count-to="<?= (int) $kpiVip ?>">0</span>
            <span class="vk-cust-kpi-trend">High value</span>
        </div>
    </div>
    <div class="vk-cust-kpi vk-cust-kpi-red">
        <div class="vk-cust-kpi-icon"><i class="bi bi-exclamation-circle"></i></div>
        <div class="vk-cust-kpi-body">
            <span class="vk-cust-kpi-label">Outstanding</span>
            <span class="vk-cust-kpi-value" data-count-to="<?= (int) $kpiOutstanding ?>" data-count-money="1">0</span>
            <span class="vk-cust-kpi-trend">Account due</span>
        </div>
    </div>
    <div class="vk-cust-kpi vk-cust-kpi-green">
        <div class="vk-cust-kpi-icon"><i class="bi bi-graph-up"></i></div>
        <div class="vk-cust-kpi-body">
            <span class="vk-cust-kpi-label">Growth</span>
            <span class="vk-cust-kpi-value" data-count-to="<?= (float) abs($growthPct) ?>" data-count-suffix="%">0</span>
            <span class="vk-cust-kpi-trend">Monthly</span>
        </div>
    </div>
    <div class="vk-cust-kpi vk-cust-kpi-green">
        <div class="vk-cust-kpi-icon"><i class="bi bi-whatsapp"></i></div>
        <div class="vk-cust-kpi-body">
            <span class="vk-cust-kpi-label">WhatsApp</span>
            <span class="vk-cust-kpi-value" data-count-to="<?= (int) $kpiWhatsapp ?>">0</span>
            <span class="vk-cust-kpi-trend">With phone</span>
        </div>
    </div>
</div>

<div class="vk-cust-analytics" role="region" aria-label="CRM analytics">
    <div class="vk-cust-chart-card">
        <h3 class="vk-cust-chart-title">Customer growth</h3>
        <div class="vk-cust-chart-metric" data-count-to="<?= (int) $kpiNewMonth ?>">0</div>
        <div class="vk-cust-bar-row"><span class="vk-cust-bar-label">This month</span><div class="vk-cust-bar-track"><div class="vk-cust-bar-fill" data-width="<?= min(100, $kpiNewMonth * 10) ?>"></div></div><span class="vk-cust-bar-val"><?= (int) $kpiNewMonth ?></span></div>
        <div class="vk-cust-bar-row"><span class="vk-cust-bar-label">Last month</span><div class="vk-cust-bar-track"><div class="vk-cust-bar-fill" data-width="<?= min(100, $kpiNewLastMonth * 10) ?>"></div></div><span class="vk-cust-bar-val"><?= (int) $kpiNewLastMonth ?></span></div>
    </div>
    <div class="vk-cust-chart-card">
        <h3 class="vk-cust-chart-title">Customer types</h3>
        <div class="vk-cust-bar-row"><span class="vk-cust-bar-label">Individual</span><div class="vk-cust-bar-track"><div class="vk-cust-bar-fill" data-width="<?= count($rows) ? (int) round(($typeDistribution['individual'] / count($rows)) * 100) : 0 ?>"></div></div><span class="vk-cust-bar-val"><?= (int) $typeDistribution['individual'] ?></span></div>
        <div class="vk-cust-bar-row"><span class="vk-cust-bar-label">Business</span><div class="vk-cust-bar-track"><div class="vk-cust-bar-fill" data-width="<?= count($rows) ? (int) round(($typeDistribution['business'] / count($rows)) * 100) : 0 ?>"></div></div><span class="vk-cust-bar-val"><?= (int) $typeDistribution['business'] ?></span></div>
        <div class="vk-cust-bar-row"><span class="vk-cust-bar-label">VIP</span><div class="vk-cust-bar-track"><div class="vk-cust-bar-fill" data-width="<?= count($rows) ? (int) round(($typeDistribution['vip'] / count($rows)) * 100) : 0 ?>"></div></div><span class="vk-cust-bar-val"><?= (int) $typeDistribution['vip'] ?></span></div>
    </div>
    <div class="vk-cust-chart-card">
        <h3 class="vk-cust-chart-title">Outstanding</h3>
        <div class="vk-cust-chart-metric">₹<?= e(number_format($kpiOutstanding, 0)) ?></div>
        <div class="vk-cust-bar-row"><span class="vk-cust-bar-label">Due</span><div class="vk-cust-bar-track"><div class="vk-cust-bar-fill" data-width="<?= min(100, (int) ($kpiOutstanding / max(1, $kpiRevenue) * 100)) ?>" style="background:linear-gradient(90deg,var(--danger),var(--warning))"></div></div><span class="vk-cust-bar-val"><?= (int) $kpiVip ?></span></div>
    </div>
    <div class="vk-cust-chart-card">
        <h3 class="vk-cust-chart-title">Service mix</h3>
        <div class="vk-cust-bar-row"><span class="vk-cust-bar-label">Repairs</span><div class="vk-cust-bar-track"><div class="vk-cust-bar-fill" data-width="<?= min(100, $kpiActiveRepairs * 2) ?>"></div></div><span class="vk-cust-bar-val"><?= (int) $kpiActiveRepairs ?></span></div>
        <div class="vk-cust-bar-row"><span class="vk-cust-bar-label">CCTV</span><div class="vk-cust-bar-track"><div class="vk-cust-bar-fill" data-width="<?= min(100, $kpiCctv * 3) ?>"></div></div><span class="vk-cust-bar-val"><?= (int) $kpiCctv ?></span></div>
        <div class="vk-cust-bar-row"><span class="vk-cust-bar-label">Maint.</span><div class="vk-cust-bar-track"><div class="vk-cust-bar-fill" data-width="<?= min(100, $kpiMaint * 4) ?>"></div></div><span class="vk-cust-bar-val"><?= (int) $kpiMaint ?></span></div>
    </div>
</div>

<form class="vk-cust-toolbar" id="vkCustFilterForm" method="get" action="" role="search">
    <div class="vk-cust-toolbar-mobile-head d-lg-none">
        <span class="fw-semibold small"><i class="bi bi-sliders"></i> Filters</span>
        <button type="button" class="vk-cust-btn vk-cust-btn-icon" data-bs-toggle="collapse" data-bs-target="#vkCustToolbarCollapse" aria-expanded="false" aria-controls="vkCustToolbarCollapse" aria-label="Toggle filters"><i class="bi bi-chevron-down"></i></button>
    </div>
    <div class="collapse vk-cust-toolbar-collapse" id="vkCustToolbarCollapse">
        <div class="vk-cust-toolbar-inner">
            <div class="vk-cust-search-wrap">
                <i class="bi bi-search vk-cust-search-ico" aria-hidden="true"></i>
                <input type="search" name="q" id="vkCustSearch" class="form-control vk-cust-ctl" placeholder="Search customers… ( / )" value="<?= e($q) ?>" autocomplete="off" aria-label="Search customers">
            </div>
            <select id="vkCustFilterType" class="form-select vk-cust-ctl vk-cust-ctl-sm" aria-label="Customer type">
                <option value="">Type</option>
                <option value="individual">Individual</option>
                <option value="business">Business</option>
                <option value="vip">VIP</option>
                <option value="government">Government</option>
                <option value="school">School</option>
                <option value="ngo">NGO</option>
            </select>
            <select id="vkCustFilterStatus" class="form-select vk-cust-ctl vk-cust-ctl-sm" aria-label="Status">
                <option value="">Status</option>
                <option value="active">Active</option>
                <option value="lead">Lead</option>
            </select>
            <select class="form-select vk-cust-ctl vk-cust-ctl-sm" disabled title="Category not in schema"><option>Category</option></select>
            <input type="search" class="form-control vk-cust-ctl vk-cust-ctl-sm" placeholder="City" disabled>
            <select class="form-select vk-cust-ctl vk-cust-ctl-sm" disabled title="District not in schema"><option>District</option></select>
            <select class="form-select vk-cust-ctl vk-cust-ctl-sm" disabled title="Staff assignment not in schema"><option>Staff</option></select>
            <input type="date" class="form-control vk-cust-ctl vk-cust-ctl-date" disabled aria-label="Date from">
            <input type="date" class="form-control vk-cust-ctl vk-cust-ctl-date" disabled aria-label="Date to">
            <select name="per_page" class="form-select vk-cust-ctl vk-cust-ctl-xs" aria-label="Rows per page" onchange="this.form.requestSubmit()">
                <?php foreach ($perPageOptions as $opt): ?>
                <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
            <div class="vk-cust-toolbar-btns">
                <a class="vk-cust-btn vk-cust-btn-primary" href="<?= e(BASE_URL) ?>/modules/customers/add.php"><i class="bi bi-person-plus"></i><span class="d-none d-xl-inline">New</span></a>
                <a class="vk-cust-btn" href="<?= e(BASE_URL) ?>/modules/customers/add.php" title="Import via add form"><i class="bi bi-upload"></i></a>
                <button type="button" class="vk-cust-btn" id="vkCustExportCsv" aria-label="Export CSV"><i class="bi bi-filetype-csv"></i></button>
                <button type="button" class="vk-cust-btn" id="vkCustExportExcel" aria-label="Export Excel"><i class="bi bi-file-earmark-spreadsheet"></i></button>
                <button type="button" class="vk-cust-btn" id="vkCustExportPdf" aria-label="Print PDF"><i class="bi bi-file-pdf"></i></button>
                <button type="button" class="vk-cust-btn" id="vkCustPrint" aria-label="Print"><i class="bi bi-printer"></i></button>
                <button type="button" class="vk-cust-btn" id="vkCustRefresh" aria-label="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
                <button type="button" class="vk-cust-btn" id="vkCustReset" aria-label="Reset"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
    </div>
</form>

<div class="vk-cust-panel" id="vkCustPanel">
    <div class="vk-cust-panel-scroll vk-cust-desktop-only">
        <table class="table vk-cust-table mb-0" id="vkCustTable">
            <thead>
                <tr>
                    <th class="vk-cust-sticky-col vk-cust-sticky-check" style="width:34px"><input type="checkbox" class="form-check-input" id="vkCustSelectAll" aria-label="Select all"></th>
                    <th class="vk-cust-sticky-col vk-cust-sticky-id" style="width:88px">ID</th>
                    <th style="width:200px">Customer</th>
                    <th class="vk-cust-col-hide-lg" style="width:120px">Company</th>
                    <th style="width:110px">Phone</th>
                    <th class="vk-cust-col-hide-md" style="width:40px">WA</th>
                    <th class="vk-cust-col-hide-md" style="width:140px">Email</th>
                    <th class="vk-cust-col-hide-lg" style="width:88px">City</th>
                    <th style="width:88px">Type</th>
                    <th style="width:72px">Status</th>
                    <th style="width:88px">Due</th>
                    <th class="vk-cust-col-hide-md" style="width:88px">Last Svc</th>
                    <th class="vk-cust-col-hide-lg" style="width:88px">Created</th>
                    <th style="width:280px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
            <?php else: ?>
            <?php foreach ($rows as $r): ?>
            <?php
                $cid = (int) $r['id'];
                $bal = (float) ($r['current_balance'] ?? 0);
                $repCnt = $repairCounts[$cid] ?? 0;
                $cctvCnt = $cctvCounts[$cid] ?? 0;
                $maintCnt = $maintCounts[$cid] ?? 0;
                $invCnt = $invoiceCounts[$cid] ?? 0;
                $type = $vkCustType($r, $bal);
                $status = $vkCustStatus($r, $repCnt);
                $balUi = $vkCustBalanceUi($bal);
                $company = $vkCustCompany((string) $r['name']);
                $city = $vkCustCity($r['address'] ?? null);
                $lastSvc = $lastServices[$cid] ?? '';
                $phone = (string) ($r['phone'] ?? '');
                $email = (string) ($r['email'] ?? '');
                $waLink = $vkCustWaLink($phone);
                $drawerJson = htmlspecialchars(json_encode([
                    'id' => $cid,
                    'name' => (string) $r['name'],
                    'company' => $company !== '—' ? $company : '',
                    'phone' => $phone,
                    'email' => $email,
                    'address' => (string) ($r['address'] ?? ''),
                    'account' => (string) ($r['account_code'] ?? ''),
                    'balance' => number_format($bal, 2),
                    'created' => $vkCustFormatDate((string) ($r['created_at'] ?? '')),
                    'repairs' => (string) $repCnt,
                    'cctv' => (string) $cctvCnt,
                    'maint' => (string) $maintCnt,
                    'invoices' => (string) $invCnt,
                    'lastService' => $vkCustFormatDate($lastSvc),
                    'initials' => $vkCustInitials((string) $r['name']),
                ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
            ?>
            <tr data-customer-id="<?= $cid ?>"
                data-type="<?= e($type['key']) ?>"
                data-status="<?= e($status['key']) ?>"
                data-export-id="VK-<?= $cid ?>"
                data-export-name="<?= e((string) $r['name']) ?>"
                data-export-company="<?= e($company) ?>"
                data-export-phone="<?= e($phone) ?>"
                data-export-email="<?= e($email) ?>"
                data-export-city="<?= e($city) ?>"
                data-export-type="<?= e($type['label']) ?>"
                data-export-status="<?= e($status['label']) ?>"
                data-export-balance="<?= e(number_format($bal, 2)) ?>"
                data-export-last-service="<?= e($vkCustFormatDate($lastSvc)) ?>"
                data-export-created="<?= e($vkCustFormatDate((string) ($r['created_at'] ?? ''))) ?>">
                <td class="vk-cust-sticky-col vk-cust-sticky-check" onclick="event.stopPropagation()"><input type="checkbox" class="form-check-input vk-cust-row-check" aria-label="Select customer"></td>
                <td class="vk-cust-sticky-col vk-cust-sticky-id"><span class="vk-cust-id">VK-<?= $cid ?></span></td>
                <td>
                    <div class="vk-cust-person">
                        <div class="vk-cust-avatar" aria-hidden="true"><?= e($vkCustInitials((string) $r['name'])) ?></div>
                        <div class="min-w-0">
                            <button type="button" class="vk-cust-name vk-cust-name-btn vk-cust-highlight-target" data-customer-drawer="<?= $drawerJson ?>"><?= e((string) $r['name']) ?></button>
                            <div class="vk-cust-module-badges">
                                <?php if ($repCnt): ?><span class="vk-cust-mod vk-cust-mod-has" title="Repairs">R <?= $repCnt ?></span><?php endif; ?>
                                <?php if ($maintCnt): ?><span class="vk-cust-mod vk-cust-mod-has" title="Maintenance">M <?= $maintCnt ?></span><?php endif; ?>
                                <?php if ($cctvCnt): ?><span class="vk-cust-mod vk-cust-mod-has" title="CCTV">C <?= $cctvCnt ?></span><?php endif; ?>
                                <?php if ($invCnt): ?><span class="vk-cust-mod vk-cust-mod-has" title="Invoices">I <?= $invCnt ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td class="vk-cust-col-hide-lg"><span class="vk-cust-company"><?= e($company) ?></span></td>
                <td><?php if ($phone): ?><a class="vk-cust-link" href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>" onclick="event.stopPropagation()"><?= e($phone) ?></a><?php else: ?>—<?php endif; ?></td>
                <td class="vk-cust-col-hide-md text-center"><?php if ($waLink): ?><a class="vk-cust-wa" href="<?= e($waLink) ?>" target="_blank" rel="noopener" onclick="event.stopPropagation()" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a><?php else: ?>—<?php endif; ?></td>
                <td class="vk-cust-col-hide-md"><?php if ($email): ?><a class="vk-cust-link vk-cust-email-ico" href="mailto:<?= e($email) ?>" onclick="event.stopPropagation()"><i class="bi bi-envelope me-1"></i><span class="vk-cust-highlight-target"><?= e($email) ?></span></a><?php else: ?>—<?php endif; ?></td>
                <td class="vk-cust-col-hide-lg vk-cust-date"><?= e($city) ?></td>
                <td><span class="vk-cust-badge vk-cust-type-<?= e($type['key']) ?>"><?= e($type['label']) ?></span></td>
                <td><span class="vk-cust-badge vk-cust-st-<?= e($status['key']) ?>"><?= e($status['label']) ?></span></td>
                <td class="vk-cust-amt <?= e($balUi['class']) ?>"><?= e(number_format($bal, 2)) ?></td>
                <td class="vk-cust-col-hide-md vk-cust-date"><?= e($vkCustFormatDate($lastSvc)) ?></td>
                <td class="vk-cust-col-hide-lg vk-cust-date"><?= e($vkCustFormatDate((string) ($r['created_at'] ?? ''))) ?></td>
                <td onclick="event.stopPropagation()">
                    <div class="vk-cust-actions">
                        <a class="vk-cust-act" href="<?= e(BASE_URL) ?>/modules/customers/profile.php?id=<?= $cid ?>" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></a>
                        <a class="vk-cust-act" href="<?= e(BASE_URL) ?>/modules/customers/edit.php?id=<?= $cid ?>" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a class="vk-cust-act" href="<?= e(BASE_URL) ?>/modules/customers/profile.php?id=<?= $cid ?>#tab-rep" data-bs-toggle="tooltip" title="Repair history"><i class="bi bi-tools"></i></a>
                        <a class="vk-cust-act" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php?q=<?= e(rawurlencode((string) $r['name'])) ?>" data-bs-toggle="tooltip" title="Maintenance"><i class="bi bi-wrench"></i></a>
                        <a class="vk-cust-act" href="<?= e(BASE_URL) ?>/modules/invoices/list.php?customer_id=<?= $cid ?>" data-bs-toggle="tooltip" title="Invoices"><i class="bi bi-receipt"></i></a>
                        <a class="vk-cust-act" href="<?= e(BASE_URL) ?>/modules/invoices/create.php" data-bs-toggle="tooltip" title="Quotation"><i class="bi bi-file-earmark-text"></i></a>
                        <?php if ($waLink): ?>
                        <a class="vk-cust-act" href="<?= e(BASE_URL) ?>/modules/whatsapp/index.php?chat=<?= e(rawurlencode($phone)) ?>" data-bs-toggle="tooltip" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        <?php else: ?>
                        <span class="vk-cust-act" aria-disabled="true" data-bs-toggle="tooltip" title="No phone"><i class="bi bi-whatsapp"></i></span>
                        <?php endif; ?>
                        <?php if ($phone): ?>
                        <a class="vk-cust-act" href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>" data-bs-toggle="tooltip" title="Call"><i class="bi bi-telephone"></i></a>
                        <?php endif; ?>
                        <?php if ($email): ?>
                        <a class="vk-cust-act" href="mailto:<?= e($email) ?>" data-bs-toggle="tooltip" title="Email"><i class="bi bi-envelope"></i></a>
                        <?php endif; ?>
                        <a class="vk-cust-act vk-cust-act-danger" href="<?= e(BASE_URL) ?>/modules/customers/delete.php?id=<?= $cid ?>" data-bs-toggle="tooltip" title="Delete" onclick="return confirm('Delete this customer? Blocked if invoices exist.');"><i class="bi bi-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php if (!$rows): ?>
        <div class="vk-cust-empty">
            <div class="vk-cust-empty-icon"><i class="bi bi-people"></i></div>
            <h2>No customers found</h2>
            <p>Try a different search or add your first customer.</p>
            <a class="vk-cust-btn vk-cust-btn-primary" href="<?= e(BASE_URL) ?>/modules/customers/add.php"><i class="bi bi-person-plus"></i> Create Customer</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="vk-cust-mobile-only">
        <?php if (!$rows): ?>
        <div class="vk-cust-empty">
            <div class="vk-cust-empty-icon"><i class="bi bi-people"></i></div>
            <h2>No customers found</h2>
            <a class="vk-cust-btn vk-cust-btn-primary mt-2" href="<?= e(BASE_URL) ?>/modules/customers/add.php">Create Customer</a>
        </div>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <?php
            $cid = (int) $r['id'];
            $bal = (float) ($r['current_balance'] ?? 0);
            $repCnt = $repairCounts[$cid] ?? 0;
            $type = $vkCustType($r, $bal);
            $status = $vkCustStatus($r, $repCnt);
            $balUi = $vkCustBalanceUi($bal);
            $phone = (string) ($r['phone'] ?? '');
            $drawerJson = htmlspecialchars(json_encode([
                'id' => $cid, 'name' => (string) $r['name'], 'phone' => $phone, 'email' => (string) ($r['email'] ?? ''),
                'address' => (string) ($r['address'] ?? ''), 'account' => (string) ($r['account_code'] ?? ''),
                'balance' => number_format($bal, 2), 'created' => $vkCustFormatDate((string) ($r['created_at'] ?? '')),
                'repairs' => (string) $repCnt, 'cctv' => (string) ($cctvCounts[$cid] ?? 0),
                'maint' => (string) ($maintCounts[$cid] ?? 0), 'invoices' => (string) ($invoiceCounts[$cid] ?? 0),
                'lastService' => $vkCustFormatDate($lastServices[$cid] ?? ''), 'initials' => $vkCustInitials((string) $r['name']),
            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        ?>
        <article class="vk-cust-mobile-card" data-customer-id="<?= $cid ?>" data-type="<?= e($type['key']) ?>" data-status="<?= e($status['key']) ?>">
            <div class="vk-cust-mobile-card-head">
                <div class="vk-cust-avatar"><?= e($vkCustInitials((string) $r['name'])) ?></div>
                <div class="flex-grow-1 min-w-0">
                    <button type="button" class="vk-cust-name vk-cust-name-btn" data-customer-drawer="<?= $drawerJson ?>"><?= e((string) $r['name']) ?></button>
                    <div class="vk-cust-id d-inline-block mt-1">VK-<?= $cid ?></div>
                </div>
                <span class="vk-cust-amt <?= e($balUi['class']) ?>"><?= e(number_format($bal, 2)) ?></span>
            </div>
            <div class="d-flex gap-2 mb-2">
                <span class="vk-cust-badge vk-cust-type-<?= e($type['key']) ?>"><?= e($type['label']) ?></span>
                <span class="vk-cust-badge vk-cust-st-<?= e($status['key']) ?>"><?= e($status['label']) ?></span>
            </div>
            <dl class="vk-cust-mobile-grid">
                <dt>Phone</dt><dd><?= e($phone ?: '—') ?></dd>
                <dt>Repairs</dt><dd><?= $repCnt ?></dd>
                <dt>Created</dt><dd><?= e($vkCustFormatDate((string) ($r['created_at'] ?? ''))) ?></dd>
                <dt>Last svc</dt><dd><?= e($vkCustFormatDate($lastServices[$cid] ?? '')) ?></dd>
            </dl>
            <div class="vk-cust-actions">
                <a class="vk-cust-act" href="<?= e(BASE_URL) ?>/modules/customers/profile.php?id=<?= $cid ?>"><i class="bi bi-eye"></i></a>
                <a class="vk-cust-act" href="<?= e(BASE_URL) ?>/modules/customers/edit.php?id=<?= $cid ?>"><i class="bi bi-pencil"></i></a>
                <a class="vk-cust-act" href="<?= e(BASE_URL) ?>/modules/invoices/list.php?customer_id=<?= $cid ?>"><i class="bi bi-receipt"></i></a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <footer class="vk-cust-footer">
        <span id="vkCustMeta">Showing <?= (int) $pageFrom ?>–<?= (int) $pageTo ?> of <?= (int) $total ?></span>
        <?php if ($pg['pages'] > 1): ?>
        <nav class="vk-cust-page-nav" aria-label="Pagination">
            <?php if ($pg['page'] > 1): ?>
            <a class="vk-cust-page-link" href="?<?= e($queryBase(['p' => $pg['page'] - 1])) ?>" aria-label="Previous"><i class="bi bi-chevron-left"></i></a>
            <?php endif; ?>
            <?php
            $start = max(1, $pg['page'] - 2);
            $end = min($pg['pages'], $pg['page'] + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a class="vk-cust-page-link<?= $i === $pg['page'] ? ' is-active' : '' ?>" href="?<?= e($queryBase(['p' => $i])) ?>"<?= $i === $pg['page'] ? ' aria-current="page"' : '' ?>><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pg['page'] < $pg['pages']): ?>
            <a class="vk-cust-page-link" href="?<?= e($queryBase(['p' => $pg['page'] + 1])) ?>" aria-label="Next"><i class="bi bi-chevron-right"></i></a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </footer>
</div>

<div class="vk-cust-drawer-backdrop" id="vkCustDrawerBackdrop" aria-hidden="true"></div>
<aside class="vk-cust-drawer" id="vkCustDrawer" aria-hidden="true" aria-label="Customer profile">
    <div class="vk-cust-drawer-head">
        <div class="vk-cust-avatar" id="vkCustDrawerAvatar" style="width:48px;height:48px;font-size:16px">C</div>
        <div class="min-w-0 flex-grow-1">
            <h2 class="h6 mb-0 fw-bold" id="vkCustDrawerName">Customer</h2>
            <p class="small text-muted mb-0" id="vkCustDrawerCompany">—</p>
        </div>
        <button type="button" class="vk-cust-drawer-close" id="vkCustDrawerClose" aria-label="Close profile"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="vk-cust-drawer-scroll">
        <div class="vk-cust-drawer-section">
            <h3 class="vk-cust-drawer-section-title">Contact</h3>
            <p class="small mb-1"><i class="bi bi-telephone me-2 text-muted"></i><span id="vkCustDrawerPhone">—</span></p>
            <p class="small mb-1"><i class="bi bi-envelope me-2 text-muted"></i><span id="vkCustDrawerEmail">—</span></p>
            <p class="small mb-0"><i class="bi bi-geo-alt me-2 text-muted"></i><span id="vkCustDrawerAddress">—</span></p>
        </div>
        <div class="vk-cust-drawer-section">
            <h3 class="vk-cust-drawer-section-title">Account</h3>
            <div class="vk-cust-stat-grid">
                <div class="vk-cust-stat"><div class="vk-cust-stat-label">Account</div><div class="vk-cust-stat-value" id="vkCustDrawerAccount">—</div></div>
                <div class="vk-cust-stat"><div class="vk-cust-stat-label">Balance</div><div class="vk-cust-stat-value" id="vkCustDrawerBalance">—</div></div>
                <div class="vk-cust-stat"><div class="vk-cust-stat-label">Since</div><div class="vk-cust-stat-value" id="vkCustDrawerSince">—</div></div>
                <div class="vk-cust-stat"><div class="vk-cust-stat-label">Last service</div><div class="vk-cust-stat-value" id="vkCustDrawerLastService">—</div></div>
            </div>
        </div>
        <div class="vk-cust-drawer-section">
            <h3 class="vk-cust-drawer-section-title">VK modules</h3>
            <div class="vk-cust-stat-grid">
                <div class="vk-cust-stat"><div class="vk-cust-stat-label">Repairs</div><div class="vk-cust-stat-value" id="vkCustDrawerRepairs">0</div></div>
                <div class="vk-cust-stat"><div class="vk-cust-stat-label">CCTV</div><div class="vk-cust-stat-value" id="vkCustDrawerCctv">0</div></div>
                <div class="vk-cust-stat"><div class="vk-cust-stat-label">Maintenance</div><div class="vk-cust-stat-value" id="vkCustDrawerMaint">0</div></div>
                <div class="vk-cust-stat"><div class="vk-cust-stat-label">Invoices</div><div class="vk-cust-stat-value" id="vkCustDrawerInvoices">0</div></div>
            </div>
        </div>
        <div class="vk-cust-drawer-section">
            <h3 class="vk-cust-drawer-section-title">Timeline</h3>
            <ul class="vk-cust-timeline">
                <li>Customer profile synced with VK CRM</li>
                <li>View full history on the profile page</li>
            </ul>
        </div>
        <div class="vk-cust-drawer-section">
            <h3 class="vk-cust-drawer-section-title">Quick actions</h3>
            <div class="vk-cust-drawer-actions">
                <a class="vk-cust-btn" id="vkCustDrawerProfile" href="#"><i class="bi bi-person"></i> Profile</a>
                <a class="vk-cust-btn" id="vkCustDrawerEdit" href="#"><i class="bi bi-pencil"></i> Edit</a>
                <a class="vk-cust-btn" href="<?= e(BASE_URL) ?>/modules/repairs/add.php"><i class="bi bi-tools"></i> Repair</a>
                <a class="vk-cust-btn" href="<?= e(BASE_URL) ?>/modules/invoices/create.php"><i class="bi bi-receipt"></i> Invoice</a>
            </div>
        </div>
    </div>
</aside>

</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/customers-list.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
