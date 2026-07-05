<?php
declare(strict_types=1);

$pageTitle = 'Repair jobs';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPageOptions = [10, 15, 25, 50, 100];
$perPageReq = (int) ($_GET['per_page'] ?? 15);
$perPage = in_array($perPageReq, $perPageOptions, true) ? $perPageReq : 15;

$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (r.job_number LIKE ? OR c.name LIKE ? OR r.problem_description LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$statusAllowed = ['pending', 'diagnosing', 'in_progress', 'completed', 'delivered'];
if ($status !== '' && in_array($status, $statusAllowed, true)) {
    $where .= ' AND r.status = ?';
    $params[] = $status;
}

$countSt = $pdo->prepare("SELECT COUNT(*) FROM repair_jobs r JOIN customers c ON c.id = r.customer_id WHERE $where");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();
$pg = paginate($total, $page, $perPage);

$sql = "SELECT r.*, c.name AS customer_name, t.name AS technician_name
        FROM repair_jobs r
        JOIN customers c ON c.id = r.customer_id
        LEFT JOIN technicians t ON t.id = r.technician_id
        WHERE $where
        ORDER BY r.id DESC
        LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$today = date('Y-m-d');
$pageFrom = $total > 0 ? $pg['offset'] + 1 : 0;
$pageTo = min($pg['offset'] + count($rows), $total);
$pageRevenue = 0.0;
$todayRevenue = 0.0;
$kpiNew = $kpiDiagnosis = $kpiParts = $kpiProgress = $kpiReady = $kpiDelivered = $kpiCancelled = 0;

$queryBase = static function (array $extra = []) use ($q, $status, $perPage): string {
    return http_build_query(array_merge([
        'q' => $q,
        'status' => $status,
        'per_page' => $perPage,
    ], $extra));
};

$vkRepInitials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(substr((string) ($parts[0] ?? 'C'), 0, 1) . substr((string) ($parts[1] ?? ''), 0, 1));
};

$vkRepDeviceIcon = static function (string $type): string {
    return match ($type) {
        'printer' => 'bi-printer',
        'computer', 'laptop', 'desktop' => 'bi-laptop',
        'cctv_dvr', 'cctv', 'dvr' => 'bi-camera-video',
        default => 'bi-tools',
    };
};

$vkRepPriority = static function (array $r): array {
    if (!empty($r['emergency_priority'])) {
        return ['key' => 'critical', 'label' => 'Critical'];
    }
    return match ((string) ($r['status'] ?? '')) {
        'diagnosing', 'in_progress' => ['key' => 'high', 'label' => 'High'],
        'pending' => ['key' => 'medium', 'label' => 'Medium'],
        default => ['key' => 'low', 'label' => 'Low'],
    };
};

$vkRepStatusDisplay = static function (string $st): array {
    return match ($st) {
        'pending' => ['key' => 'received', 'label' => 'Received'],
        'diagnosing' => ['key' => 'diagnosing', 'label' => 'Diagnosing'],
        'in_progress' => ['key' => 'repairing', 'label' => 'Repairing'],
        'completed' => ['key' => 'ready', 'label' => 'Ready'],
        'delivered' => ['key' => 'delivered', 'label' => 'Delivered'],
        default => ['key' => 'pending', 'label' => str_replace('_', ' ', $st)],
    };
};

$vkRepPaymentDisplay = static function (array $r): array {
    $st = (string) ($r['status'] ?? '');
    if ($st === 'delivered' && !empty($r['invoice_id'])) {
        return ['key' => 'paid', 'label' => 'Paid'];
    }
    if (!empty($r['invoice_id'])) {
        return ['key' => 'partial', 'label' => 'Partial'];
    }
    return ['key' => 'pending', 'label' => 'Pending'];
};

