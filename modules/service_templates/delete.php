<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once __DIR__ . '/service_image_upload.php';
require_once dirname(__DIR__, 2) . '/includes/service_templates_service.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invalid template.');
    redirect('/modules/service_templates/list.php');
}

$perms = vk_st_templates_permissions((string) ($_SESSION['user_role'] ?? 'viewer'));
if (!$perms['can_delete']) {
    flash_set('error', 'Delete not permitted.');
    redirect('/modules/service_templates/list.php');
}

$result = vk_st_templates_soft_delete($pdo, $id, (int) ($_SESSION['user_id'] ?? 0));
if ($result['ok']) {
    flash_set('success', 'Template archived / removed.');
} else {
    flash_set('error', $result['error'] ?? 'Could not delete template.');
}
redirect('/modules/service_templates/list.php');
