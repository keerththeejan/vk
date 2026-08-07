<?php
declare(strict_types=1);
$id = (int) ($_GET['id'] ?? 0);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/modules/quotations/create.php');
    exit;
}
header('Location: ' . BASE_URL . '/modules/quotations/create.php?id=' . $id);
exit;