$vkRepFormatDate = static function (?string $iso): string {
    if ($iso === null || $iso === '') {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d M Y', $ts) : '—';
};

$vkRepProblemShort = static function (?string $text): string {
    $t = trim((string) $text);
    if ($t === '') {
        return '—';
    }
    return strlen($t) > 56 ? substr($t, 0, 53) . '…' : $t;
};

$vkRepHasWarranty = static function (?string $expiry): bool {
    if ($expiry === null || $expiry === '') {
        return false;
    }
    return $expiry >= date('Y-m-d');
};

foreach ($rows as $rowSum) {
    $pageRevenue += (float) ($rowSum['estimated_cost'] ?? 0);
    $created = substr((string) ($rowSum['created_at'] ?? ''), 0, 10);
    if ($created === $today) {
        $todayRevenue += (float) ($rowSum['estimated_cost'] ?? 0);
    }
    $stRow = (string) ($rowSum['status'] ?? '');
    match ($stRow) {
        'pending' => $kpiNew++,
        'diagnosing' => $kpiDiagnosis++,
        'in_progress' => $kpiProgress++,
        'completed' => $kpiReady++,
        'delivered' => $kpiDelivered++,
        default => null,
    };
}

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/repairs-list.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/repairs-list.js');
$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link href="' . e(base_url('assets/css/repairs-list.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="vkRepairApp" class="vk-repair-admin"
     data-filtered-total="<?= (int) $total ?>"
     data-page-from="<?= (int) $pageFrom ?>"
     data-page-to="<?= (int) $pageTo ?>"
     data-page-total="<?= (int) count($rows) ?>"
     data-page-revenue="<?= e((string) $pageRevenue) ?>"
     data-today-revenue="<?= e((string) $todayRevenue) ?>"
     data-search-query="<?= e($q) ?>">

<header class="vk-repair-header">
    <div class="vk-repair-header-inner">
        <div>
            <h1 class="vk-repair-title">Repair Jobs</h1>
            <p class="vk-repair-subtitle d-none d-md-block">Enterprise repair queue · diagnostics, parts &amp; delivery</p>
        </div>
        <a class="btn vk-repair-btn vk-repair-btn-primary vk-repair-header-cta" href="<?= e(BASE_URL) ?>/modules/repairs/add.php">
            <i class="bi bi-tools" aria-hidden="true"></i><span>Create Repair Job</span>
        </a>
    </div>
</header>

<div class="vk-repair-kpi-grid" role="region" aria-label="Repair statistics">
    <div class="vk-repair-kpi vk-repair-kpi-blue">
        <div class="vk-repair-kpi-icon"><i class="bi bi-collection"></i></div>
        <div class="vk-repair-kpi-body">
            <span class="vk-repair-kpi-label">Total Repairs</span>
            <span class="vk-repair-kpi-value"><?= e((string) $total) ?></span>
            <span class="vk-repair-kpi-trend">Matching filters</span>
        </div>
    </div>
    <div class="vk-repair-kpi vk-repair-kpi-gray">
        <div class="vk-repair-kpi-icon"><i class="bi bi-inbox"></i></div>
        <div class="vk-repair-kpi-body">
            <span class="vk-repair-kpi-label">New Jobs</span>
            <span class="vk-repair-kpi-value"><?= e((string) $kpiNew) ?></span>
            <span class="vk-repair-kpi-trend">On this page</span>
        </div>
    </div>
    <div class="vk-repair-kpi vk-repair-kpi-teal">
        <div class="vk-repair-kpi-icon"><i class="bi bi-search"></i></div>
        <div class="vk-repair-kpi-body">
            <span class="vk-repair-kpi-label">Diagnosis</span>
            <span class="vk-repair-kpi-value"><?= e((string) $kpiDiagnosis) ?></span>
            <span class="vk-repair-kpi-trend">On this page</span>
        </div>
    </div>
    <div class="vk-repair-kpi vk-repair-kpi-orange">
        <div class="vk-repair-kpi-icon"><i class="bi bi-box-seam"></i></div>
        <div class="vk-repair-kpi-body">
            <span class="vk-repair-kpi-label">Waiting Parts</span>
            <span class="vk-repair-kpi-value"><?= e((string) $kpiParts) ?></span>
            <span class="vk-repair-kpi-trend">Not tracked</span>
        </div>
    </div>
    <div class="vk-repair-kpi vk-repair-kpi-purple">
        <div class="vk-repair-kpi-icon"><i class="bi bi-gear-wide-connected"></i></div>
        <div class="vk-repair-kpi-body">
            <span class="vk-repair-kpi-label">In Progress</span>
            <span class="vk-repair-kpi-value" id="vkRepairStatInProgressGlobal"><?= e((string) $kpiProgress) ?></span>
            <span class="vk-repair-kpi-trend" id="vkRepairStatInProgressGlobalTrend">On this page</span>
        </div>
    </div>
    <div class="vk-repair-kpi vk-repair-kpi-green">
        <div class="vk-repair-kpi-icon"><i class="bi bi-check2-circle"></i></div>
        <div class="vk-repair-kpi-body">
            <span class="vk-repair-kpi-label">Ready</span>
            <span class="vk-repair-kpi-value" id="vkRepairStatReadyGlobal"><?= e((string) $kpiReady) ?></span>
            <span class="vk-repair-kpi-trend" id="vkRepairStatReadyGlobalTrend">Completed</span>
        </div>
    </div>
    <div class="vk-repair-kpi vk-repair-kpi-emerald">
        <div class="vk-repair-kpi-icon"><i class="bi bi-truck"></i></div>
        <div class="vk-repair-kpi-body">
            <span class="vk-repair-kpi-label">Delivered</span>
            <span class="vk-repair-kpi-value" id="vkRepairStatDeliveredGlobal"><?= e((string) $kpiDelivered) ?></span>
            <span class="vk-repair-kpi-trend" id="vkRepairStatDeliveredGlobalTrend">On this page</span>
        </div>
    </div>
    <div class="vk-repair-kpi vk-repair-kpi-red">
        <div class="vk-repair-kpi-icon"><i class="bi bi-x-circle"></i></div>
        <div class="vk-repair-kpi-body">
            <span class="vk-repair-kpi-label">Cancelled</span>
            <span class="vk-repair-kpi-value"><?= e((string) $kpiCancelled) ?></span>
            <span class="vk-repair-kpi-trend">Not tracked</span>
        </div>
    </div>
    <div class="vk-repair-kpi vk-repair-kpi-blue">
        <div class="vk-repair-kpi-icon"><i class="bi bi-currency-rupee"></i></div>
        <div class="vk-repair-kpi-body">
            <span class="vk-repair-kpi-label">Page Revenue</span>
            <span class="vk-repair-kpi-value" id="vkRepairStatRevenue">—</span>
            <span class="vk-repair-kpi-trend">Estimates</span>
        </div>
    </div>
    <div class="vk-repair-kpi vk-repair-kpi-teal">
        <div class="vk-repair-kpi-icon"><i class="bi bi-calendar-day"></i></div>
        <div class="vk-repair-kpi-body">
            <span class="vk-repair-kpi-label">Today</span>
            <span class="vk-repair-kpi-value" id="vkRepairStatToday">—</span>
            <span class="vk-repair-kpi-trend">Created today</span>
        </div>
    </div>
</div>

<form class="vk-repair-toolbar" id="vkRepairFilterForm" method="get" action="" role="search">
    <div class="vk-repair-toolbar-mobile-head d-lg-none">
        <span class="fw-semibold small"><i class="bi bi-sliders"></i> Filters</span>
        <button type="button" class="vk-repair-btn vk-repair-btn-icon" data-bs-toggle="collapse" data-bs-target="#vkRepairToolbarCollapse" aria-expanded="false" aria-controls="vkRepairToolbarCollapse" aria-label="Toggle filters"><i class="bi bi-chevron-down"></i></button>
    </div>
    <div class="collapse vk-repair-toolbar-collapse" id="vkRepairToolbarCollapse">
        <div class="vk-repair-toolbar-inner">
            <div class="vk-repair-search-wrap">
                <i class="bi bi-search vk-repair-search-ico" aria-hidden="true"></i>
                <input type="search" name="q" id="vkRepairSearch" class="form-control vk-repair-ctl" placeholder="Search repairs… ( / )" value="<?= e($q) ?>" autocomplete="off" aria-label="Search repair jobs">
            </div>
            <select name="status" class="form-select vk-repair-ctl vk-repair-ctl-sm" aria-label="Repair status">
                <option value="">Status</option>
                <?php foreach ($statusAllowed as $s): ?>
                <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="search" class="form-control vk-repair-ctl vk-repair-ctl-sm" placeholder="Customer" disabled title="Use search for customer">
            <select class="form-select vk-repair-ctl vk-repair-ctl-sm" disabled title="Device filter coming soon"><option>Device</option></select>
            <input type="search" class="form-control vk-repair-ctl vk-repair-ctl-sm" placeholder="Brand" disabled>
            <select class="form-select vk-repair-ctl vk-repair-ctl-sm" disabled title="Technician filter coming soon"><option>Technician</option></select>
            <select class="form-select vk-repair-ctl vk-repair-ctl-xs" disabled title="Priority from job data"><option>Priority</option></select>
            <select class="form-select vk-repair-ctl vk-repair-ctl-sm" disabled title="Payment filter coming soon"><option>Payment</option></select>
            <input type="date" class="form-control vk-repair-ctl vk-repair-ctl-date" disabled aria-label="Date from">
            <input type="date" class="form-control vk-repair-ctl vk-repair-ctl-date" disabled aria-label="Date to">
            <select class="form-select vk-repair-ctl vk-repair-ctl-xs" disabled><option>Branch</option></select>
            <select name="per_page" class="form-select vk-repair-ctl vk-repair-ctl-xs" aria-label="Rows per page">
                <?php foreach ($perPageOptions as $opt): ?>
                <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
            <div class="vk-repair-toolbar-btns">
                <button type="submit" class="vk-repair-btn vk-repair-btn-primary" aria-label="Apply filters"><i class="bi bi-funnel"></i><span class="d-none d-xl-inline ms-1">Apply</span></button>
                <button type="button" class="vk-repair-btn vk-repair-btn-ghost" id="vkRepairReset">Reset</button>
                <button type="button" class="vk-repair-btn vk-repair-btn-ghost" id="vkRepairRefresh" title="Refresh" aria-label="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
                <button type="button" class="vk-repair-btn vk-repair-btn-ghost" id="vkRepairExportCsv" title="Export CSV" aria-label="Export CSV"><i class="bi bi-download"></i></button>
                <button type="button" class="vk-repair-btn vk-repair-btn-ghost" id="vkRepairExportExcel" title="Export Excel" aria-label="Export Excel"><i class="bi bi-file-earmark-spreadsheet"></i></button>
                <button type="button" class="vk-repair-btn vk-repair-btn-ghost" id="vkRepairExportPdf" title="Export PDF" aria-label="Export PDF"><i class="bi bi-file-earmark-pdf"></i></button>
                <button type="button" class="vk-repair-btn vk-repair-btn-ghost" id="vkRepairPrint" title="Print" aria-label="Print"><i class="bi bi-printer"></i></button>
            </div>
        </div>
    </div>
</form>

<div class="vk-repair-panel" id="vkRepairPanel">
    <div class="vk-repair-panel-scroll vk-repair-desktop-only">
        <table class="table table-sm table-borderless vk-repair-table sortable mb-0" id="vkRepairTable">
            <thead>
                <tr>
                    <th class="vk-repair-sticky-col vk-repair-sticky-check" scope="col" style="width:34px"><input type="checkbox" class="form-check-input" id="vkRepairSelectAll" aria-label="Select all"></th>
                    <th class="vk-repair-sticky-col vk-repair-sticky-no" data-sort="0" scope="col" style="width:108px">Repair No</th>
                    <th data-sort="1" scope="col" style="width:140px">Customer</th>
                    <th class="vk-rep-col-phone d-none d-xxl-table-cell" scope="col" style="width:100px">Phone</th>
                    <th data-sort="2" scope="col" style="width:130px">Device</th>
                    <th class="d-none d-xl-table-cell" scope="col" style="width:80px">Brand</th>
                    <th class="d-none d-xl-table-cell" scope="col" style="width:80px">Model</th>
                    <th class="d-none d-xxl-table-cell" scope="col" style="width:90px">Serial</th>
                    <th class="d-none d-xxl-table-cell" scope="col" style="width:90px">IMEI</th>
                    <th class="d-none d-lg-table-cell" scope="col" style="width:160px">Problem</th>
                    <th scope="col" style="width:110px">Technician</th>
                    <th scope="col" style="width:72px">Priority</th>
                    <th data-sort="3" scope="col" style="width:96px">Status</th>
                    <th class="d-none d-md-table-cell" scope="col" style="width:80px">Payment</th>
                    <th data-sort="4" data-type="number" scope="col" style="width:88px" class="text-end">Estimate</th>
                    <th class="d-none d-xl-table-cell text-end" scope="col" style="width:72px">Paid</th>
                    <th class="d-none d-xl-table-cell text-end" scope="col" style="width:72px">Balance</th>
                    <th class="d-none d-lg-table-cell" scope="col" style="width:96px">Created</th>
                    <th class="d-none d-xl-table-cell" scope="col" style="width:96px">Expected</th>
                    <th scope="col" style="width:340px" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody class="vk-repair-data-body">
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="20">
                        <div class="vk-repair-empty" role="status">
                            <div class="vk-repair-empty-icon"><i class="bi bi-tools"></i></div>
                            <div class="vk-repair-empty-title">No repair jobs found</div>
                            <p class="vk-repair-empty-text"><?= ($q !== '' || $status !== '') ? 'Try adjusting your search or filters.' : 'Create your first repair job to get started.' ?></p>
                            <?php if ($q === '' && $status === ''): ?>
                            <a class="vk-repair-btn vk-repair-btn-primary" href="<?= e(BASE_URL) ?>/modules/repairs/add.php"><i class="bi bi-plus-lg"></i> Create Repair Job</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r):
                    $id = (int) $r['id'];
                    $pri = $vkRepPriority($r);
                    $disp = $vkRepStatusDisplay((string) $r['status']);
                    $pay = $vkRepPaymentDisplay($r);
                    $init = $vkRepInitials((string) $r['customer_name']);
                    $deviceLabel = repair_device_type_label((string) $r['device_type']);
                    $viewUrl = BASE_URL . '/modules/repairs/view.php?id=' . $id;
                    $editUrl = BASE_URL . '/modules/repairs/edit.php?id=' . $id;
                    $printUrl = BASE_URL . '/modules/repairs/print.php?id=' . $id;
                    $payUrl = BASE_URL . '/modules/payments/job_payment.php?kind=repair&job_id=' . $id;
                    $invUrl = !empty($r['invoice_id'])
                        ? BASE_URL . '/modules/invoices/view.php?id=' . (int) $r['invoice_id']
                        : BASE_URL . '/modules/invoices/create.php?repair_job_id=' . $id;
                    $techName = trim((string) ($r['technician_name'] ?? ''));
                    $techInit = $techName !== '' ? $vkRepInitials($techName) : '—';
                    $hasWarranty = $vkRepHasWarranty($r['warranty_expiry'] ?? null);
                    $expected = $r['warranty_expiry'] ?? null;
                ?>
                <tr data-export-no="<?= e($r['job_number']) ?>"
                    data-export-customer="<?= e($r['customer_name']) ?>"
                    data-export-phone="—"
                    data-export-device="<?= e($deviceLabel) ?>"
                    data-export-brand="—"
                    data-export-model="—"
                    data-export-serial="—"
                    data-export-imei="—"
                    data-export-problem="<?= e($vkRepProblemShort($r['problem_description'] ?? null)) ?>"
                    data-export-technician="<?= e($techName !== '' ? $techName : 'Unassigned') ?>"
                    data-export-priority="<?= e($pri['label']) ?>"
                    data-export-status="<?= e($disp['label']) ?>"
                    data-export-payment="<?= e($pay['label']) ?>"
                    data-export-estimate="<?= e(number_format((float) $r['estimated_cost'], 2)) ?>"
                    data-export-paid="—"
                    data-export-balance="—"
                    data-export-created="<?= e($vkRepFormatDate($r['created_at'] ?? null)) ?>"
                    data-export-expected="<?= e($vkRepFormatDate($expected)) ?>">
                    <td class="vk-repair-sticky-col vk-repair-sticky-check"><input type="checkbox" class="form-check-input vk-repair-row-cb" value="<?= $id ?>" aria-label="Select <?= e($r['job_number']) ?>"></td>
                    <td class="vk-repair-sticky-col vk-repair-sticky-no"><span class="vk-repair-job vk-repair-highlight-target"><?= e($r['job_number']) ?></span></td>
                    <td>
                        <div class="vk-repair-person">
                            <span class="vk-repair-avatar" aria-hidden="true"><?= e($init) ?></span>
                            <div class="vk-repair-cell-text">
                                <div class="vk-repair-cell-name vk-repair-highlight-target">
                                    <?= e($r['customer_name']) ?>
                                    <?php if ($hasWarranty): ?><span class="vk-repair-warranty" title="Under warranty"><i class="bi bi-shield-check"></i></span><?php endif; ?>
                                </div>
                                <div class="vk-repair-cell-sub"><?= e($deviceLabel) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="vk-rep-col-phone d-none d-xxl-table-cell"><span class="vk-repair-muted-cell">—</span></td>
                    <td>
                        <div class="vk-repair-device-cell">
                            <span class="vk-repair-device-icon" aria-hidden="true"><i class="bi <?= e($vkRepDeviceIcon((string) $r['device_type'])) ?>"></i></span>
                            <div class="vk-repair-cell-text">
                                <div class="vk-repair-cell-name"><?= e($deviceLabel) ?></div>
                                <div class="vk-repair-cell-sub vk-repair-highlight-target"><?= e($vkRepProblemShort($r['problem_description'] ?? null)) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-xl-table-cell"><span class="vk-repair-muted-cell">—</span></td>
                    <td class="d-none d-xl-table-cell"><span class="vk-repair-muted-cell">—</span></td>
                    <td class="d-none d-xxl-table-cell"><span class="vk-repair-muted-cell">—</span></td>
                    <td class="d-none d-xxl-table-cell"><span class="vk-repair-muted-cell">—</span></td>
                    <td class="d-none d-lg-table-cell"><span class="vk-repair-ellipsis vk-repair-highlight-target" title="<?= e((string) ($r['problem_description'] ?? '')) ?>"><?= e($vkRepProblemShort($r['problem_description'] ?? null)) ?></span></td>
                    <td>
                        <div class="vk-repair-person">
                            <?php if ($techName !== ''): ?>
                            <span class="vk-repair-avatar" aria-hidden="true"><?= e($techInit) ?></span>
                            <div class="vk-repair-cell-text"><div class="vk-repair-cell-name"><?= e($techName) ?></div></div>
                            <?php else: ?>
                            <span class="vk-repair-avatar vk-repair-avatar-muted" aria-hidden="true">—</span>
                            <div class="vk-repair-cell-text"><div class="vk-repair-cell-name text-muted">Unassigned</div></div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><span class="vk-repair-pri vk-repair-pri-<?= e($pri['key']) ?>"><?= e($pri['label']) ?></span></td>
                    <td><span class="vk-repair-pill vk-repair-st-<?= e($disp['key']) ?>"><?= e($disp['label']) ?></span></td>
                    <td class="d-none d-md-table-cell"><span class="vk-repair-pill vk-repair-pay-<?= e($pay['key']) ?>"><?= e($pay['label']) ?></span></td>
                    <td class="text-end"><span class="vk-repair-amt"><?= e(number_format((float) $r['estimated_cost'], 2)) ?></span></td>
                    <td class="d-none d-xl-table-cell text-end"><span class="vk-repair-muted-cell">—</span></td>
                    <td class="d-none d-xl-table-cell text-end"><span class="vk-repair-muted-cell">—</span></td>
                    <td class="d-none d-lg-table-cell"><span class="vk-repair-date"><?= e($vkRepFormatDate($r['created_at'] ?? null)) ?></span></td>
                    <td class="d-none d-xl-table-cell"><span class="vk-repair-date"><?= e($vkRepFormatDate($expected)) ?></span></td>
                    <td class="text-end">
                        <div class="vk-repair-actions">
                            <a class="vk-repair-act" href="<?= e($viewUrl) ?>" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></a>
                            <a class="vk-repair-act" href="<?= e($editUrl) ?>" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a class="vk-repair-act" href="<?= e($editUrl) ?>" data-bs-toggle="tooltip" title="Update status"><i class="bi bi-arrow-repeat"></i></a>
                            <a class="vk-repair-act" href="<?= e($editUrl) ?>" data-bs-toggle="tooltip" title="Assign technician"><i class="bi bi-person-gear"></i></a>
                            <a class="vk-repair-act" href="<?= e($viewUrl) ?>" data-bs-toggle="tooltip" title="Photos"><i class="bi bi-camera"></i></a>
                            <a class="vk-repair-act" href="<?= e($invUrl) ?>" data-bs-toggle="tooltip" title="Invoice"><i class="bi bi-receipt"></i></a>
                            <a class="vk-repair-act" href="<?= e($payUrl) ?>" data-bs-toggle="tooltip" title="Payment"><i class="bi bi-credit-card"></i></a>
                            <a class="vk-repair-act" href="<?= e($viewUrl) ?>" data-bs-toggle="tooltip" title="Timeline"><i class="bi bi-clock-history"></i></a>
                            <a class="vk-repair-act" href="<?= e($printUrl) ?>" target="_blank" rel="noopener" data-bs-toggle="tooltip" title="Print job card"><i class="bi bi-printer"></i></a>
                            <a class="vk-repair-act" href="<?= e($editUrl) ?>" data-bs-toggle="tooltip" title="Delivery"><i class="bi bi-box-seam"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="vk-repair-mobile-only" aria-label="Repair jobs">
        <?php if (!$rows): ?>
            <div class="vk-repair-empty" role="status">
                <div class="vk-repair-empty-icon"><i class="bi bi-tools"></i></div>
                <div class="vk-repair-empty-title">No repair jobs found</div>
                <a class="vk-repair-btn vk-repair-btn-primary" href="<?= e(BASE_URL) ?>/modules/repairs/add.php">Create Repair Job</a>
            </div>
        <?php else: ?>
            <?php foreach ($rows as $r):
                $id = (int) $r['id'];
                $pri = $vkRepPriority($r);
                $disp = $vkRepStatusDisplay((string) $r['status']);
                $init = $vkRepInitials((string) $r['customer_name']);
                $deviceLabel = repair_device_type_label((string) $r['device_type']);
                $viewUrl = BASE_URL . '/modules/repairs/view.php?id=' . $id;
                $editUrl = BASE_URL . '/modules/repairs/edit.php?id=' . $id;
                $techName = trim((string) ($r['technician_name'] ?? ''));
            ?>
            <article class="vk-repair-mcard">
                <div class="vk-repair-mcard-top">
                    <div class="vk-repair-person">
                        <span class="vk-repair-avatar"><?= e($init) ?></span>
                        <div class="vk-repair-cell-text">
                            <div class="vk-repair-cell-name"><?= e($r['customer_name']) ?></div>
                            <div class="vk-repair-cell-sub"><span class="vk-repair-job"><?= e($r['job_number']) ?></span></div>
                        </div>
                    </div>
                    <span class="vk-repair-pill vk-repair-st-<?= e($disp['key']) ?>"><?= e($disp['label']) ?></span>
                </div>
                <div class="vk-repair-mcard-meta">
                    <span class="vk-repair-pri vk-repair-pri-<?= e($pri['key']) ?>"><?= e($pri['label']) ?></span>
                    <span><?= e($deviceLabel) ?></span>
                    <span class="vk-repair-amt"><?= e(number_format((float) $r['estimated_cost'], 2)) ?></span>
                </div>
                <div class="vk-repair-cell-sub"><?= e($techName !== '' ? $techName : 'Unassigned') ?> · <?= e($vkRepProblemShort($r['problem_description'] ?? null)) ?></div>
                <div class="vk-repair-mcard-actions">
                    <a class="vk-repair-act" href="<?= e($viewUrl) ?>" title="View"><i class="bi bi-eye"></i></a>
                    <a class="vk-repair-act" href="<?= e($editUrl) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                    <a class="vk-repair-act" href="<?= e($viewUrl) ?>" title="Payment"><i class="bi bi-credit-card"></i></a>
                    <a class="vk-repair-act" href="<?= e(BASE_URL) ?>/modules/repairs/print.php?id=<?= $id ?>" target="_blank" rel="noopener" title="Print"><i class="bi bi-printer"></i></a>
                </div>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer class="vk-repair-panel-footer">
        <div class="vk-repair-range" id="vkRepairMeta">Showing —</div>
        <?php if ($pg['pages'] > 1): ?>
        <nav class="vk-repair-pagination" aria-label="Repair pagination">
            <?php if ($pg['page'] > 1): ?>
            <a class="vk-repair-page-link" href="?<?= e($queryBase(['p' => $pg['page'] - 1])) ?>" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
            <?php endif; ?>
            <?php
            $start = max(1, $pg['page'] - 2);
            $end = min($pg['pages'], $pg['page'] + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a class="vk-repair-page-link<?= $i === $pg['page'] ? ' is-active' : '' ?>" href="?<?= e($queryBase(['p' => $i])) ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pg['page'] < $pg['pages']): ?>
            <a class="vk-repair-page-link" href="?<?= e($queryBase(['p' => $pg['page'] + 1])) ?>" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </footer>
</div>
</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/repairs-list.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
