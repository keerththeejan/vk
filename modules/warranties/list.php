<?php
declare(strict_types=1);

$pageTitle = 'Warranties';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
vk_bootstrap_module('warranty_service');

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'warranty_no' => trim((string) ($_GET['warranty_no'] ?? '')),
    'customer' => trim((string) ($_GET['customer'] ?? '')),
    'invoice' => trim((string) ($_GET['invoice'] ?? '')),
    'product' => trim((string) ($_GET['product'] ?? '')),
    'brand' => trim((string) ($_GET['brand'] ?? '')),
    'model' => trim((string) ($_GET['model'] ?? '')),
    'serial' => trim((string) ($_GET['serial'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? ($_GET['filter'] ?? ''))),
    'warranty_type' => trim((string) ($_GET['warranty_type'] ?? '')),
    'purchase_from' => trim((string) ($_GET['purchase_from'] ?? '')),
    'purchase_to' => trim((string) ($_GET['purchase_to'] ?? '')),
    'expiry_from' => trim((string) ($_GET['expiry_from'] ?? '')),
    'expiry_to' => trim((string) ($_GET['expiry_to'] ?? '')),
    'created_from' => trim((string) ($_GET['created_from'] ?? '')),
    'created_to' => trim((string) ($_GET['created_to'] ?? '')),
];

$page = max(1, (int) ($_GET['p'] ?? 1));
$perPageOptions = [10, 20, 50, 100];
$perPageReq = (int) ($_GET['per_page'] ?? 20);
$perPage = in_array($perPageReq, $perPageOptions, true) ? $perPageReq : 20;
$sort = trim((string) ($_GET['sort'] ?? 'end_date'));
$dir = strtolower(trim((string) ($_GET['dir'] ?? 'asc'))) === 'desc' ? 'desc' : 'asc';
if (!in_array($sort, vk_warranty_sort_columns(), true)) {
    $sort = 'end_date';
}

$total = vk_warranty_count($pdo, $filters);
$pg = paginate($total, $page, $perPage);
$query = vk_warranty_list_query($filters, $sort, $dir, $pg['perPage'], $pg['offset']);
$st = $pdo->prepare($query['sql']);
$st->execute($query['params']);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$kpis = vk_warranty_kpis($pdo);
$alertDays = vk_warranty_alert_days();

$queryBase = static function (array $extra = []) use ($filters, $perPage, $sort, $dir): string {
    return http_build_query(array_merge($filters, [
        'per_page' => $perPage,
        'sort' => $sort,
        'dir' => $dir,
    ], $extra));
};

