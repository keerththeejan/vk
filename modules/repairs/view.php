<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$id = (int) ($_GET['id'] ?? 0);

function render_repair_view_error(string $title, string $message, ?string $debug = null): never
{
    $pageTitle = 'Repair job';
    require dirname(__DIR__, 2) . '/includes/layout_start.php';
    ?>
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <div class="card vk-card border-0 shadow-sm">
                <div class="card-body p-4 text-center">
                    <div class="display-6 text-warning mb-3"><i class="bi bi-exclamation-triangle"></i></div>
                    <h1 class="h4 mb-2"><?= e($title) ?></h1>
                    <p class="text-muted mb-4"><?= e($message) ?></p>
                    <?php if ($debug !== null && $debug !== ''): ?>
                        <div class="alert alert-danger text-start small"><?= nl2br(e($debug)) ?></div>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/repairs/list.php"><i class="bi bi-arrow-left me-1"></i>Back to repairs</a>
                        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/dashboard.php">Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    require dirname(__DIR__, 2) . '/includes/layout_end.php';
    exit;
}

if ($id <= 0) {
    render_repair_view_error('Invalid repair ID', 'The selected repair job link is missing a valid ID.');
}

try {
    if (!db_table_exists($pdo, 'repair_jobs')) {
        render_repair_view_error('Repair module unavailable', 'The repair jobs table is missing. Please run the repair module database upgrade.');
    }
    if (!db_table_exists($pdo, 'customers')) {
        render_repair_view_error('Customer data unavailable', 'The customers table is missing, so repair job details cannot be loaded.');
    }

    $select = [
        'r.*',
        'COALESCE(c.name, "Unknown customer") AS customer_name',
        'c.phone',
        'c.email',
        'c.address',
    ];
    $joins = ['LEFT JOIN customers c ON c.id = r.customer_id'];

    $hasTechnician = db_table_exists($pdo, 'technicians') && db_column_exists($pdo, 'repair_jobs', 'technician_id');
    if ($hasTechnician) {
        $select[] = 't.name AS technician_name';
        $joins[] = 'LEFT JOIN technicians t ON t.id = r.technician_id';
    } else {
        $select[] = 'NULL AS technician_name';
    }

    $hasTemplate = db_table_exists($pdo, 'service_templates') && db_column_exists($pdo, 'repair_jobs', 'service_template_id');
    if ($hasTemplate) {
        $select[] = 'svc_tpl.name AS template_name';
        $joins[] = 'LEFT JOIN service_templates svc_tpl ON svc_tpl.id = r.service_template_id';
    } else {
        $select[] = 'NULL AS template_name';
    }

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM repair_jobs r ' . implode(' ', $joins) . ' WHERE r.id = ? LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute([$id]);
    $job = $st->fetch();
} catch (Throwable $e) {
    error_log('Repair view load failed for ID ' . $id . ': ' . $e->getMessage());
    render_repair_view_error('Repair view could not load', 'The repair record could not be loaded right now.', APP_DEBUG ? $e->getMessage() : null);
}

if (!$job) {
    render_repair_view_error('Repair record not found', 'This repair job may have been deleted, moved, or the link is outdated.');
}

$partRows = [];
$advances = 0.0;
$payments = [];
$inv = null;

try {
    if (db_table_exists($pdo, 'repair_job_parts') && db_table_exists($pdo, 'products')) {
        $parts = $pdo->prepare(
            'SELECT jp.*, COALESCE(p.name, "Deleted part") AS product_name
             FROM repair_job_parts jp
             LEFT JOIN products p ON p.id = jp.product_id
             WHERE jp.repair_job_id = ?
             ORDER BY jp.id'
        );
        $parts->execute([$id]);
        $partRows = $parts->fetchAll();
    }

    if (db_table_exists($pdo, 'payments') && db_column_exists($pdo, 'payments', 'repair_job_id')) {
        $adv = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE repair_job_id = ?');
        $adv->execute([$id]);
        $advances = (float) $adv->fetchColumn();

        $payList = $pdo->prepare('SELECT * FROM payments WHERE repair_job_id = ? ORDER BY paid_at DESC, id DESC');
        $payList->execute([$id]);
        $payments = $payList->fetchAll();
    }

    if (!empty($job['invoice_id']) && db_table_exists($pdo, 'invoices')) {
        $ist = $pdo->prepare('SELECT id, invoice_number, grand_total, paid_amount, status FROM invoices WHERE id = ? LIMIT 1');
        $ist->execute([(int) $job['invoice_id']]);
        $inv = $ist->fetch() ?: null;
    }
} catch (Throwable $e) {
    error_log('Repair view related data failed for ID ' . $id . ': ' . $e->getMessage());
    if (APP_DEBUG) {
        flash_set('warning', 'Some related repair data could not be loaded: ' . $e->getMessage());
    }
}

