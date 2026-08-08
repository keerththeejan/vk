<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/invoices_service.php';
vk_ensure_finance_schemas($pdo);
vk_invoice_require_perm('create');

$prefillRepairId = (int) ($_GET['repair_job_id'] ?? 0);
$prefillCctvId = (int) ($_GET['cctv_job_id'] ?? 0);
$prefillCustomerId = 0;
$prefillLabel = '';
$prefillRepair = null;
$prefillCctv = null;
$perms = vk_invoice_permissions();

if ($prefillRepairId > 0) {
    $st = $pdo->prepare(
        'SELECT r.*, c.name AS customer_name FROM repair_jobs r JOIN customers c ON c.id = r.customer_id WHERE r.id = ?'
    );
    $st->execute([$prefillRepairId]);
    $prefillRepair = $st->fetch();
    if ($prefillRepair && empty($prefillRepair['invoice_id'])) {
        $prefillCustomerId = (int) $prefillRepair['customer_id'];
        $prefillLabel = 'Linked repair job ' . $prefillRepair['job_number'] . ' — ' . $prefillRepair['customer_name'];
    } else {
        $prefillRepairId = 0;
        $prefillRepair = null;
    }
}
if ($prefillCctvId > 0) {
    $st = $pdo->prepare(
        'SELECT v.*, c.name AS customer_name FROM cctv_installations v JOIN customers c ON c.id = v.customer_id WHERE v.id = ?'
    );
    $st->execute([$prefillCctvId]);
    $prefillCctv = $st->fetch();
    if ($prefillCctv && empty($prefillCctv['invoice_id'])) {
        $prefillCustomerId = (int) $prefillCctv['customer_id'];
        $prefillLabel = 'Linked CCTV job ' . $prefillCctv['job_number'] . ' — ' . $prefillCctv['customer_name'];
    } else {
        $prefillCctvId = 0;
        $prefillCctv = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired. Please refresh and try again.');
        redirect('/modules/invoices/create.php');
    }

    $header = vk_invoice_header_from_post($_POST);
    $header['repair_job_id'] = (int) ($_POST['repair_job_id'] ?? 0);
    $header['cctv_job_id'] = (int) ($_POST['cctv_job_id'] ?? 0);
    $lines = vk_invoice_parse_lines_from_post($_POST);
    $action = (string) ($_POST['form_action'] ?? 'create');
    $header['is_draft'] = ($action === 'draft');

    // Payments
    $payAmounts = $_POST['pay_amount'] ?? [];
    $payMethods = $_POST['pay_method'] ?? [];
    $payNotes = $_POST['pay_note'] ?? [];
    $paymentRows = [];
    $nPay = is_array($payAmounts) ? count($payAmounts) : 0;
    for ($i = 0; $i < $nPay; $i++) {
        $amt = round((float) ($payAmounts[$i] ?? 0), 2);
        $method = trim((string) ($payMethods[$i] ?? ''));
        if ($amt <= 0) {
            continue;
        }
        if (!in_array($method, ['cash', 'card', 'bank', 'online'], true)) {
            flash_set('error', 'Select a valid payment method for each payment row with an amount.');
            redirect('/modules/invoices/create.php');
        }
        $paymentRows[] = [
            'amount' => $amt,
            'method' => $method,
            'note' => trim((string) ($payNotes[$i] ?? '')),
        ];
    }

    try {
        $invoiceId = vk_invoice_create($pdo, $header, $lines, $header['is_draft'] ? [] : $paymentRows);
        $inv = vk_invoice_get($pdo, $invoiceId);
        $msg = $header['is_draft']
            ? 'Draft invoice ' . ($inv['invoice_number'] ?? '') . ' saved.'
            : 'Invoice ' . ($inv['invoice_number'] ?? '') . ' created successfully.';
        flash_set('success', $msg);
        redirect('/modules/invoices/view.php?id=' . $invoiceId);
    } catch (InvalidArgumentException $e) {
        flash_set('error', $e->getMessage());
        redirect('/modules/invoices/create.php');
    } catch (Throwable $e) {
        flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not create invoice.');
        redirect('/modules/invoices/create.php');
    }
}

