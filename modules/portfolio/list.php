<?php
declare(strict_types=1);
$pageTitle = 'Portfolio posts';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/web_portfolio_schema.php';
vk_ensure_web_portfolio_tables($pdo);
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

if (!db_table_exists($pdo, 'web_portfolio_posts')) {
    echo '<div class="alert alert-danger">Portfolio tables could not be created. Ensure <code>repair_jobs</code> and <code>cctv_installations</code> exist, then run <code>sql/patch_portfolio_if_missing.sql</code> on your database.</div>';
    require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
    exit;
}

$rows = $pdo->query('SELECT * FROM web_portfolio_posts ORDER BY id DESC')->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Website portfolio</h1>
    <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/portfolio/add.php"><i class="bi bi-plus-lg me-1"></i>New post</a>
</div>
<div class="card vk-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Title</th><th>Date</th><th>Published</th><th>Job ref</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No posts yet.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['title']) ?></td>
                        <td><?= e($r['display_date']) ?></td>
                        <td><?= (int) $r['published'] ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-secondary">Draft</span>' ?></td>
                        <td class="small"><?= $r['repair_job_id'] ? '<a href="' . e(BASE_URL) . '/modules/repairs/view.php?id=' . (int) $r['repair_job_id'] . '">Repair #' . (int) $r['repair_job_id'] . '</a>' : ($r['cctv_job_id'] ? 'CCTV #' . (int) $r['cctv_job_id'] : '—') ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-danger" href="<?= e(BASE_URL) ?>/modules/portfolio/delete.php?id=<?= (int) $r['id'] ?>" onclick="return confirm('Delete this post and its images?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2"><a target="_blank" href="<?= e(BASE_URL) ?>/portfolio.php">View public portfolio</a></p>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
