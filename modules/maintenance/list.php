<?php
declare(strict_types=1);

$pageTitle = 'Maintenance contracts';
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
    $where .= ' AND (m.contract_number LIKE ? OR m.title LIKE ? OR c.name LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
if ($status !== '' && in_array($status, ['active', 'paused', 'expired', 'cancelled'], true)) {
    $where .= ' AND m.status = ?';
    $params[] = $status;
}

$countSt = $pdo->prepare("SELECT COUNT(*) FROM maintenance_contracts m JOIN customers c ON c.id = m.customer_id WHERE $where");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();
$pg = paginate($total, $page, $perPage);

$sql = "SELECT m.*, c.name AS customer_name
        FROM maintenance_contracts m
        JOIN customers c ON c.id = m.customer_id
        WHERE $where
        ORDER BY m.id DESC
        LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$today = date('Y-m-d');
$pageFrom = $total > 0 ? $pg['offset'] + 1 : 0;
$pageTo = min($pg['offset'] + count($rows), $total);
$pageRevenue = 0.0;
$kpiScheduledToday = 0;
$kpiInProgress = 0;
$kpiCompleted = 0;
$kpiOverdue = 0;
$kpiPending = 0;

$queryBase = static function (array $extra = []) use ($q, $status, $perPage): string {
    return http_build_query(array_merge([
        'q' => $q,
        'status' => $status,
        'per_page' => $perPage,
    ], $extra));
};

$contractTypes = [
    'computer_amc' => 'Computer AMC',
    'cctv_maintenance' => 'CCTV maintenance',
];

$freqLabels = [
    'monthly' => 'Monthly',
    'quarterly' => 'Quarterly',
    'yearly' => 'Yearly',
    'one_time' => 'One-time',
];

$vkMaintInitials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(substr((string) ($parts[0] ?? 'C'), 0, 1) . substr((string) ($parts[1] ?? ''), 0, 1));
};

$vkMaintPriority = static function (string $freq, bool $isOverdue): array {
    if ($isOverdue) {
        return ['key' => 'critical', 'label' => 'Critical'];
    }
    return match ($freq) {
        'monthly' => ['key' => 'high', 'label' => 'High'],
        'quarterly' => ['key' => 'medium', 'label' => 'Medium'],
        default => ['key' => 'low', 'label' => 'Low'],
    };
};

$vkMaintDisplayStatus = static function (string $st, bool $isOverdue): array {
    if ($st === 'active' && $isOverdue) {
        return ['key' => 'overdue', 'label' => 'Overdue'];
    }
    return match ($st) {
        'active' => ['key' => 'scheduled', 'label' => 'Scheduled'],
        'paused' => ['key' => 'in_progress', 'label' => 'In progress'],
        'expired' => ['key' => 'completed', 'label' => 'Completed'],
        'cancelled' => ['key' => 'cancelled', 'label' => 'Cancelled'],
        default => ['key' => 'active', 'label' => $st],
    };
};

