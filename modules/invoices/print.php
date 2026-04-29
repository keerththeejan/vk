<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
$pdo = db();
require_once dirname(__DIR__, 2) . '/includes/invoices_schema.php';
vk_ensure_invoice_items_table($pdo);

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT i.*, c.name AS customer_name, c.phone, c.email, c.address
     FROM invoices i
     JOIN customers c ON c.id = i.customer_id
     WHERE i.id = ?'
);
$st->execute([$id]);
$inv = $st->fetch();
if (!$inv) {
    http_response_code(404);
    echo 'Invoice not found.';
    exit;
}

$items = $pdo->prepare(
    'SELECT ii.*, p.name AS product_name
     FROM invoice_items ii
     LEFT JOIN products p ON p.id = ii.product_id
     WHERE ii.invoice_id = ?'
);
$items->execute([$id]);
$lines = $items->fetchAll();
$due = (float) $inv['grand_total'] - (float) $inv['paid_amount'];
$businessName = defined('VK_BUSINESS_NAME') ? VK_BUSINESS_NAME : 'VK IT Network';
$businessPhone = defined('VK_BUSINESS_PHONE_E164') ? ltrim(VK_BUSINESS_PHONE_E164, '+') : '0778870135';
$businessEmail = 'keerththeejan@gmail.com';
$businessWebsite = 'www.vkitnet.info';
$businessAddress = '26/3 Thiruvaiyaru, Kilinochchi, Sri Lanka';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice <?= e($inv['invoice_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Inter, system-ui, sans-serif; font-size: 11pt; }
        .vk-banner {
            background: linear-gradient(90deg, #0a2a5c, #134a9e);
            color: #fff;
            padding: 1rem 1.25rem;
            border-radius: 0.35rem;
        }
        .vk-logo {
            width: 44px; height: 44px; border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #134a9e, #0a2a5c);
            box-shadow: 0 0 0 3px #3b82c4;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 700;
        }
        .vk-red { color: #dc2626; font-weight: 700; font-size: 1.35rem; }
        @media print {
            .no-print { display: none !important; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body class="p-3 p-md-4">
<div class="container">
    <div class="vk-banner mb-4">
        <div class="row align-items-center g-3">
            <div class="col-md-6 d-flex align-items-center gap-3">
                <span class="vk-logo text-white">VK</span>
                <div>
                    <div class="vk-red"><?= e($businessName) ?></div>
                    <div class="text-white-50 text-uppercase" style="font-size:0.65rem;letter-spacing:.12em;">Software development solutions</div>
                </div>
            </div>
            <div class="col-md-6 small text-md-end">
                <div><?= e($businessPhone) ?></div>
                <div><?= e($businessEmail) ?></div>
                <div><?= e($businessWebsite) ?></div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h4 mb-1">TAX INVOICE</h1>
            <div class="text-muted"><?= e($inv['invoice_number']) ?></div>
            <div class="text-muted">Date: <?= e($inv['invoice_date']) ?></div>
        </div>
        <button type="button" class="btn btn-primary no-print" onclick="window.print()">Print</button>
    </div>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="fw-bold text-uppercase small text-muted mb-1">Bill to</div>
            <div class="fw-semibold"><?= e($inv['customer_name']) ?></div>
            <?php if ($inv['phone']): ?><div><?= e($inv['phone']) ?></div><?php endif; ?>
            <?php if ($inv['email']): ?><div><?= e($inv['email']) ?></div><?php endif; ?>
            <?php if ($inv['address']): ?><div class="mt-1"><?= nl2br(e($inv['address'])) ?></div><?php endif; ?>
        </div>
    </div>
    <table class="table table-bordered table-sm">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Description</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Unit</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php $i = 1; foreach ($lines as $ln): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= e(($ln['item_type'] ?? 'product') === 'service' ? (string) ($ln['line_description'] ?? '') : (string) ($ln['product_name'] ?? '')) ?></td>
                <td class="text-end"><?= (int) $ln['quantity'] ?></td>
                <td class="text-end"><?= e(number_format((float) $ln['unit_price'], 2)) ?></td>
                <td class="text-end"><?= e(number_format((float) $ln['line_total'], 2)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="row justify-content-end">
        <div class="col-md-5">
            <table class="table table-sm">
                <tr><td>Subtotal</td><td class="text-end"><?= e(number_format((float) $inv['subtotal'], 2)) ?></td></tr>
                <tr><td>Discount</td><td class="text-end"><?= e(number_format((float) $inv['discount'], 2)) ?></td></tr>
                <tr><td>Tax</td><td class="text-end"><?= e(number_format((float) $inv['tax'], 2)) ?></td></tr>
                <tr class="fw-bold"><td>Grand total</td><td class="text-end"><?= e(number_format((float) $inv['grand_total'], 2)) ?></td></tr>
                <tr><td>Paid</td><td class="text-end"><?= e(number_format((float) $inv['paid_amount'], 2)) ?></td></tr>
                <tr class="fw-bold"><td>Amount due</td><td class="text-end"><?= e(number_format($due, 2)) ?></td></tr>
            </table>
        </div>
    </div>
    <?php if ($inv['notes']): ?>
        <p class="small text-muted mt-3"><strong>Notes:</strong> <?= nl2br(e($inv['notes'])) ?></p>
    <?php endif; ?>
    <p class="small text-muted border-top pt-3 mt-4 mb-0">
        <strong>Address:</strong> 26/3 Thiruvaiyaru, Kilinochchi, Sri Lanka — VK IT Network
    </p>
</div>
</body>
</html>
