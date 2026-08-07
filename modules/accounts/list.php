<?php
declare(strict_types=1);
$pageTitle = 'Accounts';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$rows = $pdo->query(
    'SELECT a.*, c.name AS customer_name
     FROM accounts a
     LEFT JOIN customers c ON c.id = a.customer_id
     ORDER BY a.account_type DESC, a.code ASC'
)->fetchAll();
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Accounts</h1>
        <p class="text-muted small mb-0">Each customer has one linked receivable account. Main system account pools receipts.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/accounts/transfer.php"><i class="bi bi-arrow-left-right me-1"></i>Transfer</a>
</div>
<div class="card vk-card">
    <div class="table-responsive table-responsive-stack">
        <table class="table table-hover mb-0 sortable">
            <thead class="table-light">
                <tr>
                    <th data-sort="0">Code</th>
                    <th data-sort="1">Name</th>
                    <th data-sort="2">Type</th>
                    <th data-sort="3">Customer</th>
                    <th data-sort="4" data-type="number">Balance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><code><?= e($r['code']) ?></code></td>
                        <td><?= e($r['name']) ?></td>
                        <td><span class="badge text-bg-<?= $r['account_type'] === 'system' ? 'primary' : 'secondary' ?>"><?= e($r['account_type']) ?></span></td>
                        <td><?= e($r['customer_name'] ?? '—') ?></td>
                        <td><?= e(number_format((float) $r['current_balance'], 2)) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/accounts/ledger.php?id=<?= (int) $r['id'] ?>">Ledger</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
