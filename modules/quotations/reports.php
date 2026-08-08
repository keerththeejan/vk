<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_require_perm('export');

$reportType = trim((string) ($_GET['type'] ?? 'summary'));
$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
$export = isset($_GET['export']);

$validTypes = ['summary', 'executive', 'customer', 'monthly', 'yearly', 'product', 'status', 'lost', 'conversion'];
if (!in_array($reportType, $validTypes, true)) {
    $reportType = 'summary';
}

$dateWhere = '';
$params = [];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $dateWhere .= ' AND q.quotation_date >= ?';
    $params[] = $from;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $dateWhere .= ' AND q.quotation_date <= ?';
    $params[] = $to;
}

$rows = [];
$headers = [];
$title = ucfirst($reportType) . ' Report';

switch ($reportType) {
    case 'summary':
        $headers = ['Quotation', 'Customer', 'Date', 'Status', 'Amount'];
        $sql = "SELECT q.quotation_number, c.name AS customer_name, q.quotation_date, q.status, q.grand_total, q.currency
                FROM quotations q JOIN customers c ON c.id = q.customer_id
                WHERE 1=1 $dateWhere ORDER BY q.quotation_date DESC";
        break;
    case 'executive':
        $headers = ['Executive', 'Count', 'Total value', 'Avg value'];
        $sql = "SELECT COALESCE(u.fullname, 'Unassigned') AS label, COUNT(*) AS cnt,
                       COALESCE(SUM(q.grand_total),0) AS total, COALESCE(AVG(q.grand_total),0) AS avg_val
                FROM quotations q LEFT JOIN users u ON u.id = q.sales_executive_id
                WHERE 1=1 $dateWhere GROUP BY q.sales_executive_id, u.fullname ORDER BY total DESC";
        break;
    case 'customer':
        $headers = ['Customer', 'Count', 'Total value'];
        $sql = "SELECT c.name AS label, COUNT(*) AS cnt, COALESCE(SUM(q.grand_total),0) AS total
                FROM quotations q JOIN customers c ON c.id = q.customer_id
                WHERE 1=1 $dateWhere GROUP BY q.customer_id, c.name ORDER BY total DESC";
        break;
    case 'monthly':
        $headers = ['Month', 'Count', 'Total value'];
        $sql = "SELECT DATE_FORMAT(q.quotation_date, '%Y-%m') AS label, COUNT(*) AS cnt, COALESCE(SUM(q.grand_total),0) AS total
                FROM quotations q WHERE 1=1 $dateWhere GROUP BY label ORDER BY label ASC";
        break;
    case 'yearly':
        $headers = ['Year', 'Count', 'Total value'];
        $sql = "SELECT DATE_FORMAT(q.quotation_date, '%Y') AS label, COUNT(*) AS cnt, COALESCE(SUM(q.grand_total),0) AS total
                FROM quotations q WHERE 1=1 $dateWhere GROUP BY label ORDER BY label ASC";
        break;
    case 'product':
        $headers = ['Product', 'Qty', 'Total value'];
        $sql = "SELECT qi.product_name AS label, COALESCE(SUM(qi.quantity),0) AS cnt, COALESCE(SUM(qi.line_total),0) AS total
                FROM quotation_items qi JOIN quotations q ON q.id = qi.quotation_id
                WHERE 1=1 $dateWhere GROUP BY qi.product_name ORDER BY total DESC LIMIT 100";
        break;
    case 'status':
        $headers = ['Status', 'Count', 'Total value'];
        $sql = "SELECT q.status AS label, COUNT(*) AS cnt, COALESCE(SUM(q.grand_total),0) AS total
                FROM quotations q WHERE 1=1 $dateWhere GROUP BY q.status ORDER BY cnt DESC";
        break;
    case 'lost':
        $headers = ['Quotation', 'Customer', 'Date', 'Reason', 'Amount'];
        $sql = "SELECT q.quotation_number, c.name AS customer_name, q.quotation_date, q.status, q.grand_total, q.currency
                FROM quotations q JOIN customers c ON c.id = q.customer_id
                WHERE q.status IN ('rejected','expired') $dateWhere ORDER BY q.quotation_date DESC";
        break;
    case 'conversion':
        $headers = ['Metric', 'Count', 'Value'];
        $sql = "SELECT q.status AS label, COUNT(*) AS cnt, COALESCE(SUM(q.grand_total),0) AS total
                FROM quotations q WHERE q.status IN ('accepted','converted_invoice','converted_so','rejected','expired') $dateWhere
                GROUP BY q.status ORDER BY cnt DESC";
        break;
    default:
        $sql = "SELECT q.quotation_number, c.name AS customer_name, q.quotation_date, q.status, q.grand_total, q.currency
                FROM quotations q JOIN customers c ON c.id = q.customer_id WHERE 1=1 $dateWhere ORDER BY q.id DESC";
}

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="quotation-' . $reportType . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        if ($reportType === 'summary' || $reportType === 'lost') {
            fputcsv($out, [$r['quotation_number'], $r['customer_name'], $r['quotation_date'], vk_quotation_status_label($r['status']), $r['grand_total']]);
        } elseif (in_array($reportType, ['executive', 'customer', 'monthly', 'yearly', 'status', 'conversion'], true)) {
            fputcsv($out, [$r['label'], $r['cnt'], $r['total']]);
        } elseif ($reportType === 'product') {
            fputcsv($out, [$r['label'], $r['cnt'], $r['total']]);
        }
    }
    fclose($out);
    exit;
}