$renderRows = static function (array $rows): string {
    if (!$rows) {
        return '<tr><td colspan="18"><div class="vk-war-empty"><i class="bi bi-shield-exclamation d-block mb-2" style="font-size:1.6rem"></i>No warranty records match your filters.</div></td></tr>';
    }
    ob_start();
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        $status = vk_warranty_status($r);
        $wrNo = vk_warranty_number($id);
        $period = vk_warranty_period_label((string) $r['start_date'], (string) $r['end_date']);
        $daysLabel = $status['days'] === null ? '—' : ((int) $status['days'] < 0 ? 'Expired' : ((int) $status['days'] . 'd'));
        $base = rtrim(BASE_URL, '/');
        ?>
        <tr>
            <td><input type="checkbox" class="form-check-input vk-war-row-check" value="<?= $id ?>" aria-label="Select <?= e($wrNo) ?>"></td>
            <td><strong><?= e($wrNo) ?></strong></td>
            <td><?= e((string) $r['customer_name']) ?></td>
            <td><?= e((string) ($r['invoice_number'] ?: '—')) ?></td>
            <td title="<?= e((string) ($r['description'] ?? '')) ?>"><?= e((string) $r['title']) ?></td>
            <td class="text-muted">—</td>
            <td class="text-muted">—</td>
            <td class="text-muted">—</td>
            <td><span class="text-capitalize"><?= e((string) $r['warranty_type']) ?></span></td>
            <td><?= e($period) ?></td>
            <td><?= e((string) $r['start_date']) ?></td>
            <td><?= e((string) $r['end_date']) ?></td>
            <td><?= e($daysLabel) ?></td>
            <td><span class="vk-war-badge vk-war-badge-<?= e($status['class']) ?>"><?= e($status['label']) ?></span></td>
            <td class="text-muted">System</td>
            <td><?= e(substr((string) ($r['created_at'] ?? ''), 0, 10)) ?></td>
            <td><?= e(substr((string) ($r['created_at'] ?? ''), 0, 10)) ?></td>
            <td>
                <div class="vk-war-actions" role="group" aria-label="Actions for <?= e($wrNo) ?>">
                    <a class="vk-war-act" href="<?= e($base) ?>/modules/warranties/view.php?id=<?= $id ?>" title="View"><i class="bi bi-eye"></i></a>
                    <a class="vk-war-act" href="<?= e($base) ?>/modules/warranties/edit.php?id=<?= $id ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                    <a class="vk-war-act" href="<?= e($base) ?>/modules/warranties/print.php?id=<?= $id ?>" target="_blank" title="Print Warranty"><i class="bi bi-printer"></i></a>
                    <a class="vk-war-act" href="<?= e($base) ?>/modules/warranties/print.php?id=<?= $id ?>&pdf=1" target="_blank" title="Download PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                    <button type="button" class="vk-war-act" data-war-action="email" data-id="<?= $id ?>" title="Email Warranty"><i class="bi bi-envelope"></i></button>
                    <button type="button" class="vk-war-act" data-war-action="renew" data-id="<?= $id ?>" title="Renew Warranty"><i class="bi bi-arrow-repeat"></i></button>
                    <button type="button" class="vk-war-act" data-war-action="deactivate" data-id="<?= $id ?>" title="Deactivate"><i class="bi bi-slash-circle"></i></button>
                    <button type="button" class="vk-war-act vk-war-act-danger" data-war-action="delete" data-id="<?= $id ?>" title="Delete"><i class="bi bi-trash"></i></button>
                    <a class="vk-war-act" href="<?= e($base) ?>/modules/warranties/view.php?id=<?= $id ?>#history" title="History"><i class="bi bi-clock-history"></i></a>
                </div>
            </td>
        </tr>
        <?php
    }

    return (string) ob_get_clean();
};

$renderFooter = static function (array $pg, int $total, callable $queryBase, string $sort, string $dir): string {
    ob_start();
    $from = $total > 0 ? $pg['offset'] + 1 : 0;
    $to = min($pg['offset'] + $pg['perPage'], $total);
    ?>
    <div class="text-muted small">Showing <?= (int) $from ?>–<?= (int) $to ?> of <?= (int) $total ?></div>
    <?php if ($pg['pages'] > 1): ?>
    <nav aria-label="Warranty pagination">
        <ul class="pagination pagination-sm mb-0 flex-wrap">
            <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
                <li class="page-item <?= $i === $pg['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= e($queryBase(['p' => $i, 'sort' => $sort, 'dir' => $dir])) ?>" data-page="<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif;

    return (string) ob_get_clean();
};

$isPartial = isset($_GET['partial']) && (string) $_GET['partial'] === '1';
if ($isPartial) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'tbody' => $renderRows($rows),
        'footer' => $renderFooter($pg, $total, $queryBase, $sort, $dir),
        'total' => $total,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/warranties-list.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/warranties-list.js');
