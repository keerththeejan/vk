<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/staff_model.php';
vk_staff_ensure_table($pdo);

$id = $_SERVER['REQUEST_METHOD'] === 'POST' ? max(0, (int) ($_POST['id'] ?? 0)) : 0;
if ($id > 0) {
    vk_staff_delete($pdo, $id);
    flash_set('success', 'Staff profile deleted.');
} else {
    flash_set('error', 'Invalid staff profile.');
}

redirect('/modules/staff/list.php');
