<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
$id = (int) ($_GET['id'] ?? 0);
header('Location: ' . BASE_URL . '/modules/customers/profile.php?id=' . $id);
exit;