$pageTitle = 'Quotation Reports';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
if (isset($_GET['print'])) {
    $extraHead .= '<style>@media print { .no-print { display:none!important } .qtn-report-table { font-size:10pt } }</style>';
}
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 no-print">
        <div>
            <p class="qtn-eyebrow mb-1">Analytics</p>
            <h1 class="h3 mb-0">Quotation Reports</h1>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/analytics.php">Analytics</a>
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        </div>
    </div>

    <form class="card vk-card mb-3 no-print" method="get">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-0">Report type</label>
                    <select name="type" class="form-select">
                        <?php foreach ($validTypes as $t): ?>
                            <option value="<?= e($t) ?>" <?= $reportType === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">From</label>
                    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">To</label>
                    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Run report</button>
                    <a class="btn btn-outline-secondary" href="?type=<?= e(urlencode($reportType)) ?>&from=<?= e(urlencode($from)) ?>&to=<?= e(urlencode($to)) ?>&export=1">CSV</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card vk-card qtn-report-print">
        <div class="card-header bg-transparent">
            <strong><?= e($title) ?></strong>
            <span class="text-muted small ms-2"><?= e($from) ?> — <?= e($to) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle qtn-report-table">
                <thead class="table-light">
                    <tr>
                        <?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="<?= count($headers) ?>" class="text-center text-muted py-4">No data for selected period.</td></tr>
                <?php elseif ($reportType === 'summary' || $reportType === 'lost'): ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= e($r['quotation_number']) ?></td>
                            <td><?= e($r['customer_name']) ?></td>
                            <td><?= e($r['quotation_date']) ?></td>
                            <td><span class="badge text-bg-<?= e(vk_quotation_status_badge($r['status'])) ?>"><?= e(vk_quotation_status_label($r['status'])) ?></span></td>
                            <td class="text-end"><?= e(formatCurrency($r['grand_total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php elseif (in_array($reportType, ['executive', 'customer', 'monthly', 'yearly', 'status', 'conversion', 'product'], true)): ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= e($reportType === 'status' || $reportType === 'conversion' ? vk_quotation_status_label($r['label']) : (string) $r['label']) ?></td>
                            <td class="text-end"><?= e((string) (is_numeric($r['cnt']) && str_contains((string) $r['cnt'], '.') ? number_format((float) $r['cnt'], 2) : $r['cnt'])) ?></td>
                            <td class="text-end"><?= e(formatCurrency($r['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-transparent small text-muted"><?= count($rows) ?> row(s)</div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
