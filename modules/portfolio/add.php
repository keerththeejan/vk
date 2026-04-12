<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/web_portfolio_schema.php';
vk_ensure_web_portfolio_tables($pdo);

if (!db_table_exists($pdo, 'web_portfolio_posts')) {
    flash_set('error', 'Portfolio tables missing. Import sql/patch_portfolio_if_missing.sql or ensure repair/CCTV job tables exist.');
    redirect('/modules/dashboard.php');
}

$prefillRepair = (int) ($_GET['repair_job_id'] ?? 0);

function portfolio_save_upload(array $file, string $subdir): ?string
{
    if (empty($file['name']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ((int) $file['size'] > 4 * 1024 * 1024) {
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return null;
    }
    $ext = match ($info[2]) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        default => 'bin',
    };
    $dir = ROOT_PATH . '/uploads/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fn = 'pf_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $fn)) {
        return 'uploads/' . $subdir . '/' . $fn;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $desc = trim((string) ($_POST['description'] ?? ''));
    $disp = trim((string) ($_POST['display_date'] ?? ''));
    $pub = !empty($_POST['published']) ? 1 : 0;
    $rid = (int) ($_POST['repair_job_id'] ?? 0);
    $cid = (int) ($_POST['cctv_job_id'] ?? 0);
    $dispOk = ($disp !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $disp)) ? $disp : '';

    if ($title === '' || $dispOk === '') {
        flash_set('error', 'Title and display date are required.');
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'INSERT INTO web_portfolio_posts (title, description, published, display_date, repair_job_id, cctv_job_id)
                 VALUES (?,?,?,?,?,?)'
            )->execute([
                $title,
                $desc !== '' ? $desc : null,
                $pub,
                $dispOk,
                $rid > 0 ? $rid : null,
                $cid > 0 ? $cid : null,
            ]);
            $postId = (int) $pdo->lastInsertId();
            $sort = 0;
            if (!empty($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
                $roles = $_POST['img_role'] ?? [];
                $n = count($_FILES['images']['name']);
                for ($i = 0; $i < $n; $i++) {
                    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    $file = [
                        'name' => $_FILES['images']['name'][$i],
                        'type' => $_FILES['images']['type'][$i],
                        'tmp_name' => $_FILES['images']['tmp_name'][$i],
                        'error' => $_FILES['images']['error'][$i],
                        'size' => $_FILES['images']['size'][$i],
                    ];
                    $path = portfolio_save_upload($file, 'portfolio');
                    if ($path) {
                        $role = isset($roles[$i]) && in_array($roles[$i], ['before', 'after', 'general'], true) ? $roles[$i] : 'general';
                        $pdo->prepare(
                            'INSERT INTO web_portfolio_images (post_id, image_path, image_role, sort_order) VALUES (?,?,?,?)'
                        )->execute([$postId, $path, $role, $sort++]);
                    }
                }
            }
            $pdo->commit();
            flash_set('success', 'Portfolio post saved.');
            redirect('/modules/portfolio/list.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not save.');
        }
    }
}

$pageTitle = 'New portfolio post';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="mb-3"><a href="<?= e(BASE_URL) ?>/modules/portfolio/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a></div>
<h1 class="h3 mb-3">New portfolio post</h1>
<div class="card vk-card" style="max-width: 720px;">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input class="form-control" name="title" required maxlength="255" value="<?= e($_POST['title'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="4"><?= e($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Display date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="display_date" required value="<?= e($_POST['display_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Repair job ID (optional)</label>
                    <input type="number" min="0" class="form-control" name="repair_job_id" value="<?= e((string) ($_POST['repair_job_id'] ?? $prefillRepair)) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">CCTV job ID (optional)</label>
                    <input type="number" min="0" class="form-control" name="cctv_job_id" value="<?= e((string) ($_POST['cctv_job_id'] ?? 0)) ?>">
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="published" id="published" value="1" <?= !empty($_POST['published']) || !isset($_POST['title']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="published">Published on website</label>
            </div>
            <div class="mb-3">
                <label class="form-label">Images (before / after)</label>
                <input type="file" class="form-control" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
                <div class="form-text">Optional. First file role:</div>
                <select class="form-select form-select-sm mt-1" name="img_role[0]" style="max-width:200px;">
                    <option value="general">General</option>
                    <option value="before">Before</option>
                    <option value="after">After</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Publish</button>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