$waPhone = preg_replace('/\D+/', '', (string) ($job['phone'] ?? ''));
$waLink = $waPhone !== '' ? 'https://wa.me/' . $waPhone : '';

$printerLabels = [
    'cartridge' => 'Cartridge / toner',
    'paper_jam' => 'Paper jam',
    'roller' => 'Roller replacement',
    'ink_refill' => 'Ink refill',
    'other' => 'Other',
];
$pi = (string) ($job['printer_issue'] ?? '');
$printerLabel = $printerLabels[$pi] ?? ($pi !== '' ? $pi : null);

$statusBadge = repair_status_badge_class((string) $job['status']);
$warrBadge = warranty_expiry_badge_class($job['warranty_expiry'] ?? null);

$mapsKey = defined('GOOGLE_MAPS_API_KEY') ? (string) GOOGLE_MAPS_API_KEY : '';
$extraHead = ($mapsKey !== '' && !empty($job['latitude']) && !empty($job['longitude']))
    ? '<script src="https://maps.googleapis.com/maps/api/js?key=' . e($mapsKey) . '" async defer></script>'
    : '';

$jobAtts = [];
if (db_table_exists($pdo, 'job_attachments')) {
    $ja = $pdo->prepare('SELECT * FROM job_attachments WHERE repair_job_id = ? ORDER BY id DESC');
    $ja->execute([$id]);
    $jobAtts = $ja->fetchAll();
}

