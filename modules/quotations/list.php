<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_mark_expired($pdo);

$q = trim((string) ($_GET['q'] ?? ''));
$customerQ = trim((string) ($_GET['customer'] ?? ''));
$numberQ = trim((string) ($_GET['number'] ?? ''));
$phoneQ = trim((string) ($_GET['phone'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$month = trim((string) ($_GET['month'] ?? ''));
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
if ($customerQ !== '') {
    $where[] = '(c.name LIKE ? OR q.company_name LIKE ? OR q.contact_person LIKE ?)';
    $like = '%' . $customerQ . '%';
    array_push($params, $like, $like, $like);
}
if ($numberQ !== '') {
    $where[] = 'q.quotation_number LIKE ?';
    $params[] = '%' . $numberQ . '%';
}
if ($phoneQ !== '') {
    $where[] = '(q.phone LIKE ? OR q.mobile LIKE ?)';
    $like = '%' . $phoneQ . '%';
    array_push($params, $like, $like);
}
if ($status !== '' && in_array($status, vk_quotation_statuses(), true)) {
    $where[] = 'q.status = ?';
    $params[] = $status;
}
if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
    $where[] = "DATE_FORMAT(q.quotation_date, '%Y-%m') = ?";
    $params[] = $month;
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

$qs = static function (array $extra = []) use ($q, $customerQ, $numberQ, $phoneQ, $status, $from, $to, $month, $exec, $cust, $cat, $currency, $approval, $branch): string {
    return http_build_query(array_merge([
        'q' => $q, 'customer' => $customerQ, 'number' => $numberQ, 'phone' => $phoneQ,
        'status' => $status, 'from' => $from, 'to' => $to, 'month' => $month,
        'sales_executive_id' => $exec, 'customer_id' => $cust, 'category_id' => $cat,
        'currency' => $currency, 'approval_status' => $approval, 'branch' => $branch,
    ], $extra));
};
?>
<div class="qtn-page">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
        <div>
            <p class="qtn-eyebrow mb-1">Quotations</p>
            <h1 class="h3 mb-0">Quotation Management</h1>
            <p class="text-muted small mb-0"><?= (int) $total ?> quotation(s) found</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
            <?php if ($perms['create']): ?>
            <a class="btn btn-qtn-primary" href="<?= e(BASE_URL) ?>/modules/quotations/create.php"><i class="bi bi-plus-lg me-1"></i>Create Quotation</a>
            <?php endif; ?>
        </div>
    </div>

    <form class="card qtn-card qtn-filter-card mb-3" method="get" id="qtnFilterForm">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-3">
                    <label class="form-label">Live Search</label>
                    <input type="search" name="q" id="qtnLiveSearch" class="form-control" placeholder="No, customer, phone, product…" value="<?= e($q) ?>" autocomplete="off">
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="form-label">Customer</label>
                    <input type="search" name="customer" class="form-control qtn-live-field" placeholder="Customer name" value="<?= e($customerQ) ?>">
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="form-label">Quotation No</label>
                    <input type="search" name="number" class="form-control qtn-live-field" placeholder="QTN-…" value="<?= e($numberQ) ?>">
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="form-label">Phone</label>
                    <input type="search" name="phone" class="form-control qtn-live-field" placeholder="Phone" value="<?= e($phoneQ) ?>">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select qtn-auto-submit">
                        <option value="">All</option>
                        <?php foreach (vk_quotation_statuses() as $s): ?>
                            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(vk_quotation_status_label($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-1">
                    <label class="form-label">Month</label>
                    <input type="month" name="month" class="form-control qtn-auto-submit" value="<?= e($month) ?>">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label">From</label>
                    <input type="date" name="from" class="form-control qtn-auto-submit" value="<?= e($from) ?>">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label">To</label>
                    <input type="date" name="to" class="form-control qtn-auto-submit" value="<?= e($to) ?>">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label">Sales Executive</label>
                    <select name="sales_executive_id" class="form-select qtn-auto-submit">
                        <option value="0">All</option>
                        <?php foreach ($executives as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= $exec === (int) $u['id'] ? 'selected' : '' ?>><?= e((string) $u['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label">Customer List</label>
                    <select name="customer_id" class="form-select qtn-auto-submit">
                        <option value="0">All</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $cust === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select qtn-auto-submit">
                        <option value="0">All</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a class="btn btn-link" href="<?= e(BASE_URL) ?>/modules/quotations/list.php">Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card qtn-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle sortable qtn-table">
                <thead>
                    <tr>
                        <th>Quotation No</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Sales Executive</th>
                        <th>Date</th>
                        <th>Valid Until</th>
                        <th class="text-end" data-type="number">Amount</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">No quotations match your filters.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <?php
                    $id = (int) $r['id'];
                    $wa = vk_quotation_whatsapp_url($pdo, $r);
                    $printUrl = BASE_URL . '/modules/quotations/print.php?id=' . $id;
                    $pdfUrl = $printUrl . '&download=1';
                    ?>
                    <tr>
                        <td>
                            <a class="fw-semibold text-decoration-none" href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>"><?= e($r['quotation_number']) ?></a>
                            <?php if ((int) $r['revision_no'] > 0): ?>
                                <span class="badge text-bg-light border">R<?= (int) $r['revision_no'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= e($r['customer_name']) ?></div>
                            <?php if (!empty($r['company_name']) && $r['company_name'] !== $r['customer_name']): ?>
                                <div class="small text-muted"><?= e((string) $r['company_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($r['phone'] ?: '—')) ?></td>
                        <td><?= e((string) ($r['executive_name'] ?: '—')) ?></td>
                        <td><?= e($r['quotation_date']) ?></td>
                        <td><?= e((string) ($r['expiry_date'] ?: '—')) ?></td>
                        <td class="text-end fw-semibold"><?= e($r['currency']) ?> <?= e(number_format((float) $r['grand_total'], 2)) ?></td>
                        <td><span class="badge text-bg-<?= e(vk_quotation_status_badge($r['status'])) ?>"><?= e(vk_quotation_status_label($r['status'])) ?></span></td>
                        <td class="text-end">
                            <div class="qtn-action-btns">
                                <a class="btn btn-outline-secondary" title="View" href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>"><i class="bi bi-eye"></i></a>
                                <?php if ($perms['edit'] && in_array($r['status'], ['draft','rejected','pending_approval'], true)): ?>
                                <a class="btn btn-outline-primary" title="Edit" href="<?= e(BASE_URL) ?>/modules/quotations/edit.php?id=<?= $id ?>"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
                                <?php if ($perms['create']): ?>
                                <a class="btn btn-outline-secondary" title="Duplicate" href="<?= e(BASE_URL) ?>/modules/quotations/duplicate.php?id=<?= $id ?>"><i class="bi bi-copy"></i></a>
                                <?php endif; ?>
                                <a class="btn btn-outline-secondary" title="Print" target="_blank" href="<?= e($printUrl) ?>"><i class="bi bi-printer"></i></a>
                                <a class="btn btn-outline-secondary" title="Download PDF" target="_blank" href="<?= e($pdfUrl) ?>"><i class="bi bi-filetype-pdf"></i></a>
                                <a class="btn btn-outline-secondary" title="Email" href="<?= e(BASE_URL) ?>/modules/quotations/email.php?id=<?= $id ?>"><i class="bi bi-envelope"></i></a>
                                <a class="btn btn-outline-success" title="WhatsApp" target="_blank" href="<?= e($wa) ?>"><i class="bi bi-whatsapp"></i></a>
                                <?php if ($perms['approve'] && $r['status'] === 'pending_approval'): ?>
                                <a class="btn btn-outline-warning" title="Approve / Reject" href="<?= e(BASE_URL) ?>/modules/quotations/approve.php?id=<?= $id ?>"><i class="bi bi-shield-check"></i></a>
                                <?php endif; ?>
                                <?php if ($perms['convert'] && in_array($r['status'], ['approved','accepted'], true)): ?>
                                <a class="btn btn-outline-success" title="Convert to Invoice" href="<?= e(BASE_URL) ?>/modules/quotations/convert.php?id=<?= $id ?>&to=invoice"><i class="bi bi-receipt"></i></a>
                                <?php endif; ?>
                                <?php if ($perms['delete']): ?>
                                <a class="btn btn-outline-danger" title="Delete" href="<?= e(BASE_URL) ?>/modules/quotations/delete.php?id=<?= $id ?>" onclick="return confirm('Delete this quotation?')"><i class="bi bi-trash"></i></a>
                                <?php endif; ?>
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
</div>
<a class="qtn-fab" href="<?= e(BASE_URL) ?>/modules/quotations/create.php" title="New quotation (N)"><i class="bi bi-plus-lg"></i></a>
<script src="<?= e(base_url('assets/js/quotations-list.js')) ?>?v=<?= e(vk_asset_mtime_version('assets/js/quotations-list.js')) ?>" defer></script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
