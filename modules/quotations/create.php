<?php
declare(strict_types=1);
/**
 * Premium ERP Quotation Create / Edit screen.
 * Route: /modules/quotations/create.php  (edit via ?id=)
 */
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_require_perm('create');

$editId = (int) ($_GET['id'] ?? 0);
$existing = null;
$existingItems = [];
$existingAttachments = [];
$wantsJson = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
    || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
);

if ($editId > 0) {
    $existing = vk_quotation_get($pdo, $editId);
    if (!$existing) {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Quotation not found'], JSON_THROW_ON_ERROR);
            exit;
        }
        flash_set('error', 'Quotation not found.');
        redirect('/modules/quotations/list.php');
    }
    if (!in_array($existing['status'], ['draft', 'rejected', 'pending_approval'], true)) {
        flash_set('error', 'Only draft / rejected / pending quotations can be edited.');
        redirect('/modules/quotations/view.php?id=' . $editId);
    }
    $existingItems = vk_quotation_items($pdo, $editId);
    try {
        $ast = $pdo->prepare('SELECT * FROM quotation_attachments WHERE quotation_id = ? ORDER BY id DESC');
        $ast->execute([$editId]);
        $existingAttachments = $ast->fetchAll();
    } catch (Throwable $e) {
        $existingAttachments = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $respond = static function (bool $ok, string $message, array $extra = []) use ($wantsJson, $editId): void {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$ok) {
                http_response_code(422);
            }
            echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_THROW_ON_ERROR);
            exit;
        }
        flash_set($ok ? 'success' : 'error', $message);
        if ($ok && !empty($extra['id'])) {
            redirect('/modules/quotations/view.php?id=' . (int) $extra['id']);
        }
        redirect($editId > 0 ? '/modules/quotations/edit.php?id=' . $editId : '/modules/quotations/create.php');
    };

    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $respond(false, 'Security token expired. Please refresh and try again.');
    }

    $header = vk_quotation_header_from_post($_POST);
    $lines = vk_quotation_parse_lines_from_post($_POST);
    $action = (string) ($_POST['form_action'] ?? 'draft');
    $postId = (int) ($_POST['id'] ?? $editId);

    if ($action === 'cancel') {
        if ($postId > 0) {
            $pdo->prepare("UPDATE quotations SET status='cancelled' WHERE id=? AND status IN ('draft','pending_approval','rejected')")->execute([$postId]);
            vk_quotation_log($pdo, $postId, 'cancelled', 'Cancelled from create screen');
            $respond(true, 'Quotation cancelled.', ['id' => $postId, 'redirect' => BASE_URL . '/modules/quotations/list.php']);
        }
        $respond(true, 'Cancelled.', ['redirect' => BASE_URL . '/modules/quotations/list.php']);
    }

    if ($header['customer_id'] <= 0) {
        $respond(false, 'Please select a customer.');
    }
    if ($lines === []) {
        $respond(false, 'Add at least one line item.');
    }

    if ($action === 'submit' || $action === 'save') {
        $header['status'] = 'pending_approval';
        $header['approval_status'] = 'pending';
    } else {
        $header['status'] = 'draft';
        $header['approval_status'] = 'none';
    }

    try {
        $id = vk_quotation_save($pdo, $header, $lines, $postId > 0 ? $postId : null);

        if (!empty($_FILES['attachments'])) {
            vk_quotation_store_attachments($pdo, $id, $_FILES['attachments']);
        }

        if (($action === 'submit' || $action === 'save') && vk_quotation_setting($pdo, 'require_approval', '1') === '1') {
            vk_quotation_submit_approval($pdo, $id);
        } elseif ($action === 'submit' || $action === 'save') {
            $pdo->prepare("UPDATE quotations SET status='approved', approval_status='approved' WHERE id=?")->execute([$id]);
        }

        $q = vk_quotation_get($pdo, $id);
        $msg = match ($action) {
            'submit', 'save' => 'Quotation saved successfully.',
            default => 'Draft saved successfully.',
        };
        $respond(true, $msg, [
            'id' => $id,
            'number' => (string) ($q['quotation_number'] ?? ''),
            'status' => (string) ($q['status'] ?? 'draft'),
            'redirect' => BASE_URL . '/modules/quotations/view.php?id=' . $id,
            'print_url' => BASE_URL . '/modules/quotations/print.php?id=' . $id,
            'email_url' => BASE_URL . '/modules/quotations/email.php?id=' . $id,
        ]);
    } catch (Throwable $e) {
        error_log('quotation save: ' . $e->getMessage());
        $respond(false, 'Could not save quotation: ' . $e->getMessage());
    }
}