$pageTitle = 'Create invoice';
$canViewCost = !empty($perms['view_cost']);
$staff = [];
try {
    $nameCol = db_column_exists($pdo, 'users', 'fullname') ? 'fullname' : (db_column_exists($pdo, 'users', 'name') ? 'name' : 'username');
    $staff = $pdo->query(
        "SELECT id, COALESCE(NULLIF({$nameCol}, ''), username) AS name FROM users WHERE role NOT IN ('technician') ORDER BY name LIMIT 100"
    )->fetchAll() ?: [];
} catch (Throwable $e) {
    $staff = [];
}

$products = $pdo->query('SELECT id, name, price, stock, category FROM products ORDER BY name')->fetchAll();
$extraScripts = '<script>window.VK_INVOICE_CFG=' . json_encode([
    'mode' => 'create',
    'canViewCost' => $canViewCost,
    'productsUrl' => BASE_URL . '/api/invoices_products.php',
    'customersUrl' => BASE_URL . '/api/customers_search.php',
], JSON_THROW_ON_ERROR) . ';</script>'
    . '<script src="' . e(BASE_URL) . '/assets/js/invoice_create.js?v=3"></script>';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$notesPrefill = $prefillRepair
    ? 'Repair job: ' . $prefillRepair['job_number'] . ' — Est. ' . formatCurrency($prefillRepair['estimated_cost'])
    : ($prefillCctv
        ? 'CCTV job: ' . $prefillCctv['job_number'] . ' @ ' . $prefillCctv['location']
        : '');
?>
<div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <a href="<?= e(BASE_URL) ?>/modules/invoices/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <div class="small text-muted d-none d-md-block">Shortcuts: <kbd>Alt</kbd>+<kbd>A</kbd> add row · <kbd>Alt</kbd>+<kbd>S</kbd> save</div>
</div>
<h1 class="h3 mb-3">Create invoice</h1>

<?php if ($prefillLabel !== ''): ?>
    <div class="alert alert-info"><?= e($prefillLabel) ?></div>
<?php endif; ?>

