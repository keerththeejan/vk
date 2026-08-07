<?php
declare(strict_types=1);
$pageTitle = 'Lead Tracking';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';
vk_marketing_suite_seed($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/marketing/leads.php');
    }
    $st = $pdo->prepare(
        'INSERT INTO marketing_leads (lead_name, email, phone, source, service_interest, stage, score, estimated_value, last_touch_at, notes)
         VALUES (?,?,?,?,?,?,?,?,NOW(),?)'
    );
    $st->execute([
        trim((string) $_POST['lead_name']),
        trim((string) ($_POST['email'] ?? '')),
        trim((string) ($_POST['phone'] ?? '')),
        trim((string) ($_POST['source'] ?? 'Website')),
        trim((string) ($_POST['service_interest'] ?? '')),
        (string) ($_POST['stage'] ?? 'new'),
        max(0, min(100, (int) ($_POST['score'] ?? 50))),
        (float) ($_POST['estimated_value'] ?? 0),
        trim((string) ($_POST['notes'] ?? '')),
    ]);
    flash_set('success', 'Lead added to pipeline.');
    redirect('/modules/marketing/leads.php');
}
$stage = (string) ($_GET['stage'] ?? '');
$where = $stage !== '' ? 'WHERE stage = ' . $pdo->quote($stage) : '';
$rows = $pdo->query("SELECT * FROM marketing_leads {$where} ORDER BY FIELD(stage,'new','contacted','qualified','proposal','won','lost'), score DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$stages = ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'];
?>
<div class="vk-suite-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><span class="vk-suite-kicker"><i class="bi bi-funnel"></i> Lead Tracking</span><h1 class="h3 mb-0">Lead Pipeline</h1></div>
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/marketing/export.php?type=leads"><i class="bi bi-filetype-csv me-2"></i>CSV export</a>
    </div>
    <div class="row g-3">
        <div class="col-xl-4">
            <form class="card vk-card" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <div class="card-header bg-transparent fw-semibold">Add lead</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Lead name</label><input class="form-control" name="lead_name" required></div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6"><label class="form-label">Source</label><input class="form-control" name="source" value="Website"></div>
                        <div class="col-md-6"><label class="form-label">Stage</label><select class="form-select" name="stage"><?php foreach ($stages as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="mt-3"><label class="form-label">Service interest</label><input class="form-control" name="service_interest"></div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6"><label class="form-label">Score</label><input class="form-control" type="number" name="score" value="50" min="0" max="100"></div>
                        <div class="col-md-6"><label class="form-label">Estimated value</label><input class="form-control" type="number" step="0.01" name="estimated_value" value="0"></div>
                    </div>
                    <div class="mt-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3"></textarea></div>
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-primary" type="submit">Add lead</button></div>
            </form>
        </div>
        <div class="col-xl-8">
            <div class="vk-pipeline-board">
                <?php foreach ($stages as $s): ?>
                    <section class="vk-pipeline-column">
                        <h2><?= e(ucfirst($s)) ?></h2>
                        <?php foreach (array_filter($rows, fn($r) => $r['stage'] === $s) as $lead): ?>
                            <article class="vk-pipeline-card">
                                <span class="badge text-bg-<?= vk_lead_stage_badge($s) ?>"><?= e($s) ?></span>
                                <strong><?= e((string) $lead['lead_name']) ?></strong>
                                <small><?= e((string) $lead['source']) ?> · <?= e((string) $lead['service_interest']) ?></small>
                                <div class="vk-lead-score"><span style="width: <?= (int) $lead['score'] ?>%"></span></div>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
