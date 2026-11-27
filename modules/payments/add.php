<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/invoices_service.php';
require_once dirname(__DIR__, 2) . '/includes/invoice_payment_service.php';
vk_ensure_finance_schemas($pdo);
$perms = vk_invoice_permissions();
if (empty($perms['collect_payment'])) { http_response_code(403); exit('You do not have permission to collect invoice payments.'); }
$invoiceId = (int) ($_GET['invoice_id'] ?? $_POST['invoice_id'] ?? 0);
$st = $pdo->prepare('SELECT i.*, c.name AS customer_name FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.id=?');
$st->execute([$invoiceId]);
$inv = $st->fetch(PDO::FETCH_ASSOC);
if (!$inv) { flash_set('error', 'Invoice not found.'); redirect('/modules/invoices/list.php'); }
$summary = vk_invoice_payment_snapshot($inv);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) { flash_set('error', 'Security token expired. Please refresh and try again.'); redirect('/modules/payments/add.php?invoice_id=' . $invoiceId); }
    $_POST['payment_date'] = $_POST['payment_date'] ?? date('Y-m-d');
    $_POST['request_token'] = $_POST['request_token'] ?? bin2hex(random_bytes(24));
    try {
        $result = vk_invoice_collect_payment($pdo, $_POST, (int) ($currentUser['id'] ?? 0));
        flash_set('success', 'Payment recorded. Receipt ' . $result['payment']['reference'] . '.');
        redirect('/modules/invoices/view.php?id=' . $invoiceId);
    } catch (Throwable $e) { flash_set('error', APP_DEBUG ? $e->getMessage() : $e->getMessage()); }
}
$pageTitle = 'Record payment';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="mb-3"><a href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= $invoiceId ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to invoice</a></div>
<h1 class="h3 mb-3">Record payment</h1>
<div class="row g-3"><div class="col-12 col-lg-5"><div class="card vk-card"><div class="card-body"><div class="fw-semibold"><?= e($inv['invoice_number']) ?><?= !empty($summary['is_draft']) ? ' · Draft' : '' ?></div><div class="text-muted small"><?= e($inv['customer_name']) ?></div><hr><dl class="row small mb-0"><dt class="col-6">Grand total</dt><dd class="col-6 text-end"><?= e(formatCurrency($inv['grand_total'])) ?></dd><dt class="col-6">Paid</dt><dd class="col-6 text-end"><?= e(formatCurrency($inv['paid_amount'])) ?></dd><dt class="col-6 text-danger">Outstanding</dt><dd class="col-6 text-end text-danger fw-bold"><?= e(formatCurrency($summary['balance'])) ?></dd></dl></div></div></div>
<div class="col-12 col-lg-7"><div class="card vk-card"><div class="card-body">
<?php if ($summary['balance'] <= 0): ?><p class="text-success mb-0">This invoice is fully paid.</p><?php else: ?>
<form method="post" data-loading><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="invoice_id" value="<?= $invoiceId ?>"><input type="hidden" name="request_token" value="<?= e(bin2hex(random_bytes(24))) ?>">
<div class="mb-3"><label class="form-label" for="amount">Amount (LKR)</label><input type="number" step="0.01" min="0.01" max="<?= e(number_format($summary['balance'],2,'.','')) ?>" class="form-control" name="amount" id="amount" required value="<?= e(number_format($summary['balance'],2,'.','')) ?>"></div>
<div class="mb-3"><label class="form-label" for="method">Method</label><select class="form-select" name="method" id="method" required><option value="cash">Cash</option><option value="card">Card</option><option value="bank">Bank transfer</option><option value="online">Online payment</option></select></div>
<div class="mb-3"><label class="form-label" for="payment_date">Payment date</label><input class="form-control" type="date" name="payment_date" id="payment_date" value="<?= date('Y-m-d') ?>" required></div>
<div class="mb-3"><label class="form-label" for="reference_number">Transaction reference (optional)</label><input class="form-control" name="reference_number" id="reference_number" maxlength="128"></div>
<div class="mb-3"><label class="form-label" for="note">Note</label><input type="text" class="form-control" name="note" id="note" maxlength="255"></div>
<button type="submit" class="btn btn-primary">Save payment</button></form><?php endif; ?>
</div></div></div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
