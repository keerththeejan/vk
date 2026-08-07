<?php
declare(strict_types=1);

$pageTitle = 'CCTV installations';
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
    $where .= ' AND (v.job_number LIKE ? OR c.name LIKE ? OR v.location LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
if ($status !== '' && in_array($status, ['pending', 'in_progress', 'completed', 'delivered'], true)) {
    $where .= ' AND v.status = ?';
    $params[] = $status;
}

$countSt = $pdo->prepare("SELECT COUNT(*) FROM cctv_installations v JOIN customers c ON c.id = v.customer_id WHERE $where");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();
$pg = paginate($total, $page, $perPage);

$sql = "SELECT v.*, c.name AS customer_name
        FROM cctv_installations v
        JOIN customers c ON c.id = v.customer_id
        WHERE $where
        ORDER BY v.id DESC
        LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$pageRevenue = 0.0;
foreach ($rows as $rowSum) {
    $pageRevenue += (float) ($rowSum['installation_charge'] ?? 0);
}
$pageFrom = $total > 0 ? $pg['offset'] + 1 : 0;
$pageTo = min($pg['offset'] + count($rows), $total);

$queryBase = static function (array $extra = []) use ($q, $status, $perPage): string {
    return http_build_query(array_merge([
        'q' => $q,
        'status' => $status,
        'per_page' => $perPage,
    ], $extra));
};

$vkCctvInitials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $a = strtoupper(substr((string) ($parts[0] ?? 'C'), 0, 1));
    $b = strtoupper(substr((string) ($parts[1] ?? ''), 0, 1));
    return $a . $b;
};

$vkCctvPriority = static function (string $st): array {
    return match ($st) {
        'in_progress' => ['key' => 'high', 'label' => 'High'],
        'pending' => ['key' => 'medium', 'label' => 'Medium'],
        'completed' => ['key' => 'low', 'label' => 'Low'],
        default => ['key' => 'normal', 'label' => 'Normal'],
    };
};

