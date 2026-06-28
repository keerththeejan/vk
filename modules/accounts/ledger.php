<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
vk_ensure_finance_schemas($pdo);

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT a.*, c.name AS customer_name
     FROM accounts a
     LEFT JOIN customers c ON c.id = a.customer_id
     WHERE a.id = ?'
);
$st->execute([$id]);
$acc = $st->fetch();
if (!$acc) {
    flash_set('error', 'Account not found.');
    redirect('/modules/accounts/list.php');
}

$pageTitle = 'Account ledger';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$ledger = $pdo->prepare(
    'SELECT * FROM account_ledger WHERE account_id = ? ORDER BY id DESC LIMIT 500'
);
$ledger->execute([$id]);
$entries = $ledger->fetchAll();
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/accounts/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Accounts</a>
</div>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0"><?= e($acc['name']) ?></h1>
        <p class="text-muted mb-0"><code><?= e($acc['code']) ?></code> · <?= e($acc['account_type']) ?>
            <?php if ($acc['customer_name']): ?> · <?= e($acc['customer_name']) ?><?php endif; ?></p>
    </div>
    <div class="text-end">
        <div class="small text-muted">Current balance</div>
        <div class="fs-4 fw-bold"><?= e(number_format((float) $acc['current_balance'], 2)) ?></div>
        <div class="small text-muted">Customer: debit increases amount due · credit reduces it.</div>
    </div>
</div>
<div class="card vk-card">
    <div class="table-responsive table-responsive-stack">
        <table class="table table-sm table-hover mb-0 sortable">
            <thead class="table-light">
                <tr>
                    <th data-sort="0">ID</th>
                    <th data-sort="1">When</th>
                    <th data-sort="2">Description</th>
                    <th data-sort="3" data-type="number">Debit</th>
                    <th data-sort="4" data-type="number">Credit</th>
                    <th data-sort="5" data-type="number">Balance</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$entries): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No ledger entries yet.</td></tr>
            <?php else: ?>
                <?php foreach ($entries as $e): ?>
                    <tr>
                        <td><?= (int) $e['id'] ?></td>
                        <td><?= e($e['entry_datetime']) ?></td>
                        <td><?= e($e['description'] ?? '') ?></td>
                        <td><?= (float) $e['debit'] > 0 ? e(number_format((float) $e['debit'], 2)) : '—' ?></td>
                        <td><?= (float) $e['credit'] > 0 ? e(number_format((float) $e['credit'], 2)) : '—' ?></td>
                        <td class="fw-semibold"><?= e(number_format((float) $e['balance'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
