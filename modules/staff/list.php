<?php
declare(strict_types=1);
$pageTitle = 'Staff portfolio';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/staff_model.php';
vk_staff_ensure_table($pdo);
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$rows = vk_staff_get_all($pdo, false);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Staff &amp; Owner Portfolio</h1>
        <p class="text-muted small mb-0">Manage public owner, administrator, and technician profiles.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/staff/add.php"><i class="bi bi-person-plus me-1"></i>Add profile</a>
</div>

<div class="card vk-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Profile</th>
                    <th>Role</th>
                    <th>Experience</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No staff profiles yet.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php $img = vk_staff_image_url($r['image'] ?? null); ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($img !== ''): ?>
                                    <img src="<?= e($img) ?>" alt="" class="rounded-circle object-fit-cover" style="width:48px;height:48px;">
                                <?php else: ?>
                                    <span class="rounded-circle bg-body-secondary d-inline-flex align-items-center justify-content-center fw-semibold" style="width:48px;height:48px;"><?= e(strtoupper(substr((string) $r['name'], 0, 1))) ?></span>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-semibold"><?= e((string) $r['name']) ?></div>
                                    <div class="small text-muted"><?= e(substr(trim((string) ($r['description'] ?? '')), 0, 90)) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= e((string) $r['role']) ?></td>
                        <td><?= e((string) ($r['experience'] ?? '')) ?: '&mdash;' ?></td>
                        <td><?= (int) $r['active'] === 1 ? '<span class="badge text-bg-success">Published</span>' : '<span class="badge text-bg-secondary">Draft</span>' ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(BASE_URL) ?>/staff/<?= (int) $r['id'] ?>">View</a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/staff/edit.php?id=<?= (int) $r['id'] ?>">Edit</a>
                            <form method="post" action="<?= e(BASE_URL) ?>/modules/staff/delete.php" class="d-inline" onsubmit="return confirm('Delete this staff profile?');">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2"><a target="_blank" href="<?= e(BASE_URL) ?>/staff">View public staff portfolio</a></p>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
