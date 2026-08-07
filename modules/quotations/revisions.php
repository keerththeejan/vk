<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    $pageTitle = 'Quotation Revisions';
    $extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
    require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
    $recent = $pdo->query(
        "SELECT q.id, q.quotation_number, q.revision_no, q.status, c.name AS customer_name, q.updated_at
         FROM quotations q
         JOIN customers c ON c.id = q.customer_id
         WHERE q.revision_no > 0 OR EXISTS (SELECT 1 FROM quotation_revisions r WHERE r.quotation_id = q.id)
         ORDER BY q.updated_at DESC, q.id DESC
         LIMIT 50"
    )->fetchAll();
    ?>
    <div class="qtn-page">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <p class="qtn-eyebrow mb-1">Quotations</p>
                <h1 class="h3 mb-0">Quotation Revisions</h1>
            </div>
            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/list.php">Quotation list</a>
        </div>
        <div class="card vk-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Quotation</th><th>Customer</th><th>Rev</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$recent): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No revisions yet. Edit a quotation to create revision history.</td></tr>
                    <?php else: foreach ($recent as $r): ?>
                        <tr>
                            <td><?= e($r['quotation_number']) ?></td>
                            <td><?= e($r['customer_name']) ?></td>
                            <td>R<?= (int) $r['revision_no'] ?></td>
                            <td><?= e(vk_quotation_status_label($r['status'])) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?id=<?= (int) $r['id'] ?>">Compare / restore</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
    exit;
}

$q = vk_quotation_get($pdo, $id);
if (!$q) {
    flash_set('error', 'Quotation not found.');
    redirect('/modules/quotations/revisions.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/quotations/revisions.php?id=' . $id);
    }
    $restoreId = (int) ($_POST['restore_rev_id'] ?? 0);
    if ($restoreId <= 0) {
        flash_set('error', 'Select a revision to restore.');
        redirect('/modules/quotations/revisions.php?id=' . $id);
    }
    $st = $pdo->prepare('SELECT snapshot_json FROM quotation_revisions WHERE id = ? AND quotation_id = ?');
    $st->execute([$restoreId, $id]);
    $snapRaw = $st->fetchColumn();
    if (!$snapRaw) {
        flash_set('error', 'Revision not found.');
        redirect('/modules/quotations/revisions.php?id=' . $id);
    }
    try {
        $snap = json_decode((string) $snapRaw, true, 512, JSON_THROW_ON_ERROR);
        $header = $snap['header'] ?? [];
        $items = $snap['items'] ?? [];
        $lines = [];
        foreach ($items as $ln) {
            $lines[] = [
                'sort_order' => (int) ($ln['sort_order'] ?? 0),
                'item_type' => $ln['item_type'] ?? 'product',
                'product_id' => !empty($ln['product_id']) ? (int) $ln['product_id'] : null,
                'product_code' => $ln['product_code'] ?? null,
                'barcode' => $ln['barcode'] ?? null,
                'product_name' => $ln['product_name'] ?? 'Item',
                'category_name' => $ln['category_name'] ?? null,
                'description' => $ln['description'] ?? null,
                'unit' => $ln['unit'] ?? 'pcs',
                'quantity' => (float) ($ln['quantity'] ?? 1),
                'unit_price' => (float) ($ln['unit_price'] ?? 0),
                'cost_price' => (float) ($ln['cost_price'] ?? 0),
                'discount_pct' => (float) ($ln['discount_pct'] ?? 0),
                'discount_amount' => (float) ($ln['discount_amount'] ?? 0),
                'tax_pct' => (float) ($ln['tax_pct'] ?? 0),
            ];
        }
        $headerData = [
            'customer_id' => (int) ($header['customer_id'] ?? $q['customer_id']),
            'company_name' => $header['company_name'] ?? null,
            'contact_person' => $header['contact_person'] ?? null,
            'phone' => $header['phone'] ?? null,
            'email' => $header['email'] ?? null,
            'billing_address' => $header['billing_address'] ?? null,
            'shipping_address' => $header['shipping_address'] ?? null,
            'currency' => $header['currency'] ?? 'LKR',
            'sales_executive_id' => $header['sales_executive_id'] ?? null,
            'category_id' => $header['category_id'] ?? null,
            'template_id' => $header['template_id'] ?? null,
            'reference_number' => $header['reference_number'] ?? null,
            'quotation_date' => $header['quotation_date'] ?? date('Y-m-d'),
            'expiry_date' => $header['expiry_date'] ?? null,
            'payment_terms' => $header['payment_terms'] ?? null,
            'delivery_terms' => $header['delivery_terms'] ?? null,
            'validity_days' => (int) ($header['validity_days'] ?? 30),
            'tax_method' => $header['tax_method'] ?? 'exclusive',
            'status' => $q['status'],
            'approval_status' => $q['approval_status'],
            'overall_discount_pct' => (float) ($header['overall_discount_pct'] ?? 0),
            'overall_discount_amount' => (float) ($header['overall_discount_amount'] ?? 0),
            'shipping_amount' => (float) ($header['shipping_amount'] ?? 0),
            'additional_charges' => (float) ($header['additional_charges'] ?? 0),
            'round_off' => (float) ($header['round_off'] ?? 0),
            'notes' => $header['notes'] ?? null,
            'internal_notes' => $header['internal_notes'] ?? null,
            'terms_html' => $header['terms_html'] ?? null,
            'expected_closing_date' => $header['expected_closing_date'] ?? null,
            'branch' => $header['branch'] ?? null,
        ];
        vk_quotation_save($pdo, $headerData, $lines, $id);
        vk_quotation_log($pdo, $id, 'revision_restored', 'Restored from revision #' . $restoreId);
        flash_set('success', 'Revision restored successfully.');
        redirect('/modules/quotations/view.php?id=' . $id);
    } catch (Throwable $e) {
        flash_set('error', 'Restore failed: ' . $e->getMessage());
        redirect('/modules/quotations/revisions.php?id=' . $id);
    }
}

