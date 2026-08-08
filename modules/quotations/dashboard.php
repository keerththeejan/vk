<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_mark_expired($pdo);

$kpi = vk_quotation_dashboard_kpis($pdo);
$perms = vk_quotation_permissions();

$latest = $pdo->query(
    "SELECT q.id, q.quotation_number, q.quotation_date, q.expiry_date, q.grand_total, q.status, q.currency,
            c.name AS customer_name, u.fullname AS executive_name
     FROM quotations q
     JOIN customers c ON c.id = q.customer_id
     LEFT JOIN users u ON u.id = q.sales_executive_id
     ORDER BY q.id DESC LIMIT 10"
)->fetchAll();

$activities = $pdo->query(
    "SELECT l.*, q.quotation_number, u.fullname AS user_name
     FROM quotation_activity_logs l
     LEFT JOIN quotations q ON q.id = l.quotation_id
     LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.id DESC LIMIT 12"
)->fetchAll();

$topCustomers = $pdo->query(
    "SELECT c.name, COUNT(*) AS cnt, COALESCE(SUM(q.grand_total),0) AS value
     FROM quotations q JOIN customers c ON c.id = q.customer_id
     GROUP BY q.customer_id, c.name
     ORDER BY value DESC LIMIT 6"
)->fetchAll();

$topExecs = $pdo->query(
    "SELECT COALESCE(u.fullname, 'Unassigned') AS name, COUNT(*) AS cnt, COALESCE(SUM(q.grand_total),0) AS value
     FROM quotations q
     LEFT JOIN users u ON u.id = q.sales_executive_id
     GROUP BY q.sales_executive_id, u.fullname
     ORDER BY value DESC LIMIT 6"
)->fetchAll();

$monthly = $pdo->query(
    "SELECT DATE_FORMAT(quotation_date, '%Y-%m') AS ym, COUNT(*) AS cnt, COALESCE(SUM(grand_total),0) AS value
     FROM quotations
     WHERE quotation_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
     GROUP BY ym ORDER BY ym ASC"
)->fetchAll();

$statusRows = $pdo->query(
    "SELECT status, COUNT(*) AS cnt FROM quotations GROUP BY status"
)->fetchAll();

$converted = (int) $pdo->query("SELECT COUNT(*) FROM quotations WHERE status IN ('converted_invoice','converted_so','accepted')")->fetchColumn();
$totalForConv = max(1, (int) $kpi['total']);
$conversionRate = round(($converted / $totalForConv) * 100, 1);