// Reference data
$customers = $pdo->query(
    'SELECT c.id, c.name, c.phone, c.email, c.address,
            a.code AS account_code, a.current_balance
     FROM customers c
     LEFT JOIN accounts a ON a.customer_id = c.id
     ORDER BY c.name'
)->fetchAll();
$executives = $pdo->query("SELECT id, fullname FROM users WHERE role NOT IN ('technician') ORDER BY fullname")->fetchAll();
$categories = $pdo->query('SELECT id, name FROM quotation_categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
$templates = $pdo->query('SELECT id, name, payment_terms, delivery_terms, validity_days, terms_html, notes FROM quotation_templates WHERE is_active = 1 ORDER BY name')->fetchAll();
$termRows = $pdo->query('SELECT id, title, body, is_default FROM quotation_terms WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$defaultTerms = '';
foreach ($termRows as $tr) {
    if ((int) ($tr['is_default'] ?? 0) === 1) {
        $defaultTerms = (string) $tr['body'];
        break;
    }
}
if ($defaultTerms === '' && $termRows) {
    $defaultTerms = (string) $termRows[0]['body'];
}

$warehouses = [];
if (db_table_exists($pdo, 'warehouses')) {
    try {
        $warehouses = $pdo->query('SELECT id, name, code FROM warehouses ORDER BY is_default DESC, name')->fetchAll();
    } catch (Throwable $e) {
        $warehouses = [];
    }
}
if ($warehouses === []) {
    $warehouses = [
        ['id' => 0, 'name' => 'Main Warehouse', 'code' => 'MAIN'],
        ['id' => 0, 'name' => 'Kilinochchi', 'code' => 'KIL'],
        ['id' => 0, 'name' => 'Field Stock', 'code' => 'FIELD'],
    ];
}

$validityDefault = (int) vk_quotation_setting($pdo, 'default_validity_days', '30');
$taxPctDefault = (float) vk_quotation_setting($pdo, 'default_tax_pct', '0');
$taxMethodDefault = vk_quotation_setting($pdo, 'default_tax_method', 'exclusive');
$currencyDefault = vk_quotation_setting($pdo, 'default_currency', 'LKR');

$h = $existing ?: [];
$status = (string) ($h['status'] ?? 'draft');
$previewNumber = (string) ($h['quotation_number'] ?? '');
if ($previewNumber === '') {
    // Display-only preview QT-YYYY-000001 (assigned on first save)
    $prefixBase = vk_quotation_setting($pdo, 'prefix', 'QT');
    if ($prefixBase === '' || strtoupper($prefixBase) === 'QTN') {
        $prefixBase = 'QT';
    }
    $prefix = $prefixBase . '-' . date('Y') . '-';
    $stPrev = $pdo->prepare('SELECT quotation_number FROM quotations WHERE quotation_number LIKE ? ORDER BY id DESC LIMIT 1');
    $stPrev->execute([$prefix . '%']);
    $lastPrev = $stPrev->fetchColumn();
    $seqPrev = 1;
    if ($lastPrev && preg_match('/-(\d+)$/', (string) $lastPrev, $mPrev)) {
        $seqPrev = (int) $mPrev[1] + 1;
    }
    $previewNumber = $prefix . str_pad((string) $seqPrev, 6, '0', STR_PAD_LEFT);
}
$qDate = (string) ($h['quotation_date'] ?? date('Y-m-d'));
$validity = (int) ($h['validity_days'] ?? $validityDefault);
$expiry = (string) ($h['expiry_date'] ?? '');
if ($expiry === '') {
    try {
        $expiry = (new DateTime($qDate))->modify('+' . $validity . ' days')->format('Y-m-d');
    } catch (Throwable $e) {
        $expiry = date('Y-m-d', strtotime('+' . $validity . ' days'));
    }
}

$isEdit = $editId > 0;
$pageTitle = $isEdit ? 'Edit Quotation' : 'Create New Quotation';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$selExec = (int) ($h['sales_executive_id'] ?? ($_SESSION['user_id'] ?? 0));
?>
<div class="qtn-page qtn-create-erp" id="qtnCreateApp">
    <nav aria-label="breadcrumb" class="qtn-breadcrumb mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= e(BASE_URL) ?>/modules/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= e(BASE_URL) ?>/modules/quotations/dashboard.php">Sales</a></li>
            <li class="breadcrumb-item"><a href="<?= e(BASE_URL) ?>/modules/quotations/list.php">Quotations</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Edit' : 'Create' ?></li>
        </ol>
    </nav>

    <div class="qtn-sticky-toolbar card vk-card mb-3">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-2">
                <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3">
                    <div>
                        <h1 class="h4 mb-0"><?= e($pageTitle) ?></h1>
                        <div class="small text-muted">
                            <span class="font-monospace fw-semibold text-body" id="disp_quote_no"><?= e($previewNumber) ?></span>
                            <span class="mx-1">·</span>
                            <span class="badge text-bg-<?= e(vk_quotation_status_badge($status)) ?>" id="disp_status"><?= e(vk_quotation_status_label($status)) ?></span>
                        </div>
                    </div>
                </div>
                <div class="qtn-toolbar-actions d-flex flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSaveDraft" title="Ctrl+S"><i class="bi bi-floppy me-1"></i>Save Draft</button>
                    <button type="button" class="btn btn-sm btn-qtn-primary" id="btnSaveQuote"><i class="bi bi-check2-circle me-1"></i>Save Quotation</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPreview" <?= $isEdit ? '' : 'disabled' ?>><i class="bi bi-eye me-1"></i>Preview</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPrint" <?= $isEdit ? '' : 'disabled' ?>><i class="bi bi-printer me-1"></i>Print</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPdf" <?= $isEdit ? '' : 'disabled' ?>><i class="bi bi-filetype-pdf me-1"></i>Download PDF</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnEmail" <?= $isEdit ? '' : 'disabled' ?>><i class="bi bi-envelope me-1"></i>Email</button>
                    <?php if ($isEdit): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/duplicate.php?id=<?= $editId ?>"><i class="bi bi-copy me-1"></i>Duplicate</a>
                    <a class="btn btn-sm btn-outline-success" href="<?= e(BASE_URL) ?>/modules/quotations/convert.php?id=<?= $editId ?>&to=so"><i class="bi bi-cart-check me-1"></i>Sales Order</a>
                    <a class="btn btn-sm btn-outline-success" href="<?= e(BASE_URL) ?>/modules/quotations/convert.php?id=<?= $editId ?>&to=invoice"><i class="bi bi-receipt me-1"></i>Invoice</a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnCancel"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <form method="post" id="quotationForm" enctype="multipart/form-data" novalidate autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="form_action" id="form_action" value="draft">
        <input type="hidden" name="id" id="quotation_id" value="<?= (int) $editId ?>">
        <input type="hidden" name="status" value="draft">

        <div class="row g-3">
            <div class="col-12 col-xl-9">
                <!-- Document meta -->
                <div class="card vk-card qtn-section mb-3">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <strong><i class="bi bi-file-earmark-text me-2 text-primary"></i>Document</strong>
                        <span class="small text-muted">Auto-numbered · Draft by default</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <label class="form-label">Quotation No</label>
                                <input type="text" class="form-control font-monospace" value="<?= e($previewNumber) ?>" readonly>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Quotation Date</label>
                                <input type="date" name="quotation_date" id="quotation_date" class="form-control" value="<?= e($qDate) ?>" required>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">Validity (days)</label>
                                <input type="number" name="validity_days" id="validity_days" class="form-control" min="1" max="365" value="<?= $validity ?>">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" name="expiry_date" id="expiry_date" class="form-control" value="<?= e($expiry) ?>">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">Status</label>
                                <input type="text" class="form-control" value="Draft" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer -->
                <div class="card vk-card qtn-section mb-3">
                    <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <strong><i class="bi bi-person-badge me-2 text-primary"></i>Customer</strong>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                            <i class="bi bi-person-plus me-1"></i>New Customer
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <label class="form-label">Search customer <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" id="customer_search" placeholder="Name, phone, email, code…" value="<?= e((string) ($h['customer_name'] ?? '')) ?>" autocomplete="off">
                                    </div>
                                    <input type="hidden" name="customer_id" id="customer_id" value="<?= (int) ($h['customer_id'] ?? 0) ?>" required>
                                    <div id="customer_results" class="qtn-autocomplete d-none"></div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <label class="form-label">Customer Code</label>
                                <input type="text" name="customer_code" id="customer_code" class="form-control" value="<?= e((string) ($h['customer_code'] ?? '')) ?>" readonly>
                            </div>
                            <div class="col-6 col-lg-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" name="company_name" id="company_name" class="form-control" value="<?= e((string) ($h['company_name'] ?? '')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact_person" id="contact_person" class="form-control" value="<?= e((string) ($h['contact_person'] ?? '')) ?>">
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" id="phone" class="form-control" value="<?= e((string) ($h['phone'] ?? '')) ?>">
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label">Mobile</label>
                                <input type="text" name="mobile" id="mobile" class="form-control" value="<?= e((string) ($h['mobile'] ?? '')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control" value="<?= e((string) ($h['email'] ?? '')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tax Number</label>
                                <input type="text" name="tax_number" id="tax_number" class="form-control" value="<?= e((string) ($h['tax_number'] ?? '')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Credit Limit</label>
                                <input type="number" step="0.01" name="credit_limit" id="credit_limit" class="form-control" value="<?= e((string) ($h['credit_limit'] ?? '0')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Billing Address</label>
                                <textarea name="billing_address" id="billing_address" class="form-control" rows="2"><?= e((string) ($h['billing_address'] ?? '')) ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Shipping / Delivery Address</label>
                                <textarea name="shipping_address" id="shipping_address" class="form-control" rows="2"><?= e((string) ($h['shipping_address'] ?? '')) ?></textarea>
                            </div>
                            <div class="col-12">
                                <div class="qtn-cust-intel small text-muted" id="customer_meta">Select a customer to load account balance and history.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quotation details -->
                <div class="card vk-card qtn-section mb-3">
                    <button class="card-header bg-transparent d-flex justify-content-between align-items-center border-0 w-100 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#qtnDetailsCollapse" aria-expanded="true">
                        <strong><i class="bi bi-sliders me-2 text-primary"></i>Quotation Details</strong>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="collapse show" id="qtnDetailsCollapse">
                        <div class="card-body pt-0">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Reference Number</label>
                                    <input type="text" name="reference_number" class="form-control" value="<?= e((string) ($h['reference_number'] ?? '')) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Customer PO Number</label>
                                    <input type="text" name="customer_po_number" class="form-control" value="<?= e((string) ($h['customer_po_number'] ?? '')) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Sales Person</label>
                                    <select name="sales_executive_id" id="sales_executive_id" class="form-select">
                                        <option value="0">— Select —</option>
                                        <?php foreach ($executives as $u): ?>
                                            <option value="<?= (int) $u['id'] ?>" <?= $selExec === (int) $u['id'] ? 'selected' : '' ?>><?= e((string) $u['fullname']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" class="form-select">
                                        <option value="0">— None —</option>
                                        <?php foreach ($categories as $c): ?>
                                            <option value="<?= (int) $c['id'] ?>" <?= (int) ($h['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Branch</label>
                                    <input type="text" name="branch" class="form-control" value="<?= e((string) ($h['branch'] ?? 'Kilinochchi')) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" name="department" class="form-control" value="<?= e((string) ($h['department'] ?? 'Sales')) ?>" list="deptList">
                                    <datalist id="deptList">
                                        <option value="Sales"><option value="Service"><option value="Projects"><option value="CCTV"><option value="Hardware">
                                    </datalist>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Warehouse</label>
                                    <select name="warehouse" id="warehouse" class="form-select">
                                        <?php foreach ($warehouses as $w): ?>
                                            <?php $wLabel = (string) ($w['name'] ?? ''); ?>
                                            <option value="<?= e($wLabel) ?>" <?= (($h['warehouse'] ?? 'Main Warehouse') === $wLabel) ? 'selected' : '' ?>><?= e($wLabel) ?><?= !empty($w['code']) ? ' (' . e((string) $w['code']) . ')' : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tax Method</label>
                                    <select name="tax_method" id="tax_method" class="form-select">
                                        <?php foreach (['exclusive' => 'Tax exclusive', 'inclusive' => 'Tax inclusive', 'none' => 'No tax'] as $k => $lab): ?>
                                            <option value="<?= $k ?>" <?= (($h['tax_method'] ?? $taxMethodDefault) === $k) ? 'selected' : '' ?>><?= e($lab) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Currency</label>
                                    <select name="currency" id="currency" class="form-select">
                                        <?php foreach (['LKR','USD','EUR','INR','GBP'] as $cur): ?>
                                            <option value="<?= $cur ?>" <?= (($h['currency'] ?? $currencyDefault) === $cur) ? 'selected' : '' ?>><?= $cur ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Exchange Rate</label>
                                    <input type="number" step="0.000001" min="0.000001" name="exchange_rate" id="exchange_rate" class="form-control" value="<?= e((string) ($h['exchange_rate'] ?? '1')) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Payment Terms</label>
                                    <input type="text" name="payment_terms" id="payment_terms" class="form-control" placeholder="e.g. 50% advance" value="<?= e((string) ($h['payment_terms'] ?? '')) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Delivery Terms</label>
                                    <input type="text" name="delivery_terms" id="delivery_terms" class="form-control" placeholder="e.g. 7–14 working days" value="<?= e((string) ($h['delivery_terms'] ?? '')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Customer Notes</label>
                                    <textarea name="notes" id="notes" class="form-control" rows="2"><?= e((string) ($h['notes'] ?? '')) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Internal Notes</label>
                                    <textarea name="internal_notes" id="internal_notes" class="form-control" rows="2"><?= e((string) ($h['internal_notes'] ?? '')) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items -->
                <div class="card vk-card qtn-section mb-3">
                    <div class="card-header bg-transparent">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <strong><i class="bi bi-box-seam me-2 text-primary"></i>Line Items</strong>
                            <div class="d-flex flex-wrap gap-2">
                                <div class="position-relative" style="min-width:min(100%,280px)">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                                        <input type="search" id="product_search" class="form-control" placeholder="Product / barcode search…" autocomplete="off">
                                    </div>
                                    <div id="product_results" class="qtn-autocomplete d-none"></div>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" id="addCustomLine"><i class="bi bi-plus-lg me-1"></i>Add Item</button>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive qtn-items-wrap">
                        <table class="table table-sm align-middle mb-0 qtn-items-table" id="itemsTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width:28px"></th>
                                    <th style="min-width:90px">Item Code</th>
                                    <th style="min-width:90px">Barcode</th>
                                    <th style="min-width:160px">Product</th>
                                    <th style="min-width:120px">Description</th>
                                    <th style="width:70px">Unit</th>
                                    <th class="text-end" style="width:80px">Qty</th>
                                    <th class="text-end" style="width:100px">Unit Price</th>
                                    <th class="text-end" style="width:80px">Disc %</th>
                                    <th class="text-end" style="width:90px">Disc Amt</th>
                                    <th class="text-end" style="width:70px">Tax %</th>
                                    <th class="text-end" style="width:90px">Tax Amt</th>
                                    <th class="text-end" style="width:100px">Line Total</th>
                                    <th style="min-width:110px">Warehouse</th>
                                    <th class="text-end" style="width:70px">Stock</th>
                                    <th style="width:80px"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody"></tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-transparent small text-muted d-flex flex-wrap justify-content-between gap-2">
                        <span><kbd>Enter</kbd> add from search · drag to reorder · duplicate / delete per row</span>
                        <span>Lines: <strong id="disp_line_count">0</strong></span>
                    </div>
                </div>

                <!-- Terms -->
                <div class="card vk-card qtn-section mb-3">
                    <button class="card-header bg-transparent d-flex justify-content-between align-items-center border-0 w-100 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#qtnTermsCollapse" aria-expanded="true">
                        <strong><i class="bi bi-journal-text me-2 text-primary"></i>Terms &amp; Conditions</strong>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="collapse show" id="qtnTermsCollapse">
                        <div class="card-body pt-0">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Template</label>
                                    <select id="terms_template" class="form-select">
                                        <option value="">— Select template —</option>
                                        <?php foreach ($termRows as $tr): ?>
                                            <option value="<?= e((string) $tr['body']) ?>"><?= e((string) $tr['title']) ?></option>
                                        <?php endforeach; ?>
                                        <?php foreach ($templates as $t): ?>
                                            <option value="<?= e((string) ($t['terms_html'] ?? '')) ?>"
                                                data-payment="<?= e((string) ($t['payment_terms'] ?? '')) ?>"
                                                data-delivery="<?= e((string) ($t['delivery_terms'] ?? '')) ?>"
                                                data-validity="<?= (int) ($t['validity_days'] ?? 30) ?>">
                                                Quote tpl: <?= e($t['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Warranty</label>
                                    <input type="text" name="warranty_terms" id="warranty_terms" class="form-control" placeholder="e.g. 12 months manufacturer warranty" value="<?= e((string) ($h['warranty_terms'] ?? '')) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Terms text</label>
                                    <textarea name="terms_html" id="terms_html" class="form-control font-monospace" rows="5"><?= e((string) ($h['terms_html'] ?? $defaultTerms)) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attachments -->
                <div class="card vk-card qtn-section mb-3">
                    <div class="card-header bg-transparent"><strong><i class="bi bi-paperclip me-2 text-primary"></i>Attachments</strong></div>
                    <div class="card-body">
                        <input type="file" name="attachments[]" id="attachments" class="form-control" multiple
                               accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.xls,.xlsx,.doc,.docx,.dwg,.dxf,.csv,application/pdf,image/*,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                        <div class="form-text">PDF, images, Excel, Word, drawings · max 8 MB each</div>
                        <?php if ($existingAttachments): ?>
                        <ul class="list-group list-group-flush mt-3">
                            <?php foreach ($existingAttachments as $att): ?>
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-file-earmark me-2"></i><?= e($att['file_name']) ?></span>
                                    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(base_url((string) $att['file_path'])) ?>">Open</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <div id="attachmentPreview" class="small text-muted mt-2"></div>
                    </div>
                </div>
            </div>

            <!-- Sticky totals -->
            <div class="col-12 col-xl-3">
                <div class="qtn-sticky-totals">
                    <div class="card vk-card qtn-summary-card">
                        <div class="card-header bg-transparent fw-semibold">Totals</div>
                        <div class="card-body">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small mb-0">Overall disc %</label>
                                    <input type="number" step="0.01" name="overall_discount_pct" id="overall_discount_pct" class="form-control form-control-sm" value="<?= e((string) ($h['overall_discount_pct'] ?? '0')) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">Overall disc amt</label>
                                    <input type="number" step="0.01" name="overall_discount_amount" id="overall_discount_amount" class="form-control form-control-sm" value="<?= e((string) ($h['overall_discount_amount'] ?? '0')) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">Shipping</label>
                                    <input type="number" step="0.01" name="shipping_amount" id="shipping_amount" class="form-control form-control-sm" value="<?= e((string) ($h['shipping_amount'] ?? '0')) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">Other charges</label>
                                    <input type="number" step="0.01" name="additional_charges" id="additional_charges" class="form-control form-control-sm" value="<?= e((string) ($h['additional_charges'] ?? '0')) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-0">Round off</label>
                                    <input type="number" step="0.01" name="round_off" id="round_off" class="form-control form-control-sm" value="<?= e((string) ($h['round_off'] ?? '0')) ?>">
                                </div>
                            </div>
                            <dl class="qtn-totals mb-0">
                                <div><dt>Total quantity</dt><dd id="disp_qty">0</dd></div>
                                <div><dt>Subtotal</dt><dd id="disp_subtotal">0.00</dd></div>
                                <div><dt>Total discount</dt><dd id="disp_item_disc">0.00</dd></div>
                                <div><dt>Overall discount</dt><dd id="disp_overall_disc">0.00</dd></div>
                                <div><dt>Total tax</dt><dd id="disp_tax">0.00</dd></div>
                                <div><dt>Shipping</dt><dd id="disp_shipping">0.00</dd></div>
                                <div><dt>Other charges</dt><dd id="disp_additional">0.00</dd></div>
                                <div><dt>Round off</dt><dd id="disp_round">0.00</dd></div>
                                <div class="qtn-grand"><dt>Grand total</dt><dd><span id="disp_currency"><?= e((string) ($h['currency'] ?? $currencyDefault)) ?></span> <span id="disp_grand">0.00</span></dd></div>
                                <div><dt>Est. cost</dt><dd id="disp_cost">0.00</dd></div>
                                <div><dt>Net profit</dt><dd id="disp_profit">0.00</dd></div>
                                <div><dt>Margin</dt><dd id="disp_margin">0%</dd></div>
                            </dl>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary" id="btnSaveDraftSide">Save Draft</button>
                                <button type="button" class="btn btn-qtn-primary" id="btnSaveQuoteSide">Save Quotation</button>
                            </div>
                            <p class="small text-muted mt-2 mb-0" id="autosaveStatus">Ready</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- New customer modal -->
<div class="modal fade" id="newCustomerModal" tabindex="-1" aria-labelledby="newCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newCustomerModalLabel">Create Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newCustomerForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="nc_name" required maxlength="255">
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" id="nc_phone" maxlength="64">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="nc_email" maxlength="255">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" id="nc_address" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="nc_submit">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="qtn-loading d-none" id="qtnLoading" aria-hidden="true">
    <div class="spinner-border text-light" role="status"><span class="visually-hidden">Saving…</span></div>
</div>

<template id="qtnLineTpl">
    <tr class="qtn-line" draggable="true">
        <td class="qtn-drag text-muted"><i class="bi bi-grip-vertical"></i></td>
        <td>
            <input type="hidden" name="item_type[]" class="item-type" value="custom">
            <input type="hidden" name="product_id[]" class="product-id" value="">
            <input type="hidden" name="category_name[]" class="category-name" value="">
            <input type="hidden" name="cost_price[]" class="cost-price" value="0">
            <input type="text" name="product_code[]" class="form-control form-control-sm product-code" placeholder="Code">
        </td>
        <td><input type="text" name="barcode[]" class="form-control form-control-sm barcode" placeholder="Barcode"></td>
        <td><input type="text" name="product_name[]" class="form-control form-control-sm product-name" required placeholder="Product name"></td>
        <td><input type="text" name="description[]" class="form-control form-control-sm description" placeholder="Description"></td>
        <td><input type="text" name="unit[]" class="form-control form-control-sm unit" value="pcs"></td>
        <td><input type="number" step="0.001" min="0" name="quantity[]" class="form-control form-control-sm text-end qty" value="1"></td>
        <td><input type="number" step="0.01" min="0" name="unit_price[]" class="form-control form-control-sm text-end unit-price" value="0"></td>
        <td><input type="number" step="0.01" min="0" max="100" name="discount_pct[]" class="form-control form-control-sm text-end discount-pct" value="0"></td>
        <td><input type="number" step="0.01" min="0" name="discount_amount[]" class="form-control form-control-sm text-end discount-amount" value="0"></td>
        <td><input type="number" step="0.01" min="0" name="tax_pct[]" class="form-control form-control-sm text-end tax-pct" value="<?= e((string) $taxPctDefault) ?>"></td>
        <td class="text-end small tax-amount-disp">0.00</td>
        <td class="text-end fw-semibold line-total">0.00</td>
        <td>
            <select name="line_warehouse[]" class="form-select form-select-sm line-warehouse">
                <?php foreach ($warehouses as $w): ?>
                    <option value="<?= e((string) $w['name']) ?>"><?= e((string) $w['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="text-end">
            <input type="hidden" name="stock_available[]" class="stock-available" value="">
            <span class="badge text-bg-light border stock-badge">—</span>
        </td>
        <td class="text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-secondary dup-line" title="Duplicate"><i class="bi bi-copy"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger rm-line" title="Remove"><i class="bi bi-trash"></i></button>
        </td>
    </tr>
</template>

<script>
window.QTN_CUSTOMERS = <?= json_encode(array_map(static function ($c) {
    return [
        'id' => (int) $c['id'],
        'name' => $c['name'],
        'phone' => $c['phone'],
        'email' => $c['email'],
        'address' => $c['address'],
        'code' => $c['account_code'] ?? ('CUS-' . str_pad((string) $c['id'], 5, '0', STR_PAD_LEFT)),
        'balance' => (float) ($c['current_balance'] ?? 0),
    ];
}, $customers), JSON_THROW_ON_ERROR) ?>;
window.QTN_EXISTING_ITEMS = <?= json_encode($existingItems, JSON_THROW_ON_ERROR) ?>;
window.QTN_DEFAULT_TAX = <?= json_encode($taxPctDefault, JSON_THROW_ON_ERROR) ?>;
window.QTN_DEFAULT_WAREHOUSE = <?= json_encode((string) ($h['warehouse'] ?? 'Main Warehouse'), JSON_THROW_ON_ERROR) ?>;
window.QTN_PRODUCT_API = <?= json_encode(base_url('api/quotations_products.php'), JSON_THROW_ON_ERROR) ?>;
window.QTN_AUTOSAVE_API = <?= json_encode(base_url('api/quotations_autosave.php'), JSON_THROW_ON_ERROR) ?>;
window.QTN_CUSTOMER_CREATE_API = <?= json_encode(base_url('api/quotations_customer_create.php'), JSON_THROW_ON_ERROR) ?>;
window.QTN_EDIT_ID = <?= (int) $editId ?>;
window.QTN_VIEW_BASE = <?= json_encode(BASE_URL . '/modules/quotations/', JSON_THROW_ON_ERROR) ?>;
</script>
<script src="<?= e(base_url('assets/js/quotations-create.js')) ?>?v=<?= e(vk_asset_mtime_version('assets/js/quotations-create.js')) ?>" defer></script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
