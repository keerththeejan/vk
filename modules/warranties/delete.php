<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invalid warranty.');
    redirect('/modules/warranties/list.php');
}
$pdo->prepare('DELETE FROM warranty_records WHERE id = ?')->execute([$id]);
flash_set('success', 'Warranty record removed.');
redirect('/modules/warranties/list.php');
