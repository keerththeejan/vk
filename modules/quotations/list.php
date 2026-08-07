<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_mark_expired($pdo);

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$exec = (int) ($_GET['sales_executive_id'] ?? 0);
$cust = (int) ($_GET['customer_id'] ?? 0);
$cat = (int) ($_GET['category_id'] ?? 0);
$currency = trim((string) ($_GET['currency'] ?? ''));
$approval = trim((string) ($_GET['approval_status'] ?? ''));
$branch = trim((string) ($_GET['branch'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 20;
$perms = vk_quotation_permissions();

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(q.quotation_number LIKE ? OR c.name LIKE ? OR q.phone LIKE ? OR q.email LIKE ? OR q.reference_number LIKE ? OR q.company_name LIKE ? OR EXISTS (
        SELECT 1 FROM quotation_items qi WHERE qi.quotation_id = q.id AND (qi.product_name LIKE ? OR qi.product_code LIKE ?)
    ))';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
}
if ($status !== '' && in_array($status, vk_quotation_statuses(), true)) {
    $where[] = 'q.status = ?';
    $params[] = $status;
}
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[] = 'q.quotation_date >= ?';
    $params[] = $from;
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[] = 'q.quotation_date <= ?';
    $params[] = $to;
}
if ($exec > 0) {
    $where[] = 'q.sales_executive_id = ?';
    $params[] = $exec;
}
if ($cust > 0) {
    $where[] = 'q.customer_id = ?';
    $params[] = $cust;
}
if ($cat > 0) {
    $where[] = 'q.category_id = ?';
    $params[] = $cat;
}
if ($currency !== '') {
    $where[] = 'q.currency = ?';
    $params[] = $currency;
}
if ($approval !== '') {
    $where[] = 'q.approval_status = ?';
    $params[] = $approval;
}
if ($branch !== '') {
    $where[] = 'q.branch LIKE ?';
    $params[] = '%' . $branch . '%';
}

$whereSql = implode(' AND ', $where);
$countSt = $pdo->prepare("SELECT COUNT(*) FROM quotations q JOIN customers c ON c.id = q.customer_id WHERE $whereSql");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();
$pg = paginate($total, $page, $perPage);

$sql = "SELECT q.*, c.name AS customer_name, u.fullname AS executive_name, cb.fullname AS created_by_name
        FROM quotations q
        JOIN customers c ON c.id = q.customer_id
        LEFT JOIN users u ON u.id = q.sales_executive_id
        LEFT JOIN users cb ON cb.id = q.created_by
        WHERE $whereSql
        ORDER BY q.id DESC
        LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
$executives = $pdo->query("SELECT id, fullname FROM users WHERE role NOT IN ('technician') ORDER BY fullname")->fetchAll();
$categories = $pdo->query('SELECT id, name FROM quotation_categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();

$pageTitle = 'Quotation List';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$qs = static function (array $extra = []) use ($q, $status, $from, $to, $exec, $cust, $cat, $currency, $approval, $branch): string {
    return http_build_query(array_merge([
        'q' => $q, 'status' => $status, 'from' => $from, 'to' => $to,
        'sales_executive_id' => $exec, 'customer_id' => $cust, 'category_id' => $cat,
        'currency' => $currency, 'approval_status' => $approval, 'branch' => $branch,
    ], $extra));
};
?>
<div class="qtn-page">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
        <div>
            <p class="qtn-eyebrow mb-1">Quotations</p>
            <h1 class="h3 mb-0">Quotation List</h1>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
            <?php if ($perms['create']): ?>
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/quotations/create.php"><i class="bi bi-plus-lg me-1"></i>Create quotation</a>
            <?php endif; ?>
        </div>
    </div>

    <form class="card vk-card mb-3" method="get">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-3">
                    <label class="form-label small mb-0">Search</label>
                    <input type="search" name="q" class="form-control" placeholder="No, customer, phone, product…" value="<?= e($q) ?>">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small mb-0">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <?php foreach (vk_quotation_statuses() as $s): ?>
                            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(vk_quotation_status_label($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small mb-0">From</label>
                    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small mb-0">To</label>
                    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small mb-0">Sales executive</label>
                    <select name="sales_executive_id" class="form-select">
                        <option value="0">All</option>
                        <?php foreach ($executives as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= $exec === (int) $u['id'] ? 'selected' : '' ?>><?= e((string) $u['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small mb-0">Customer</label>
                    <select name="customer_id" class="form-select">
                        <option value="0">All</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $cust === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small mb-0">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="0">All</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2 col-lg-1">
                    <label class="form-label small mb-0">Currency</label>
                    <input type="text" name="currency" class="form-control" value="<?= e($currency) ?>" placeholder="LKR">
                </div>
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label small mb-0">Approval</label>
                    <select name="approval_status" class="form-select">
                        <option value="">All</option>
                        <?php foreach (['none','pending','approved','rejected'] as $a): ?>
                            <option value="<?= e($a) ?>" <?= $approval === $a ? 'selected' : '' ?>><?= e(ucfirst($a)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label small mb-0">Branch</label>
                    <input type="text" name="branch" class="form-control" value="<?= e($branch) ?>">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a class="btn btn-link" href="<?= e(BASE_URL) ?>/modules/quotations/list.php">Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card vk-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle sortable qtn-table">
                <thead class="table-light">
                    <tr>
                        <th>Quotation No</th>
                        <th>Customer</th>
                        <th>Company</th>
                        <th>Sales Executive</th>
                        <th>Date</th>
                        <th>Expiry</th>
                        <th class="text-end" data-type="number">Amount</th>
                        <th>Status</th>
                        <th>Approval</th>
                        <th>Created By</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="12" class="text-center text-muted py-5">No quotations match your filters.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <?php $id = (int) $r['id']; ?>
                    <tr>
                        <td>
                            <a class="fw-semibold" href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>"><?= e($r['quotation_number']) ?></a>
                            <?php if ((int) $r['revision_no'] > 0): ?>
                                <span class="badge text-bg-light border">R<?= (int) $r['revision_no'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($r['customer_name']) ?></td>
                        <td><?= e((string) ($r['company_name'] ?: '—')) ?></td>
                        <td><?= e((string) ($r['executive_name'] ?: '—')) ?></td>
                        <td><?= e($r['quotation_date']) ?></td>
                        <td><?= e((string) ($r['expiry_date'] ?: '—')) ?></td>
                        <td class="text-end"><?= e($r['currency']) ?> <?= e(number_format((float) $r['grand_total'], 2)) ?></td>
                        <td><span class="badge text-bg-<?= e(vk_quotation_status_badge($r['status'])) ?>"><?= e(vk_quotation_status_label($r['status'])) ?></span></td>
                        <td><span class="badge text-bg-light border"><?= e(ucfirst((string) $r['approval_status'])) ?></span></td>
                        <td><?= e((string) ($r['created_by_name'] ?: '—')) ?></td>
                        <td class="small text-muted"><?= e(substr((string) $r['created_at'], 0, 16)) ?></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>"><i class="bi bi-eye me-2"></i>View</a></li>
                                    <?php if ($perms['edit'] && in_array($r['status'], ['draft','rejected','pending_approval'], true)): ?>
                                    <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/modules/quotations/edit.php?id=<?= $id ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                    <?php endif; ?>
                                    <?php if ($perms['create']): ?>
                                    <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/modules/quotations/duplicate.php?id=<?= $id ?>"><i class="bi bi-copy me-2"></i>Duplicate</a></li>
                                    <?php endif; ?>
                                    <?php if ($perms['approve'] && $r['status'] === 'pending_approval'): ?>
                                    <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/modules/quotations/approve.php?id=<?= $id ?>"><i class="bi bi-check2 me-2"></i>Approve / Reject</a></li>
                                    <?php endif; ?>
                                    <?php if ($perms['convert'] && in_array($r['status'], ['approved','accepted'], true)): ?>
                                    <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/modules/quotations/convert.php?id=<?= $id ?>&to=invoice"><i class="bi bi-receipt me-2"></i>Convert to Invoice</a></li>
                                    <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/modules/quotations/convert.php?id=<?= $id ?>&to=so"><i class="bi bi-cart-check me-2"></i>Convert to Sales Order</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" target="_blank" href="<?= e(BASE_URL) ?>/modules/quotations/print.php?id=<?= $id ?>"><i class="bi bi-printer me-2"></i>Print / PDF</a></li>
                                    <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/modules/quotations/email.php?id=<?= $id ?>"><i class="bi bi-envelope me-2"></i>Email</a></li>
                                    <li><a class="dropdown-item" target="_blank" href="<?= e(vk_quotation_whatsapp_url($pdo, $r)) ?>"><i class="bi bi-whatsapp me-2"></i>WhatsApp</a></li>
                                    <?php if ($perms['delete']): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="<?= e(BASE_URL) ?>/modules/quotations/delete.php?id=<?= $id ?>" onclick="return confirm('Delete this quotation?')"><i class="bi bi-trash me-2"></i>Delete</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pg['pages'] > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm flex-wrap">
            <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
                <li class="page-item <?= $i === $pg['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= e($qs(['p' => $i])) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <p class="text-muted small mt-2"><?= (int) $total ?> quotation(s)</p>
</div>
<a class="qtn-fab" href="<?= e(BASE_URL) ?>/modules/quotations/create.php" title="New quotation (N)"><i class="bi bi-plus-lg"></i></a>
<script src="<?= e(base_url('assets/js/quotations-list.js')) ?>?v=<?= e(vk_asset_mtime_version('assets/js/quotations-list.js')) ?>" defer></script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
