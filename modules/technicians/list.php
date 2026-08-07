<?php
declare(strict_types=1);
$pageTitle = 'Technicians';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$hasGeo = db_column_exists($pdo, 'technicians', 'latitude');
$hasAvail = db_column_exists($pdo, 'technicians', 'availability');

$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 20;
$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (name LIKE ? OR phone LIKE ? OR specialization LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$countSt = $pdo->prepare("SELECT COUNT(*) FROM technicians WHERE $where");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();
$pg = paginate($total, $page, $perPage);

$sql = "SELECT * FROM technicians WHERE $where ORDER BY active DESC, name ASC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
    <h1 class="h3 mb-0">Technicians</h1>
    <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/technicians/add.php"><i class="bi bi-person-badge me-1"></i>Add technician</a>
</div>
<form class="row g-2 mb-3" method="get" action="">
    <div class="col-12 col-md-6 col-lg-4">
        <input type="search" name="q" class="form-control" placeholder="Search name, phone, skill" value="<?= e($q) ?>">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary" type="submit">Search</button>
    </div>
</form>
<div class="card vk-card">
    <div class="table-responsive table-responsive-stack">
        <table class="table table-hover mb-0 sortable">
            <thead class="table-light">
                <tr>
                    <th data-sort="0">Name</th>
                    <th data-sort="1">Phone</th>
                    <th data-sort="2">Specialization</th>
                    <?php if ($hasGeo): ?><th>Map</th><?php endif; ?>
                    <?php if ($hasAvail): ?><th>Avail.</th><?php endif; ?>
                    <th data-sort="3">Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="<?= 5 + ($hasGeo ? 1 : 0) + ($hasAvail ? 1 : 0) ?>" class="text-center text-muted py-4">No technicians found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['name']) ?></td>
                        <td><?= e($r['phone'] ?? '') ?></td>
                        <td><?= e($r['specialization'] ?? '') ?></td>
                        <?php if ($hasGeo): ?>
                            <td>
                                <?php if ($r['latitude'] !== null && $r['latitude'] !== '' && $r['longitude'] !== null && $r['longitude'] !== ''): ?>
                                    <span class="badge text-bg-info">Geo</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <?php if ($hasAvail): ?>
                            <td>
                                <?php if (($r['availability'] ?? 'available') === 'busy'): ?>
                                    <span class="badge text-bg-warning">Busy</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">OK</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td>
                            <?php if ((int) $r['active'] === 1): ?>
                                <span class="badge text-bg-success">Yes</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">No</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/technicians/edit.php?id=<?= (int) $r['id'] ?>">Edit</a>
                            <a class="btn btn-sm btn-outline-danger" href="<?= e(BASE_URL) ?>/modules/technicians/delete.php?id=<?= (int) $r['id'] ?>" onclick="return confirm('Remove this technician?');">Delete</a>
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
                <a class="page-link" href="?<?= e(http_build_query(['q' => $q, 'p' => $i])) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
