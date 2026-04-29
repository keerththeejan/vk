<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
$pdo = db();
require_once dirname(__DIR__, 2) . '/includes/invoices_schema.php';
require_once dirname(__DIR__, 2) . '/includes/accounting_schema.php';
vk_ensure_invoice_items_table($pdo);
vk_ensure_account_ledger_table($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/cctv/list.php');
}

$id = (int) ($_POST['cctv_job_id'] ?? 0);
$st = $pdo->prepare(
    'SELECT v.*, c.name AS customer_name FROM cctv_installations v JOIN customers c ON c.id = v.customer_id WHERE v.id = ?'
);
$st->execute([$id]);
$job = $st->fetch();
if (!$job || !empty($job['invoice_id'])) {
    flash_set('error', 'Cannot generate invoice for this job.');
    redirect($id > 0 ? '/modules/cctv/view.php?id=' . $id : '/modules/cctv/list.php');
}

$customerId = (int) $job['customer_id'];
$charge = round((float) $job['installation_charge'], 2);
if ($charge <= 0) {
    flash_set('error', 'Set a positive installation charge before generating an invoice.');
    redirect('/modules/cctv/view.php?id=' . $id);
}

$accSt = $pdo->prepare('SELECT id FROM accounts WHERE customer_id = ? LIMIT 1');
$accSt->execute([$customerId]);
$customerAccountId = $accSt->fetchColumn();
if (!$customerAccountId) {
    flash_set('error', 'Customer account missing.');
    redirect('/modules/cctv/view.php?id=' . $id);
}
$customerAccountId = (int) $customerAccountId;

$desc = 'CCTV installation — ' . $job['job_number'];
$desc .= '. Location: ' . preg_replace('/\s+/', ' ', trim((string) $job['location']));
$desc .= '. Cameras: ' . (int) $job['num_cameras'] . ', Cable: ' . number_format((float) $job['cable_length_m'], 2) . 'm';
if (!empty($job['equipment_used'])) {
    $eq = preg_replace('/\s+/', ' ', trim((string) $job['equipment_used']));
    if (strlen($eq) > 180) {
        $eq = substr($eq, 0, 177) . '…';
    }
    $desc .= '. Equipment: ' . $eq;
}
if (strlen($desc) > 500) {
    $desc = substr($desc, 0, 497) . '…';
}

try {
    $pdo->beginTransaction();
    $invNo = next_invoice_number($pdo);
    $today = date('Y-m-d');
    $subtotal = $charge;
    $grand = $charge;

    $pdo->prepare(
        'INSERT INTO invoices (invoice_number, customer_id, invoice_date, subtotal, discount, tax, grand_total, paid_amount, status, notes, source, repair_job_id, cctv_job_id)
         VALUES (?,?,?,?,0,0,? ,0,\'unpaid\',?,\'cctv\',NULL,?)'
    )->execute([
        $invNo,
        $customerId,
        $today,
        $subtotal,
        $grand,
        'Auto-generated from CCTV job ' . $job['job_number'],
        $id,
    ]);
    $invoiceId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO invoice_items (invoice_id, item_type, product_id, line_description, quantity, unit_price, line_total)
         VALUES (?,\'service\',NULL,?,1,?,?)'
    )->execute([$invoiceId, $desc, $charge, $grand]);

    $pdo->prepare('UPDATE cctv_installations SET invoice_id = ? WHERE id = ?')->execute([$invoiceId, $id]);

    ledger_apply(
        $pdo,
        $customerAccountId,
        $grand,
        0,
        'Invoice ' . $invNo . ' — CCTV installation',
        $invoiceId,
        null,
        null
    );

    $pdo->commit();
    flash_set('success', 'Invoice ' . $invNo . ' created from CCTV job.');
    redirect('/modules/invoices/view.php?id=' . $invoiceId);
} catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not create invoice.');
    redirect('/modules/cctv/view.php?id=' . $id);
}
