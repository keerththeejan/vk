<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/invoices_service.php';
vk_ensure_finance_schemas($pdo);
vk_invoice_require_perm('view');

$id = (int) ($_GET['id'] ?? 0);
$inv = vk_invoice_get($pdo, $id);
if (!$inv) {
    flash_set('error', 'Invoice not found.');
    redirect('/modules/invoices/list.php');
}

$perms = vk_invoice_permissions();
$pageTitle = 'Invoice';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$lines = vk_invoice_items($pdo, $id);

$paySt = $pdo->prepare(
    'SELECT * FROM payments WHERE invoice_id = ? ORDER BY paid_at DESC'
);
$paySt->execute([$id]);
$payments = $paySt->fetchAll();

$due = (float) $inv['grand_total'] - (float) $inv['paid_amount'];
$itemDisc = (float) ($inv['item_discount_total'] ?? 0);
$invDisc = (float) ($inv['invoice_discount_amount'] ?? $inv['discount'] ?? 0);
$shipping = (float) ($inv['shipping_amount'] ?? 0);
$adjustment = (float) ($inv['adjustment_amount'] ?? 0);
$roundOff = (float) ($inv['round_off'] ?? 0);
$isCancelled = ($inv['status'] ?? '') === 'cancelled';
$isDraft = !empty($inv['is_draft']) || ($inv['status'] ?? '') === 'draft';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <a href="<?= e(BASE_URL) ?>/modules/invoices/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <h1 class="h3 mt-2 mb-0"><?= e($inv['invoice_number']) ?>
            <?php if (!empty($inv['revision_no'])): ?>
                <span class="badge text-bg-secondary fs-6 align-middle">Rev <?= (int) $inv['revision_no'] ?></span>
            <?php endif; ?>
        </h1>
        <p class="text-muted mb-0"><?= e($inv['invoice_date']) ?> · <?= e($inv['customer_name']) ?></p>
        <?php
        if (!empty($inv['repair_job_id'])) {
            $rj = $pdo->prepare('SELECT job_number FROM repair_jobs WHERE id = ?');
            $rj->execute([(int) $inv['repair_job_id']]);
            $jn = $rj->fetchColumn();
            if ($jn) {
                echo '<p class="small mb-0"><span class="badge text-bg-secondary">Repair</span> <a href="' . e(BASE_URL) . '/modules/repairs/view.php?id=' . (int) $inv['repair_job_id'] . '">' . e((string) $jn) . '</a></p>';
            }
        } elseif (!empty($inv['cctv_job_id'])) {
            $cj = $pdo->prepare('SELECT job_number FROM cctv_installations WHERE id = ?');
            $cj->execute([(int) $inv['cctv_job_id']]);
            $jn = $cj->fetchColumn();
            if ($jn) {
                echo '<p class="small mb-0"><span class="badge text-bg-info">CCTV</span> <a href="' . e(BASE_URL) . '/modules/cctv/view.php?id=' . (int) $inv['cctv_job_id'] . '">' . e((string) $jn) . '</a></p>';
            }
        }
        ?>
    </div>
    <div class="d-flex flex-wrap gap-2 no-print">
        <?php if (!$isCancelled && (!empty($perms['edit']) || ($isDraft && !empty($perms['edit_draft'])))): ?>
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/invoices/edit.php?id=<?= $id ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" target="_blank" href="<?= e(BASE_URL) ?>/modules/invoices/print.php?id=<?= $id ?>"><i class="bi bi-printer me-1"></i>Print</a>
        <a class="btn btn-outline-secondary" target="_blank" href="<?= e(BASE_URL) ?>/modules/invoices/print.php?id=<?= $id ?>&download=1"><i class="bi bi-file-pdf me-1"></i>PDF</a>
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/invoices/history.php?id=<?= $id ?>"><i class="bi bi-clock-history me-1"></i>History</a>
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/invoices/revisions.php?id=<?= $id ?>"><i class="bi bi-layers me-1"></i>Revisions</a>
        <?php if (!empty($perms['create'])): ?>
            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/invoices/duplicate.php?id=<?= $id ?>" onclick="return confirm('Duplicate as draft?');"><i class="bi bi-copy me-1"></i>Duplicate</a>
        <?php endif; ?>
        <?php if ($due > 0.0001 && !$isCancelled && !$isDraft): ?>
            <a class="btn btn-success" href="<?= e(BASE_URL) ?>/modules/payments/add.php?invoice_id=<?= $id ?>"><i class="bi bi-cash-coin me-1"></i>Record payment</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card vk-card">
            <div class="card-header bg-transparent fw-semibold">Line items</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Discount</th>
                            <th class="text-end">Tax</th>
                            <th class="text-end">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $ln): ?>
                        <?php
                        $desc = ($ln['item_type'] ?? 'product') === 'service'
                            ? (string) ($ln['line_description'] ?? '')
                            : (string) ($ln['line_description'] ?? $ln['product_name'] ?? '');
                        $typeBadge = ($ln['item_type'] ?? 'product') === 'service' ? '<span class="badge text-bg-light text-dark border me-1">Service</span>' : '';
                        $net = (float) ($ln['net_amount'] ?? $ln['line_total'] ?? 0);
                        ?>
                        <tr>
                            <td class="align-middle py-3 ps-3"><?= $typeBadge ?><?= e($desc) ?></td>
                            <td class="text-end align-middle"><?= (int) $ln['quantity'] ?></td>
                            <td class="text-end align-middle"><?= e(number_format((float) $ln['unit_price'], 2)) ?></td>
                            <td class="text-end align-middle"><?= e(number_format((float) ($ln['discount_amount'] ?? 0), 2)) ?></td>
                            <td class="text-end align-middle"><?= e(number_format((float) ($ln['tax_amount'] ?? 0), 2)) ?></td>
                            <td class="text-end align-middle fw-semibold"><?= e(number_format($net, 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent">
                <div class="row justify-content-end">
                    <div class="col-12 col-sm-7 col-lg-5">
                        <dl class="row small mb-0">
                            <dt class="col-6">Subtotal</dt>
                            <dd class="col-6 text-end"><?= e(number_format((float) $inv['subtotal'], 2)) ?></dd>
                            <dt class="col-6">Item discount</dt>
                            <dd class="col-6 text-end"><?= e(number_format($itemDisc, 2)) ?></dd>
                            <dt class="col-6">Invoice discount</dt>
                            <dd class="col-6 text-end"><?= e(number_format($invDisc, 2)) ?></dd>
                            <?php if (abs($shipping) > 0.0001): ?>
                                <dt class="col-6">Shipping</dt>
                                <dd class="col-6 text-end"><?= e(number_format($shipping, 2)) ?></dd>
                            <?php endif; ?>
                            <?php if (abs($adjustment) > 0.0001): ?>
                                <dt class="col-6">Adjustment</dt>
                                <dd class="col-6 text-end"><?= e(number_format($adjustment, 2)) ?></dd>
                            <?php endif; ?>
                            <dt class="col-6">Tax</dt>
                            <dd class="col-6 text-end"><?= e(number_format((float) $inv['tax'], 2)) ?></dd>
                            <?php if (abs($roundOff) > 0.0001): ?>
                                <dt class="col-6">Round off</dt>
                                <dd class="col-6 text-end"><?= e(number_format($roundOff, 2)) ?></dd>
                            <?php endif; ?>
                            <dt class="col-6 fw-bold">Grand total</dt>
                            <dd class="col-6 text-end fw-bold"><?= e(number_format((float) $inv['grand_total'], 2)) ?></dd>
                            <dt class="col-6">Paid</dt>
                            <dd class="col-6 text-end"><?= e(number_format((float) $inv['paid_amount'], 2)) ?></dd>
                            <dt class="col-6 text-danger">Balance</dt>
                            <dd class="col-6 text-end text-danger fw-semibold"><?= e(number_format($due, 2)) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($inv['notes']): ?>
            <div class="card vk-card mt-3">
                <div class="card-body">
                    <div class="fw-semibold small text-muted">Notes</div>
                    <div><?= nl2br(e($inv['notes'])) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card vk-card mb-3">
            <div class="card-header bg-transparent fw-semibold">Customer</div>
            <div class="card-body small">
                <div class="fw-semibold"><?= e($inv['customer_name']) ?></div>
                <?php if ($inv['phone']): ?><div><i class="bi bi-telephone me-1"></i><?= e($inv['phone']) ?></div><?php endif; ?>
                <?php if ($inv['email']): ?><div><i class="bi bi-envelope me-1"></i><?= e($inv['email']) ?></div><?php endif; ?>
                <?php if ($inv['address']): ?><div class="mt-2"><?= nl2br(e($inv['address'])) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="card vk-card">
            <div class="card-header bg-transparent fw-semibold">Payments</div>
            <div class="card-body p-0">
                <?php if (!$payments): ?>
                    <p class="text-muted small p-3 mb-0">No payments recorded.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($payments as $p): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold"><?= e(number_format((float) $p['amount'], 2)) ?> · <?= e($p['method']) ?></div>
                                    <div class="text-muted"><?= e($p['paid_at']) ?></div>
                                    <?php if ($p['note']): ?><div><?= e($p['note']) ?></div><?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