$pageTitle = 'Repair job';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <a href="<?= e(BASE_URL) ?>/modules/repairs/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Jobs</a>
        <h1 class="h3 mt-2 mb-0"><code><?= e($job['job_number']) ?></code>
            <?php if (!empty($job['emergency_priority'])): ?><span class="badge text-bg-danger ms-1">Emergency priority</span><?php endif; ?>
        </h1>
        <p class="text-muted mb-0"><?= e($job['customer_name']) ?> · <?= e(repair_device_type_label((string) $job['device_type'])) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" target="_blank" href="<?= e(BASE_URL) ?>/modules/repairs/print.php?id=<?= $id ?>"><i class="bi bi-printer me-1"></i>Print card</a>
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/repairs/edit.php?id=<?= $id ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
        <a class="btn btn-outline-warning" href="<?= e(BASE_URL) ?>/modules/warranties/add.php?repair_job_id=<?= $id ?>&customer_id=<?= (int) $job['customer_id'] ?>"><i class="bi bi-shield-check me-1"></i>Warranty record</a>
        <?php if ($job['status'] === 'completed' && db_table_exists($pdo, 'web_portfolio_posts')): ?>
            <a class="btn btn-outline-info" href="<?= e(BASE_URL) ?>/modules/portfolio/add.php?repair_job_id=<?= $id ?>"><i class="bi bi-camera me-1"></i>Create portfolio post</a>
        <?php endif; ?>
        <?php if (empty($job['invoice_id'])): ?>
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/invoices/create.php?repair_job_id=<?= $id ?>"><i class="bi bi-receipt me-1"></i>Create invoice</a>
            <a class="btn btn-success" href="<?= e(BASE_URL) ?>/modules/payments/job_payment.php?kind=repair&job_id=<?= $id ?>"><i class="bi bi-cash me-1"></i>Advance / pay</a>
        <?php endif; ?>
        <?php if ($waLink): ?>
            <a class="btn btn-outline-success" target="_blank" rel="noopener" href="<?= e($waLink) ?>"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card vk-card mb-3">
            <div class="card-header bg-transparent fw-semibold">Job details</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9"><span class="badge text-bg-<?= e($statusBadge) ?>"><?= e(str_replace('_', ' ', $job['status'])) ?></span></dd>
                    <dt class="col-sm-3">Technician</dt>
                    <dd class="col-sm-9"><?= e($job['technician_name'] ?? '—') ?></dd>
                    <?php if (!empty($job['field_status'])): ?>
                        <dt class="col-sm-3">Field status</dt>
                        <dd class="col-sm-9"><span class="badge text-bg-info text-dark"><?= e(str_replace('_', ' ', (string) $job['field_status'])) ?></span></dd>
                    <?php endif; ?>
                    <?php if (!empty($job['latitude']) && !empty($job['longitude'])): ?>
                        <dt class="col-sm-3">Coordinates</dt>
                        <dd class="col-sm-9">
                            <a target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?= urlencode((string) $job['latitude'] . ',' . (string) $job['longitude']) ?>"><?= e($job['latitude']) ?>, <?= e($job['longitude']) ?></a>
                        </dd>
                    <?php endif; ?>
                    <?php if ($printerLabel): ?>
                        <dt class="col-sm-3">Printer issue</dt>
                        <dd class="col-sm-9"><?= e($printerLabel) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($job['template_name'])): ?>
                        <dt class="col-sm-3">Template</dt>
                        <dd class="col-sm-9"><?= e($job['template_name']) ?></dd>
                    <?php endif; ?>
                    <dt class="col-sm-3">Problem</dt>
                    <dd class="col-sm-9"><?= nl2br(e($job['problem_description'] ?? '')) ?></dd>
                    <dt class="col-sm-3">Accessories</dt>
                    <dd class="col-sm-9"><?= nl2br(e($job['accessories_received'] ?? '—')) ?></dd>
                    <dt class="col-sm-3">Estimated</dt>
                    <dd class="col-sm-9"><?= e(number_format((float) $job['estimated_cost'], 2)) ?></dd>
                    <dt class="col-sm-3">Technician notes</dt>
                    <dd class="col-sm-9"><?= nl2br(e($job['technician_notes'] ?? '—')) ?></dd>
                    <dt class="col-sm-3">Repair warranty</dt>
                    <dd class="col-sm-9">
                        <?php if (!empty($job['warranty_expiry'])): ?>
                            <span class="badge text-bg-<?= e($warrBadge) ?>"><?= e($job['warranty_expiry']) ?></span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>
        <div class="card vk-card">
            <div class="card-header bg-transparent fw-semibold">Parts used</div>
            <div class="table-responsive">
                <table class="table mb-0 table-sm">
                    <thead class="table-light"><tr><th>Part</th><th class="text-end">Qty</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php if (!$partRows): ?>
                        <tr><td colspan="3" class="text-muted text-center py-3">No parts recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($partRows as $p): ?>
                            <tr>
                                <td><?= e($p['product_name']) ?></td>
                                <td class="text-end"><?= (int) $p['quantity'] ?></td>
                                <td class="text-end"><?= e(number_format((float) $p['line_total'], 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (!empty($job['latitude']) && !empty($job['longitude']) && $mapsKey !== ''): ?>
            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent fw-semibold">Map</div>
                <div class="card-body">
                    <div id="repairMapView" class="rounded border" style="height:220px;"></div>
                    <a class="btn btn-sm btn-outline-primary mt-2" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode((string) $job['latitude'] . ',' . (string) $job['longitude']) ?>">Navigate</a>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
              if (!window.google || !document.getElementById('repairMapView')) return;
              var p = { lat: <?= json_encode((float) $job['latitude']) ?>, lng: <?= json_encode((float) $job['longitude']) ?> };
              var map = new google.maps.Map(document.getElementById('repairMapView'), { zoom: 15, center: p });
              new google.maps.Marker({ position: p, map: map });
            });
            </script>
        <?php endif; ?>
        <?php if ($jobAtts): ?>
            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent fw-semibold">Field photos</div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php foreach ($jobAtts as $a): ?>
                            <div class="col-6 col-md-4">
                                <a href="<?= e(BASE_URL) ?>/<?= e($a['file_path']) ?>" target="_blank" rel="noopener">
                                    <img src="<?= e(BASE_URL) ?>/<?= e($a['file_path']) ?>" class="img-fluid rounded border" alt="">
                                </a>
                                <div class="small text-muted"><?= e($a['kind']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card vk-card mb-3">
            <div class="card-header bg-transparent fw-semibold">Billing</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between"><span>Advances / job payments</span><strong><?= e(number_format($advances, 2)) ?></strong></div>
                <?php if ($inv): ?>
                    <hr>
                    <div class="fw-semibold">Invoice <?= e($inv['invoice_number']) ?></div>
                    <div>Total <?= e(number_format((float) $inv['grand_total'], 2)) ?> · Paid <?= e(number_format((float) $inv['paid_amount'], 2)) ?></div>
                    <a class="btn btn-sm btn-outline-primary mt-2" href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= (int) $inv['id'] ?>">Open invoice</a>
                <?php else: ?>
                    <p class="text-muted mb-0 mt-2">No invoice linked yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card vk-card">
            <div class="card-header bg-transparent fw-semibold">Payment log</div>
            <div class="card-body p-0">
                <?php if (!$payments): ?>
                    <p class="text-muted small p-3 mb-0">No payments on this job.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($payments as $p): ?>
                            <li class="list-group-item">
                                <div class="fw-semibold"><?= e(number_format((float) $p['amount'], 2)) ?> · <?= e($p['method']) ?></div>
                                <div class="text-muted"><?= e($p['paid_at']) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
