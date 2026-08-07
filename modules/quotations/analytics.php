<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_mark_expired($pdo);

$stats = $pdo->query(
    "SELECT
        COUNT(*) AS total_count,
        COALESCE(SUM(grand_total), 0) AS total_value,
        SUM(status IN ('accepted','converted_invoice','converted_so')) AS won_count,
        SUM(status IN ('rejected','expired')) AS lost_count
     FROM quotations"
)->fetch();

$totalCount = max(1, (int) ($stats['total_count'] ?? 0));
$totalValue = (float) ($stats['total_value'] ?? 0);
$wonCount = (int) ($stats['won_count'] ?? 0);
$lostCount = (int) ($stats['lost_count'] ?? 0);
$winRate = round(($wonCount / $totalCount) * 100, 1);
$lossRate = round(($lostCount / $totalCount) * 100, 1);
$avgQuotation = $totalCount > 0 ? round($totalValue / $totalCount, 2) : 0.0;

$monthly = $pdo->query(
    "SELECT DATE_FORMAT(quotation_date, '%Y-%m') AS ym,
            COUNT(*) AS cnt,
            COALESCE(SUM(grand_total), 0) AS value
     FROM quotations
     WHERE quotation_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
     GROUP BY ym ORDER BY ym ASC"
)->fetchAll();

$funnel = [];
foreach (vk_quotation_statuses() as $st) {
    $fst = $pdo->prepare('SELECT COUNT(*) FROM quotations WHERE status = ?');
    $fst->execute([$st]);
    $funnel[] = ['status' => $st, 'label' => vk_quotation_status_label($st), 'count' => (int) $fst->fetchColumn()];
}

$pageTitle = 'Quotation Analytics';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">'
    . '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <p class="qtn-eyebrow mb-1">Analytics</p>
            <h1 class="h3 mb-0">Quotation Analytics</h1>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/reports.php">Reports</a>
            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/dashboard.php">Dashboard</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card vk-card qtn-kpi qtn-kpi--primary h-100">
                <div class="card-body">
                    <div class="qtn-kpi-value">LKR <?= e(number_format($totalValue, 0)) ?></div>
                    <div class="qtn-kpi-label">Total value</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card vk-card qtn-kpi qtn-kpi--success h-100">
                <div class="card-body">
                    <div class="qtn-kpi-value"><?= e((string) $winRate) ?>%</div>
                    <div class="qtn-kpi-label">Win rate</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card vk-card qtn-kpi qtn-kpi--danger h-100">
                <div class="card-body">
                    <div class="qtn-kpi-value"><?= e((string) $lossRate) ?>%</div>
                    <div class="qtn-kpi-label">Loss rate</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card vk-card qtn-kpi qtn-kpi--indigo h-100">
                <div class="card-body">
                    <div class="qtn-kpi-value">LKR <?= e(number_format($avgQuotation, 0)) ?></div>
                    <div class="qtn-kpi-label">Average quotation</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card vk-card qtn-kpi qtn-kpi--info h-100">
                <div class="card-body">
                    <div class="qtn-kpi-value"><?= (int) ($stats['total_count'] ?? 0) ?></div>
                    <div class="qtn-kpi-label">Total quotes</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card vk-card qtn-kpi qtn-kpi--teal h-100">
                <div class="card-body">
                    <div class="qtn-kpi-value"><?= $wonCount ?></div>
                    <div class="qtn-kpi-label">Won / converted</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card vk-card h-100">
                <div class="card-header bg-transparent fw-semibold">Monthly quotations (value)</div>
                <div class="card-body"><canvas id="qtnAnalyticsMonthly" height="120"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card vk-card h-100">
                <div class="card-header bg-transparent fw-semibold">Conversion funnel</div>
                <div class="card-body">
                    <?php foreach ($funnel as $f): ?>
                        <?php $pct = $totalCount > 0 ? round(($f['count'] / $totalCount) * 100, 1) : 0; ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span><?= e($f['label']) ?></span>
                                <span><?= $f['count'] ?> (<?= e((string) $pct) ?>%)</span>
                            </div>
                            <div class="progress" style="height:6px">
                                <div class="progress-bar bg-primary" style="width:<?= min(100, $pct) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    var el = document.getElementById('qtnAnalyticsMonthly');
    if (!el) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($monthly, 'ym'), JSON_THROW_ON_ERROR) ?>,
            datasets: [{
                label: 'Value (LKR)',
                data: <?= json_encode(array_map('floatval', array_column($monthly, 'value')), JSON_THROW_ON_ERROR) ?>,
                backgroundColor: 'rgba(11, 77, 186, 0.75)',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
