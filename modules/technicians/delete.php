<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invalid technician.');
    redirect('/modules/technicians/list.php');
}
try {
    $pdo->prepare('DELETE FROM technicians WHERE id = ?')->execute([$id]);
    flash_set('success', 'Technician removed.');
} catch (Throwable $e) {
    flash_set('error', 'Could not delete technician.');
}
redirect('/modules/technicians/list.php');
