<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/invoices_service.php';
vk_ensure_finance_schemas($pdo);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$inv = $id > 0 ? vk_invoice_get($pdo, $id) : null;
if (!$inv) {
    flash_set('error', 'Invoice not found.');
    redirect('/modules/invoices/list.php');
}

$perms = vk_invoice_permissions();
$isDraft = !empty($inv['is_draft']) || ($inv['status'] ?? '') === 'draft';
if ($isDraft) {
    vk_invoice_require_perm('edit_draft');
} else {
    vk_invoice_require_perm('edit');
}

if (($inv['status'] ?? '') === 'cancelled') {
    flash_set('error', 'Cancelled invoices cannot be edited.');
    redirect('/modules/invoices/view.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired. Please refresh and try again.');
        redirect('/modules/invoices/edit.php?id=' . $id);
    }

    $action = (string) ($_POST['form_action'] ?? 'update');

    if ($action === 'cancel') {
        try {
            vk_invoice_require_perm('cancel');
            vk_invoice_cancel($pdo, $id, trim((string) ($_POST['edit_reason'] ?? 'Cancelled from edit')));
            flash_set('success', 'Invoice cancelled.');
            redirect('/modules/invoices/view.php?id=' . $id);
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
            redirect('/modules/invoices/edit.php?id=' . $id);
        }
    }

    if ($action === 'delete') {
        try {
            vk_invoice_require_perm('delete');
            vk_invoice_delete($pdo, $id);
            flash_set('success', 'Invoice deleted.');
            redirect('/modules/invoices/list.php');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
            redirect('/modules/invoices/edit.php?id=' . $id);
        }
    }

    $header = vk_invoice_header_from_post($_POST);
    $header['is_draft'] = ($action === 'draft') || ($isDraft && $action !== 'update' && $action !== 'create');
    if ($action === 'update' || $action === 'create') {
        $header['is_draft'] = false;
    }
    $lines = vk_invoice_parse_lines_from_post($_POST);

    try {
        vk_invoice_update($pdo, $id, $header, $lines);
        flash_set('success', 'Invoice Updated Successfully.');
        redirect('/modules/invoices/view.php?id=' . $id);
    } catch (InvalidArgumentException $e) {
        flash_set('error', $e->getMessage());
        redirect('/modules/invoices/edit.php?id=' . $id);
    } catch (Throwable $e) {
        flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not update invoice.');
        redirect('/modules/invoices/edit.php?id=' . $id);
    }
}

$items = vk_invoice_items($pdo, $id);
$canViewCost = !empty($perms['view_cost']);
$pageTitle = 'Edit invoice ' . $inv['invoice_number'];

$staff = [];
try {
    $nameCol = db_column_exists($pdo, 'users', 'fullname') ? 'fullname' : (db_column_exists($pdo, 'users', 'name') ? 'name' : 'username');
    $staff = $pdo->query(
        "SELECT id, COALESCE(NULLIF({$nameCol}, ''), username) AS name FROM users WHERE role NOT IN ('technician') ORDER BY name LIMIT 100"
    )->fetchAll() ?: [];
} catch (Throwable $e) {
    $staff = [];
}

$existingLines = [];
foreach ($items as $it) {
    $existingLines[] = [
        'item_type' => $it['item_type'] ?? 'product',
        'product_id' => $it['product_id'] ?? null,
        'product_name' => $it['product_name'] ?? null,
        'product_stock' => $it['product_stock'] ?? null,
        'item_code' => $it['item_code'] ?? null,
        'line_description' => $it['line_description'] ?? ($it['product_name'] ?? null),
        'unit' => $it['unit'] ?? 'pcs',
        'quantity' => (float) $it['quantity'],
        'unit_price' => (float) $it['unit_price'],
        'discount_type' => $it['discount_type'] ?? 'percent',
        'discount_value' => (float) ($it['discount_value'] ?? 0),
        'tax_pct' => (float) ($it['tax_pct'] ?? 0),
        'cost_price' => (float) ($it['cost_price'] ?? 0),
    ];
}

$extraScripts = '<script>window.VK_INVOICE_CFG=' . json_encode([
    'mode' => 'edit',
    'canViewCost' => $canViewCost,
    'paidAmount' => (float) $inv['paid_amount'],
    'productsUrl' => BASE_URL . '/api/invoices_products.php',
    'customersUrl' => BASE_URL . '/api/customers_search.php',
    'existingLines' => $existingLines,
], JSON_THROW_ON_ERROR) . ';</script>'
    . '<script src="' . e(BASE_URL) . '/assets/js/invoice_create.js?v=3"></script>';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$discType = (string) ($inv['invoice_discount_type'] ?? 'fixed');