$vkCctvFormatDate = static function (?string $iso): string {
    if ($iso === null || $iso === '') {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d M Y', $ts) : '—';
};

$vkCctvLocShort = static function (string $loc): string {
    return strlen($loc) > 48 ? substr($loc, 0, 45) . '…' : $loc;
};

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/cctv-list.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/cctv-list.js');
$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link href="' . e(base_url('assets/css/cctv-list.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="vkCctvApp" class="vk-cctv-admin"
     data-filtered-total="<?= (int) $total ?>"
     data-page-from="<?= (int) $pageFrom ?>"
     data-page-to="<?= (int) $pageTo ?>"
     data-page-total="<?= (int) count($rows) ?>"
     data-page-revenue="<?= e((string) $pageRevenue) ?>">

<header class="vk-cctv-header">
    <div class="vk-cctv-header-inner">
        <div>
            <h1 class="vk-cctv-title">CCTV Installations</h1>
            <p class="vk-cctv-subtitle d-none d-md-block">Enterprise job queue · field installs &amp; billing</p>
        </div>
        <a class="btn vk-cctv-btn vk-cctv-btn-primary vk-cctv-header-cta" href="<?= e(BASE_URL) ?>/modules/cctv/add.php">
            <i class="bi bi-camera-video" aria-hidden="true"></i><span>Create CCTV Job</span>
        </a>
    </div>
</header>

<div class="vk-cctv-kpi-grid" role="region" aria-label="CCTV statistics">
    <div class="vk-cctv-kpi vk-cctv-kpi-blue">
        <div class="vk-cctv-kpi-icon" aria-hidden="true"><i class="bi bi-collection"></i></div>
        <div class="vk-cctv-kpi-body">
            <span class="vk-cctv-kpi-label">Total Jobs</span>
            <span class="vk-cctv-kpi-value" id="vkCctvStatTotal"><?= e((string) $total) ?></span>
            <span class="vk-cctv-kpi-trend" id="vkCctvStatTotalTrend">Matching filters</span>
        </div>
    </div>
    <div class="vk-cctv-kpi vk-cctv-kpi-teal">
        <div class="vk-cctv-kpi-icon" aria-hidden="true"><i class="bi bi-play-circle"></i></div>
        <div class="vk-cctv-kpi-body">
            <span class="vk-cctv-kpi-label">Active</span>
            <span class="vk-cctv-kpi-value" id="vkCctvStatActive">—</span>
            <span class="vk-cctv-kpi-trend" id="vkCctvStatActiveTrend">Loading…</span>
        </div>
    </div>
    <div class="vk-cctv-kpi vk-cctv-kpi-green">
        <div class="vk-cctv-kpi-icon" aria-hidden="true"><i class="bi bi-check-circle"></i></div>
        <div class="vk-cctv-kpi-body">
            <span class="vk-cctv-kpi-label">Completed</span>
            <span class="vk-cctv-kpi-value" id="vkCctvStatCompleted">—</span>
            <span class="vk-cctv-kpi-trend" id="vkCctvStatCompletedTrend">Loading…</span>
        </div>
    </div>
    <div class="vk-cctv-kpi vk-cctv-kpi-orange">
        <div class="vk-cctv-kpi-icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></div>
        <div class="vk-cctv-kpi-body">
            <span class="vk-cctv-kpi-label">Pending</span>
            <span class="vk-cctv-kpi-value" id="vkCctvStatPending">—</span>
            <span class="vk-cctv-kpi-trend" id="vkCctvStatPendingTrend">Loading…</span>
        </div>
    </div>
    <div class="vk-cctv-kpi vk-cctv-kpi-red">
        <div class="vk-cctv-kpi-icon" aria-hidden="true"><i class="bi bi-x-circle"></i></div>
        <div class="vk-cctv-kpi-body">
            <span class="vk-cctv-kpi-label">Cancelled</span>
            <span class="vk-cctv-kpi-value" id="vkCctvStatCancelled">0</span>
            <span class="vk-cctv-kpi-trend" id="vkCctvStatCancelledTrend">Not tracked</span>
        </div>
    </div>
    <div class="vk-cctv-kpi vk-cctv-kpi-gray">
        <div class="vk-cctv-kpi-icon" aria-hidden="true"><i class="bi bi-currency-rupee"></i></div>
        <div class="vk-cctv-kpi-body">
            <span class="vk-cctv-kpi-label">Page Revenue</span>
            <span class="vk-cctv-kpi-value" id="vkCctvStatRevenue">—</span>
            <span class="vk-cctv-kpi-trend" id="vkCctvStatRevenueTrend">Current page</span>
        </div>
    </div>
</div>

<form class="vk-cctv-toolbar" id="vkCctvFilterForm" method="get" action="" role="search">
    <div class="vk-cctv-toolbar-mobile-head d-lg-none">
        <span class="fw-semibold small"><i class="bi bi-sliders"></i> Filters</span>
        <button type="button" class="vk-cctv-btn vk-cctv-btn-icon" data-bs-toggle="collapse" data-bs-target="#vkCctvToolbarCollapse" aria-expanded="false" aria-controls="vkCctvToolbarCollapse" aria-label="Toggle filters">
            <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    <div class="collapse vk-cctv-toolbar-collapse" id="vkCctvToolbarCollapse">
        <div class="vk-cctv-toolbar-inner">
            <div class="vk-cctv-search-wrap">
                <i class="bi bi-search vk-cctv-search-ico" aria-hidden="true"></i>
                <input type="search" name="q" id="vkCctvSearch" class="form-control vk-cctv-ctl" placeholder="Search jobs…" value="<?= e($q) ?>" autocomplete="off" aria-label="Search CCTV jobs">
            </div>
            <select name="status" class="form-select vk-cctv-ctl vk-cctv-ctl-sm" aria-label="Status">
                <option value="">Status</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In progress</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
            </select>
            <select class="form-select vk-cctv-ctl vk-cctv-ctl-sm" aria-label="Technician" disabled title="Technician assignment is not available on CCTV jobs">
                <option>Technician</option>
            </select>
            <input type="search" class="form-control vk-cctv-ctl vk-cctv-ctl-sm" placeholder="Customer" aria-label="Customer filter hint" disabled title="Use search for customer name">
            <input type="date" class="form-control vk-cctv-ctl vk-cctv-ctl-date" aria-label="Date from" disabled title="Date filters coming soon">
            <input type="date" class="form-control vk-cctv-ctl vk-cctv-ctl-date" aria-label="Date to" disabled title="Date filters coming soon">
            <select class="form-select vk-cctv-ctl vk-cctv-ctl-xs" aria-label="Priority" disabled title="Priority is derived from status">
                <option>Priority</option>
            </select>
            <select name="per_page" class="form-select vk-cctv-ctl vk-cctv-ctl-xs" aria-label="Rows per page">
                <?php foreach ($perPageOptions as $opt): ?>
                <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
            <div class="vk-cctv-toolbar-btns">
                <button type="submit" class="vk-cctv-btn vk-cctv-btn-primary" aria-label="Apply filters"><i class="bi bi-funnel"></i><span class="d-none d-xl-inline ms-1">Apply</span></button>
                <button type="button" class="vk-cctv-btn vk-cctv-btn-ghost" id="vkCctvReset">Reset</button>
                <button type="button" class="vk-cctv-btn vk-cctv-btn-ghost" id="vkCctvExportCsv" title="Export CSV" aria-label="Export CSV"><i class="bi bi-download"></i></button>
                <button type="button" class="vk-cctv-btn vk-cctv-btn-ghost" id="vkCctvExportPdf" title="Export PDF" aria-label="Export PDF"><i class="bi bi-file-earmark-pdf"></i></button>
            </div>
        </div>
    </div>
</form>

<div class="vk-cctv-panel" id="vkCctvPanel">
    <div class="vk-cctv-panel-scroll vk-cctv-desktop-only">
        <table class="table table-sm table-borderless vk-cctv-table sortable mb-0" id="vkCctvTable">
            <thead>
                <tr>
                    <th class="vk-cctv-w-check" scope="col"><input type="checkbox" class="form-check-input" id="vkCctvSelectAll" aria-label="Select all rows"></th>
                    <th class="vk-cctv-w-job" data-sort="0" scope="col">Job No</th>
                    <th class="vk-cctv-w-customer" data-sort="1" scope="col">Customer</th>
                    <th class="vk-cctv-w-loc vk-cctv-col-loc d-none d-xl-table-cell" data-sort="2" scope="col">Location</th>
                    <th class="vk-cctv-w-tech vk-cctv-col-tech d-none d-lg-table-cell" scope="col">Technician</th>
                    <th class="vk-cctv-w-pri vk-cctv-col-pri d-none d-md-table-cell" scope="col">Priority</th>
                    <th class="vk-cctv-w-status" data-sort="3" scope="col">Status</th>
                    <th class="vk-cctv-w-amt text-end" data-sort="4" data-type="number" scope="col">Amount</th>
                    <th class="vk-cctv-w-date d-none d-lg-table-cell" data-sort="5" scope="col">Created</th>
                    <th class="vk-cctv-w-act text-end" scope="col">Actions</th>
                </tr>
            </thead>
            <tbody class="vk-cctv-data-body">
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="10">
                        <div class="vk-cctv-empty" role="status">
                            <div class="vk-cctv-empty-icon"><i class="bi bi-camera-video-off"></i></div>
                            <div class="vk-cctv-empty-title">No CCTV jobs found</div>
                            <p class="vk-cctv-empty-text"><?= ($q !== '' || $status !== '') ? 'Try adjusting your search or filters.' : 'Create your first installation job to get started.' ?></p>
                            <?php if ($q === '' && $status === ''): ?>
                            <a class="vk-cctv-btn vk-cctv-btn-primary" href="<?= e(BASE_URL) ?>/modules/cctv/add.php"><i class="bi bi-plus-lg"></i> Create CCTV Job</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r):
                    $id = (int) $r['id'];
                    $pri = $vkCctvPriority((string) $r['status']);
                    $init = $vkCctvInitials((string) $r['customer_name']);
                    $stKey = (string) $r['status'];
                    $viewUrl = BASE_URL . '/modules/cctv/view.php?id=' . $id;
                    $editUrl = BASE_URL . '/modules/cctv/edit.php?id=' . $id;
                    $invUrl = !empty($r['invoice_id'])
                        ? BASE_URL . '/modules/invoices/view.php?id=' . (int) $r['invoice_id']
                        : $viewUrl;
                    $invTitle = !empty($r['invoice_id']) ? 'View invoice' : 'Generate invoice';
                ?>
                <tr data-export-job="<?= e($r['job_number']) ?>"
                    data-export-customer="<?= e($r['customer_name']) ?>"
                    data-export-location="<?= e($vkCctvLocShort((string) $r['location'])) ?>"
                    data-export-status="<?= e(str_replace('_', ' ', $stKey)) ?>"
                    data-export-priority="<?= e($pri['label']) ?>"
                    data-export-amount="<?= e(number_format((float) $r['installation_charge'], 2)) ?>"
                    data-export-created="<?= e($vkCctvFormatDate($r['created_at'] ?? null)) ?>">
                    <td><input type="checkbox" class="form-check-input vk-cctv-row-cb" value="<?= $id ?>" aria-label="Select job <?= e($r['job_number']) ?>"></td>
                    <td><span class="vk-cctv-job"><?= e($r['job_number']) ?></span></td>
                    <td>
                        <div class="vk-cctv-person">
                            <span class="vk-cctv-avatar" aria-hidden="true"><?= e($init) ?></span>
                            <div class="vk-cctv-person-text">
                                <div class="vk-cctv-person-name"><?= e($r['customer_name']) ?></div>
                                <div class="vk-cctv-person-sub"><?= (int) $r['num_cameras'] ?> camera<?= (int) $r['num_cameras'] === 1 ? '' : 's' ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="vk-cctv-col-loc d-none d-xl-table-cell" title="<?= e((string) $r['location']) ?>">
                        <span class="vk-cctv-loc"><?= e($vkCctvLocShort((string) $r['location'])) ?></span>
                    </td>
                    <td class="vk-cctv-col-tech d-none d-lg-table-cell">
                        <div class="vk-cctv-person">
                            <span class="vk-cctv-avatar vk-cctv-avatar-muted" aria-hidden="true">—</span>
                            <div class="vk-cctv-person-text">
                                <div class="vk-cctv-person-name text-muted">Unassigned</div>
                            </div>
                        </div>
                    </td>
                    <td class="vk-cctv-col-pri d-none d-md-table-cell">
                        <span class="vk-cctv-pri vk-cctv-pri-<?= e($pri['key']) ?>"><?= e($pri['label']) ?></span>
                    </td>
                    <td><span class="vk-cctv-pill vk-cctv-pill-<?= e($stKey) ?>"><?= e(str_replace('_', ' ', $stKey)) ?></span></td>
                    <td class="text-end"><span class="vk-cctv-amt"><?= e(number_format((float) $r['installation_charge'], 2)) ?></span></td>
                    <td class="d-none d-lg-table-cell"><span class="vk-cctv-date"><?= e($vkCctvFormatDate($r['created_at'] ?? null)) ?></span></td>
                    <td class="text-end">
                        <div class="vk-cctv-actions">
                            <a class="vk-cctv-act" href="<?= e($viewUrl) ?>" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></a>
                            <a class="vk-cctv-act" href="<?= e($editUrl) ?>" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a class="vk-cctv-act" href="<?= e($editUrl) ?>" data-bs-toggle="tooltip" title="Assign"><i class="bi bi-person-plus"></i></a>
                            <a class="vk-cctv-act" href="<?= e($invUrl) ?>" data-bs-toggle="tooltip" title="<?= e($invTitle) ?>"><i class="bi bi-receipt"></i></a>
                            <a class="vk-cctv-act" href="<?= e($viewUrl) ?>" data-bs-toggle="tooltip" title="History"><i class="bi bi-clock-history"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="vk-cctv-mobile-only" aria-label="CCTV jobs list">
        <?php if (!$rows): ?>
            <div class="vk-cctv-empty" role="status">
                <div class="vk-cctv-empty-icon"><i class="bi bi-camera-video-off"></i></div>
                <div class="vk-cctv-empty-title">No CCTV jobs found</div>
                <p class="vk-cctv-empty-text">Try adjusting filters or create a new job.</p>
                <a class="vk-cctv-btn vk-cctv-btn-primary" href="<?= e(BASE_URL) ?>/modules/cctv/add.php">Create CCTV Job</a>
            </div>
        <?php else: ?>
            <?php foreach ($rows as $r):
                $id = (int) $r['id'];
                $pri = $vkCctvPriority((string) $r['status']);
                $init = $vkCctvInitials((string) $r['customer_name']);
                $stKey = (string) $r['status'];
                $viewUrl = BASE_URL . '/modules/cctv/view.php?id=' . $id;
                $editUrl = BASE_URL . '/modules/cctv/edit.php?id=' . $id;
            ?>
            <article class="vk-cctv-mcard">
                <div class="vk-cctv-mcard-top">
                    <div class="vk-cctv-person">
                        <span class="vk-cctv-avatar"><?= e($init) ?></span>
                        <div class="vk-cctv-person-text">
                            <div class="vk-cctv-person-name"><?= e($r['customer_name']) ?></div>
                            <div class="vk-cctv-person-sub"><span class="vk-cctv-job"><?= e($r['job_number']) ?></span></div>
                        </div>
                    </div>
                    <span class="vk-cctv-pill vk-cctv-pill-<?= e($stKey) ?>"><?= e(str_replace('_', ' ', $stKey)) ?></span>
                </div>
                <div class="vk-cctv-mcard-meta">
                    <span class="vk-cctv-pri vk-cctv-pri-<?= e($pri['key']) ?>"><?= e($pri['label']) ?></span>
                    <span class="vk-cctv-amt"><?= e(number_format((float) $r['installation_charge'], 2)) ?></span>
                    <span class="vk-cctv-date"><?= e($vkCctvFormatDate($r['created_at'] ?? null)) ?></span>
                </div>
                <div class="vk-cctv-loc"><?= e($vkCctvLocShort((string) $r['location'])) ?></div>
                <div class="vk-cctv-mcard-actions">
                    <a class="vk-cctv-act" href="<?= e($viewUrl) ?>" title="View"><i class="bi bi-eye"></i></a>
                    <a class="vk-cctv-act" href="<?= e($editUrl) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                    <a class="vk-cctv-act" href="<?= e($viewUrl) ?>" title="Invoice"><i class="bi bi-receipt"></i></a>
                </div>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer class="vk-cctv-panel-footer">
        <div class="vk-cctv-range" id="vkCctvMeta">Showing —</div>
        <?php if ($pg['pages'] > 1): ?>
        <nav class="vk-cctv-pagination" aria-label="CCTV pagination">
            <?php if ($pg['page'] > 1): ?>
            <a class="vk-cctv-page-link" href="?<?= e($queryBase(['p' => $pg['page'] - 1])) ?>" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
            <?php endif; ?>
            <?php
            $start = max(1, $pg['page'] - 2);
            $end = min($pg['pages'], $pg['page'] + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a class="vk-cctv-page-link<?= $i === $pg['page'] ? ' is-active' : '' ?>" href="?<?= e($queryBase(['p' => $i])) ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pg['page'] < $pg['pages']): ?>
            <a class="vk-cctv-page-link" href="?<?= e($queryBase(['p' => $pg['page'] + 1])) ?>" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </footer>
</div>
</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/cctv-list.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
