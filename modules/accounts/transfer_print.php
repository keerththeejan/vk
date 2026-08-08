<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
$pdo = db();
require_once dirname(__DIR__, 2) . '/includes/account_transfer_service.php';
vk_ensure_account_transfers_schema($pdo);

$id = (int) ($_GET['id'] ?? 0);
$row = vk_transfer_get($pdo, $id);
if (!$row) {
    http_response_code(404);
    echo 'Transfer voucher not found.';
    exit;
}

$asPdf = !empty($_GET['pdf']);
$company = function_exists('vk_app_setting') ? (string) vk_app_setting('company_name', 'VK Network') : 'VK Network';
$tagline = function_exists('vk_app_setting') ? (string) vk_app_setting('company_tagline', 'Connecting You to a Smarter Digital World') : '';
$logoFile = dirname(__DIR__, 2) . '/assets/images/vk-logo.png';
$logoUrl = is_file($logoFile) ? base_url('assets/images/vk-logo.png?v=' . (string) @filemtime($logoFile)) : '';
$voucherNo = vk_transfer_voucher_no((int) $row['id']);
$status = (string) ($row['status'] ?? 'posted');
$date = (string) ($row['voucher_date'] ?? substr((string) $row['created_at'], 0, 10));
$amount = (float) $row['amount'];
$currency = (string) ($row['currency'] ?? 'LKR');
$atts = [];
if (!empty($row['attachments_json'])) {
    $decoded = json_decode((string) $row['attachments_json'], true);
    if (is_array($decoded)) {
        $atts = $decoded;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($voucherNo) ?> — Transfer Voucher</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { --ink:#1a1a1a; --muted:#5c5c5c; --line:#d5d5d5; --accent:#0d6efd; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", Tahoma, sans-serif; color: var(--ink); background: #f3f4f6; }
        .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; padding: 18mm 16mm; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
        .hdr { display: grid; grid-template-columns: 90px 1fr auto; gap: 16px; align-items: center; border-bottom: 2px solid var(--ink); padding-bottom: 12px; }
        .hdr img { max-width: 90px; max-height: 70px; object-fit: contain; }
        .hdr h1 { margin: 0; font-size: 22px; letter-spacing: .04em; }
        .hdr .sub { color: var(--muted); font-size: 12px; margin-top: 2px; }
        .meta { text-align: right; font-size: 12px; }
        .meta strong { display: block; font-size: 16px; color: var(--accent); }
        h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .08em; margin: 22px 0 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid var(--line); padding: 8px 10px; vertical-align: top; }
        th { background: #f8fafc; text-align: left; width: 28%; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .box { border: 1px solid var(--line); border-radius: 8px; padding: 12px; }
        .box h3 { margin: 0 0 8px; font-size: 13px; }
        .amt { font-size: 20px; font-weight: 700; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #e7f1ff; color: #0b5ed7; }
        .signs { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px; margin-top: 48px; }
        .sign { border-top: 1px solid var(--ink); padding-top: 8px; font-size: 12px; text-align: center; min-height: 64px; }
        .toolbar { width: 210mm; margin: 12px auto 0; display: flex; gap: 8px; justify-content: flex-end; }
        .toolbar button, .toolbar a { border: 1px solid #cbd5e1; background: #fff; padding: 8px 12px; border-radius: 8px; text-decoration: none; color: inherit; font-size: 13px; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; }
            .toolbar { display: none !important; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">Print / Save PDF</button>
    <a href="<?= e(base_url('modules/accounts/transfer.php?id=' . $id)) ?>">Back to voucher</a>
</div>
<article class="sheet">
    <header class="hdr">
        <div><?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" alt="Logo"><?php endif; ?></div>
        <div>
            <h1><?= e($company) ?></h1>
            <div class="sub"><?= e($tagline !== '' ? $tagline : 'Transfer Voucher') ?></div>
            <div class="sub">Account Transfer · Double-entry</div>
        </div>
        <div class="meta">
            <strong><?= e($voucherNo) ?></strong>
            <div>Date: <?= e($date) ?></div>
            <div>Status: <span class="badge"><?= e(ucfirst($status)) ?></span></div>
        </div>
    </header>

    <h2>Voucher details</h2>
    <table>
        <tr><th>Reference No</th><td><?= e((string) ($row['reference_no'] ?? '—')) ?></td><th>Transaction Type</th><td><?= e((string) ($row['transaction_type'] ?? 'Account Transfer')) ?></td></tr>
        <tr><th>Branch</th><td><?= e((string) ($row['branch'] ?? '—')) ?></td><th>Department</th><td><?= e((string) ($row['department'] ?? '—')) ?></td></tr>
        <tr><th>Cost Centre</th><td><?= e((string) ($row['cost_centre'] ?? '—')) ?></td><th>Currency</th><td><?= e($currency) ?></td></tr>
        <tr><th>Remarks</th><td colspan="3"><?= e((string) ($row['remarks'] ?? $row['note'] ?? '—')) ?></td></tr>
    </table>

    <h2>Transfer</h2>
    <div class="grid-2">
        <div class="box">
            <h3>Transfer From (Credit)</h3>
            <div><strong><?= e((string) $row['from_code']) ?></strong> — <?= e((string) $row['from_name']) ?></div>
            <div class="sub" style="color:var(--muted);font-size:12px;margin-top:4px;">Group: <?= e((string) ($row['from_type'] ?? '')) ?></div>
            <div class="amt" style="margin-top:10px;"><?= e(formatCurrency($amount)) ?></div>
            <div style="margin-top:8px;font-size:12px;"><?= e((string) ($row['from_narration'] ?? $row['note'] ?? '')) ?></div>
        </div>
        <div class="box">
            <h3>Transfer To (Debit)</h3>
            <div><strong><?= e((string) $row['to_code']) ?></strong> — <?= e((string) $row['to_name']) ?></div>
            <div class="sub" style="color:var(--muted);font-size:12px;margin-top:4px;">Group: <?= e((string) ($row['to_type'] ?? '')) ?></div>
            <div class="amt" style="margin-top:10px;"><?= e(formatCurrency($amount)) ?></div>
            <div style="margin-top:8px;font-size:12px;"><?= e((string) ($row['to_narration'] ?? $row['note'] ?? '')) ?></div>
        </div>
    </div>

    <h2>Accounting summary</h2>
    <table>
        <tr><th>Debit Total</th><td><?= e(formatCurrency($amount)) ?></td><th>Credit Total</th><td><?= e(formatCurrency($amount)) ?></td></tr>
        <tr><th>Difference</th><td>0.00</td><th>Balance Status</th><td><strong>Balanced</strong></td></tr>
    </table>

    <?php if ($atts): ?>
    <h2>Attachments</h2>
    <ul>
        <?php foreach ($atts as $a): ?>
            <li><?= e((string) ($a['name'] ?? 'file')) ?> (<?= e((string) ($a['type'] ?? '')) ?>)</li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <div class="signs">
        <div class="sign">Prepared By<br><strong><?= e((string) ($row['prepared_by'] ?? '—')) ?></strong></div>
        <div class="sign">Approved By<br><strong><?= e((string) ($row['approved_by'] ?? '—')) ?></strong></div>
        <div class="sign">Receiver / Accounts Seal</div>
    </div>
</article>
<?php if ($asPdf): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
</body>
</html>