$discValue = (float) ($inv['invoice_discount_value'] ?? $inv['discount'] ?? 0);
?>
<div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <a href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= $id ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(BASE_URL) ?>/modules/invoices/print.php?id=<?= $id ?>"><i class="bi bi-printer"></i> Print</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/invoices/history.php?id=<?= $id ?>"><i class="bi bi-clock-history"></i> History</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/invoices/revisions.php?id=<?= $id ?>"><i class="bi bi-layers"></i> Revisions</a>
    </div>
</div>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Edit invoice</h1>
        <p class="text-muted mb-0">
            <span class="fw-semibold text-body"><?= e($inv['invoice_number']) ?></span>
            <span class="badge text-bg-secondary ms-1">Rev <?= (int) ($inv['revision_no'] ?? 0) ?></span>
            <span class="badge text-bg-<?= $isDraft ? 'warning' : 'primary' ?> ms-1"><?= e($inv['status']) ?></span>
        </p>
        <p class="small text-muted mb-0">Invoice number and ID cannot be changed.</p>
    </div>
</div>

<form method="post" id="invoiceForm" data-loading class="row g-3 vk-invoice-form" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="form_action" id="form_action" value="update">
    <input type="hidden" name="invoice_number" value="<?= e($inv['invoice_number']) ?>" disabled>

    <div class="col-12 col-xl-9">
        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <div class="card vk-card h-100">
                    <div class="card-header bg-transparent fw-semibold">Customer</div>
                    <div class="card-body position-relative">
                        <input type="hidden" name="customer_id" id="customer_id" value="<?= (int) $inv['customer_id'] ?>" required>
                        <label class="form-label" for="customer_search">Search customer</label>
                        <input type="text" class="form-control" id="customer_search" placeholder="Type name, phone, or email" autocomplete="off"
                            value="<?= e($inv['customer_name']) ?>">
                        <div class="list-group mt-1 shadow-sm position-absolute w-100 d-none" id="customer_results" style="z-index: 20; max-height: 220px; overflow-y: auto;"></div>
                        <div class="mt-2 small text-muted" id="customer_selected">Selected: <?= e($inv['customer_name']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-7">
                <div class="card vk-card">
                    <div class="card-header bg-transparent fw-semibold">Details</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Invoice number</label>
                            <input type="text" class="form-control" value="<?= e($inv['invoice_number']) ?>" readonly disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="invoice_date">Invoice date</label>
                            <input type="date" class="form-control" name="invoice_date" id="invoice_date" required value="<?= e($inv['invoice_date']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="due_date">Due date</label>
                            <input type="date" class="form-control" name="due_date" id="due_date" value="<?= e((string) ($inv['due_date'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="currency">Currency</label>
                            <select class="form-select" name="currency" id="currency">
                                <?php foreach (['LKR', 'USD', 'EUR'] as $cur): ?>
                                    <option value="<?= $cur ?>" <?= ($inv['currency'] ?? 'LKR') === $cur ? 'selected' : '' ?>><?= $cur ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="branch">Branch</label>
                            <input type="text" class="form-control" name="branch" id="branch" maxlength="128" value="<?= e((string) ($inv['branch'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="salesperson_id">Salesperson</label>
                            <select class="form-select" name="salesperson_id" id="salesperson_id">
                                <option value="">— Select —</option>
                                <?php foreach ($staff as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>" <?= (int) ($inv['salesperson_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="reference_number">Reference</label>
                            <input type="text" class="form-control" name="reference_number" id="reference_number" maxlength="128" value="<?= e((string) ($inv['reference_number'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="payment_method">Payment method</label>
                            <select class="form-select" name="payment_method" id="payment_method">
                                <option value="">— Select —</option>
                                <?php foreach (['cash', 'card', 'bank', 'online', 'credit'] as $m): ?>
                                    <option value="<?= $m ?>" <?= ($inv['payment_method'] ?? '') === $m ? 'selected' : '' ?>><?= ucfirst($m) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Remarks</label>
                            <textarea class="form-control" name="notes" id="notes" rows="2" maxlength="2000"><?= e((string) ($inv['notes'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="terms">Terms &amp; conditions</label>
                            <textarea class="form-control" name="terms" id="terms" rows="2" maxlength="4000"><?= e((string) ($inv['terms'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="internal_notes">Internal notes</label>
                            <textarea class="form-control" name="internal_notes" id="internal_notes" rows="2" maxlength="2000"><?= e((string) ($inv['internal_notes'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="edit_reason">Reason for edit (audit)</label>
                            <input type="text" class="form-control" name="edit_reason" id="edit_reason" maxlength="512" placeholder="Optional reason recorded in history">
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
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addProductLine"><i class="bi bi-plus-lg"></i> Add product</button>
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
        </div>
    </div>

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
                                <option value="percent" <?= $discType === 'percent' ? 'selected' : '' ?>>%</option>
                                <option value="fixed" <?= $discType !== 'percent' ? 'selected' : '' ?>>LKR</option>
                            </select>
                        </dd>
                    </div>
                    <div class="vk-total-row">
                        <dt></dt>
                        <dd><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="invoice_discount_value" id="invoice_discount_value" value="<?= e((string) $discValue) ?>"></dd>
                    </div>
                    <div class="vk-total-row"><dt>Invoice Disc. Amt</dt><dd id="disp_invoice_discount">0.00</dd></div>
                    <div class="vk-total-row">
                        <dt>Shipping</dt>
                        <dd><input type="number" step="0.01" class="form-control form-control-sm text-end" name="shipping_amount" id="shipping_amount" value="<?= e((string) ($inv['shipping_amount'] ?? 0)) ?>"></dd>
                    </div>
                    <div class="vk-total-row">
                        <dt>Adjustment</dt>
                        <dd><input type="number" step="0.01" class="form-control form-control-sm text-end" name="adjustment_amount" id="adjustment_amount" value="<?= e((string) ($inv['adjustment_amount'] ?? 0)) ?>"></dd>
                    </div>
                    <div class="vk-total-row"><dt>Tax</dt><dd id="disp_tax">0.00</dd></div>
                    <input type="hidden" name="tax" id="tax" value="<?= e((string) $inv['tax']) ?>">
                    <div class="vk-total-row">
                        <dt>Round Off</dt>
                        <dd><input type="number" step="0.01" class="form-control form-control-sm text-end" name="round_off" id="round_off" value="<?= e((string) ($inv['round_off'] ?? 0)) ?>"></dd>
                    </div>
                    <div class="vk-total-row vk-total-row-grand"><dt>Grand Total</dt><dd id="disp_grand">0.00</dd></div>
                    <div class="vk-total-row"><dt>Paid</dt><dd><?= e(number_format((float) $inv['paid_amount'], 2)) ?></dd></div>
                    <div class="vk-total-row vk-total-row-balance"><dt>Balance</dt><dd id="disp_balance">0.00</dd></div>
                </dl>
                <div class="d-grid gap-2 mt-3">
                    <button type="submit" class="btn btn-primary btn-lg" data-action="update"><i class="bi bi-check2-circle me-1"></i>Update Invoice</button>
                    <?php if ($isDraft): ?>
                        <button type="submit" class="btn btn-outline-secondary" data-action="draft"><i class="bi bi-file-earmark me-1"></i>Save Draft</button>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" target="_blank" href="<?= e(BASE_URL) ?>/modules/invoices/print.php?id=<?= $id ?>"><i class="bi bi-eye me-1"></i>Preview / Print</a>
                    <a class="btn btn-outline-secondary" target="_blank" href="<?= e(BASE_URL) ?>/modules/invoices/print.php?id=<?= $id ?>&download=1"><i class="bi bi-file-pdf me-1"></i>Download PDF</a>
                    <?php
                    $waText = rawurlencode('Invoice ' . $inv['invoice_number'] . ' Total: ' . number_format((float) $inv['grand_total'], 2));
                    $waPhone = preg_replace('/\D+/', '', (string) ($inv['phone'] ?? ''));
                    ?>
                    <?php if (!empty($inv['email'])): ?>
                        <a class="btn btn-outline-secondary" href="mailto:<?= e($inv['email']) ?>?subject=<?= e(rawurlencode('Invoice ' . $inv['invoice_number'])) ?>"><i class="bi bi-envelope me-1"></i>Email</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-success" target="_blank" href="https://wa.me/<?= e($waPhone) ?>?text=<?= $waText ?>"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
                    <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/invoices/duplicate.php?id=<?= $id ?>" onclick="return confirm('Duplicate this invoice as a new draft?');"><i class="bi bi-copy me-1"></i>Duplicate Invoice</a>
                    <?php if (!empty($perms['cancel'])): ?>
                        <button type="submit" class="btn btn-outline-warning" data-action="cancel" onclick="return confirm('Cancel this invoice? Stock and ledger will be reversed.');"><i class="bi bi-x-circle me-1"></i>Cancel Invoice</button>
                    <?php endif; ?>
                    <?php if (!empty($perms['delete']) && ($isDraft || ($inv['status'] ?? '') === 'cancelled')): ?>
                        <button type="submit" class="btn btn-outline-danger" data-action="delete" onclick="return confirm('Permanently delete this invoice?');"><i class="bi bi-trash me-1"></i>Delete Invoice</button>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= $id ?>"><i class="bi bi-arrow-left me-1"></i>Back</a>
                </div>
            </div>
        </div>
    </div>
</form>

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

<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
