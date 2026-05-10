<?php
declare(strict_types=1);
$pageTitle = 'Add staff profile';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/staff_model.php';
vk_staff_ensure_table($pdo);

$data = [
    'name' => '',
    'role' => '',
    'description' => '',
    'skills' => '',
    'experience' => '',
    'years_experience' => '',
    'completed_projects' => '',
    'specialization' => '',
    'certifications' => '',
    'email' => '',
    'phone' => '',
    'social_links' => '',
    'status' => 'active',
    'active' => 1,
    'sort_order' => 0,
    'image' => '',
    'image_thumb' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'role' => trim((string) ($_POST['role'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'skills' => trim((string) ($_POST['skills'] ?? '')),
        'experience' => trim((string) ($_POST['experience'] ?? '')),
        'years_experience' => trim((string) ($_POST['years_experience'] ?? '')),
        'completed_projects' => trim((string) ($_POST['completed_projects'] ?? '')),
        'specialization' => trim((string) ($_POST['specialization'] ?? '')),
        'certifications' => trim((string) ($_POST['certifications'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'social_links' => trim((string) ($_POST['social_links'] ?? '')),
        'status' => vk_staff_normalize_status((string) ($_POST['status'] ?? 'active')),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'image' => '',
        'image_thumb' => '',
    ];
    $errors = vk_staff_validate($data);
    if (!$errors) {
        try {
            $upload = vk_staff_upload_image('image');
            $data['image'] = $upload['image'] ?? '';
            $data['image_thumb'] = $upload['image_thumb'] ?? '';
            vk_staff_insert($pdo, $data);
            flash_set('success', 'Staff profile added.');
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
<h1 class="h3 mb-3">Add staff profile</h1>
<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php require __DIR__ . '/form.php'; ?>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
