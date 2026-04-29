<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$kind = (string) ($_GET['kind'] ?? $_POST['kind'] ?? 'repair');
if (!in_array($kind, ['repair', 'cctv'], true)) {
    $kind = 'repair';
}
$jobId = (int) ($_GET['job_id'] ?? $_POST['job_id'] ?? 0);

$job = null;
$customerName = '';
$jobNumber = '';
$repairJobId = null;
$cctvJobId = null;

if ($kind === 'repair') {
    $st = $pdo->prepare(
        'SELECT r.*, c.name AS customer_name, a.id AS customer_account_id
         FROM repair_jobs r
         JOIN customers c ON c.id = r.customer_id
         JOIN accounts a ON a.customer_id = c.id
         WHERE r.id = ?'
    );
    $st->execute([$jobId]);
    $job = $st->fetch();
    if ($job) {
        $customerName = (string) $job['customer_name'];
        $jobNumber = (string) $job['job_number'];
        $repairJobId = $jobId;
    }
} else {
    $st = $pdo->prepare(
        'SELECT v.*, c.name AS customer_name, a.id AS customer_account_id
         FROM cctv_installations v
         JOIN customers c ON c.id = v.customer_id
         JOIN accounts a ON a.customer_id = c.id
         WHERE v.id = ?'
    );
    $st->execute([$jobId]);
    $job = $st->fetch();
    if ($job) {
        $customerName = (string) $job['customer_name'];
        $jobNumber = (string) $job['job_number'];
        $cctvJobId = $jobId;
    }
}

if (!$job) {
    flash_set('error', 'Job not found.');
    redirect($kind === 'repair' ? '/modules/repairs/list.php' : '/modules/cctv/list.php');
}

$custAcc = (int) $job['customer_account_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float) ($_POST['amount'] ?? 0);
    $method = (string) ($_POST['method'] ?? 'cash');
    $note = trim((string) ($_POST['note'] ?? ''));
    if (!in_array($method, ['cash', 'card', 'bank', 'online'], true)) {
        $method = 'cash';
    }
    if ($amount <= 0) {
        flash_set('error', 'Enter a positive amount.');
    } else {
        try {
            $pdo->beginTransaction();
            $sysId = system_account_id($pdo);
            $label = $kind === 'repair' ? 'Repair job' : 'CCTV job';

            $pdo->prepare(
                'INSERT INTO payments (invoice_id, repair_job_id, cctv_job_id, customer_id, customer_account_id, amount, method, note)
                 VALUES (NULL,?,?,?,?,?,?,?)'
            )->execute([
                $repairJobId,
                $cctvJobId,
                (int) $job['customer_id'] ?? null,
                $custAcc,
                $amount,
                $method,
                $note ?: null,
            ]);
            $paymentId = (int) $pdo->lastInsertId();

            ledger_apply(
                $pdo,
                $custAcc,
                0,
                $amount,
                $label . ' ' . $jobNumber . ' — advance / payment (' . $method . ')',
                null,
                $paymentId,
                null
            );
            ledger_apply(
                $pdo,
                $sysId,
                $amount,
                0,
                'Receipt — ' . strtolower($label) . ' ' . $jobNumber . ' (' . $method . ')',
                null,
                $paymentId,
                null
            );

            $pdo->commit();
            flash_set('success', 'Payment recorded.');
            redirect($kind === 'repair' ? '/modules/repairs/view.php?id=' . $jobId : '/modules/cctv/view.php?id=' . $jobId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not record payment.');
        }
    }
}

$pageTitle = 'Job payment / advance';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$backUrl = $kind === 'repair'
    ? BASE_URL . '/modules/repairs/view.php?id=' . $jobId
    : BASE_URL . '/modules/cctv/view.php?id=' . $jobId;
?>
<div class="mb-3">
    <a href="<?= e($backUrl) ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to job</a>
</div>
<h1 class="h3 mb-3">Record payment / advance</h1>
<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card vk-card">
            <div class="card-body">
                <div class="fw-semibold"><?= e($jobNumber) ?></div>
                <div class="text-muted small"><?= e($customerName) ?></div>
                <div class="small mt-2"><span class="badge text-bg-secondary"><?= e($kind === 'repair' ? 'Repair' : 'CCTV') ?></span></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="card vk-card">
            <div class="card-body">
                <form method="post" data-loading>
                    <input type="hidden" name="kind" value="<?= e($kind) ?>">
                    <input type="hidden" name="job_id" value="<?= $jobId ?>">
                    <div class="mb-3">
                        <label class="form-label" for="amount">Amount</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="amount" required value="<?= e($_POST['amount'] ?? '') ?>">
                        <div class="form-text">Advances reduce the customer balance (same as invoice payments).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="method">Method</label>
                        <select class="form-select" name="method" id="method">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="bank">Bank</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="note">Note</label>
                        <input type="text" class="form-control" name="note" id="note" maxlength="255" value="<?= e($_POST['note'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
