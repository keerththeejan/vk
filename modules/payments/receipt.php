<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/invoices_service.php';
require_once dirname(__DIR__, 2) . '/includes/invoice_payment_service.php';
vk_ensure_finance_schemas($pdo);
$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT p.*, i.invoice_number, i.grand_total, i.paid_amount, c.name AS customer_name, c.phone, c.address FROM payments p JOIN invoices i ON i.id=p.invoice_id JOIN customers c ON c.id=i.customer_id WHERE p.id=?');
$st->execute([$id]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); exit('Payment receipt not found.'); }
$payment = vk_invoice_payment_details($pdo, $row);
$pageTitle = 'Payment receipt ' . $payment['reference'];
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<style>@media print{.no-print,header,nav,.sidebar{display:none!important}body{background:#fff!important;color:#111!important}.card{box-shadow:none!important;border:1px solid #bbb!important}}</style>
<div class="d-flex justify-content-between align-items-center mb-3 no-print"><a href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= (int) $row['invoice_id'] ?>" class="btn btn-outline-secondary">Back to invoice</a><button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print receipt</button></div>
<div class="card vk-card mx-auto" style="max-width:760px"><div class="card-body p-4 p-md-5">
<div class="d-flex justify-content-between border-bottom pb-3 mb-4"><div><h1 class="h3 mb-1">Payment receipt</h1><div class="text-muted">VK Network ERP</div></div><div class="text-end"><div class="fw-bold fs-5"><?= e($payment['reference']) ?></div><div class="text-muted small"><?= e($payment['payment_date']) ?></div></div></div>
<dl class="row"><dt class="col-5">Invoice</dt><dd class="col-7"><?= e($row['invoice_number']) ?></dd><dt class="col-5">Customer</dt><dd class="col-7"><?= e($row['customer_name']) ?></dd><?php if (!empty($row['phone'])): ?><dt class="col-5">Phone</dt><dd class="col-7"><?= e($row['phone']) ?></dd><?php endif; ?><dt class="col-5">Payment method</dt><dd class="col-7"><?= e(ucfirst($payment['method'])) ?></dd><?php if ($payment['transaction_reference']): ?><dt class="col-5">Transaction reference</dt><dd class="col-7"><?= e($payment['transaction_reference']) ?></dd><?php endif; ?><dt class="col-5">Received by</dt><dd class="col-7"><?= e($payment['received_by']) ?></dd><?php if ($payment['note']): ?><dt class="col-5">Note</dt><dd class="col-7"><?= e($payment['note']) ?></dd><?php endif; ?></dl>
<div class="border-top pt-3 mt-4 d-flex justify-content-between fs-5"><strong>Amount received</strong><strong><?= e(formatCurrency($payment['amount'])) ?></strong></div><p class="text-muted small mt-4 mb-0">This receipt records payment against invoice <?= e($row['invoice_number']) ?>.</p>
</div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
