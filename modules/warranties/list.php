<?php
declare(strict_types=1);
$pageTitle = 'Warranties';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$q = trim((string) ($_GET['q'] ?? ''));
$filter = trim((string) ($_GET['filter'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 20;

$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (w.title LIKE ? OR w.description LIKE ? OR c.name LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$alertDays = defined('WARRANTY_ALERT_DAYS') ? (int) WARRANTY_ALERT_DAYS : 30;
if ($filter === 'expiring') {
    $where .= ' AND w.end_date >= CURDATE() AND w.end_date <= DATE_ADD(CURDATE(), INTERVAL ' . (int) $alertDays . ' DAY)';
} elseif ($filter === 'expired') {
    $where .= ' AND w.end_date < CURDATE()';
}

$countSt = $pdo->prepare("SELECT COUNT(*) FROM warranty_records w JOIN customers c ON c.id = w.customer_id WHERE $where");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();
$pg = paginate($total, $page, $perPage);

$sql = "SELECT w.*, c.name AS customer_name
        FROM warranty_records w
        JOIN customers c ON c.id = w.customer_id
        WHERE $where
        ORDER BY w.end_date ASC, w.id DESC
        LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
    <h1 class="h3 mb-0">Warranty records</h1>
    <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/warranties/add.php"><i class="bi bi-shield-check me-1"></i>Add warranty</a>
</div>
<form class="row g-2 mb-3 align-items-end" method="get" action="">
    <div class="col-12 col-md-4">
        <label class="form-label small mb-0">Search</label>
        <input type="search" name="q" class="form-control" placeholder="Title, customer" value="<?= e($q) ?>">
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label small mb-0">Quick filter</label>
        <select name="filter" class="form-select">
            <option value="">All (by end date)</option>
            <option value="expiring" <?= $filter === 'expiring' ? 'selected' : '' ?>>Expiring within <?= (int) $alertDays ?> days</option>
            <option value="expired" <?= $filter === 'expired' ? 'selected' : '' ?>>Expired</option>
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
                    <th data-sort="0">Customer</th>
                    <th data-sort="1">Title</th>
                    <th data-sort="2">Type</th>
                    <th data-sort="3">End date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No warranty records.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php $badge = warranty_expiry_badge_class($r['end_date'] ?? null); ?>
                    <tr class="<?= $badge === 'warning' ? 'table-warning' : ($badge === 'dark' ? 'table-secondary' : '') ?>">
                        <td><?= e($r['customer_name']) ?></td>
                        <td><?= e($r['title']) ?></td>
                        <td><?= e($r['warranty_type']) ?></td>
                        <td>
                            <span class="badge text-bg-<?= e($badge) ?>"><?= e($r['end_date']) ?></span>
                            <?php
                            $rem = warranty_days_remaining($r['end_date'] ?? null);
                            if ($rem !== null && $rem >= 0): ?>
                                <span class="small text-muted ms-1"><?= (int) $rem ?>d left</span>
                            <?php elseif ($rem !== null && $rem < 0): ?>
                                <span class="small text-muted ms-1">Expired</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/warranties/edit.php?id=<?= (int) $r['id'] ?>">Edit</a>
                            <a class="btn btn-sm btn-outline-danger" href="<?= e(BASE_URL) ?>/modules/warranties/delete.php?id=<?= (int) $r['id'] ?>" onclick="return confirm('Delete this warranty record?');">Delete</a>
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
                <a class="page-link" href="?<?= e(http_build_query(['q' => $q, 'filter' => $filter, 'p' => $i])) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
