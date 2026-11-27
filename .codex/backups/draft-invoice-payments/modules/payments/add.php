<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
vk_ensure_finance_schemas($pdo);

$invoiceId = (int) ($_GET['invoice_id'] ?? $_POST['invoice_id'] ?? 0);
$st = $pdo->prepare(
    'SELECT i.*, c.name AS customer_name, a.id AS customer_account_id
     FROM invoices i
     JOIN customers c ON c.id = i.customer_id
     JOIN accounts a ON a.customer_id = c.id
     WHERE i.id = ?'
);
$st->execute([$invoiceId]);
$inv = $st->fetch();
if (!$inv) {
    flash_set('error', 'Invoice not found.');
    redirect('/modules/invoices/list.php');
}

$due = (float) $inv['grand_total'] - (float) $inv['paid_amount'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float) ($_POST['amount'] ?? 0);
    $method = (string) ($_POST['method'] ?? 'cash');
    $note = trim((string) ($_POST['note'] ?? ''));
    if (!in_array($method, ['cash', 'card', 'bank', 'online'], true)) {
        $method = 'cash';
    }
    if ($amount <= 0) {
        flash_set('error', 'Enter a positive amount.');
    } elseif ($amount - $due > 0.01) {
        flash_set('error', 'Amount cannot exceed amount due (' . formatCurrency($due) . ').');
    } else {
        try {
            $pdo->beginTransaction();
            $stLock = $pdo->prepare('SELECT id, grand_total, paid_amount FROM invoices WHERE id = ? FOR UPDATE');
            $stLock->execute([$invoiceId]);
            $row = $stLock->fetch();
            if (!$row) {
                throw new RuntimeException('Invoice missing.');
            }
            $dueNow = (float) $row['grand_total'] - (float) $row['paid_amount'];
            if ($amount - $dueNow > 0.01) {
                throw new RuntimeException('Amount due changed. Retry.');
            }
            $custAcc = (int) $inv['customer_account_id'];
            $sysId = system_account_id($pdo);

            $pdo->prepare(
                'INSERT INTO payments (invoice_id, customer_id, customer_account_id, amount, method, note) VALUES (?,?,?,?,?,?)'
            )->execute([$invoiceId, (int) $inv['customer_id'], $custAcc, $amount, $method, $note ?: null]);
            $paymentId = (int) $pdo->lastInsertId();

            $newPaid = (float) $row['paid_amount'] + $amount;
            $pdo->prepare('UPDATE invoices SET paid_amount = ? WHERE id = ?')->execute([$newPaid, $invoiceId]);
            invoice_recalc_status($pdo, $invoiceId);

            ledger_apply(
                $pdo,
                $custAcc,
                0,
                $amount,
                'Payment for invoice ' . $inv['invoice_number'] . ' (' . $method . ')',
                null,
                $paymentId,
                null
            );
            ledger_apply(
                $pdo,
                $sysId,
                $amount,
                0,
                'Receipt — invoice ' . $inv['invoice_number'] . ' (' . $method . ')',
                null,
                $paymentId,
                null
            );

            $pdo->commit();
            flash_set('success', 'Payment recorded.');
            redirect('/modules/invoices/view.php?id=' . $invoiceId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not record payment.');
        }
    }
}

$pageTitle = 'Record payment';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= $invoiceId ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to invoice</a>
</div>
<h1 class="h3 mb-3">Record payment</h1>
<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card vk-card mb-3">
            <div class="card-body">
                <div class="fw-semibold"><?= e($inv['invoice_number']) ?></div>
                <div class="text-muted small"><?= e($inv['customer_name']) ?></div>
                <hr>
                <dl class="row small mb-0">
                    <dt class="col-6">Grand total</dt>
                    <dd class="col-6 text-end"><?= e(formatCurrency($inv['grand_total'])) ?></dd>
                    <dt class="col-6">Paid</dt>
                    <dd class="col-6 text-end"><?= e(formatCurrency($inv['paid_amount'])) ?></dd>
                    <dt class="col-6 text-danger">Due</dt>
                    <dd class="col-6 text-end text-danger fw-bold"><?= e(formatCurrency($due)) ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="card vk-card">
            <div class="card-body">
                <?php if ($due <= 0.0001): ?>
                    <p class="text-success mb-0">This invoice is fully paid.</p>
                <?php else: ?>
                    <form method="post" data-loading>
                        <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
                        <div class="mb-3">
                            <label class="form-label" for="amount">Amount</label>
                            <input type="number" step="0.01" min="0.01" max="<?= e((string) $due) ?>" class="form-control" name="amount" id="amount" required value="<?= e((string) min($due, (float) ($_POST['amount'] ?? $due))) ?>">
                            <div class="form-text">Partial payments are allowed.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="method">Method</label>
                            <select class="form-select" name="method" id="method">
                                <option value="cash" <?= ($_POST['method'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="card" <?= ($_POST['method'] ?? '') === 'card' ? 'selected' : '' ?>>Card</option>
                                <option value="bank" <?= ($_POST['method'] ?? '') === 'bank' ? 'selected' : '' ?>>Bank</option>
                                <option value="online" <?= ($_POST['method'] ?? '') === 'online' ? 'selected' : '' ?>>Online</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="note">Note</label>
                            <input type="text" class="form-control" name="note" id="note" maxlength="255" value="<?= e($_POST['note'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Save payment</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
