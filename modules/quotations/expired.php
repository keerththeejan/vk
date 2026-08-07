<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/quotations/expired.php');
    }
    $count = vk_quotation_mark_expired($pdo);
    flash_set('success', $count . ' quotation(s) marked as expired.');
    redirect('/modules/quotations/expired.php');
}

vk_quotation_mark_expired($pdo);

$q = trim((string) ($_GET['q'] ?? ''));
$where = ["q.status = 'expired'"];
$params = [];
if ($q !== '') {
    $where[] = '(q.quotation_number LIKE ? OR c.name LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(' AND ', $where);
$sql = "SELECT q.*, c.name AS customer_name, u.fullname AS executive_name
        FROM quotations q
        JOIN customers c ON c.id = q.customer_id
        LEFT JOIN users u ON u.id = q.sales_executive_id
        WHERE $whereSql
        ORDER BY q.expiry_date DESC, q.id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Expired Quotations';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <p class="qtn-eyebrow mb-1">Quotations</p>
            <h1 class="h3 mb-0">Expired Quotations</h1>
        </div>
        <form method="post" class="d-inline">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="btn btn-outline-warning"><i class="bi bi-clock-history me-1"></i>Run mark expired</button>
        </form>
    </div>

    <form class="card vk-card mb-3" method="get">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-0">Search</label>
                    <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Number or customer…">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card vk-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Quotation</th><th>Customer</th><th>Date</th><th>Expired on</th>
                        <th class="text-end">Amount</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No expired quotations.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <?php $rid = (int) $r['id']; ?>
                    <tr>
                        <td><a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $rid ?>"><?= e($r['quotation_number']) ?></a></td>
                        <td><?= e($r['customer_name']) ?></td>
                        <td><?= e($r['quotation_date']) ?></td>
                        <td><?= e((string) ($r['expiry_date'] ?: '—')) ?></td>
                        <td class="text-end"><?= e($r['currency']) ?> <?= e(number_format((float) $r['grand_total'], 2)) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/duplicate.php?id=<?= $rid ?>">Duplicate</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="text-muted small mt-2"><?= count($rows) ?> expired</p>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
