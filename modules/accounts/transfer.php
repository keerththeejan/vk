<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/account_transfer_service.php';
vk_ensure_finance_schemas($pdo);
vk_ensure_account_transfers_schema($pdo);

$actorId = (int) ($currentUser['id'] ?? $_SESSION['user_id'] ?? 0);
$actorName = trim((string) ($currentUser['fullname'] ?? $currentUser['username'] ?? $_SESSION['user_name'] ?? 'Admin'));
$accounts = vk_transfer_accounts_list($pdo);
$accountsJson = [];
foreach ($accounts as $a) {
    $accountsJson[(int) $a['id']] = [
        'id' => (int) $a['id'],
        'code' => (string) $a['code'],
        'name' => (string) $a['name'],
        'type' => (string) $a['account_type'],
        'balance' => (float) $a['current_balance'],
        'group' => (string) $a['account_type'],
        'customer' => (string) ($a['customer_name'] ?? ''),
    ];
}

$dupId = (int) ($_GET['duplicate'] ?? 0);
$viewId = (int) ($_GET['id'] ?? 0);
$prefill = null;
if ($dupId > 0) {
    $prefill = vk_transfer_get($pdo, $dupId);
} elseif ($viewId > 0) {
    $prefill = vk_transfer_get($pdo, $viewId);
}

