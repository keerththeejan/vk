<?php
declare(strict_types=1);
$pageTitle = 'Invoices';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$cust = (int) ($_GET['customer_id'] ?? 0);
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 15;

$where = '1=1';
$params = [];
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where .= ' AND i.invoice_date >= ?';
    $params[] = $from;
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where .= ' AND i.invoice_date <= ?';
    $params[] = $to;
}
if ($cust > 0) {
    $where .= ' AND i.customer_id = ?';
    $params[] = $cust;
}

$countSt = $pdo->prepare("SELECT COUNT(*) FROM invoices i WHERE $where");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();
$pg = paginate($total, $page, $perPage);

$sql = "SELECT i.*, c.name AS customer_name
        FROM invoices i
        JOIN customers c ON c.id = i.customer_id
        WHERE $where
        ORDER BY i.id DESC
        LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$custs = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
    <h1 class="h3 mb-0">Invoices</h1>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/invoices/print_settings.php"><i class="bi bi-printer me-1"></i>Print Settings</a>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/invoices/create.php"><i class="bi bi-plus-lg me-1"></i>Create invoice</a>
    </div>
</div>
<form class="row g-2 mb-3 align-items-end" method="get" action="">
    <div class="col-6 col-md-2">
        <label class="form-label small mb-0">From</label>
        <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small mb-0">To</label>
        <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label small mb-0">Customer</label>
        <select name="customer_id" class="form-select">
            <option value="0">All customers</option>
            <?php foreach ($custs as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $cust === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary" type="submit">Filter</button>
    </div>
</form>
<div class="card vk-card">
    <div class="table-responsive table-responsive-stack">
        <table class="table table-hover mb-0 sortable">
            <thead class="table-light">
                <tr>
                    <th data-sort="0">#</th>
                    <th data-sort="1">Invoice</th>
                    <th data-sort="2">Date</th>
                    <th data-sort="3">Customer</th>
                    <th data-sort="4" data-type="number">Total</th>
                    <th data-sort="5" data-type="number">Paid</th>
                    <th data-sort="6" data-type="number">Due</th>
                    <th data-sort="7">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No invoices found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $due = (float) $r['grand_total'] - (float) $r['paid_amount'];
                    ?>
                    <tr>
                        <td><?= (int) $r['id'] ?></td>
                        <td><?= e($r['invoice_number']) ?></td>
                        <td><?= e($r['invoice_date']) ?></td>
                        <td><?= e($r['customer_name']) ?></td>
                        <td><?= e(number_format((float) $r['grand_total'], 2)) ?></td>
                        <td><?= e(number_format((float) $r['paid_amount'], 2)) ?></td>
                        <td><?= e(number_format($due, 2)) ?></td>
                        <td>
                            <?php
                            $badge = match ($r['status']) {
                                'paid' => 'success',
                                'partial' => 'warning',
                                'draft' => 'info',
                                'cancelled' => 'danger',
                                default => 'secondary',
                            };
                            ?>
                            <span class="badge text-bg-<?= e($badge) ?>"><?= e($r['status']) ?></span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= (int) $r['id'] ?>">View</a>
                            <?php if (($r['status'] ?? '') !== 'cancelled'): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/invoices/edit.php?id=<?= (int) $r['id'] ?>">Edit</a>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(BASE_URL) ?>/modules/invoices/print.php?id=<?= (int) $r['id'] ?>">Print</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($pg['pages'] > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm flex-wrap">
        <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
            <li class="page-item <?= $i === $pg['page'] ? 'active' : '' ?>">
                <a class="page-link" href="?<?= e(http_build_query(['from' => $from, 'to' => $to, 'customer_id' => $cust, 'p' => $i])) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
