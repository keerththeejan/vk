<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);

$type = trim((string) ($_GET['type'] ?? ''));
$where = ["q.status IN ('converted_invoice','converted_so')"];
$params = [];
if ($type === 'invoice') {
    $where = ["q.status = 'converted_invoice'"];
} elseif ($type === 'so') {
    $where = ["q.status = 'converted_so'"];
}

$whereSql = implode(' AND ', $where);
$sql = "SELECT q.*, c.name AS customer_name, i.invoice_number
        FROM quotations q
        JOIN customers c ON c.id = q.customer_id
        LEFT JOIN invoices i ON i.id = q.converted_invoice_id
        WHERE $whereSql
        ORDER BY q.converted_at DESC, q.id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Converted Quotations';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <p class="qtn-eyebrow mb-1">Quotations</p>
            <h1 class="h3 mb-0">Converted Quotations</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/dashboard.php">Dashboard</a>
    </div>

    <div class="btn-group mb-3">
        <a class="btn btn-outline-secondary <?= $type === '' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/modules/quotations/converted.php">All</a>
        <a class="btn btn-outline-secondary <?= $type === 'invoice' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/modules/quotations/converted.php?type=invoice">Invoices</a>
        <a class="btn btn-outline-secondary <?= $type === 'so' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/modules/quotations/converted.php?type=so">Sales orders</a>
    </div>

    <div class="card vk-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Quotation</th><th>Customer</th><th>Converted</th><th>Type</th>
                        <th>Linked document</th><th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No converted quotations.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= (int) $r['id'] ?>"><?= e($r['quotation_number']) ?></a></td>
                        <td><?= e($r['customer_name']) ?></td>
                        <td class="small"><?= e((string) ($r['converted_at'] ?: '—')) ?></td>
                        <td><span class="badge text-bg-<?= e(vk_quotation_status_badge($r['status'])) ?>"><?= e(vk_quotation_status_label($r['status'])) ?></span></td>
                        <td>
                            <?php if ($r['status'] === 'converted_invoice' && !empty($r['converted_invoice_id'])): ?>
                                <a href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= (int) $r['converted_invoice_id'] ?>">
                                    <?= e((string) ($r['invoice_number'] ?: 'Invoice #' . $r['converted_invoice_id'])) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= e(formatCurrency($r['grand_total'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
