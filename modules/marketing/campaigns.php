<?php
declare(strict_types=1);
$pageTitle = 'Campaign Management';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';
vk_marketing_suite_seed($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/marketing/campaigns.php');
    }
    $st = $pdo->prepare(
        'INSERT INTO marketing_campaigns (campaign_name, channel, objective, segment, status, budget, starts_at, ends_at, notes, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $st->execute([
        trim((string) $_POST['campaign_name']),
        (string) $_POST['channel'],
        trim((string) $_POST['objective']),
        trim((string) $_POST['segment']),
        (string) $_POST['status'],
        (float) $_POST['budget'],
        ($_POST['starts_at'] ?? '') !== '' ? (string) $_POST['starts_at'] : null,
        ($_POST['ends_at'] ?? '') !== '' ? (string) $_POST['ends_at'] : null,
        trim((string) ($_POST['notes'] ?? '')),
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
    ]);
    flash_set('success', 'Campaign created.');
    redirect('/modules/marketing/campaigns.php');
}

$rows = $pdo->query('SELECT * FROM marketing_campaigns ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="vk-suite-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><span class="vk-suite-kicker"><i class="bi bi-megaphone"></i> Campaign Management</span><h1 class="h3 mb-0">Campaigns & Scheduler</h1></div>
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/marketing/export.php?type=campaigns"><i class="bi bi-download me-2"></i>CSV export</a>
    </div>
    <div class="row g-3">
        <div class="col-xl-4">
            <form class="card vk-card h-100" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <div class="card-header bg-transparent fw-semibold">New campaign</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Campaign name</label><input class="form-control" name="campaign_name" required></div>
                    <div class="mb-3"><label class="form-label">Channel</label><select class="form-select" name="channel"><option value="multi_channel">Multi channel</option><option value="whatsapp">WhatsApp</option><option value="email">Email</option><option value="sms">SMS</option><option value="facebook">Facebook</option><option value="instagram">Instagram</option></select></div>
                    <div class="mb-3"><label class="form-label">Objective</label><input class="form-control" name="objective" placeholder="Lead generation, renewals, awareness"></div>
                    <div class="mb-3"><label class="form-label">Customer segment</label><input class="form-control" name="segment" value="All customers"></div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><option>draft</option><option>scheduled</option><option>active</option><option>paused</option></select></div>
                        <div class="col-md-6"><label class="form-label">Budget</label><input class="form-control" type="number" step="0.01" name="budget" value="0"></div>
                        <div class="col-md-6"><label class="form-label">Start</label><input class="form-control" type="datetime-local" name="starts_at"></div>
                        <div class="col-md-6"><label class="form-label">End</label><input class="form-control" type="datetime-local" name="ends_at"></div>
                    </div>
                    <div class="mt-3"><label class="form-label">AI brief / notes</label><textarea class="form-control" name="notes" rows="4"></textarea></div>
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-primary" type="submit">Create campaign</button></div>
            </form>
        </div>
        <div class="col-xl-8">
            <div class="card vk-card">
                <div class="card-header bg-transparent fw-semibold">Active campaign scheduler</div>
                <div class="card-body p-0">
                    <div class="table-responsive table-responsive-stack">
                        <table class="table table-hover align-middle mb-0 sortable">
                            <thead><tr><th data-sort="0">Campaign</th><th>Channel</th><th>Status</th><th>Schedule</th><th>Performance</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><strong><?= e((string) $r['campaign_name']) ?></strong><div class="small text-muted"><?= e((string) $r['segment']) ?></div></td>
                                    <td><span class="badge text-bg-info"><?= e(str_replace('_', ' ', (string) $r['channel'])) ?></span></td>
                                    <td><span class="badge text-bg-<?= vk_marketing_status_badge((string) $r['status']) ?>"><?= e((string) $r['status']) ?></span></td>
                                    <td class="small"><?= e(substr((string) $r['starts_at'], 0, 16)) ?> → <?= e(substr((string) $r['ends_at'], 0, 16)) ?></td>
                                    <td><div class="vk-chart-row vk-chart-row-compact"><div><b style="width: <?= min(100, (int) $r['engagement'] / 8) ?>%"></b></div><strong><?= (int) $r['conversions'] ?> conv.</strong></div></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
