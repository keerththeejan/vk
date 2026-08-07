<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/invoices_service.php';
vk_ensure_finance_schemas($pdo);
vk_invoice_require_perm('view');

$id = (int) ($_GET['id'] ?? 0);
$viewRev = isset($_GET['rev']) ? (int) $_GET['rev'] : null;
$inv = $id > 0 ? vk_invoice_get($pdo, $id) : null;
if (!$inv) {
    flash_set('error', 'Invoice not found.');
    redirect('/modules/invoices/list.php');
}

$pageTitle = 'Invoice revisions';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$revs = [];
try {
    $st = $pdo->prepare(
        "SELECT r.*, COALESCE(NULLIF(u.fullname, ''), u.username) AS creator_name
         FROM invoice_revisions r
         LEFT JOIN users u ON u.id = r.created_by
         WHERE r.invoice_id = ?
         ORDER BY r.revision_no DESC"
    );
    $st->execute([$id]);
    $revs = $st->fetchAll() ?: [];
} catch (Throwable $e) {
    $revs = [];
}

$snapshot = null;
if ($viewRev !== null) {
    foreach ($revs as $r) {
        if ((int) $r['revision_no'] === $viewRev) {
            $snapshot = json_decode((string) $r['snapshot_json'], true);
            break;
        }
    }
}
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= $id ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Revisions</h1>
        <p class="text-muted mb-0"><?= e($inv['invoice_number']) ?> · current rev <?= (int) ($inv['revision_no'] ?? 0) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/invoices/history.php?id=<?= $id ?>"><i class="bi bi-clock-history me-1"></i>Field history</a>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card vk-card">
            <div class="list-group list-group-flush">
                <?php if (!$revs): ?>
                    <div class="list-group-item text-muted">No revisions stored yet.</div>
                <?php else: ?>
                    <?php foreach ($revs as $r): ?>
                        <a class="list-group-item list-group-item-action <?= $viewRev === (int) $r['revision_no'] ? 'active' : '' ?>"
                           href="?id=<?= $id ?>&rev=<?= (int) $r['revision_no'] ?>">
                            <div class="fw-semibold">Revision <?= (int) $r['revision_no'] ?></div>
                            <div class="small <?= $viewRev === (int) $r['revision_no'] ? '' : 'text-muted' ?>">
                                <?= e((string) $r['created_at']) ?>
                                · <?= e((string) ($r['creator_name'] ?? '—')) ?>
                            </div>
                            <?php if (!empty($r['change_summary'])): ?>
                                <div class="small mt-1"><?= e($r['change_summary']) ?></div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card vk-card">
            <div class="card-header bg-transparent fw-semibold">
                <?= $viewRev !== null ? 'Revision ' . (int) $viewRev . ' snapshot' : 'Select a revision' ?>
            </div>
            <div class="card-body">
                <?php if ($snapshot && is_array($snapshot)): ?>
                    <?php
                    $h = $snapshot['header'] ?? [];
                    $items = $snapshot['items'] ?? [];
                    ?>
                    <dl class="row small mb-3">
                        <dt class="col-sm-3">Customer</dt><dd class="col-sm-9"><?= e((string) ($h['customer_name'] ?? $h['customer_id'] ?? '')) ?></dd>
                        <dt class="col-sm-3">Date</dt><dd class="col-sm-9"><?= e((string) ($h['invoice_date'] ?? '')) ?></dd>
                        <dt class="col-sm-3">Subtotal</dt><dd class="col-sm-9"><?= e(number_format((float) ($h['subtotal'] ?? 0), 2)) ?></dd>
                        <dt class="col-sm-3">Discount</dt><dd class="col-sm-9"><?= e(number_format((float) ($h['discount'] ?? $h['invoice_discount_amount'] ?? 0), 2)) ?></dd>
                        <dt class="col-sm-3">Tax</dt><dd class="col-sm-9"><?= e(number_format((float) ($h['tax'] ?? 0), 2)) ?></dd>
                        <dt class="col-sm-3">Shipping</dt><dd class="col-sm-9"><?= e(number_format((float) ($h['shipping_amount'] ?? 0), 2)) ?></dd>
                        <dt class="col-sm-3">Grand total</dt><dd class="col-sm-9 fw-semibold"><?= e(number_format((float) ($h['grand_total'] ?? 0), 2)) ?></dd>
                    </dl>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Disc</th>
                                    <th class="text-end">Tax</th>
                                    <th class="text-end">Net</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $ln): ?>
                                <tr>
                                    <td><?= e((string) ($ln['line_description'] ?? $ln['product_name'] ?? '—')) ?></td>
                                    <td class="text-end"><?= e((string) ($ln['quantity'] ?? '')) ?></td>
                                    <td class="text-end"><?= e(number_format((float) ($ln['unit_price'] ?? 0), 2)) ?></td>
                                    <td class="text-end"><?= e(number_format((float) ($ln['discount_amount'] ?? 0), 2)) ?></td>
                                    <td class="text-end"><?= e(number_format((float) ($ln['tax_amount'] ?? 0), 2)) ?></td>
                                    <td class="text-end"><?= e(number_format((float) ($ln['net_amount'] ?? $ln['line_total'] ?? 0), 2)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Choose a revision on the left to view its snapshot.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
