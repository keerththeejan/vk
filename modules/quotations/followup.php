<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);

$quotationId = (int) ($_GET['quotation_id'] ?? $_POST['quotation_id'] ?? 0);
$statusFilter = trim((string) ($_GET['status'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/quotations/followup.php');
    }
    $action = (string) ($_POST['action'] ?? 'add');

    if ($action === 'done') {
        $fuId = (int) ($_POST['followup_id'] ?? 0);
        $pdo->prepare("UPDATE quotation_followups SET reminder_status = 'done', completed_at = NOW() WHERE id = ?")->execute([$fuId]);
        vk_quotation_log($pdo, $quotationId ?: null, 'followup_done', 'Follow-up #' . $fuId . ' marked done');
        flash_set('success', 'Follow-up marked as done.');
        redirect('/modules/quotations/followup.php' . ($quotationId ? '?quotation_id=' . $quotationId : ''));
    }

    $qid = (int) ($_POST['quotation_id'] ?? 0);
    $reminderDate = trim((string) ($_POST['reminder_date'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $expectedClosing = trim((string) ($_POST['expected_closing_date'] ?? '')) ?: null;
    $assignedTo = (int) ($_POST['assigned_to'] ?? 0) ?: null;

    if ($qid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reminderDate)) {
        flash_set('error', 'Quotation and reminder date are required.');
        redirect('/modules/quotations/followup.php');
    }

    $pdo->prepare(
        'INSERT INTO quotation_followups (quotation_id, reminder_date, followup_notes, expected_closing_date, assigned_to, created_by)
         VALUES (?,?,?,?,?,?)'
    )->execute([
        $qid,
        $reminderDate,
        $notes !== '' ? $notes : null,
        $expectedClosing,
        $assignedTo,
        isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
    ]);

    if ($expectedClosing) {
        $pdo->prepare('UPDATE quotations SET expected_closing_date = ? WHERE id = ?')->execute([$expectedClosing, $qid]);
    }

    vk_quotation_log($pdo, $qid, 'followup_added', 'Reminder on ' . $reminderDate);
    flash_set('success', 'Follow-up scheduled.');
    redirect('/modules/quotations/followup.php?quotation_id=' . $qid);
}

$where = ['1=1'];
$params = [];
if ($quotationId > 0) {
    $where[] = 'f.quotation_id = ?';
    $params[] = $quotationId;
}
if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'done', 'missed', 'cancelled'], true)) {
    $where[] = 'f.reminder_status = ?';
    $params[] = $statusFilter;
}

$whereSql = implode(' AND ', $where);
$sql = "SELECT f.*, q.quotation_number, c.name AS customer_name, u.fullname AS assigned_name
        FROM quotation_followups f
        JOIN quotations q ON q.id = f.quotation_id
        JOIN customers c ON c.id = q.customer_id
        LEFT JOIN users u ON u.id = f.assigned_to
        WHERE $whereSql
        ORDER BY f.reminder_date ASC, f.id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$quotations = $pdo->query(
    "SELECT q.id, q.quotation_number, c.name AS customer_name
     FROM quotations q JOIN customers c ON c.id = q.customer_id
     WHERE q.status NOT IN ('converted_invoice','cancelled','rejected')
     ORDER BY q.id DESC LIMIT 200"
)->fetchAll();
$users = $pdo->query("SELECT id, fullname FROM users WHERE role NOT IN ('technician') ORDER BY fullname")->fetchAll();

$pageTitle = 'Follow-ups';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <p class="qtn-eyebrow mb-1">Quotations</p>
            <h1 class="h3 mb-0">Follow-ups</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/dashboard.php">Dashboard</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <form class="card vk-card" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="add">
                <div class="card-header bg-transparent fw-semibold">Schedule follow-up</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Quotation</label>
                        <select name="quotation_id" class="form-select" required>
                            <option value="">Select…</option>
                            <?php foreach ($quotations as $q): ?>
                                <option value="<?= (int) $q['id'] ?>" <?= $quotationId === (int) $q['id'] ? 'selected' : '' ?>><?= e($q['quotation_number']) ?> — <?= e($q['customer_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reminder date</label>
                        <input type="date" name="reminder_date" class="form-control" required value="<?= e(date('Y-m-d')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expected closing date</label>
                        <input type="date" name="expected_closing_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assigned to</label>
                        <select name="assigned_to" class="form-select">
                            <option value="0">Unassigned</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"><?= e((string) $u['fullname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Add follow-up</button>
                </div>
            </form>
        </div>
        <div class="col-lg-7">
            <form class="card vk-card mb-3" method="get">
                <div class="card-body d-flex flex-wrap gap-2 align-items-end">
                    <div>
                        <label class="form-label small mb-0">Filter status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All</option>
                            <?php foreach (['pending', 'done', 'missed', 'cancelled'] as $s): ?>
                                <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($quotationId): ?>
                        <input type="hidden" name="quotation_id" value="<?= $quotationId ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button>
                </div>
            </form>
            <div class="card vk-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Date</th><th>Quotation</th><th>Customer</th><th>Notes</th><th>Assigned</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No follow-ups found.</td></tr>
                        <?php else: foreach ($rows as $f): ?>
                            <tr>
                                <td><?= e($f['reminder_date']) ?></td>
                                <td><a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= (int) $f['quotation_id'] ?>"><?= e($f['quotation_number']) ?></a></td>
                                <td><?= e($f['customer_name']) ?></td>
                                <td class="small"><?= e((string) ($f['followup_notes'] ?: '—')) ?></td>
                                <td class="small"><?= e((string) ($f['assigned_name'] ?: '—')) ?></td>
                                <td><span class="badge text-bg-light border"><?= e($f['reminder_status']) ?></span></td>
                                <td>
                                    <?php if ($f['reminder_status'] === 'pending'): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="done">
                                        <input type="hidden" name="followup_id" value="<?= (int) $f['id'] ?>">
                                        <input type="hidden" name="quotation_id" value="<?= (int) $f['quotation_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success">Done</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
