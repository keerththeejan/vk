<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_require_perm('approve');

$q = trim((string) ($_GET['q'] ?? ''));
$exec = (int) ($_GET['sales_executive_id'] ?? 0);
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));

$where = ["q.status = 'pending_approval'"];
$params = [];
if ($q !== '') {
    $where[] = '(q.quotation_number LIKE ? OR c.name LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($exec > 0) {
    $where[] = 'q.sales_executive_id = ?';
    $params[] = $exec;
}
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[] = 'q.quotation_date >= ?';
    $params[] = $from;
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[] = 'q.quotation_date <= ?';
    $params[] = $to;
}

$whereSql = implode(' AND ', $where);
$sql = "SELECT q.*, c.name AS customer_name, u.fullname AS executive_name
        FROM quotations q
        JOIN customers c ON c.id = q.customer_id
        LEFT JOIN users u ON u.id = q.sales_executive_id
        WHERE $whereSql
        ORDER BY q.id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$executives = $pdo->query("SELECT id, fullname FROM users WHERE role NOT IN ('technician') ORDER BY fullname")->fetchAll();

$pageTitle = 'Pending Approvals';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <p class="qtn-eyebrow mb-1">Quotations</p>
            <h1 class="h3 mb-0">Pending Approvals</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
    </div>

    <form class="card vk-card mb-3" method="get">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-0">Search</label>
                    <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Number or customer…">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Sales executive</label>
                    <select name="sales_executive_id" class="form-select">
                        <option value="0">All</option>
                        <?php foreach ($executives as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= $exec === (int) $u['id'] ? 'selected' : '' ?>><?= e((string) $u['fullname']) ?></option>
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
                    <button type="submit" class="btn btn-outline-secondary">Filter</button>
                    <a href="<?= e(BASE_URL) ?>/modules/quotations/approval.php" class="btn btn-link">Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card vk-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Quotation</th>
                        <th>Customer</th>
                        <th>Executive</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th>Level</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No quotations pending approval.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <?php $rid = (int) $r['id']; ?>
                    <tr>
                        <td><a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $rid ?>"><?= e($r['quotation_number']) ?></a></td>
                        <td><?= e($r['customer_name']) ?></td>
                        <td><?= e((string) ($r['executive_name'] ?: '—')) ?></td>
                        <td><?= e($r['quotation_date']) ?></td>
                        <td class="text-end"><?= e($r['currency']) ?> <?= e(number_format((float) $r['grand_total'], 2)) ?></td>
                        <td><span class="badge text-bg-warning">Level <?= (int) $r['approval_level'] ?></span></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-warning" href="<?= e(BASE_URL) ?>/modules/quotations/approve.php?id=<?= $rid ?>"><i class="bi bi-shield-check me-1"></i>Review</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="text-muted small mt-2"><?= count($rows) ?> pending</p>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