<form method="post" id="invoiceForm" data-loading class="row g-3 vk-invoice-form" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="form_action" id="form_action" value="create">
    <input type="hidden" name="repair_job_id" value="<?= $prefillRepairId > 0 ? (string) $prefillRepairId : '' ?>">
    <input type="hidden" name="cctv_job_id" value="<?= $prefillCctvId > 0 ? (string) $prefillCctvId : '' ?>">

    <div class="col-12 col-xl-9">
        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <div class="card vk-card h-100">
                    <div class="card-header bg-transparent fw-semibold">Customer</div>
                    <div class="card-body position-relative">
                        <input type="hidden" name="customer_id" id="customer_id" value="<?= $prefillCustomerId > 0 ? (string) $prefillCustomerId : '' ?>" required>
                        <label class="form-label" for="customer_search">Search customer</label>
                        <input type="text" class="form-control" id="customer_search" placeholder="Type name, phone, or email" autocomplete="off"
                            value="<?= $prefillRepair ? e($prefillRepair['customer_name']) : ($prefillCctv ? e($prefillCctv['customer_name']) : '') ?>">
                        <div class="list-group mt-1 shadow-sm position-absolute w-100 d-none" id="customer_results" style="z-index: 20; max-height: 220px; overflow-y: auto;"></div>
                        <div class="mt-2 small text-muted" id="customer_selected">
                            <?= $prefillCustomerId > 0 ? 'Selected from job link.' : 'No customer selected.' ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-7">
                <div class="card vk-card">
                    <div class="card-header bg-transparent fw-semibold">Details</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="invoice_date">Invoice date</label>
                            <input type="date" class="form-control" name="invoice_date" id="invoice_date" required value="<?= e(date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="due_date">Due date</label>
                            <input type="date" class="form-control" name="due_date" id="due_date" value="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="currency">Currency</label>
                            <select class="form-select" name="currency" id="currency">
                                <option value="LKR" selected>LKR</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="branch">Branch</label>
                            <input type="text" class="form-control" name="branch" id="branch" maxlength="128" placeholder="Main">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="salesperson_id">Salesperson</label>
                            <select class="form-select" name="salesperson_id" id="salesperson_id">
                                <option value="">— Select —</option>
                                <?php foreach ($staff as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="reference_number">Reference</label>
                            <input type="text" class="form-control" name="reference_number" id="reference_number" maxlength="128">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="payment_method">Payment method</label>
                            <select class="form-select" name="payment_method" id="payment_method">
                                <option value="">— Select —</option>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="bank">Bank</option>
                                <option value="online">Online</option>
                                <option value="credit">Credit</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Remarks</label>
                            <textarea class="form-control" name="notes" id="notes" rows="2" maxlength="2000"><?= e($notesPrefill) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="terms">Terms &amp; conditions</label>
                            <textarea class="form-control" name="terms" id="terms" rows="2" maxlength="4000"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="internal_notes">Internal notes</label>
                            <textarea class="form-control" name="internal_notes" id="internal_notes" rows="2" maxlength="2000"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card vk-card">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="fw-semibold">Line items</span>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <div class="input-group input-group-sm" style="min-width:220px;max-width:320px">
                                <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                                <input type="text" class="form-control" id="barcode_search" placeholder="Barcode / SKU scan" autocomplete="off">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addProductLine" title="Alt+A"><i class="bi bi-plus-lg"></i> Add product</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="addServiceLine"><i class="bi bi-wrench-adjustable"></i> Add service</button>
                            <button type="button" class="btn btn-sm btn-outline-info" id="btnRecentProducts"><i class="bi bi-clock-history"></i> Recent</button>
                            <button type="button" class="btn btn-sm btn-outline-warning" id="btnFavouriteProducts"><i class="bi bi-star"></i> Favourites</button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle table-sm vk-invoice-lines" id="linesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:36px">#</th>
                                        <th style="min-width:180px">Product</th>
                                        <th style="min-width:160px">Description</th>
                                        <th style="width:70px">Unit</th>
                                        <th style="width:80px">Qty</th>
                                        <th style="width:100px">Unit Price</th>
                                        <th style="width:90px">Disc Type</th>
                                        <th style="width:80px">Discount</th>
                                        <th style="width:90px">Disc Amt</th>
                                        <th style="width:70px">Tax %</th>
                                        <th style="width:100px">Net Amount</th>
                                        <th style="width:110px"></th>
                                    </tr>
                                </thead>
                                <tbody id="linesBody"></tbody>
                            </table>
                        </div>
                        <div id="productPreview" class="border-top px-3 py-2 small text-muted d-none">
                            <div class="d-flex gap-3 align-items-center flex-wrap">
                                <img id="productPreviewImg" src="" alt="" class="rounded d-none" style="width:48px;height:48px;object-fit:cover">
                                <div>
                                    <span class="fw-semibold text-body" id="productPreviewName"></span>
                                    <span class="ms-2">Stock: <span id="productPreviewStock">—</span></span>
                                    <span class="ms-2">Price: <span id="productPreviewPrice">—</span></span>
                                    <?php if ($canViewCost): ?>
                                        <span class="ms-2">Cost: <span id="productPreviewCost">—</span></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Section -->
            <div class="col-12">
                <div class="card vk-card border-primary">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="fw-semibold"><i class="bi bi-cash-coin me-1"></i>Payment</span>
                        <button type="button" class="btn btn-sm btn-outline-success" id="addPaymentRow"><i class="bi bi-plus-lg"></i> Add payment row</button>
                    </div>
                    <div class="card-body">
                        <div id="paymentRows">
                            <div class="row g-2 align-items-end payment-row mb-2" data-payment-row="0">
                                <div class="col-12 col-sm-3">
                                    <label class="form-label small mb-1">Amount</label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm pay-amount" name="pay_amount[]" placeholder="0.00">
                                </div>
                                <div class="col-12 col-sm-3">
                                    <label class="form-label small mb-1">Method</label>
                                    <select class="form-select form-select-sm pay-method" name="pay_method[]">
                                        <option value="">— Select —</option>
                                        <option value="cash">Cash</option>
                                        <option value="card">Card</option>
                                        <option value="bank">Bank</option>
                                        <option value="online">Online</option>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <label class="form-label small mb-1">Note</label>
                                    <input type="text" class="form-control form-control-sm pay-note" name="pay_note[]" maxlength="255" placeholder="Optional note">
                                </div>
                                <div class="col-12 col-sm-2 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger rm-pay-row d-none" title="Remove"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="vk-payment-summary-wrap mt-3">
                            <div class="vk-payment-summary-card">
                                <dl class="vk-invoice-total-list vk-payment-total-list mb-0">
                                    <div class="vk-total-row"><dt>Total Items</dt><dd id="pay_total_items">0</dd></div>
                                    <div class="vk-total-row"><dt>Total Payable</dt><dd id="pay_total_payable">0.00</dd></div>
                                    <div class="vk-total-row"><dt>Total Paying</dt><dd class="text-success" id="pay_total_paying">0.00</dd></div>
                                    <div class="vk-total-row"><dt>Change Return</dt><dd class="text-info" id="pay_change_return">0.00</dd></div>
                                    <div class="vk-total-row vk-total-row-balance"><dt>Balance Due</dt><dd class="text-danger" id="pay_balance_due">0.00</dd></div>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <span class="small text-muted">Leave payment empty to create as unpaid</span>
                        <button type="submit" class="btn btn-primary btn-lg" data-action="create"><i class="bi bi-check2-circle me-1"></i>Create Invoice</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sticky summary panel -->
    <div class="col-12 col-xl-3">
        <div class="card vk-card vk-invoice-sticky-summary">
            <div class="card-header bg-transparent fw-semibold">Invoice Summary</div>
            <div class="card-body">
                <dl class="vk-invoice-total-list mb-0">
                    <div class="vk-total-row"><dt>Subtotal</dt><dd id="disp_subtotal">0.00</dd></div>
                    <div class="vk-total-row"><dt>Total Item Discount</dt><dd id="disp_item_discount">0.00</dd></div>
                    <div class="vk-total-row align-items-center">
                        <dt>Invoice Discount</dt>
                        <dd class="d-flex gap-1 justify-content-end">
                            <select class="form-select form-select-sm" name="invoice_discount_type" id="invoice_discount_type" style="width:auto;min-width:4.5rem">
                                <option value="percent">%</option>
                                <option value="fixed" selected>LKR</option>
                            </select>
                        </dd>
                    </div>
                    <div class="vk-total-row">
                        <dt></dt>
                        <dd><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="invoice_discount_value" id="invoice_discount_value" value="0"></dd>
                    </div>
                    <div class="vk-total-row"><dt>Invoice Disc. Amt</dt><dd id="disp_invoice_discount">0.00</dd></div>
                    <div class="vk-total-row">
                        <dt>Shipping</dt>
                        <dd><input type="number" step="0.01" class="form-control form-control-sm text-end" name="shipping_amount" id="shipping_amount" value="0"></dd>
                    </div>
                    <div class="vk-total-row">
                        <dt>Adjustment</dt>
                        <dd><input type="number" step="0.01" class="form-control form-control-sm text-end" name="adjustment_amount" id="adjustment_amount" value="0"></dd>
                    </div>
                    <div class="vk-total-row"><dt>Tax</dt><dd id="disp_tax">0.00</dd></div>
                    <input type="hidden" name="tax" id="tax" value="0">
                    <div class="vk-total-row">
                        <dt>Round Off</dt>
                        <dd><input type="number" step="0.01" class="form-control form-control-sm text-end" name="round_off" id="round_off" value="0"></dd>
                    </div>
                    <div class="vk-total-row vk-total-row-grand"><dt>Grand Total</dt><dd id="disp_grand">0.00</dd></div>
                    <div class="vk-total-row vk-total-row-balance"><dt>Balance</dt><dd id="disp_balance">0.00</dd></div>
                </dl>
                <div class="d-grid gap-2 mt-3">
                    <button type="submit" class="btn btn-primary" data-action="create"><i class="bi bi-check2-circle me-1"></i>Create Invoice</button>
                    <button type="submit" class="btn btn-outline-secondary" data-action="draft"><i class="bi bi-file-earmark me-1"></i>Save Draft</button>
                    <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/invoices/list.php"><i class="bi bi-arrow-left me-1"></i>Back</a>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Product picker modal -->
<div class="modal fade" id="productPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Search products</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="search" class="form-control mb-3" id="productPickerSearch" placeholder="Search name, SKU, barcode…">
                <div class="list-group" id="productPickerResults"></div>
            </div>
        </div>
    </div>
</div>

<template id="lineTplProduct">
    <tr class="line-row" data-line-kind="product">
        <td class="line-no text-muted small">1</td>
        <td>
            <input type="hidden" name="line_type[]" value="product">
            <input type="hidden" name="cost_price[]" class="cost-price" value="0">
            <input type="hidden" name="item_code[]" class="item-code" value="">
            <input type="hidden" name="product_id[]" class="product-id" value="">
            <div class="input-group input-group-sm">
                <input type="text" class="form-control product-search" placeholder="Search product…" autocomplete="off">
                <button type="button" class="btn btn-outline-secondary btn-pick-product" title="Search"><i class="bi bi-search"></i></button>
            </div>
            <div class="list-group product-results shadow-sm position-absolute d-none" style="z-index:30;max-height:180px;overflow:auto;min-width:220px"></div>
            <input type="hidden" name="line_description[]" class="line-desc-hidden" value="">
        </td>
        <td class="align-middle"><input type="text" class="form-control form-control-sm line-desc" placeholder="Description" maxlength="512"></td>
        <td><input type="text" class="form-control form-control-sm unit-input" name="unit[]" value="pcs" maxlength="32"></td>
        <td><input type="number" class="form-control form-control-sm qty-input" name="qty[]" min="1" step="1" value="1" required></td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm unit-price-input" name="unit_price[]" value="0" required></td>
        <td>
            <select class="form-select form-select-sm discount-type" name="discount_type[]">
                <option value="percent">%</option>
                <option value="fixed">LKR</option>
            </select>
        </td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm discount-value" name="discount_value[]" value="0"></td>
        <td><span class="discount-amount text-muted small">0.00</span></td>
        <td><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm tax-pct" name="tax_pct[]" value="0"></td>
        <td><span class="line-total fw-semibold">0.00</span></td>
        <td class="text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-secondary dup-line" title="Duplicate"><i class="bi bi-copy"></i></button>
            <button type="button" class="btn btn-sm btn-outline-secondary move-up" title="Move up"><i class="bi bi-arrow-up"></i></button>
            <button type="button" class="btn btn-sm btn-outline-secondary move-down" title="Move down"><i class="bi bi-arrow-down"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger rm-line" title="Remove"><i class="bi bi-x-lg"></i></button>
        </td>
    </tr>
</template>

<template id="lineTplService">
    <tr class="line-row" data-line-kind="service">
        <td class="line-no text-muted small">1</td>
        <td>
            <input type="hidden" name="line_type[]" value="service">
            <input type="hidden" name="product_id[]" class="product-id" value="">
            <input type="hidden" name="cost_price[]" class="cost-price" value="0">
            <input type="hidden" name="item_code[]" class="item-code" value="SVC">
            <span class="badge text-bg-light text-dark border">Service</span>
            <input type="hidden" class="line-desc-hidden" value="">
        </td>
        <td class="align-middle"><input type="text" class="form-control form-control-sm line-desc" name="line_description[]" placeholder="Service description" required maxlength="512"></td>
        <td><input type="text" class="form-control form-control-sm unit-input" name="unit[]" value="job" maxlength="32"></td>
        <td><input type="number" class="form-control form-control-sm qty-input" name="qty[]" min="1" step="1" value="1" required></td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm unit-price-input" name="unit_price[]" value="0" required></td>
        <td>
            <select class="form-select form-select-sm discount-type" name="discount_type[]">
                <option value="percent">%</option>
                <option value="fixed">LKR</option>
            </select>
        </td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm discount-value" name="discount_value[]" value="0"></td>
        <td><span class="discount-amount text-muted small">0.00</span></td>
        <td><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm tax-pct" name="tax_pct[]" value="0"></td>
        <td><span class="line-total fw-semibold">0.00</span></td>
        <td class="text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-secondary dup-line" title="Duplicate"><i class="bi bi-copy"></i></button>
            <button type="button" class="btn btn-sm btn-outline-secondary move-up" title="Move up"><i class="bi bi-arrow-up"></i></button>
            <button type="button" class="btn btn-sm btn-outline-secondary move-down" title="Move down"><i class="bi bi-arrow-down"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger rm-line" title="Remove"><i class="bi bi-x-lg"></i></button>
        </td>
    </tr>
</template>

<?php
// Keep product catalog available for fallback select (data attribute)
$productCatalogJson = [];
foreach ($products as $p) {
    $productCatalogJson[] = [
        'id' => (int) $p['id'],
        'name' => $p['name'],
        'unit_price' => (float) $p['price'],
        'stock_available' => (int) $p['stock'],
        'product_code' => (string) $p['id'],
    ];
}
?>
<script>window.VK_PRODUCT_CATALOG=<?= json_encode($productCatalogJson, JSON_THROW_ON_ERROR) ?>;</script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