$revs = $pdo->prepare(
    'SELECT r.*, u.fullname AS created_by_name FROM quotation_revisions r
     LEFT JOIN users u ON u.id = r.created_by
     WHERE r.quotation_id = ? ORDER BY r.revision_no DESC'
);
$revs->execute([$id]);
$revRows = $revs->fetchAll();

$revA = (int) ($_GET['rev_a'] ?? 0);
$revB = (int) ($_GET['rev_b'] ?? 0);
$compare = null;
if ($revA > 0 && $revB > 0) {
    $cmpSt = $pdo->prepare('SELECT id, revision_no, snapshot_json FROM quotation_revisions WHERE quotation_id = ? AND id IN (?,?)');
    $cmpSt->execute([$id, $revA, $revB]);
    $cmpRows = [];
    while ($row = $cmpSt->fetch()) {
        $cmpRows[(int) $row['id']] = $row;
    }
    if (isset($cmpRows[$revA], $cmpRows[$revB])) {
        $parseSnap = static function (array $row): array {
            $data = json_decode((string) $row['snapshot_json'], true) ?: [];
            $h = $data['header'] ?? [];
            return [
                'revision_no' => (int) $row['revision_no'],
                'grand_total' => (float) ($h['grand_total'] ?? 0),
                'subtotal' => (float) ($h['subtotal'] ?? 0),
                'item_count' => count($data['items'] ?? []),
                'status' => (string) ($h['status'] ?? ''),
            ];
        };
        $compare = ['a' => $parseSnap($cmpRows[$revA]), 'b' => $parseSnap($cmpRows[$revB])];
    }
}

$pageTitle = 'Revisions — ' . $q['quotation_number'];
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="mb-3">
        <a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <h1 class="h3 mt-2 mb-1">Revision History</h1>
        <p class="text-muted mb-0"><?= e($q['quotation_number']) ?></p>
    </div>

    <?php if ($compare): ?>
    <div class="card vk-card mb-3">
        <div class="card-header bg-transparent fw-semibold">Compare revisions</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <h6>Revision R<?= (int) $compare['a']['revision_no'] ?></h6>
                    <ul class="list-unstyled small mb-0">
                        <li>Grand total: <strong><?= e(number_format($compare['a']['grand_total'], 2)) ?></strong></li>
                        <li>Subtotal: <?= e(number_format($compare['a']['subtotal'], 2)) ?></li>
                        <li>Line items: <?= (int) $compare['a']['item_count'] ?></li>
                        <li>Status: <?= e($compare['a']['status']) ?></li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Revision R<?= (int) $compare['b']['revision_no'] ?></h6>
                    <ul class="list-unstyled small mb-0">
                        <li>Grand total: <strong><?= e(number_format($compare['b']['grand_total'], 2)) ?></strong></li>
                        <li>Subtotal: <?= e(number_format($compare['b']['subtotal'], 2)) ?></li>
                        <li>Line items: <?= (int) $compare['b']['item_count'] ?></li>
                        <li>Status: <?= e($compare['b']['status']) ?></li>
                    </ul>
                </div>
            </div>
            <p class="small text-muted mt-2 mb-0">
                Δ Total: <?= e(number_format($compare['b']['grand_total'] - $compare['a']['grand_total'], 2)) ?>
                · Δ Items: <?= (int) ($compare['b']['item_count'] - $compare['a']['item_count']) ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <form class="card vk-card mb-3" method="get">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label small mb-0">Revision A</label>
                <select name="rev_a" class="form-select form-select-sm">
                    <option value="0">—</option>
                    <?php foreach ($revRows as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= $revA === (int) $r['id'] ? 'selected' : '' ?>>R<?= (int) $r['revision_no'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label small mb-0">Revision B</label>
                <select name="rev_b" class="form-select form-select-sm">
                    <option value="0">—</option>
                    <?php foreach ($revRows as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= $revB === (int) $r['id'] ? 'selected' : '' ?>>R<?= (int) $r['revision_no'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary">Compare</button>
        </div>
    </form>

    <div class="card vk-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Rev</th><th>Summary</th><th>By</th><th>Date</th><th class="text-end">Restore</th></tr>
                </thead>
                <tbody>
                <?php if (!$revRows): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No revisions saved yet.</td></tr>
                <?php else: foreach ($revRows as $r): ?>
                    <tr>
                        <td>R<?= (int) $r['revision_no'] ?></td>
                        <td><?= e((string) ($r['change_summary'] ?: 'Snapshot')) ?></td>
                        <td class="small"><?= e((string) ($r['created_by_name'] ?: 'System')) ?></td>
                        <td class="small"><?= e($r['created_at']) ?></td>
                        <td class="text-end">
                            <form method="post" class="d-inline" onsubmit="return confirm('Restore this revision? Current data will be overwritten.')">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="restore_rev_id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning">Restore</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