$action = (string) ($_POST['form_action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf((string) ($_POST['csrf_token'] ?? ''));
    $payload = [
        'from_account_id' => (int) ($_POST['from_account_id'] ?? 0),
        'to_account_id' => (int) ($_POST['to_account_id'] ?? 0),
        'amount' => (float) ($_POST['amount'] ?? 0),
        'debit_amount' => (float) ($_POST['debit_amount'] ?? $_POST['amount'] ?? 0),
        'credit_amount' => (float) ($_POST['credit_amount'] ?? $_POST['amount'] ?? 0),
        'note' => trim((string) ($_POST['remarks'] ?? $_POST['note'] ?? '')),
        'remarks' => trim((string) ($_POST['remarks'] ?? '')),
        'reference_no' => trim((string) ($_POST['reference_no'] ?? '')),
        'voucher_date' => trim((string) ($_POST['voucher_date'] ?? date('Y-m-d'))),
        'transaction_type' => trim((string) ($_POST['transaction_type'] ?? 'Account Transfer')),
        'branch' => trim((string) ($_POST['branch'] ?? '')),
        'department' => trim((string) ($_POST['department'] ?? '')),
        'cost_centre' => trim((string) ($_POST['cost_centre'] ?? '')),
        'currency' => trim((string) ($_POST['currency'] ?? 'LKR')),
        'from_narration' => trim((string) ($_POST['from_narration'] ?? '')),
        'to_narration' => trim((string) ($_POST['to_narration'] ?? '')),
        'prepared_by' => trim((string) ($_POST['prepared_by'] ?? $actorName)),
        'approved_by' => trim((string) ($_POST['approved_by'] ?? '')),
    ];
    $attachments = !empty($_FILES['attachments']) ? vk_transfer_handle_uploads($_FILES['attachments']) : [];
    $draftId = (int) ($_POST['draft_id'] ?? 0);

    if ($action === 'cancel_voucher' && $draftId > 0) {
        if (db_column_exists($pdo, 'account_transfers', 'status')) {
            $pdo->prepare("UPDATE account_transfers SET status='cancelled', modified_by=?, modified_at=NOW() WHERE id=? AND status IN ('pending','draft')")
                ->execute([$actorId > 0 ? $actorId : null, $draftId]);
            flash_set('success', 'Voucher cancelled.');
        }
        redirect('/modules/accounts/transfer.php');
    }

    if ($action === 'save_draft') {
        $res = vk_transfer_save_draft($pdo, $payload, $actorId, $actorName, $attachments);
        if ($res['ok']) {
            flash_set('success', 'Draft saved as ' . ($res['voucher_no'] ?? ''));
            redirect('/modules/accounts/transfer.php?id=' . (int) $res['id']);
        }
        flash_set('error', $res['error'] ?? 'Unable to save draft.');
    } elseif ($action === 'post_transfer' || $action === '') {
        // Preserve original immediate-post behaviour (default submit).
        $existing = null;
        if ($draftId > 0) {
            $row = vk_transfer_get($pdo, $draftId);
            if ($row && in_array((string) ($row['status'] ?? ''), ['pending', 'draft'], true)) {
                $existing = $draftId;
            }
        }
        $res = vk_transfer_post($pdo, $payload, $actorId, $actorName, $attachments, $existing);
        if ($res['ok']) {
            flash_set('success', 'Transfer posted: ' . ($res['voucher_no'] ?? ''));
            redirect('/modules/accounts/transfer_print.php?id=' . (int) $res['id']);
        }
        flash_set('error', $res['error'] ?? 'Transfer failed.');
    }
}

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'from_date' => trim((string) ($_GET['from_date'] ?? '')),
    'to_date' => trim((string) ($_GET['to_date'] ?? '')),
    'amount_min' => trim((string) ($_GET['amount_min'] ?? '')),
    'amount_max' => trim((string) ($_GET['amount_max'] ?? '')),
    'account_id' => (int) ($_GET['account_id'] ?? 0) ?: '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$history = vk_transfer_search($pdo, $filters, $page, 20);

$report = trim((string) ($_GET['report'] ?? ''));
if ($report !== '' && in_array($report, ['register', 'daily', 'monthly', 'account', 'branch'], true)) {
    $reportRows = vk_transfer_report_rows($pdo, $filters);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="transfer_' . $report . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Voucher', 'Date', 'Reference', 'From', 'To', 'Amount', 'Branch', 'Status', 'Prepared By']);
    foreach ($reportRows as $r) {
        fputcsv($out, [
            vk_transfer_voucher_no((int) $r['id']),
            (string) ($r['voucher_date'] ?? substr((string) $r['created_at'], 0, 10)),
            (string) ($r['reference_no'] ?? ''),
            (string) ($r['from_code'] ?? '') . ' — ' . (string) ($r['from_name'] ?? ''),
            (string) ($r['to_code'] ?? '') . ' — ' . (string) ($r['to_name'] ?? ''),
            number_format((float) $r['amount'], 2, '.', ''),
            (string) ($r['branch'] ?? ''),
            (string) ($r['status'] ?? 'posted'),
            (string) ($r['prepared_by'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

$pf = static function (array $row, string $key, string $default = '') use ($prefill): string {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$key])) {
        return (string) $_POST[$key];
    }
    if (is_array($prefill) && array_key_exists($key, $prefill) && $prefill[$key] !== null) {
        return (string) $prefill[$key];
    }
    return $default;
};

$isPostedView = is_array($prefill) && ($dupId <= 0) && in_array((string) ($prefill['status'] ?? 'posted'), ['posted', 'approved'], true);
$draftIdVal = (!$isPostedView && $viewId > 0 && is_array($prefill) && in_array((string) ($prefill['status'] ?? ''), ['pending', 'draft'], true))
    ? $viewId
    : 0;

$pageTitle = 'Transfer Voucher';
$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/transfer-voucher.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/transfer-voucher.js');
$extraHead = '<link href="' . e(base_url('assets/css/transfer-voucher.css')) . '?v=' . e($cssV) . '" rel="stylesheet">'
    . '<link href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="tv-root" id="transferVoucherApp"
     data-accounts="<?= e(json_encode($accountsJson, JSON_UNESCAPED_UNICODE)) ?>"
     data-currency="LKR">

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3 mb-3">
    <div>
        <a href="<?= e(BASE_URL) ?>/modules/accounts/list.php" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Accounts</a>
        <h1 class="h3 mb-1">Transfer Voucher</h1>
        <p class="text-muted small mb-0">Enterprise fund transfer between receivable / system accounts — double-entry posting preserved.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <span class="badge text-bg-primary align-self-center">Auto voucher no.</span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/accounts/transfer.php?report=register"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Transfer Register</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/accounts/transfer.php?report=daily&amp;from_date=<?= e(date('Y-m-d')) ?>&amp;to_date=<?= e(date('Y-m-d')) ?>">Daily Report</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/accounts/transfer.php?report=monthly&amp;from_date=<?= e(date('Y-m-01')) ?>&amp;to_date=<?= e(date('Y-m-t')) ?>">Monthly Report</a>
    </div>
</div>

<form method="post" enctype="multipart/form-data" id="tvForm" class="tv-form" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e((string) ($GLOBALS['vk_csrf_token'] ?? csrf_token())) ?>">
    <input type="hidden" name="draft_id" value="<?= (int) $draftIdVal ?>">
    <input type="hidden" name="form_action" id="tvFormAction" value="post_transfer">
    <input type="hidden" name="debit_amount" id="debit_amount" value="<?= e($pf([], 'amount', $pf($prefill ?? [], 'amount', ''))) ?>">
    <input type="hidden" name="credit_amount" id="credit_amount" value="<?= e($pf([], 'amount', $pf($prefill ?? [], 'amount', ''))) ?>">

    <div class="card vk-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-journal-text me-1"></i>Voucher Header</span>
            <?php if ($isPostedView && $prefill): ?>
                <span class="badge text-bg-<?= e(vk_transfer_status_badge((string) ($prefill['status'] ?? 'posted'))) ?>"><?= e(ucfirst((string) ($prefill['status'] ?? 'posted'))) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="voucher_no">Voucher No</label>
                    <input class="form-control" id="voucher_no" value="<?= $viewId > 0 && $dupId <= 0 ? e(vk_transfer_voucher_no($viewId)) : 'Auto-generated on post' ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="voucher_date">Voucher Date</label>
                    <input type="date" class="form-control" name="voucher_date" id="voucher_date" value="<?= e($pf($prefill ?? [], 'voucher_date', date('Y-m-d'))) ?>" <?= $isPostedView ? 'readonly' : '' ?> required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="reference_no">Reference No</label>
                    <input class="form-control" name="reference_no" id="reference_no" maxlength="64" value="<?= e($pf($prefill ?? [], 'reference_no')) ?>" <?= $isPostedView ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="transaction_type">Transaction Type</label>
                    <select class="form-select" name="transaction_type" id="transaction_type" <?= $isPostedView ? 'disabled' : '' ?>>
                        <?php
                        $tt = $pf($prefill ?? [], 'transaction_type', 'Account Transfer');
                        foreach (['Account Transfer', 'Internal Fund Transfer', 'Customer Reallocation', 'Bank Contra'] as $opt):
                        ?>
                        <option value="<?= e($opt) ?>" <?= $tt === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="branch">Branch</label>
                    <input class="form-control" name="branch" id="branch" value="<?= e($pf($prefill ?? [], 'branch', 'Head Office')) ?>" <?= $isPostedView ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="department">Department</label>
                    <input class="form-control" name="department" id="department" value="<?= e($pf($prefill ?? [], 'department', 'Accounts')) ?>" <?= $isPostedView ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="cost_centre">Cost Centre</label>
                    <input class="form-control" name="cost_centre" id="cost_centre" value="<?= e($pf($prefill ?? [], 'cost_centre')) ?>" <?= $isPostedView ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="tv_status_display">Status</label>
                    <input class="form-control" id="tv_status_display" value="<?= e($isPostedView ? ucfirst((string) ($prefill['status'] ?? 'posted')) : ($draftIdVal ? 'Pending' : 'New')) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="prepared_by">Prepared By</label>
                    <input class="form-control" name="prepared_by" id="prepared_by" value="<?= e($pf($prefill ?? [], 'prepared_by', $actorName)) ?>" <?= $isPostedView ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="approved_by">Approved By</label>
                    <input class="form-control" name="approved_by" id="approved_by" value="<?= e($pf($prefill ?? [], 'approved_by', $isPostedView ? $actorName : '')) ?>" <?= $isPostedView ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="remarks">Remarks</label>
                    <input class="form-control" name="remarks" id="remarks" maxlength="1000" value="<?= e($pf($prefill ?? [], 'remarks', $pf($prefill ?? [], 'note'))) ?>" <?= $isPostedView ? 'readonly' : '' ?>>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card vk-card tv-from h-100">
                <div class="card-header bg-danger-subtle"><i class="bi bi-box-arrow-up-right me-1"></i>Transfer From (Credit)</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="from_account_id">Account Name</label>
                        <select class="form-select tv-account" name="from_account_id" id="from_account_id" required <?= $isPostedView ? 'disabled' : '' ?>>
                            <option value="">— Select source account —</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= (int) $a['id'] ?>"
                                    data-code="<?= e((string) $a['code']) ?>"
                                    data-type="<?= e((string) $a['account_type']) ?>"
                                    data-balance="<?= e((string) $a['current_balance']) ?>"
                                    <?= (int) $pf($prefill ?? [], 'from_account_id') === (int) $a['id'] ? 'selected' : '' ?>>
                                    <?= e($a['code']) ?> — <?= e($a['name']) ?> (<?= e($a['account_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4"><label class="form-label">Account Group</label><input class="form-control" id="from_group" readonly></div>
                        <div class="col-md-4"><label class="form-label">Account Code</label><input class="form-control" id="from_code" readonly></div>
                        <div class="col-md-4"><label class="form-label">Currency</label><input class="form-control" name="currency" id="currency" value="<?= e($pf($prefill ?? [], 'currency', 'LKR')) ?>" <?= $isPostedView ? 'readonly' : '' ?>></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Balance</label>
                        <div class="input-group">
                            <span class="input-group-text">LKR</span>
                            <input class="form-control fw-semibold" id="from_balance" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="amount">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control form-control-lg" name="amount" id="amount" required
                               value="<?= e($pf($prefill ?? [], 'amount')) ?>" <?= $isPostedView ? 'readonly' : '' ?>>
                    </div>
                    <div>
                        <label class="form-label" for="from_narration">Narration</label>
                        <textarea class="form-control" name="from_narration" id="from_narration" rows="2" <?= $isPostedView ? 'readonly' : '' ?>><?= e($pf($prefill ?? [], 'from_narration')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card vk-card tv-to h-100">
                <div class="card-header bg-success-subtle"><i class="bi bi-box-arrow-in-down-left me-1"></i>Transfer To (Debit)</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="to_account_id">Account Name</label>
                        <select class="form-select tv-account" name="to_account_id" id="to_account_id" required <?= $isPostedView ? 'disabled' : '' ?>>
                            <option value="">— Select destination account —</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= (int) $a['id'] ?>"
                                    data-code="<?= e((string) $a['code']) ?>"
                                    data-type="<?= e((string) $a['account_type']) ?>"
                                    data-balance="<?= e((string) $a['current_balance']) ?>"
                                    <?= (int) $pf($prefill ?? [], 'to_account_id') === (int) $a['id'] ? 'selected' : '' ?>>
                                    <?= e($a['code']) ?> — <?= e($a['name']) ?> (<?= e($a['account_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6"><label class="form-label">Account Group</label><input class="form-control" id="to_group" readonly></div>
                        <div class="col-md-6"><label class="form-label">Account Code</label><input class="form-control" id="to_code" readonly></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Balance</label>
                        <div class="input-group">
                            <span class="input-group-text">LKR</span>
                            <input class="form-control fw-semibold" id="to_balance" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="to_amount_display">Amount</label>
                        <input type="number" step="0.01" class="form-control form-control-lg" id="to_amount_display" readonly>
                        <div class="form-text">Mirrored from Transfer From (Debit = Credit).</div>
                    </div>
                    <div>
                        <label class="form-label" for="to_narration">Narration</label>
                        <textarea class="form-control" name="to_narration" id="to_narration" rows="2" <?= $isPostedView ? 'readonly' : '' ?>><?= e($pf($prefill ?? [], 'to_narration')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card vk-card h-100" id="tvSummary">
                <div class="card-header fw-semibold"><i class="bi bi-calculator me-1"></i>Live Summary</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Debit Total</span><strong id="tvDebitTotal">0.00</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Credit Total</span><strong id="tvCreditTotal">0.00</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Difference</span><strong id="tvDiff">0.00</strong></div>
                    <div class="mt-3">
                        <span class="badge text-bg-secondary" id="tvBalanceBadge">Not Balanced</span>
                    </div>
                    <div class="small text-danger mt-2 d-none" id="tvValidationMsg"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card vk-card h-100">
                <div class="card-header fw-semibold"><i class="bi bi-paperclip me-1"></i>Attachments</div>
                <div class="card-body">
                    <label class="form-label" for="attachments">Invoice / Receipt / Bank Slip / Cheque / Supporting Docs</label>
                    <input type="file" class="form-control" name="attachments[]" id="attachments" multiple accept=".pdf,.png,.jpg,.jpeg,.webp,.gif,.doc,.docx,.xls,.xlsx" <?= $isPostedView ? 'disabled' : '' ?>>
                    <?php
                    $atts = [];
                    if (is_array($prefill) && !empty($prefill['attachments_json'])) {
                        $decoded = json_decode((string) $prefill['attachments_json'], true);
                        if (is_array($decoded)) {
                            $atts = $decoded;
                        }
                    }
                    ?>
                    <?php if ($atts): ?>
                        <ul class="small mt-2 mb-0">
                            <?php foreach ($atts as $att): ?>
                                <li><a href="<?= e(base_url((string) ($att['path'] ?? ''))) ?>" target="_blank" rel="noopener"><?= e((string) ($att['name'] ?? 'file')) ?></a> <span class="text-muted">(<?= e((string) ($att['type'] ?? '')) ?>)</span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card vk-card h-100">
                <div class="card-header fw-semibold"><i class="bi bi-shield-check me-1"></i>Audit Information</div>
                <div class="card-body small">
                    <div class="row g-2">
                        <div class="col-6 text-muted">Created By</div><div class="col-6"><?= e(is_array($prefill) ? (string) ($prefill['prepared_by'] ?? $actorName) : $actorName) ?></div>
                        <div class="col-6 text-muted">Created Date</div><div class="col-6"><?= e(is_array($prefill) ? (string) ($prefill['created_at'] ?? '—') : '—') ?></div>
                        <div class="col-6 text-muted">Modified By</div><div class="col-6"><?= e(is_array($prefill) && !empty($prefill['modified_by']) ? '#' . (int) $prefill['modified_by'] : '—') ?></div>
                        <div class="col-6 text-muted">Modified Date</div><div class="col-6"><?= e(is_array($prefill) ? (string) ($prefill['modified_at'] ?? '—') : '—') ?></div>
                        <div class="col-6 text-muted">Approved By</div><div class="col-6"><?= e(is_array($prefill) ? (string) ($prefill['approved_by'] ?? '—') : '—') ?></div>
                        <div class="col-6 text-muted">Approval Date</div><div class="col-6"><?= e(is_array($prefill) ? (string) ($prefill['approved_at'] ?? '—') : '—') ?></div>
                    </div>
                    <div class="mt-3">
                        <span class="badge text-bg-warning me-1">Pending</span>
                        <span class="badge text-bg-success me-1">Approved / Posted</span>
                        <span class="badge text-bg-danger me-1">Rejected</span>
                        <span class="badge text-bg-secondary">Cancelled</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tv-actions card vk-card mb-4">
        <div class="card-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div class="d-flex flex-wrap gap-2">
                <?php if (!$isPostedView): ?>
                <button type="submit" class="btn btn-outline-secondary" data-action="save_draft"><i class="bi bi-floppy me-1"></i>Save Draft</button>
                <button type="submit" class="btn btn-primary" data-action="post_transfer" id="tvPostBtn"><i class="bi bi-check2-circle me-1"></i>Post Transfer</button>
                <?php endif; ?>
                <?php if ($viewId > 0 && $dupId <= 0): ?>
                <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/accounts/transfer_print.php?id=<?= (int) $viewId ?>" target="_blank"><i class="bi bi-eye me-1"></i>Preview</a>
                <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/accounts/transfer_print.php?id=<?= (int) $viewId ?>" target="_blank"><i class="bi bi-printer me-1"></i>Print</a>
                <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/accounts/transfer_print.php?id=<?= (int) $viewId ?>&amp;pdf=1" target="_blank"><i class="bi bi-filetype-pdf me-1"></i>Export PDF</a>
                <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/accounts/transfer.php?duplicate=<?= (int) $viewId ?>"><i class="bi bi-copy me-1"></i>Duplicate</a>
                <?php endif; ?>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($draftIdVal > 0): ?>
                <button type="submit" class="btn btn-outline-danger" data-action="cancel_voucher"><i class="bi bi-x-circle me-1"></i>Cancel</button>
                <?php endif; ?>
                <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/accounts/transfer.php"><i class="bi bi-eraser me-1"></i>Clear</a>
            </div>
        </div>
    </div>
</form>

<div class="card vk-card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold"><i class="bi bi-clock-history me-1"></i>Transfer History</span>
        <form class="row g-2 align-items-end" method="get">
            <div class="col-auto"><input class="form-control form-control-sm" name="q" placeholder="Voucher / Ref / Account / User" value="<?= e((string) $filters['q']) ?>"></div>
            <div class="col-auto">
                <select class="form-select form-select-sm" name="status">
                    <option value="">All status</option>
                    <?php foreach (['posted', 'pending', 'approved', 'rejected', 'cancelled'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><input type="date" class="form-control form-control-sm" name="from_date" value="<?= e((string) $filters['from_date']) ?>"></div>
            <div class="col-auto"><input type="date" class="form-control form-control-sm" name="to_date" value="<?= e((string) $filters['to_date']) ?>"></div>
            <div class="col-auto"><input type="number" step="0.01" class="form-control form-control-sm" name="amount_min" placeholder="Min amt" value="<?= e((string) $filters['amount_min']) ?>"></div>
            <div class="col-auto"><input type="number" step="0.01" class="form-control form-control-sm" name="amount_max" placeholder="Max amt" value="<?= e((string) $filters['amount_max']) ?>"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-search"></i></button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Voucher</th><th>Date</th><th>Amount</th><th>From</th><th>To</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$history['rows']): ?>
                <tr><td colspan="7" class="text-muted small px-3 py-3">No transfers found.</td></tr>
            <?php else: ?>
                <?php foreach ($history['rows'] as $r): ?>
                    <?php $st = (string) ($r['status'] ?? 'posted'); ?>
                    <tr>
                        <td><code><?= e(vk_transfer_voucher_no((int) $r['id'])) ?></code></td>
                        <td class="small text-nowrap"><?= e((string) ($r['voucher_date'] ?? substr((string) $r['created_at'], 0, 10))) ?></td>
                        <td class="small"><?= e(number_format((float) $r['amount'], 2)) ?></td>
                        <td class="small"><?= e((string) $r['from_code']) ?> — <?= e((string) $r['from_name']) ?></td>
                        <td class="small"><?= e((string) $r['to_code']) ?> — <?= e((string) $r['to_name']) ?></td>
                        <td><span class="badge text-bg-<?= e(vk_transfer_status_badge($st)) ?>"><?= e(ucfirst($st)) ?></span></td>
                        <td class="text-nowrap text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/accounts/transfer.php?id=<?= (int) $r['id'] ?>">View</a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/accounts/transfer_print.php?id=<?= (int) $r['id'] ?>" target="_blank">Print</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/accounts/transfer.php?duplicate=<?= (int) $r['id'] ?>">Duplicate</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($history['pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between small">
        <span>Page <?= (int) $history['page'] ?> / <?= (int) $history['pages'] ?> · <?= (int) $history['total'] ?> records</span>
        <span>
            <?php if ($history['page'] > 1): ?><a href="?<?= e(http_build_query(array_merge($filters, ['page' => $history['page'] - 1]))) ?>">Prev</a><?php endif; ?>
            <?php if ($history['page'] < $history['pages']): ?> · <a href="?<?= e(http_build_query(array_merge($filters, ['page' => $history['page'] + 1]))) ?>">Next</a><?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<div class="position-fixed bottom-0 end-0 p-3 tv-loading d-none" id="tvLoading">
    <div class="d-flex align-items-center gap-2 bg-body border rounded shadow px-3 py-2">
        <div class="spinner-border spinner-border-sm text-primary"></div>
        <span>Processing voucher…</span>
    </div>
</div>
</div>

<?php
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>'
    . '<script src="' . e(base_url('assets/js/transfer-voucher.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