$extraHead = '<link href="' . e(base_url('assets/css/warranties-list.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$sortClass = static function (string $col) use ($sort, $dir): string {
    if ($sort !== $col) {
        return 'vk-war-sortable';
    }

    return 'vk-war-sortable is-sorted-' . $dir;
};
?>
<div id="vkWarApp"
     class="vk-war-admin"
     data-base="<?= e(rtrim(BASE_URL, '/')) ?>"
     data-csrf="<?= e((string) ($GLOBALS['vk_csrf_token'] ?? csrf_token())) ?>"
     role="application"
     aria-label="Warranty management">

    <header class="vk-war-header">
        <div>
            <h1 class="vk-war-title"><i class="bi bi-shield-check me-1" aria-hidden="true"></i> Warranty Management</h1>
            <p class="vk-war-subtitle">Enterprise warranty register · expiry alerts · renewals · certificates</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="vk-war-btn" href="<?= e(BASE_URL) ?>/modules/warranties/export.php?<?= e($queryBase(['report' => 'expiry'])) ?>"><i class="bi bi-graph-up"></i> Expiry Report</a>
            <a class="vk-war-btn" href="<?= e(BASE_URL) ?>/modules/warranties/export.php?<?= e($queryBase(['report' => 'monthly'])) ?>"><i class="bi bi-calendar3"></i> Monthly Report</a>
            <a class="vk-war-btn" href="<?= e(BASE_URL) ?>/modules/warranties/export.php?<?= e($queryBase()) ?>"><i class="bi bi-download"></i> Export CSV</a>
            <a class="vk-war-btn vk-war-btn-primary" href="<?= e(BASE_URL) ?>/modules/warranties/add.php"><i class="bi bi-plus-lg"></i> Add Warranty</a>
        </div>
    </header>

    <div class="vk-war-kpi-grid" role="region" aria-label="Warranty KPIs">
        <a class="vk-war-kpi vk-war-kpi-blue" href="?<?= e($queryBase(['status' => '', 'p' => 1])) ?>">
            <div class="vk-war-kpi-icon"><i class="bi bi-shield"></i></div>
            <div><span class="vk-war-kpi-label">Total Warranties</span><span class="vk-war-kpi-value"><?= (int) $kpis['total'] ?></span></div>
        </a>
        <a class="vk-war-kpi vk-war-kpi-green" href="?<?= e($queryBase(['status' => 'active', 'p' => 1])) ?>">
            <div class="vk-war-kpi-icon"><i class="bi bi-check-circle"></i></div>
            <div><span class="vk-war-kpi-label">Active</span><span class="vk-war-kpi-value"><?= (int) $kpis['active'] ?></span></div>
        </a>
        <a class="vk-war-kpi vk-war-kpi-orange" href="?<?= e($queryBase(['status' => 'expiring', 'p' => 1])) ?>">
            <div class="vk-war-kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div><span class="vk-war-kpi-label">Expiring This Month</span><span class="vk-war-kpi-value"><?= (int) $kpis['expiring_month'] ?></span></div>
        </a>
        <a class="vk-war-kpi vk-war-kpi-red" href="?<?= e($queryBase(['status' => 'expired', 'p' => 1])) ?>">
            <div class="vk-war-kpi-icon"><i class="bi bi-x-octagon"></i></div>
            <div><span class="vk-war-kpi-label">Expired</span><span class="vk-war-kpi-value"><?= (int) $kpis['expired'] ?></span></div>
        </a>
        <a class="vk-war-kpi vk-war-kpi-purple" href="?<?= e($queryBase(['status' => 'lifetime', 'p' => 1])) ?>">
            <div class="vk-war-kpi-icon"><i class="bi bi-infinity"></i></div>
            <div><span class="vk-war-kpi-label">Lifetime</span><span class="vk-war-kpi-value"><?= (int) $kpis['lifetime'] ?></span></div>
        </a>
        <a class="vk-war-kpi vk-war-kpi-cyan" href="?<?= e($queryBase(['created_from' => date('Y-m-d'), 'created_to' => date('Y-m-d'), 'p' => 1])) ?>">
            <div class="vk-war-kpi-icon"><i class="bi bi-calendar-plus"></i></div>
            <div><span class="vk-war-kpi-label">Today's Registrations</span><span class="vk-war-kpi-value"><?= (int) $kpis['today'] ?></span></div>
        </a>
    </div>

    <div class="vk-war-alerts" role="region" aria-label="Warranty notifications">
        <?php if ($kpis['expired'] > 0): ?>
            <a class="vk-war-alert-pill is-hot" href="?<?= e($queryBase(['status' => 'expired', 'p' => 1])) ?>"><i class="bi bi-exclamation-octagon"></i> <?= (int) $kpis['expired'] ?> expired</a>
        <?php endif; ?>
        <?php if ($kpis['expiring_today'] > 0): ?>
            <a class="vk-war-alert-pill is-hot" href="?<?= e($queryBase(['expiry_from' => date('Y-m-d'), 'expiry_to' => date('Y-m-d'), 'p' => 1])) ?>"><i class="bi bi-alarm"></i> <?= (int) $kpis['expiring_today'] ?> expiring today</a>
        <?php endif; ?>
        <?php if ($kpis['expiring_15'] > 0): ?>
            <a class="vk-war-alert-pill is-warn" href="?<?= e($queryBase(['status' => 'expiring', 'p' => 1])) ?>"><i class="bi bi-hourglass-split"></i> <?= (int) $kpis['expiring_15'] ?> expiring in 15 days</a>
        <?php endif; ?>
        <?php if ($kpis['expiring_30'] > 0): ?>
            <a class="vk-war-alert-pill is-warn" href="?<?= e($queryBase(['status' => 'expiring', 'p' => 1])) ?>"><i class="bi bi-bell"></i> <?= (int) $kpis['expiring_30'] ?> expiring in <?= (int) $alertDays ?> days</a>
        <?php endif; ?>
        <?php if ($kpis['today'] > 0): ?>
            <a class="vk-war-alert-pill" href="?<?= e($queryBase(['created_from' => date('Y-m-d'), 'created_to' => date('Y-m-d'), 'p' => 1])) ?>"><i class="bi bi-plus-circle"></i> <?= (int) $kpis['today'] ?> registered today</a>
        <?php endif; ?>
        <?php if ($kpis['expired'] === 0 && $kpis['expiring_30'] === 0 && $kpis['today'] === 0): ?>
            <span class="vk-war-alert-pill"><i class="bi bi-check2-circle"></i> No urgent warranty alerts</span>
        <?php endif; ?>
    </div>

    <section class="vk-war-card">
        <div class="vk-war-card-head">
            <h2><i class="bi bi-funnel me-1"></i> Search &amp; Filters</h2>
            <div class="d-flex gap-2 flex-wrap">
                <a class="vk-war-btn" href="<?= e(BASE_URL) ?>/modules/warranties/export.php?<?= e($queryBase(['report' => 'customer'])) ?>"><i class="bi bi-people"></i> Customer Report</a>
                <a class="vk-war-btn" href="<?= e(BASE_URL) ?>/modules/warranties/export.php?<?= e($queryBase(['report' => 'brand'])) ?>"><i class="bi bi-tags"></i> Brand Report</a>
            </div>
        </div>
        <form id="vkWarFilterForm" class="vk-war-filters" method="get" action="">
            <div>
                <label class="form-label" for="q">Instant search</label>
                <input class="form-control" type="search" name="q" id="q" value="<?= e($filters['q']) ?>" placeholder="Warranty, customer, invoice…" data-instant>
            </div>
            <div>
                <label class="form-label" for="warranty_no">Warranty No</label>
                <input class="form-control" name="warranty_no" id="warranty_no" value="<?= e($filters['warranty_no']) ?>" placeholder="WR-00001" data-instant>
            </div>
            <div>
                <label class="form-label" for="customer">Customer</label>
                <input class="form-control" name="customer" id="customer" value="<?= e($filters['customer']) ?>" data-instant>
            </div>
            <div>
                <label class="form-label" for="invoice">Invoice</label>
                <input class="form-control" name="invoice" id="invoice" value="<?= e($filters['invoice']) ?>" data-instant>
            </div>
            <div>
                <label class="form-label" for="product">Product</label>
                <input class="form-control" name="product" id="product" value="<?= e($filters['product']) ?>" data-instant>
            </div>
            <div>
                <label class="form-label" for="serial">Serial Number</label>
                <input class="form-control" name="serial" id="serial" value="<?= e($filters['serial']) ?>" data-instant>
            </div>
            <div>
                <label class="form-label" for="brand">Brand</label>
                <input class="form-control" name="brand" id="brand" value="<?= e($filters['brand']) ?>" data-instant>
            </div>
            <div>
                <label class="form-label" for="model">Model</label>
                <input class="form-control" name="model" id="model" value="<?= e($filters['model']) ?>" data-instant>
            </div>
            <div>
                <label class="form-label" for="status">Warranty Status</label>
                <select class="form-select" name="status" id="status" data-instant>
                    <option value="">All statuses</option>
                    <?php foreach (['active' => 'Active', 'expiring' => 'Expiring Soon', 'expired' => 'Expired', 'lifetime' => 'Lifetime', 'cancelled' => 'Cancelled'] as $k => $lbl): ?>
                        <option value="<?= e($k) ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="warranty_type">Warranty Type</label>
                <select class="form-select" name="warranty_type" id="warranty_type" data-instant>
                    <option value="">All types</option>
                    <option value="service" <?= $filters['warranty_type'] === 'service' ? 'selected' : '' ?>>Service</option>
                    <option value="product" <?= $filters['warranty_type'] === 'product' ? 'selected' : '' ?>>Product</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="purchase_from">Purchase From</label>
                <input class="form-control" type="date" name="purchase_from" id="purchase_from" value="<?= e($filters['purchase_from']) ?>" data-instant>
            </div>
            <div>
                <label class="form-label" for="purchase_to">Purchase To</label>
                <input class="form-control" type="date" name="purchase_to" id="purchase_to" value="<?= e($filters['purchase_to']) ?>" data-instant>
            </div>
            <div>
                <label class="form-label" for="expiry_from">Expiry From</label>
                <input class="form-control" type="date" name="expiry_from" id="expiry_from" value="<?= e($filters['expiry_from']) ?>" data-instant>
            </div>
            <div>
                <label class="form-label" for="expiry_to">Expiry To</label>
                <input class="form-control" type="date" name="expiry_to" id="expiry_to" value="<?= e($filters['expiry_to']) ?>" data-instant>
            </div>
            <div>
                <label class="form-label" for="per_page">Rows</label>
                <select class="form-select" name="per_page" id="per_page" data-instant>
                    <?php foreach ($perPageOptions as $n): ?>
                        <option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?> / page</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="dir" value="<?= e($dir) ?>">
            <div class="vk-war-filter-actions">
                <button class="vk-war-btn vk-war-btn-primary" type="submit"><i class="bi bi-search"></i> Apply</button>
                <button class="vk-war-btn" type="button" id="vkWarReset"><i class="bi bi-arrow-counterclockwise"></i> Reset Filter</button>
                <a class="vk-war-btn" href="<?= e(BASE_URL) ?>/modules/warranties/export.php?<?= e($queryBase()) ?>"><i class="bi bi-filetype-csv"></i> Export Filtered Data</a>
            </div>
        </form>
    </section>

    <section class="vk-war-card">
        <div class="vk-war-card-head">
            <h2><i class="bi bi-table me-1"></i> Warranty Register</h2>
            <span class="text-muted small"><?= (int) $total ?> record<?= $total === 1 ? '' : 's' ?></span>
        </div>

        <div id="vkWarBulkBar" class="vk-war-bulk">
            <strong><span id="vkWarBulkCount">0</span> selected</strong>
            <button type="button" class="vk-war-btn" data-bulk-action="export"><i class="bi bi-download"></i> Export</button>
            <button type="button" class="vk-war-btn" data-bulk-action="print"><i class="bi bi-printer"></i> Print</button>
            <button type="button" class="vk-war-btn" data-bulk-action="email"><i class="bi bi-envelope"></i> Email</button>
            <button type="button" class="vk-war-btn" data-bulk-action="renew"><i class="bi bi-arrow-repeat"></i> Renew</button>
            <button type="button" class="vk-war-btn" data-bulk-action="deactivate"><i class="bi bi-slash-circle"></i> Deactivate</button>
            <button type="button" class="vk-war-btn" data-bulk-action="delete"><i class="bi bi-trash"></i> Delete</button>
        </div>

        <div class="vk-war-table-wrap">
            <div id="vkWarLoading" class="vk-war-loading" aria-hidden="true">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
            </div>
            <table class="vk-war-table">
                <thead>
                    <tr>
                        <th style="width:34px"><input type="checkbox" class="form-check-input" id="vkWarSelectAll" aria-label="Select all"></th>
                        <th class="<?= e($sortClass('id')) ?>" data-sort="id">Warranty No</th>
                        <th class="<?= e($sortClass('customer_name')) ?>" data-sort="customer_name">Customer</th>
                        <th class="<?= e($sortClass('invoice_number')) ?>" data-sort="invoice_number">Invoice No</th>
                        <th class="<?= e($sortClass('title')) ?>" data-sort="title">Product Name</th>
                        <th>Serial</th>
                        <th>Brand</th>
                        <th>Model</th>
                        <th class="<?= e($sortClass('warranty_type')) ?>" data-sort="warranty_type">Type</th>
                        <th>Period</th>
                        <th class="<?= e($sortClass('start_date')) ?>" data-sort="start_date">Purchase Date</th>
                        <th class="<?= e($sortClass('end_date')) ?>" data-sort="end_date">Expiry Date</th>
                        <th>Days Left</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th class="<?= e($sortClass('created_at')) ?>" data-sort="created_at">Created</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="vkWarTableBody">
                    <?= $renderRows($rows) ?>
                </tbody>
            </table>
        </div>
        <div id="vkWarFooter" class="vk-war-footer">
            <?= $renderFooter($pg, $total, $queryBase, $sort, $dir) ?>
        </div>
    </section>
</div>
<script src="<?= e(base_url('assets/js/warranties-list.js')) ?>?v=<?= e($jsV) ?>" defer></script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
