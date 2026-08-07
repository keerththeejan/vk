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

<div class="card vk-card vk-staff-list-card">
    <div class="card-body border-bottom">
        <div class="row g-2 align-items-center">
            <div class="col-md-8">
                <label class="visually-hidden" for="staffSearch">Search staff</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" id="staffSearch" type="search" placeholder="Search by name, role, specialization, or skill" data-staff-search>
                </div>
            </div>
            <div class="col-md-4">
                <label class="visually-hidden" for="staffStatusFilter">Filter status</label>
                <select class="form-select" id="staffStatusFilter" data-staff-status-filter>
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="on_leave">On Leave</option>
                </select>
            </div>
        </div>
    </div>
    <div class="table-responsive vk-staff-table-wrap">
        <table class="table table-hover align-middle mb-0 sortable vk-staff-table">
            <thead class="table-light">
                <tr>
                    <th data-sort="0">Profile</th>
                    <th data-sort="1">Role</th>
                    <th data-sort="2">Experience</th>
                    <th data-sort="3">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No staff profiles yet.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $status = vk_staff_normalize_status((string) ($r['status'] ?? (!empty($r['active']) ? 'active' : 'inactive')));
                    $img = vk_staff_display_image($r, true);
                    $searchText = strtolower(implode(' ', [
                        (string) ($r['name'] ?? ''),
                        (string) ($r['role'] ?? ''),
                        (string) ($r['specialization'] ?? ''),
                        (string) ($r['skills'] ?? ''),
                    ]));
                    ?>
                    <tr data-staff-row data-status="<?= e($status) ?>" data-search="<?= e($searchText) ?>">
                        <td data-label="Profile">
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= e($img) ?>" alt="<?= e((string) $r['name']) ?>" class="vk-staff-avatar" width="56" height="56" loading="lazy" decoding="async" onerror="<?= vk_staff_image_onerror_attr() ?>">
                                <div>
                                    <div class="fw-semibold"><?= e((string) $r['name']) ?></div>
                                    <div class="small text-muted"><?= e((string) ($r['specialization'] ?: substr(trim((string) ($r['description'] ?? '')), 0, 90))) ?></div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Role"><?= e((string) $r['role']) ?></td>
                        <td data-label="Experience">
                            <span class="fw-semibold"><?= e((string) ($r['experience'] ?? '')) ?: '&mdash;' ?></span>
                            <?php if (!empty($r['completed_projects'])): ?>
                                <div class="small text-muted"><?= (int) $r['completed_projects'] ?> projects</div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status"><span class="vk-status-badge vk-status-<?= e($status) ?>"><?= e(vk_staff_status_label($status)) ?></span></td>
                        <td class="text-end text-nowrap" data-label="Actions">
                            <div class="btn-group btn-group-sm">
                                <a class="btn btn-outline-secondary" target="_blank" href="<?= e(BASE_URL) ?>/staff/<?= (int) $r['id'] ?>" aria-label="View <?= e((string) $r['name']) ?>"><i class="bi bi-eye"></i></a>
                                <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/staff/edit.php?id=<?= (int) $r['id'] ?>" aria-label="Edit <?= e((string) $r['name']) ?>"><i class="bi bi-pencil"></i></a>
                                <form method="post" action="<?= e(BASE_URL) ?>/modules/staff/delete.php" class="d-inline" onsubmit="return confirm('Delete this staff profile?');">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger" aria-label="Delete <?= e((string) $r['name']) ?>"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
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
