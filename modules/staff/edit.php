<?php
declare(strict_types=1);
$pageTitle = 'Edit staff profile';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/staff_model.php';
vk_staff_ensure_table($pdo);

$id = max(0, (int) ($_GET['id'] ?? 0));
$data = $id > 0 ? vk_staff_get_by_id($pdo, $id, false) : null;
if (!$data) {
    flash_set('error', 'Staff profile not found.');
    redirect('/modules/staff/list.php');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = array_merge($data, [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'role' => trim((string) ($_POST['role'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'skills' => trim((string) ($_POST['skills'] ?? '')),
        'experience' => trim((string) ($_POST['experience'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'social_links' => trim((string) ($_POST['social_links'] ?? '')),
        'active' => isset($_POST['active']) ? 1 : 0,
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
    ]);
    $errors = vk_staff_validate($data);
    if (!$errors) {
        try {
            $data['image'] = vk_staff_upload_image('image', (string) ($data['image'] ?? ''));
            vk_staff_update($pdo, $id, $data);
            flash_set('success', 'Staff profile updated.');
            redirect('/modules/staff/list.php');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/staff/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<h1 class="h3 mb-3">Edit staff profile</h1>
<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php require __DIR__ . '/form.php'; ?>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
