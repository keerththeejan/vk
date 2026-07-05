<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/service_templates_service.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$actorId = (int) ($_SESSION['user_id'] ?? 0);
$perms = vk_st_templates_permissions((string) ($_SESSION['user_role'] ?? 'viewer'));

if (!$perms['can_create'] || $id <= 0) {
    flash_set('error', 'Invalid request.');
    redirect('/modules/service_templates/list.php');
}

$result = vk_st_templates_duplicate($pdo, $id, $actorId);
if ($result['ok'] && isset($result['item']['id'])) {
    flash_set('success', 'Template duplicated successfully.');
    redirect('/modules/service_templates/edit.php?id=' . (int) $result['item']['id']);
}

flash_set('error', $result['error'] ?? 'Could not duplicate template.');
redirect('/modules/service_templates/list.php');