$pageTitle = 'Quotation Dashboard';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">'
    . '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$cards = [
    ['Total Quotations', $kpi['total'], 'bi-files', 'primary', 'list.php'],
    ['Draft', $kpi['draft'], 'bi-pencil-square', 'secondary', 'list.php?status=draft'],
    ['Pending', $kpi['pending_approval'], 'bi-hourglass-split', 'warning', 'approval.php'],
    ['Approved', $kpi['approved'], 'bi-check2-circle', 'success', 'list.php?status=approved'],
    ['Rejected', $kpi['rejected'], 'bi-x-circle', 'danger', 'list.php?status=rejected'],
    ['Expired', $kpi['expired'], 'bi-clock-history', 'secondary', 'expired.php'],
    ['Converted', $kpi['converted'], 'bi-arrow-left-right', 'teal', 'converted.php'],
    ['Monthly Revenue', formatCurrency($kpi['month_revenue']), 'bi-graph-up-arrow', 'indigo', 'analytics.php'],
];
?>
<div class="qtn-page qtn-dash-premium">
    <div class="qtn-dash-hero card vk-card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
                <div>
                    <p class="qtn-eyebrow mb-1">VK NETWORK · Sales</p>
                    <h1 class="h3 mb-1 text-white">Quotation Dashboard</h1>
                    <p class="mb-0 text-white-50">Enterprise quotation lifecycle · approvals · conversion analytics</p>
                </div>
                <div class="d-flex flex-wrap gap-2 qtn-dash-actions">
                    <div class="input-group" style="min-width:220px;max-width:280px">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control border-0" id="qtnQuickSearch" placeholder="Search quotations…">
                    </div>
                    <?php if ($perms['create']): ?>
                    <a class="btn btn-light fw-semibold" href="<?= e(BASE_URL) ?>/modules/quotations/create.php"><i class="bi bi-plus-lg me-1"></i>New Quotation</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-light" href="<?= e(BASE_URL) ?>/modules/quotations/list.php"><i class="bi bi-list-ul me-1"></i>Manage</a>
                    <a class="btn btn-outline-light" href="<?= e(BASE_URL) ?>/modules/quotations/reports.php"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export</a>
                    <a class="btn btn-outline-light" href="<?= e(BASE_URL) ?>/modules/quotations/settings.php"><i class="bi bi-gear me-1"></i>Settings</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php foreach ($cards as [$label, $val, $icon, $tone, $href]): ?>
        <div class="col-6 col-md-4 col-xl-3">
            <?php
            $link = $href;
            if ($link && !str_starts_with((string) $link, 'http') && !str_starts_with((string) $link, BASE_URL)) {
                $link = BASE_URL . '/modules/quotations/' . ltrim((string) $link, '/');
            }
            ?>
            <?php if ($link): ?><a href="<?= e((string) $link) ?>" class="text-decoration-none"><?php endif; ?>
            <div class="card qtn-kpi qtn-kpi--<?= e($tone) ?> qtn-kpi-glass h-100">
                <div class="card-body">
                    <span class="qtn-kpi-icon"><i class="bi <?= e($icon) ?>"></i></span>
                    <div class="qtn-kpi-value mt-2"><?= is_string($val) ? e($val) : e((string) $val) ?></div>
                    <div class="qtn-kpi-label"><?= e($label) ?></div>
                </div>
            </div>
            <?php if ($link): ?></a><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-8">
            <div class="card vk-card h-100 qtn-glass">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <strong>Sales performance</strong>
                    <span class="badge text-bg-light border">Last 12 months</span>
                </div>
                <div class="card-body"><canvas id="qtnMonthlyChart" height="120"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card vk-card h-100 qtn-glass">
                <div class="card-header bg-transparent"><strong>Status mix</strong></div>
                <div class="card-body"><canvas id="qtnStatusChart" height="180"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card vk-card h-100 qtn-glass">
                <div class="card-header bg-transparent"><strong>Conversion rate</strong></div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                    <div class="qtn-conversion-ring" style="--pct:<?= (float) $conversionRate ?>">
                        <span><?= e((string) $conversionRate) ?>%</span>
                    </div>
                    <p class="text-muted small mt-3 mb-0 text-center">Accepted / converted vs total</p>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-8">
            <div class="card vk-card h-100 qtn-glass">
                <div class="card-header bg-transparent"><strong>Monthly revenue</strong></div>
                <div class="card-body"><canvas id="qtnForecastChart" height="120"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card vk-card qtn-glass">
                <div class="card-header bg-transparent d-flex justify-content-between">
                    <strong>Latest quotations</strong>
                    <a href="<?= e(BASE_URL) ?>/modules/quotations/list.php" class="small">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle" id="qtnLatestTable">
                        <thead class="table-light"><tr><th>No</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (!$latest): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No quotations yet.</td></tr>
                        <?php else: foreach ($latest as $r): ?>
                            <tr>
                                <td><a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= (int) $r['id'] ?>"><?= e($r['quotation_number']) ?></a></td>
                                <td><?= e($r['customer_name']) ?></td>
                                <td><?= e(formatCurrency((float) $r['grand_total'])) ?></td>
                                <td><span class="badge text-bg-<?= e(vk_quotation_status_badge($r['status'])) ?>"><?= e(vk_quotation_status_label($r['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card vk-card h-100 qtn-glass">
                <div class="card-header bg-transparent"><strong>Top customers</strong></div>
                <ul class="list-group list-group-flush">
                    <?php if (!$topCustomers): ?>
                        <li class="list-group-item text-muted small">No data yet</li>
                    <?php else: foreach ($topCustomers as $c): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?= e($c['name']) ?><br><small class="text-muted"><?= (int) $c['cnt'] ?> quotes</small></span>
                            <strong class="small"><?= e(formatCurrency($c['value'])) ?></strong>
                        </li>
                    <?php endforeach; endif; ?>
                </ul>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card vk-card h-100 qtn-glass">
                <div class="card-header bg-transparent"><strong>Recent activity</strong></div>
                <ul class="list-group list-group-flush qtn-activity">
                    <?php if (!$activities): ?>
                        <li class="list-group-item text-muted small">No activity yet</li>
                    <?php else: foreach ($activities as $a): ?>
                        <li class="list-group-item">
                            <div class="fw-semibold small"><?= e(str_replace('_', ' ', (string) $a['action'])) ?></div>
                            <div class="text-muted small"><?= e((string) ($a['quotation_number'] ?? '')) ?> · <?= e((string) ($a['user_name'] ?? 'System')) ?></div>
                            <div class="text-muted" style="font-size:.7rem"><?= e((string) $a['created_at']) ?></div>
                        </li>
                    <?php endforeach; endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
window.QTN_DASH = {
    monthlyLabels: <?= json_encode(array_column($monthly, 'ym'), JSON_THROW_ON_ERROR) ?>,
    monthlyCounts: <?= json_encode(array_map('intval', array_column($monthly, 'cnt')), JSON_THROW_ON_ERROR) ?>,
    monthlyValues: <?= json_encode(array_map('floatval', array_column($monthly, 'value')), JSON_THROW_ON_ERROR) ?>,
    statusLabels: <?= json_encode(array_map('vk_quotation_status_label', array_column($statusRows, 'status')), JSON_THROW_ON_ERROR) ?>,
    statusCounts: <?= json_encode(array_map('intval', array_column($statusRows, 'cnt')), JSON_THROW_ON_ERROR) ?>,
    execNames: <?= json_encode(array_column($topExecs, 'name'), JSON_THROW_ON_ERROR) ?>,
    execValues: <?= json_encode(array_map('floatval', array_column($topExecs, 'value')), JSON_THROW_ON_ERROR) ?>
};
document.getElementById('qtnQuickSearch')?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        const q = this.value.trim();
        window.location.href = <?= json_encode(BASE_URL . '/modules/quotations/list.php?q=', JSON_THROW_ON_ERROR) ?> + encodeURIComponent(q);
    }
});
</script>
<script src="<?= e(base_url('assets/js/quotations-dashboard.js')) ?>?v=<?= e(vk_asset_mtime_version('assets/js/quotations-dashboard.js')) ?>" defer></script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