$vkMaintFormatDate = static function (?string $iso): string {
    if ($iso === null || $iso === '') {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d M Y', $ts) : '—';
};

$vkMaintAssetIcon = static function (string $type): string {
    return $type === 'cctv_maintenance' ? 'bi-camera-video' : 'bi-pc-display';
};

foreach ($rows as $rowSum) {
    $pageRevenue += (float) ($rowSum['annual_fee'] ?? 0);
    $next = $rowSum['next_service_date'] ?? null;
    $stRow = (string) ($rowSum['status'] ?? '');
    $isOverdue = $stRow === 'active' && $next && $next <= $today;
    if ($stRow === 'active' && $next === $today) {
        $kpiScheduledToday++;
    }
    if ($isOverdue) {
        $kpiOverdue++;
    }
    if ($stRow === 'paused') {
        $kpiInProgress++;
        $kpiPending++;
    }
    if ($stRow === 'expired') {
        $kpiCompleted++;
    }
}

$completionRate = count($rows) > 0 ? (int) round(($kpiCompleted / count($rows)) * 100) : 0;
$monthlyRevenue = $pageRevenue / 12;

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/maintenance-list.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/maintenance-list.js');
$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link href="' . e(base_url('assets/css/maintenance-list.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="vkMaintApp" class="vk-maint-admin"
     data-filtered-total="<?= (int) $total ?>"
     data-page-from="<?= (int) $pageFrom ?>"
     data-page-to="<?= (int) $pageTo ?>"
     data-page-total="<?= (int) count($rows) ?>"
     data-page-revenue="<?= e((string) $monthlyRevenue) ?>"
     data-completion-rate="<?= (int) $completionRate ?>"
     data-search-query="<?= e($q) ?>">

<header class="vk-maint-header">
    <div class="vk-maint-header-inner">
        <div>
            <h1 class="vk-maint-title">Maintenance Contracts</h1>
            <p class="vk-maint-subtitle d-none d-md-block">AMC &amp; service schedules · enterprise operations</p>
        </div>
        <a class="btn vk-maint-btn vk-maint-btn-primary vk-maint-header-cta" href="<?= e(BASE_URL) ?>/modules/maintenance/add.php">
            <i class="bi bi-calendar-check" aria-hidden="true"></i><span>Create Maintenance Job</span>
        </a>
    </div>
</header>

<div class="vk-maint-kpi-grid" role="region" aria-label="Maintenance statistics">
    <div class="vk-maint-kpi vk-maint-kpi-blue">
        <div class="vk-maint-kpi-icon" aria-hidden="true"><i class="bi bi-collection"></i></div>
        <div class="vk-maint-kpi-body">
            <span class="vk-maint-kpi-label">Total Jobs</span>
            <span class="vk-maint-kpi-value"><?= e((string) $total) ?></span>
            <span class="vk-maint-kpi-trend">Matching filters</span>
        </div>
    </div>
    <div class="vk-maint-kpi vk-maint-kpi-teal">
        <div class="vk-maint-kpi-icon" aria-hidden="true"><i class="bi bi-calendar-day"></i></div>
        <div class="vk-maint-kpi-body">
            <span class="vk-maint-kpi-label">Scheduled Today</span>
            <span class="vk-maint-kpi-value"><?= e((string) $kpiScheduledToday) ?></span>
            <span class="vk-maint-kpi-trend">On this page</span>
        </div>
    </div>
    <div class="vk-maint-kpi vk-maint-kpi-orange">
        <div class="vk-maint-kpi-icon" aria-hidden="true"><i class="bi bi-gear-wide-connected"></i></div>
        <div class="vk-maint-kpi-body">
            <span class="vk-maint-kpi-label">In Progress</span>
            <span class="vk-maint-kpi-value"><?= e((string) $kpiInProgress) ?></span>
            <span class="vk-maint-kpi-trend">Paused contracts</span>
        </div>
    </div>
    <div class="vk-maint-kpi vk-maint-kpi-green">
        <div class="vk-maint-kpi-icon" aria-hidden="true"><i class="bi bi-check2-circle"></i></div>
        <div class="vk-maint-kpi-body">
            <span class="vk-maint-kpi-label">Completed</span>
            <span class="vk-maint-kpi-value"><?= e((string) $kpiCompleted) ?></span>
            <span class="vk-maint-kpi-trend">Expired on page</span>
        </div>
    </div>
    <div class="vk-maint-kpi vk-maint-kpi-red">
        <div class="vk-maint-kpi-icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="vk-maint-kpi-body">
            <span class="vk-maint-kpi-label">Overdue</span>
            <span class="vk-maint-kpi-value"><?= e((string) $kpiOverdue) ?></span>
            <span class="vk-maint-kpi-trend">Due on page</span>
        </div>
    </div>
    <div class="vk-maint-kpi vk-maint-kpi-purple">
        <div class="vk-maint-kpi-icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></div>
        <div class="vk-maint-kpi-body">
            <span class="vk-maint-kpi-label">Pending Approval</span>
            <span class="vk-maint-kpi-value"><?= e((string) $kpiPending) ?></span>
            <span class="vk-maint-kpi-trend">Paused status</span>
        </div>
    </div>
    <div class="vk-maint-kpi vk-maint-kpi-gray">
        <div class="vk-maint-kpi-icon" aria-hidden="true"><i class="bi bi-currency-rupee"></i></div>
        <div class="vk-maint-kpi-body">
            <span class="vk-maint-kpi-label">Monthly Revenue</span>
            <span class="vk-maint-kpi-value" id="vkMaintStatRevenue">—</span>
            <span class="vk-maint-kpi-trend">Page annual ÷ 12</span>
        </div>
    </div>
    <div class="vk-maint-kpi vk-maint-kpi-blue">
        <div class="vk-maint-kpi-icon" aria-hidden="true"><i class="bi bi-graph-up"></i></div>
        <div class="vk-maint-kpi-body">
            <span class="vk-maint-kpi-label">Completion Rate</span>
            <span class="vk-maint-kpi-value" id="vkMaintStatRate"><?= e((string) $completionRate) ?>%</span>
            <span class="vk-maint-kpi-trend">Page snapshot</span>
        </div>
    </div>
</div>

<form class="vk-maint-toolbar" id="vkMaintFilterForm" method="get" action="" role="search">
    <div class="vk-maint-toolbar-mobile-head d-lg-none">
        <span class="fw-semibold small"><i class="bi bi-sliders"></i> Filters</span>
        <button type="button" class="vk-maint-btn vk-maint-btn-icon" data-bs-toggle="collapse" data-bs-target="#vkMaintToolbarCollapse" aria-expanded="false" aria-controls="vkMaintToolbarCollapse" aria-label="Toggle filters">
            <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    <div class="collapse vk-maint-toolbar-collapse" id="vkMaintToolbarCollapse">
        <div class="vk-maint-toolbar-inner">
            <div class="vk-maint-search-wrap">
                <i class="bi bi-search vk-maint-search-ico" aria-hidden="true"></i>
                <input type="search" name="q" id="vkMaintSearch" class="form-control vk-maint-ctl" placeholder="Search contracts…" value="<?= e($q) ?>" autocomplete="off" aria-label="Search maintenance">
            </div>
            <input type="search" class="form-control vk-maint-ctl vk-maint-ctl-sm" placeholder="Customer" disabled title="Use search for customer name" aria-label="Customer filter">
            <input type="search" class="form-control vk-maint-ctl vk-maint-ctl-sm" placeholder="Asset" disabled title="Use search for title" aria-label="Asset filter">
            <select class="form-select vk-maint-ctl vk-maint-ctl-sm" disabled title="Technician on visit log only" aria-label="Technician">
                <option>Technician</option>
            </select>
            <select class="form-select vk-maint-ctl vk-maint-ctl-sm" disabled title="Filter by contract type coming soon" aria-label="Service type">
                <option>Service Type</option>
            </select>
            <select class="form-select vk-maint-ctl vk-maint-ctl-xs" disabled title="Priority derived from frequency" aria-label="Priority">
                <option>Priority</option>
            </select>
            <select name="status" class="form-select vk-maint-ctl vk-maint-ctl-sm" aria-label="Status">
                <option value="">Status</option>
                <?php foreach (['active' => 'Active', 'paused' => 'Paused', 'expired' => 'Expired', 'cancelled' => 'Cancelled'] as $k => $lab): ?>
                <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" class="form-control vk-maint-ctl vk-maint-ctl-date" disabled title="Date filters coming soon" aria-label="Date from">
            <input type="date" class="form-control vk-maint-ctl vk-maint-ctl-date" disabled title="Date filters coming soon" aria-label="Date to">
            <select class="form-select vk-maint-ctl vk-maint-ctl-xs" disabled title="Branch not configured" aria-label="Branch">
                <option>Branch</option>
            </select>
            <select name="per_page" class="form-select vk-maint-ctl vk-maint-ctl-xs" aria-label="Rows per page">
                <?php foreach ($perPageOptions as $opt): ?>
                <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
            <div class="vk-maint-toolbar-btns">
                <button type="submit" class="vk-maint-btn vk-maint-btn-primary" aria-label="Apply filters"><i class="bi bi-funnel"></i><span class="d-none d-xl-inline ms-1">Apply</span></button>
                <button type="button" class="vk-maint-btn vk-maint-btn-ghost" id="vkMaintReset">Reset</button>
                <button type="button" class="vk-maint-btn vk-maint-btn-ghost" id="vkMaintExportCsv" title="Export CSV" aria-label="Export CSV"><i class="bi bi-download"></i></button>
                <button type="button" class="vk-maint-btn vk-maint-btn-ghost" id="vkMaintExportExcel" title="Export Excel" aria-label="Export Excel"><i class="bi bi-file-earmark-spreadsheet"></i></button>
                <button type="button" class="vk-maint-btn vk-maint-btn-ghost" id="vkMaintExportPdf" title="Export PDF" aria-label="Export PDF"><i class="bi bi-file-earmark-pdf"></i></button>
                <button type="button" class="vk-maint-btn vk-maint-btn-ghost" id="vkMaintPrint" title="Print" aria-label="Print"><i class="bi bi-printer"></i></button>
                <button type="button" class="vk-maint-btn vk-maint-btn-ghost" id="vkMaintRefresh" title="Refresh" aria-label="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>
    </div>
</form>

<div class="vk-maint-panel" id="vkMaintPanel">
    <div class="vk-maint-panel-scroll vk-maint-desktop-only">
        <table class="table table-sm table-borderless vk-maint-table sortable mb-0" id="vkMaintTable">
            <thead>
                <tr>
                    <th class="vk-maint-w-check vk-maint-sticky-col vk-maint-sticky-check" scope="col"><input type="checkbox" class="form-check-input" id="vkMaintSelectAll" aria-label="Select all"></th>
                    <th class="vk-maint-w-no vk-maint-sticky-col vk-maint-sticky-no" data-sort="0" scope="col">Maint. No</th>
                    <th class="vk-maint-w-customer" data-sort="1" scope="col">Customer</th>
                    <th class="vk-maint-w-asset vk-maint-col-asset" data-sort="2" scope="col">Asset</th>
                    <th class="vk-maint-w-tech vk-maint-col-tech d-none d-xl-table-cell" scope="col">Technician</th>
                    <th class="vk-maint-w-type vk-maint-col-type d-none d-lg-table-cell" scope="col">Service Type</th>
                    <th class="vk-maint-w-pri d-none d-md-table-cell" scope="col">Priority</th>
                    <th class="vk-maint-w-status" data-sort="3" scope="col">Status</th>
                    <th class="vk-maint-w-sched vk-maint-col-sched d-none d-xl-table-cell" scope="col">Scheduled</th>
                    <th class="vk-maint-w-due" data-sort="4" scope="col">Next Due</th>
                    <th class="vk-maint-w-cost text-end" data-sort="5" data-type="number" scope="col">Cost</th>
                    <th class="vk-maint-w-created vk-maint-col-created d-none d-lg-table-cell" scope="col">Created</th>
                    <th class="vk-maint-w-act text-end" scope="col">Actions</th>
                </tr>
            </thead>
            <tbody class="vk-maint-data-body">
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="13">
                        <div class="vk-maint-empty" role="status">
                            <div class="vk-maint-empty-icon"><i class="bi bi-calendar-x"></i></div>
                            <div class="vk-maint-empty-title">No maintenance records found</div>
                            <p class="vk-maint-empty-text"><?= ($q !== '' || $status !== '') ? 'Try adjusting your search or filters.' : 'Create your first maintenance contract to get started.' ?></p>
                            <?php if ($q === '' && $status === ''): ?>
                            <a class="vk-maint-btn vk-maint-btn-primary" href="<?= e(BASE_URL) ?>/modules/maintenance/add.php"><i class="bi bi-plus-lg"></i> Create Maintenance Job</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r):
                    $id = (int) $r['id'];
                    $next = $r['next_service_date'] ?? null;
                    $stKey = (string) $r['status'];
                    $isOverdue = $stKey === 'active' && $next && $next <= $today;
                    $pri = $vkMaintPriority((string) $r['visit_frequency'], $isOverdue);
                    $disp = $vkMaintDisplayStatus($stKey, $isOverdue);
                    $init = $vkMaintInitials((string) $r['customer_name']);
                    $typeLabel = $contractTypes[$r['contract_type']] ?? str_replace('_', ' ', (string) $r['contract_type']);
                    $editUrl = BASE_URL . '/modules/maintenance/edit.php?id=' . $id;
                    $deleteUrl = BASE_URL . '/modules/maintenance/delete.php?id=' . $id;
                    $rowClass = $isOverdue ? 'vk-maint-row-overdue' : '';
                    $scheduled = $next ?: ($r['start_date'] ?? null);
                ?>
                <tr class="<?= e($rowClass) ?>"
                    data-export-no="<?= e($r['contract_number']) ?>"
                    data-export-customer="<?= e($r['customer_name']) ?>"
                    data-export-asset="<?= e($r['title']) ?>"
                    data-export-technician="Unassigned"
                    data-export-type="<?= e($typeLabel) ?>"
                    data-export-priority="<?= e($pri['label']) ?>"
                    data-export-status="<?= e($disp['label']) ?>"
                    data-export-scheduled="<?= e($vkMaintFormatDate($scheduled)) ?>"
                    data-export-due="<?= e($vkMaintFormatDate($next)) ?>"
                    data-export-cost="<?= e(number_format((float) $r['annual_fee'], 2)) ?>"
                    data-export-created="<?= e($vkMaintFormatDate($r['created_at'] ?? null)) ?>">
                    <td class="vk-maint-sticky-col vk-maint-sticky-check"><input type="checkbox" class="form-check-input vk-maint-row-cb" value="<?= $id ?>" aria-label="Select <?= e($r['contract_number']) ?>"></td>
                    <td class="vk-maint-sticky-col vk-maint-sticky-no"><span class="vk-maint-contract vk-maint-highlight-target"><?= e($r['contract_number']) ?></span></td>
                    <td>
                        <div class="vk-maint-person">
                            <span class="vk-maint-avatar" aria-hidden="true"><?= e($init) ?></span>
                            <div class="vk-maint-cell-text">
                                <div class="vk-maint-cell-name vk-maint-highlight-target"><?= e($r['customer_name']) ?></div>
                                <div class="vk-maint-cell-sub"><?= e($freqLabels[$r['visit_frequency']] ?? $r['visit_frequency']) ?> visits</div>
                            </div>
                        </div>
                    </td>
                    <td class="vk-maint-col-asset">
                        <div class="vk-maint-asset-cell">
                            <span class="vk-maint-asset-icon" aria-hidden="true"><i class="bi <?= e($vkMaintAssetIcon((string) $r['contract_type'])) ?>"></i></span>
                            <div class="vk-maint-cell-text">
                                <div class="vk-maint-cell-name vk-maint-highlight-target"><?= e($r['title']) ?></div>
                                <div class="vk-maint-cell-sub"><?= e($r['contract_number']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="vk-maint-col-tech d-none d-xl-table-cell">
                        <div class="vk-maint-person">
                            <span class="vk-maint-avatar vk-maint-avatar-muted" aria-hidden="true">—</span>
                            <div class="vk-maint-cell-text"><div class="vk-maint-cell-name text-muted">Unassigned</div></div>
                        </div>
                    </td>
                    <td class="vk-maint-col-type d-none d-lg-table-cell"><span class="vk-maint-type vk-maint-highlight-target"><?= e($typeLabel) ?></span></td>
                    <td class="d-none d-md-table-cell"><span class="vk-maint-pri vk-maint-pri-<?= e($pri['key']) ?>"><?= e($pri['label']) ?></span></td>
                    <td><span class="vk-maint-pill vk-maint-pill-<?= e($disp['key']) ?>"><?= e($disp['label']) ?></span></td>
                    <td class="vk-maint-col-sched d-none d-xl-table-cell"><span class="vk-maint-date"><?= e($vkMaintFormatDate($scheduled)) ?></span></td>
                    <td><span class="vk-maint-date"><?= e($vkMaintFormatDate($next)) ?></span></td>
                    <td class="text-end"><span class="vk-maint-cost"><?= e(number_format((float) $r['annual_fee'], 2)) ?></span></td>
                    <td class="vk-maint-col-created d-none d-lg-table-cell"><span class="vk-maint-date"><?= e($vkMaintFormatDate($r['created_at'] ?? null)) ?></span></td>
                    <td class="text-end">
                        <div class="vk-maint-actions">
                            <a class="vk-maint-act" href="<?= e($editUrl) ?>" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></a>
                            <a class="vk-maint-act" href="<?= e($editUrl) ?>" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a class="vk-maint-act" href="<?= e($editUrl) ?>" data-bs-toggle="tooltip" title="Assign technician"><i class="bi bi-person-gear"></i></a>
                            <a class="vk-maint-act" href="<?= e($editUrl) ?>" data-bs-toggle="tooltip" title="Reschedule"><i class="bi bi-calendar-event"></i></a>
                            <a class="vk-maint-act" href="<?= e($editUrl) ?>" data-bs-toggle="tooltip" title="Fee &amp; billing"><i class="bi bi-receipt"></i></a>
                            <a class="vk-maint-act" href="<?= e($editUrl) ?>" data-bs-toggle="tooltip" title="Visit history"><i class="bi bi-clock-history"></i></a>
                            <a class="vk-maint-act" href="<?= e($editUrl) ?>" data-bs-toggle="tooltip" title="Notes &amp; attachments"><i class="bi bi-paperclip"></i></a>
                            <a class="vk-maint-act vk-maint-act-danger" href="<?= e($deleteUrl) ?>" data-bs-toggle="tooltip" title="Delete" onclick="return confirm('Delete this contract and all visit logs?');"><i class="bi bi-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="vk-maint-mobile-only" aria-label="Maintenance contracts">
        <?php if (!$rows): ?>
            <div class="vk-maint-empty" role="status">
                <div class="vk-maint-empty-icon"><i class="bi bi-calendar-x"></i></div>
                <div class="vk-maint-empty-title">No maintenance records found</div>
                <a class="vk-maint-btn vk-maint-btn-primary" href="<?= e(BASE_URL) ?>/modules/maintenance/add.php">Create Maintenance Job</a>
            </div>
        <?php else: ?>
            <?php foreach ($rows as $r):
                $id = (int) $r['id'];
                $next = $r['next_service_date'] ?? null;
                $stKey = (string) $r['status'];
                $isOverdue = $stKey === 'active' && $next && $next <= $today;
                $pri = $vkMaintPriority((string) $r['visit_frequency'], $isOverdue);
                $disp = $vkMaintDisplayStatus($stKey, $isOverdue);
                $init = $vkMaintInitials((string) $r['customer_name']);
                $typeLabel = $contractTypes[$r['contract_type']] ?? (string) $r['contract_type'];
                $editUrl = BASE_URL . '/modules/maintenance/edit.php?id=' . $id;
            ?>
            <article class="vk-maint-mcard">
                <div class="vk-maint-mcard-top">
                    <div class="vk-maint-person">
                        <span class="vk-maint-avatar"><?= e($init) ?></span>
                        <div class="vk-maint-cell-text">
                            <div class="vk-maint-cell-name"><?= e($r['customer_name']) ?></div>
                            <div class="vk-maint-cell-sub"><span class="vk-maint-contract"><?= e($r['contract_number']) ?></span></div>
                        </div>
                    </div>
                    <span class="vk-maint-pill vk-maint-pill-<?= e($disp['key']) ?>"><?= e($disp['label']) ?></span>
                </div>
                <div class="vk-maint-cell-name"><?= e($r['title']) ?></div>
                <div class="vk-maint-mcard-meta">
                    <span class="vk-maint-pri vk-maint-pri-<?= e($pri['key']) ?>"><?= e($pri['label']) ?></span>
                    <span class="vk-maint-type"><?= e($typeLabel) ?></span>
                    <span class="vk-maint-cost"><?= e(number_format((float) $r['annual_fee'], 2)) ?></span>
                    <span class="vk-maint-date">Due <?= e($vkMaintFormatDate($next)) ?></span>
                </div>
                <div class="vk-maint-mcard-actions">
                    <a class="vk-maint-act" href="<?= e($editUrl) ?>" title="View"><i class="bi bi-eye"></i></a>
                    <a class="vk-maint-act" href="<?= e($editUrl) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                    <a class="vk-maint-act" href="<?= e($editUrl) ?>" title="History"><i class="bi bi-clock-history"></i></a>
                </div>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer class="vk-maint-panel-footer">
        <div class="vk-maint-range" id="vkMaintMeta">Showing —</div>
        <?php if ($pg['pages'] > 1): ?>
        <nav class="vk-maint-pagination" aria-label="Maintenance pagination">
            <?php if ($pg['page'] > 1): ?>
            <a class="vk-maint-page-link" href="?<?= e($queryBase(['p' => $pg['page'] - 1])) ?>" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
            <?php endif; ?>
            <?php
            $start = max(1, $pg['page'] - 2);
            $end = min($pg['pages'], $pg['page'] + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a class="vk-maint-page-link<?= $i === $pg['page'] ? ' is-active' : '' ?>" href="?<?= e($queryBase(['p' => $i])) ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pg['page'] < $pg['pages']): ?>
            <a class="vk-maint-page-link" href="?<?= e($queryBase(['p' => $pg['page'] + 1])) ?>" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </footer>
</div>
</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/maintenance-list.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
