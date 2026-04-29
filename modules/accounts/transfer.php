<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$accounts = $pdo->query('SELECT id, code, name, account_type FROM accounts ORDER BY account_type DESC, code')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from = (int) ($_POST['from_account_id'] ?? 0);
    $to = (int) ($_POST['to_account_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $note = trim((string) ($_POST['note'] ?? ''));

    if ($from <= 0 || $to <= 0 || $from === $to) {
        flash_set('error', 'Select two different accounts.');
    } elseif ($amount <= 0) {
        flash_set('error', 'Amount must be positive.');
    } else {
        try {
            $pdo->beginTransaction();

            $st = $pdo->prepare('SELECT id, current_balance FROM accounts WHERE id = ? FOR UPDATE');
            $st->execute([$from]);
            $aFrom = $st->fetch();
            $st->execute([$to]);
            $aTo = $st->fetch();
            if (!$aFrom || !$aTo) {
                throw new RuntimeException('Invalid account.');
            }
            if ((float) $aFrom['current_balance'] < $amount - 0.0001) {
                throw new RuntimeException('Source account balance is insufficient.');
            }

            $pdo->prepare(
                'INSERT INTO account_transfers (from_account_id, to_account_id, amount, note) VALUES (?,?,?,?)'
            )->execute([$from, $to, $amount, $note ?: null]);
            $xferId = (int) $pdo->lastInsertId();

            ledger_apply(
                $pdo,
                $from,
                0,
                $amount,
                'Transfer out' . ($note ? ': ' . $note : ''),
                null,
                null,
                $xferId
            );
            ledger_apply(
                $pdo,
                $to,
                $amount,
                0,
                'Transfer in' . ($note ? ': ' . $note : ''),
                null,
                null,
                $xferId
            );

            $pdo->commit();
            flash_set('success', 'Transfer completed.');
            redirect('/modules/accounts/list.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', APP_DEBUG ? $e->getMessage() : 'Transfer failed.');
        }
    }
}

$pageTitle = 'Account transfer';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/accounts/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Accounts</a>
</div>
<h1 class="h3 mb-3">Transfer between accounts</h1>
<p class="text-muted small">Moves balance from the source account to the destination (e.g. reassign receivable between customers or into the main pool).</p>
<div class="card vk-card" style="max-width: 720px;">
    <div class="card-body">
        <form method="post" data-loading>
            <div class="mb-3">
                <label class="form-label" for="from_account_id">From</label>
                <select class="form-select" name="from_account_id" id="from_account_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int) $a['id'] ?>"><?= e($a['code']) ?> — <?= e($a['name']) ?> (<?= e($a['account_type']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="to_account_id">To</label>
                <select class="form-select" name="to_account_id" id="to_account_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int) $a['id'] ?>"><?= e($a['code']) ?> — <?= e($a['name']) ?> (<?= e($a['account_type']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="amount">Amount</label>
                <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="amount" required value="<?= e($_POST['amount'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="note">Note</label>
                <input type="text" class="form-control" name="note" id="note" maxlength="512" value="<?= e($_POST['note'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary">Execute transfer</button>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
