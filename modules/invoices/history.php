<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/invoices_service.php';
vk_ensure_finance_schemas($pdo);
vk_invoice_require_perm('view');

$id = (int) ($_GET['id'] ?? 0);
$inv = $id > 0 ? vk_invoice_get($pdo, $id) : null;
if (!$inv) {
    flash_set('error', 'Invoice not found.');
    redirect('/modules/invoices/list.php');
}

$pageTitle = 'Invoice history';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$rows = [];
try {
    $st = $pdo->prepare(
        "SELECT h.*, COALESCE(NULLIF(u.fullname, ''), u.username) AS editor_name
         FROM invoice_history h
         LEFT JOIN users u ON u.id = h.edited_by
         WHERE h.invoice_id = ?
         ORDER BY h.id DESC
         LIMIT 500"
    );
    $st->execute([$id]);
    $rows = $st->fetchAll() ?: [];
} catch (Throwable $e) {
    $rows = [];
}
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= $id ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Audit history</h1>
        <p class="text-muted mb-0"><?= e($inv['invoice_number']) ?> · <?= e($inv['customer_name']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/invoices/revisions.php?id=<?= $id ?>"><i class="bi bi-layers me-1"></i>Revisions</a>
</div>

<div class="card vk-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Rev</th>
                    <th>Field</th>
                    <th>Old value</th>
                    <th>New value</th>
                    <th>Edited by</th>
                    <th>Date</th>
                    <th>IP</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No history yet.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int) $r['id'] ?></td>
                        <td><?= (int) $r['revision_no'] ?></td>
                        <td><code><?= e($r['field_name']) ?></code></td>
                        <td class="text-break" style="max-width:180px"><?= e(mb_strimwidth((string) ($r['old_value'] ?? ''), 0, 120, '…')) ?></td>
                        <td class="text-break" style="max-width:180px"><?= e(mb_strimwidth((string) ($r['new_value'] ?? ''), 0, 120, '…')) ?></td>
                        <td><?= e((string) ($r['editor_name'] ?? '—')) ?></td>
                        <td class="text-nowrap"><?= e((string) $r['edited_at']) ?></td>
                        <td><?= e((string) ($r['ip_address'] ?? '')) ?></td>
                        <td><?= e((string) ($r['reason'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
