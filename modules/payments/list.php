<?php
declare(strict_types=1);
$pageTitle = 'Payments';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 20;

$where = '1=1';
$params = [];
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where .= ' AND DATE(p.paid_at) >= ?';
    $params[] = $from;
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where .= ' AND DATE(p.paid_at) <= ?';
    $params[] = $to;
}

$countSt = $pdo->prepare("SELECT COUNT(*) FROM payments p WHERE $where");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();
$pg = paginate($total, $page, $perPage);

$sql = "SELECT p.*, i.invoice_number, c.name AS customer_name,
               r.job_number AS repair_job_number, v.job_number AS cctv_job_number
        FROM payments p
        JOIN accounts a ON a.id = p.customer_account_id
        JOIN customers c ON c.id = a.customer_id
        LEFT JOIN invoices i ON i.id = p.invoice_id
        LEFT JOIN repair_jobs r ON r.id = p.repair_job_id
        LEFT JOIN cctv_installations v ON v.id = p.cctv_job_id
        WHERE $where
        ORDER BY p.paid_at DESC
        LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>
<div class="mb-3">
    <h1 class="h3 mb-0">Payments</h1>
    <p class="text-muted small mb-0">Receipts for invoices, repair advances, and CCTV jobs.</p>
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
    <div class="col-auto">
        <button class="btn btn-outline-secondary" type="submit">Filter</button>
    </div>
</form>
<div class="card vk-card">
    <div class="table-responsive table-responsive-stack">
        <table class="table table-hover mb-0 sortable">
            <thead class="table-light">
                <tr>
                    <th data-sort="0">ID</th>
                    <th data-sort="1">Date</th>
                    <th data-sort="2">Reference</th>
                    <th data-sort="3">Customer</th>
                    <th data-sort="4" data-type="number">Amount</th>
                    <th data-sort="5">Method</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No payments found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $ref = '—';
                    $link = null;
                    if (!empty($r['invoice_id'])) {
                        $ref = 'Inv: ' . ($r['invoice_number'] ?? '');
                        $link = BASE_URL . '/modules/invoices/view.php?id=' . (int) $r['invoice_id'];
                    } elseif (!empty($r['repair_job_id'])) {
                        $ref = 'Repair: ' . ($r['repair_job_number'] ?? '');
                        $link = BASE_URL . '/modules/repairs/view.php?id=' . (int) $r['repair_job_id'];
                    } elseif (!empty($r['cctv_job_id'])) {
                        $ref = 'CCTV: ' . ($r['cctv_job_number'] ?? '');
                        $link = BASE_URL . '/modules/cctv/view.php?id=' . (int) $r['cctv_job_id'];
                    }
                    ?>
                    <tr>
                        <td><?= (int) $r['id'] ?></td>
                        <td><?= e($r['paid_at']) ?></td>
                        <td><?= $link ? '<a href="' . e($link) . '">' . e($ref) . '</a>' : e($ref) ?></td>
                        <td><?= e($r['customer_name']) ?></td>
                        <td><?= e(number_format((float) $r['amount'], 2)) ?></td>
                        <td><span class="badge text-bg-secondary"><?= e($r['method']) ?></span></td>
                        <td class="text-end">
                            <?php if (!empty($r['invoice_id'])): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= (int) $r['invoice_id'] ?>">Open</a>
                            <?php elseif (!empty($r['repair_job_id'])): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/repairs/view.php?id=<?= (int) $r['repair_job_id'] ?>">Job</a>
                            <?php elseif (!empty($r['cctv_job_id'])): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/cctv/view.php?id=<?= (int) $r['cctv_job_id'] ?>">Job</a>
                            <?php endif; ?>
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
                <a class="page-link" href="?<?= e(http_build_query(['from' => $from, 'to' => $to, 'p' => $i])) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
