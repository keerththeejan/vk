<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invalid contract.');
    redirect('/modules/maintenance/list.php');
}
try {
    $pdo->prepare('DELETE FROM maintenance_contracts WHERE id = ?')->execute([$id]);
    flash_set('success', 'Contract removed.');
} catch (Throwable $e) {
    flash_set('error', 'Could not delete contract.');
}
redirect('/modules/maintenance/list.php');
