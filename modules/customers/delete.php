<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invalid customer.');
    redirect('/modules/customers/list.php');
}
$st = $pdo->prepare('SELECT COUNT(*) FROM invoices WHERE customer_id = ?');
$st->execute([$id]);
if ((int) $st->fetchColumn() > 0) {
    flash_set('error', 'Cannot delete customer with existing invoices.');
    redirect('/modules/customers/list.php');
}
$st = $pdo->prepare('SELECT COUNT(*) FROM repair_jobs WHERE customer_id = ?');
$st->execute([$id]);
if ((int) $st->fetchColumn() > 0) {
    flash_set('error', 'Cannot delete customer with repair jobs on file.');
    redirect('/modules/customers/list.php');
}
$st = $pdo->prepare('SELECT COUNT(*) FROM cctv_installations WHERE customer_id = ?');
$st->execute([$id]);
if ((int) $st->fetchColumn() > 0) {
    flash_set('error', 'Cannot delete customer with CCTV installation jobs.');
    redirect('/modules/customers/list.php');
}
$st = $pdo->prepare('SELECT COUNT(*) FROM maintenance_contracts WHERE customer_id = ?');
$st->execute([$id]);
if ((int) $st->fetchColumn() > 0) {
    flash_set('error', 'Cannot delete customer with maintenance contracts.');
    redirect('/modules/customers/list.php');
}
$st = $pdo->prepare('SELECT COUNT(*) FROM warranty_records WHERE customer_id = ?');
$st->execute([$id]);
if ((int) $st->fetchColumn() > 0) {
    flash_set('error', 'Cannot delete customer with warranty records.');
    redirect('/modules/customers/list.php');
}
try {
    $pdo->beginTransaction();
    $stA = $pdo->prepare('SELECT id FROM accounts WHERE customer_id = ? LIMIT 1');
    $stA->execute([$id]);
    $accId = $stA->fetchColumn();
    if ($accId) {
        $pdo->prepare('DELETE FROM account_ledger WHERE account_id = ?')->execute([(int) $accId]);
        $pdo->prepare('DELETE FROM accounts WHERE id = ?')->execute([(int) $accId]);
    }
    $pdo->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
    $pdo->commit();
    vk_cache_flush_dashboard();
    flash_set('success', 'Customer removed.');
} catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('error', 'Could not delete customer.');
}
redirect('/modules/customers/list.php');
