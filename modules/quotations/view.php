<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);

$id = (int) ($_GET['id'] ?? 0);
$q = vk_quotation_get($pdo, $id);
if (!$q) {
    flash_set('error', 'Quotation not found.');
    redirect('/modules/quotations/list.php');
}
$items = vk_quotation_items($pdo, $id);
$perms = vk_quotation_permissions();

$approvals = $pdo->prepare(
    'SELECT a.*, u.fullname AS approver_name FROM quotation_approvals a
     LEFT JOIN users u ON u.id = a.approver_id
     WHERE a.quotation_id = ? ORDER BY a.level ASC'
);
$approvals->execute([$id]);
$approvalRows = $approvals->fetchAll();

$followups = $pdo->prepare('SELECT * FROM quotation_followups WHERE quotation_id = ? ORDER BY reminder_date DESC');
$followups->execute([$id]);
$fuRows = $followups->fetchAll();

$logs = $pdo->prepare(
    'SELECT l.*, u.fullname AS user_name FROM quotation_activity_logs l
     LEFT JOIN users u ON u.id = l.user_id
     WHERE l.quotation_id = ? ORDER BY l.id DESC LIMIT 30'
);
$logs->execute([$id]);
$logRows = $logs->fetchAll();

$prevQuotes = $pdo->prepare(
    'SELECT id, quotation_number, quotation_date, grand_total, status
     FROM quotations WHERE customer_id = ? AND id <> ? ORDER BY id DESC LIMIT 8'
);
$prevQuotes->execute([(int) $q['customer_id'], $id]);
$prevRows = $prevQuotes->fetchAll();

$balance = 0.0;
$creditLimit = 0.0;
try {
    $bst = $pdo->prepare('SELECT current_balance FROM accounts WHERE customer_id = ? LIMIT 1');
    $bst->execute([(int) $q['customer_id']]);
    $balance = (float) ($bst->fetchColumn() ?: 0);
} catch (Throwable $e) {
}

$revisions = $pdo->prepare('SELECT id, revision_no, change_summary, created_at FROM quotation_revisions WHERE quotation_id = ? ORDER BY revision_no DESC');
$revisions->execute([$id]);
$revRows = $revisions->fetchAll();

$pageTitle = $q['quotation_number'];
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <a href="<?= e(BASE_URL) ?>/modules/quotations/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to list</a>
            <h1 class="h3 mt-2 mb-1"><?= e($q['quotation_number']) ?>
                <?php if ((int) $q['revision_no'] > 0): ?><span class="badge text-bg-light border">Rev <?= (int) $q['revision_no'] ?></span><?php endif; ?>
            </h1>
            <p class="text-muted mb-0"><?= e($q['customer_name']) ?> · <?= e($q['quotation_date']) ?> ·
                <span class="badge text-bg-<?= e(vk_quotation_status_badge($q['status'])) ?>"><?= e(vk_quotation_status_label($q['status'])) ?></span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($perms['edit'] && in_array($q['status'], ['draft','rejected','pending_approval'], true)): ?>
            <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/quotations/edit.php?id=<?= $id ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
            <?php endif; ?>
            <?php if ($perms['approve'] && $q['status'] === 'pending_approval'): ?>
            <a class="btn btn-warning" href="<?= e(BASE_URL) ?>/modules/quotations/approve.php?id=<?= $id ?>"><i class="bi bi-shield-check me-1"></i>Approve</a>
            <?php endif; ?>
            <?php if ($perms['convert'] && in_array($q['status'], ['approved','accepted'], true)): ?>
            <a class="btn btn-success" href="<?= e(BASE_URL) ?>/modules/quotations/convert.php?id=<?= $id ?>&to=invoice"><i class="bi bi-receipt me-1"></i>To Invoice</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" target="_blank" href="<?= e(BASE_URL) ?>/modules/quotations/print.php?id=<?= $id ?>"><i class="bi bi-printer me-1"></i>Print / PDF</a>
            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/email.php?id=<?= $id ?>"><i class="bi bi-envelope me-1"></i>Email</a>
            <a class="btn btn-outline-success" target="_blank" href="<?= e(vk_quotation_whatsapp_url($pdo, $q)) ?>"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/duplicate.php?id=<?= $id ?>"><i class="bi bi-copy me-1"></i>Duplicate</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent fw-semibold">Line items</div>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th><th>Code</th><th>Product</th><th>Unit</th>
                                <th class="text-end">Qty</th><th class="text-end">Price</th>
                                <th class="text-end">Disc</th><th class="text-end">Tax</th><th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $i => $ln): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e((string) ($ln['product_code'] ?: '—')) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e($ln['product_name']) ?></div>
                                    <?php if ($ln['description']): ?><div class="small text-muted"><?= e($ln['description']) ?></div><?php endif; ?>
                                </td>
                                <td><?= e($ln['unit']) ?></td>
                                <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $ln['quantity'], 3), '0'), '.')) ?></td>
                                <td class="text-end"><?= e(number_format((float) $ln['unit_price'], 2)) ?></td>
                                <td class="text-end"><?= e(number_format((float) $ln['discount_amount'], 2)) ?></td>
                                <td class="text-end"><?= e(number_format((float) $ln['tax_amount'], 2)) ?></td>
                                <td class="text-end fw-semibold"><?= e(number_format((float) $ln['line_total'], 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($approvalRows): ?>
            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent fw-semibold">Approval workflow</div>
                <div class="card-body">
                    <div class="qtn-approval-track">
                        <?php foreach ($approvalRows as $a): ?>
                            <div class="qtn-approval-step qtn-approval-step--<?= e($a['action']) ?>">
                                <div class="fw-semibold"><?= e(ucwords(str_replace('_', ' ', $a['role_label']))) ?></div>
                                <div class="small"><?= e(ucfirst($a['action'])) ?><?= $a['approver_name'] ? ' · ' . e($a['approver_name']) : '' ?></div>
                                <?php if ($a['notes']): ?><div class="small text-muted"><?= e($a['notes']) ?></div><?php endif; ?>
                                <?php if ($a['acted_at']): ?><div class="small text-muted"><?= e($a['acted_at']) ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent d-flex justify-content-between">
                    <strong>Follow-ups</strong>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/quotations/followup.php?quotation_id=<?= $id ?>">Manage</a>
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (!$fuRows): ?>
                        <li class="list-group-item text-muted small">No follow-ups scheduled.</li>
                    <?php else: foreach ($fuRows as $f): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><?= e($f['reminder_date']) ?> · <?= e($f['followup_notes'] ?: 'Reminder') ?></span>
                            <span class="badge text-bg-light border"><?= e($f['reminder_status']) ?></span>
                        </li>
                    <?php endforeach; endif; ?>
                </ul>
            </div>

            <?php if ($q['notes'] || $q['terms_html']): ?>
            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent fw-semibold">Notes &amp; terms</div>
                <div class="card-body">
                    <?php if ($q['notes']): ?><p><?= nl2br(e($q['notes'])) ?></p><?php endif; ?>
                    <?php if ($q['terms_html']): ?><pre class="small mb-0 bg-body-secondary p-3 rounded"><?= e($q['terms_html']) ?></pre><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card vk-card mb-3 qtn-summary-card">
                <div class="card-header bg-transparent fw-semibold">Totals</div>
                <div class="card-body">
                    <dl class="qtn-totals mb-0">
                        <div><dt>Subtotal</dt><dd><?= e(number_format((float) $q['subtotal'], 2)) ?></dd></div>
                        <div><dt>Item discount</dt><dd><?= e(number_format((float) $q['item_discount_total'], 2)) ?></dd></div>
                        <div><dt>Overall discount</dt><dd><?= e(number_format((float) $q['overall_discount_amount'], 2)) ?></dd></div>
                        <div><dt>Tax</dt><dd><?= e(number_format((float) $q['tax_total'], 2)) ?></dd></div>
                        <div><dt>Shipping</dt><dd><?= e(number_format((float) $q['shipping_amount'], 2)) ?></dd></div>
                        <div><dt>Additional</dt><dd><?= e(number_format((float) $q['additional_charges'], 2)) ?></dd></div>
                        <div><dt>Round off</dt><dd><?= e(number_format((float) $q['round_off'], 2)) ?></dd></div>
                        <div class="qtn-grand"><dt>Grand total</dt><dd><?= e($q['currency']) ?> <?= e(number_format((float) $q['grand_total'], 2)) ?></dd></div>
                        <div><dt>Est. cost</dt><dd><?= e(number_format((float) $q['estimated_cost'], 2)) ?></dd></div>
                        <div><dt>Net profit</dt><dd><?= e(number_format((float) $q['net_profit'], 2)) ?></dd></div>
                        <div><dt>Margin</dt><dd><?= e(number_format((float) $q['profit_margin_pct'], 2)) ?>%</dd></div>
                    </dl>
                </div>
            </div>

            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent fw-semibold">Customer intelligence</div>
                <div class="card-body small">
                    <div class="mb-2"><strong><?= e($q['customer_name']) ?></strong></div>
                    <div>Phone: <?= e((string) ($q['phone'] ?: $q['customer_phone_db'] ?: '—')) ?></div>
                    <div>Email: <?= e((string) ($q['email'] ?: $q['customer_email_db'] ?: '—')) ?></div>
                    <div class="mt-2">Outstanding balance: <strong><?= e(number_format($balance, 2)) ?></strong></div>
                    <div>Credit limit: <strong><?= e(number_format($creditLimit, 2)) ?></strong> <span class="text-muted">(configure in settings)</span></div>
                    <hr>
                    <div class="fw-semibold mb-1">Previous quotations</div>
                    <?php if (!$prevRows): ?>
                        <div class="text-muted">None</div>
                    <?php else: foreach ($prevRows as $p): ?>
                        <div class="d-flex justify-content-between">
                            <a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= (int) $p['id'] ?>"><?= e($p['quotation_number']) ?></a>
                            <span><?= e(number_format((float) $p['grand_total'], 0)) ?></span>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <?php if ($revRows): ?>
            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent d-flex justify-content-between">
                    <strong>Revisions</strong>
                    <a class="small" href="<?= e(BASE_URL) ?>/modules/quotations/revisions.php?id=<?= $id ?>">Compare</a>
                </div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($revRows as $r): ?>
                        <li class="list-group-item">R<?= (int) $r['revision_no'] ?> · <?= e((string) ($r['change_summary'] ?: 'Snapshot')) ?>
                            <div class="text-muted"><?= e($r['created_at']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent fw-semibold">Activity</div>
                <ul class="list-group list-group-flush qtn-activity">
                    <?php foreach ($logRows as $a): ?>
                        <li class="list-group-item small">
                            <strong><?= e(str_replace('_', ' ', $a['action'])) ?></strong>
                            <div class="text-muted"><?= e((string) ($a['user_name'] ?: 'System')) ?> · <?= e($a['created_at']) ?></div>
                            <?php if ($a['details']): ?><div><?= e($a['details']) ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="card vk-card">
                <div class="card-body small">
                    <div>Payment: <?= e((string) ($q['payment_terms'] ?: '—')) ?></div>
                    <div>Delivery: <?= e((string) ($q['delivery_terms'] ?: '—')) ?></div>
                    <div>Expiry: <?= e((string) ($q['expiry_date'] ?: '—')) ?></div>
                    <div>Executive: <?= e((string) ($q['sales_executive_name'] ?: '—')) ?></div>
                    <div>Created by: <?= e((string) ($q['created_by_name'] ?: '—')) ?></div>
                    <?php if (!empty($q['converted_invoice_id'])): ?>
                    <div class="mt-2"><a href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= (int) $q['converted_invoice_id'] ?>">View converted invoice #<?= (int) $q['converted_invoice_id'] ?></a></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
