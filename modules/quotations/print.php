<?php
declare(strict_types=1);
/**
 * Enterprise Quotation Print / PDF — alignment / spacing polish only.
 * Keeps VK NETWORK letterhead branding, colors, fonts, and all business logic.
 */
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
$pdo = db();
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);

$id = (int) ($_GET['id'] ?? 0);
$q = vk_quotation_get($pdo, $id);
if (!$q) {
    http_response_code(404);
    echo 'Quotation not found.';
    exit;
}
$items = vk_quotation_items($pdo, $id);

$projectRoot = dirname(__DIR__, 2);
$letterheadRel = vk_quotation_setting($pdo, 'letterhead_path', 'assets/images/vk-letterhead.png');
$letterheadAbs = $projectRoot . '/' . ltrim(str_replace('\\', '/', $letterheadRel), '/');
if (!is_file($letterheadAbs)) {
    $letterheadRel = 'assets/images/vk-letterhead.png';
    $letterheadAbs = $projectRoot . '/' . $letterheadRel;
}
$letterheadUrl = is_file($letterheadAbs)
    ? base_url($letterheadRel . '?v=' . (string) filemtime($letterheadAbs))
    : '';

$signatureRel = vk_quotation_setting($pdo, 'signature_path', 'assets/images/digital-signature.png');
$stampRel = vk_quotation_setting($pdo, 'stamp_path', 'assets/images/company-stamp.png');
$signatureAbs = $projectRoot . '/' . ltrim(str_replace('\\', '/', $signatureRel), '/');
$stampAbs = $projectRoot . '/' . ltrim(str_replace('\\', '/', $stampRel), '/');
if (!is_file($signatureAbs) && is_file($projectRoot . '/assets/images/signature.png')) {
    $signatureRel = 'assets/images/signature.png';
    $signatureAbs = $projectRoot . '/assets/images/signature.png';
}
if (!is_file($stampAbs) && is_file($projectRoot . '/assets/images/stamp.png')) {
    $stampRel = 'assets/images/stamp.png';
    $stampAbs = $projectRoot . '/assets/images/stamp.png';
}
$hasSignature = is_file($signatureAbs);
$hasStamp = is_file($stampAbs);
$signatureUrl = $hasSignature ? base_url($signatureRel . '?v=' . filemtime($signatureAbs)) : '';
$stampUrl = $hasStamp ? base_url($stampRel . '?v=' . filemtime($stampAbs)) : '';

$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=80x80&margin=0&data=' . rawurlencode(
    'https://vkitnet.info/quote/' . rawurlencode((string) $q['quotation_number']) . '?id=' . $id
);

$bankName = vk_quotation_setting($pdo, 'bank_name', 'Commercial Bank');
$bankAccountName = vk_quotation_setting($pdo, 'bank_account_name', 'VK Network');
$bankAccountNumber = vk_quotation_setting($pdo, 'bank_account_number', '');
$bankBranch = vk_quotation_setting($pdo, 'bank_branch', 'Kilinochchi');

$currency = (string) ($q['currency'] ?? 'LKR');
$customerName = (string) ($q['company_name'] ?: $q['customer_name']);
$contactPerson = (string) ($q['contact_person'] ?: '');
$phone = (string) ($q['phone'] ?: $q['customer_phone_db'] ?: '');
$mobile = (string) ($q['mobile'] ?? '');
$email = (string) ($q['email'] ?: $q['customer_email_db'] ?: '');
$billing = (string) ($q['billing_address'] ?: $q['customer_address_db'] ?: '');
$amountWords = vk_quotation_amount_in_words((float) $q['grand_total'], $currency);
$preparedBy = (string) ($q['created_by_name'] ?: $q['sales_executive_name'] ?: 'VK Network');
$statusLabel = strtoupper(vk_quotation_status_label($q['status']));
$showDraftMark = in_array($q['status'], ['draft', 'pending_approval', 'rejected', 'cancelled', 'expired'], true);
$autoPrint = isset($_GET['autoprint']);
$generatedAt = date('Y-m-d H:i');
$totalDisc = (float) $q['item_discount_total'] + (float) $q['overall_discount_amount'];

$fmtDate = static function (?string $d): string {
    if ($d === null || $d === '' || $d === '—') {
        return '—';
    }
    try {
        return (new DateTime($d))->format('d-M-Y');
    } catch (Throwable $e) {
        return $d;
    }
};
$dateDisp = $fmtDate((string) $q['quotation_date']);
$expiryDisp = $fmtDate((string) ($q['expiry_date'] ?: ''));
$money = static function (float $n): string {
    return number_format($n, 2);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quotation <?= e($q['quotation_number']) ?> — VK NETWORK</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #123C7A;
            --secondary: #0B5ED7;
            --blue: #0B4DBA;
            --gray: #6C757D;
            --border: #DEE2E6;
            --text: #212529;
            --alt-row: #F8F9FA;
            --label: #6C757D;
            /* Letterhead safe zone (preserves branded header/footer art) */
            --pad-top: 38mm;
            --pad-bottom: 32mm;
            --pad-left: 15mm;
            --pad-right: 15mm;
            --section-gap: 18px;
        }
        * { box-sizing: border-box; }
        @page {
            size: A4 portrait;
            margin: 0;
        }
        html, body {
            margin: 0;
            padding: 0;
            background: #d5dde8;
            color: var(--text);
            font-family: Poppins, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .toolbar {
            max-width: 210mm;
            margin: 14px auto;
            padding: 0 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }
        .toolbar a, .toolbar button {
            appearance: none;
            border: 0;
            border-radius: 8px;
            padding: 9px 14px;
            font: 600 12px Poppins, Arial, sans-serif;
            cursor: pointer;
            text-decoration: none;
            background: var(--secondary);
            color: #fff;
        }
        .toolbar .ghost {
            background: #fff;
            color: var(--primary);
            border: 1px solid var(--border);
        }

        .sheet {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            height: 297mm;
            margin: 0 auto 18px;
            background-color: #fff;
            background-image: <?= $letterheadUrl !== '' ? "url('" . e($letterheadUrl) . "')" : 'none' ?>;
            background-repeat: no-repeat;
            background-position: center top;
            background-size: 210mm 297mm;
            box-shadow: 0 12px 36px rgba(18, 60, 122, .14);
            overflow: hidden;
        }

        .content {
            position: absolute;
            top: var(--pad-top);
            right: var(--pad-right);
            bottom: var(--pad-bottom);
            left: var(--pad-left);
            z-index: 2;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .draft-mark {
            position: absolute;
            top: 48%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-28deg);
            font-size: 60px;
            font-weight: 700;
            color: rgba(18, 60, 122, .055);
            letter-spacing: .12em;
            z-index: 1;
            pointer-events: none;
            white-space: nowrap;
        }

        /* ── Title + 3-column meta ── */
        .title-block {
            text-align: center;
            margin: 0 0 var(--section-gap);
        }
        .header-title {
            margin: 0 0 14px;
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: var(--primary);
            text-transform: uppercase;
            line-height: 1.15;
        }
        .header-meta {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            column-gap: 16px;
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            padding: 12px 8px;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        .header-meta .item {
            text-align: center;
            min-width: 0;
        }
        .header-meta .label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--label);
            margin-bottom: 4px;
            letter-spacing: 0.02em;
        }
        .header-meta .value {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--primary);
            word-break: break-word;
        }

        /* ── Bill To / Sales cards ── */
        .parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            align-items: stretch;
            margin: 0 0 var(--section-gap);
        }
        .party-card {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            background: #fff;
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }
        .party-card h3 {
            margin: 0 0 12px;
            font-size: 14px;
            font-weight: 700;
            color: var(--blue);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }
        .party-card .field {
            display: grid;
            grid-template-columns: 110px 1fr;
            column-gap: 8px;
            align-items: start;
            margin: 0 0 6px;
            font-size: 11px;
            line-height: 1.45;
        }
        .party-card .field:last-child { margin-bottom: 0; }
        .party-card .field .k {
            color: var(--label);
            font-weight: 500;
        }
        .party-card .field .v {
            color: var(--text);
            font-weight: 700;
            word-break: break-word;
        }
        .party-card .name-row .v {
            font-size: 12px;
            color: var(--primary);
        }

        /* ── Item table ── */
        .table-wrap {
            width: 100%;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid var(--border);
            margin: 0 0 var(--section-gap);
        }
        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .items thead th {
            height: 40px;
            background: var(--primary);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 8px 6px;
            text-align: center;
            border: 1px solid #0e2f5f;
            vertical-align: middle;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .items thead th.desc { text-align: left; padding-left: 10px; }
        .items thead th.num,
        .items tbody td.num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .items thead th.ctr,
        .items tbody td.ctr { text-align: center; }
        .items tbody td {
            height: 40px;
            padding: 10px 6px;
            border: 1px solid var(--border);
            vertical-align: middle;
            font-size: 11px;
            color: #343a40;
            word-wrap: break-word;
        }
        .items tbody td.desc {
            text-align: left;
            padding-left: 10px;
            padding-right: 8px;
        }
        .items tbody tr:nth-child(even) td { background: var(--alt-row); }
        .item-sub {
            display: block;
            margin-top: 2px;
            font-size: 10px;
            color: var(--gray);
            font-weight: 400;
        }

        /* ── Words + Totals ── */
        .mid {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 16px;
            align-items: start;
            margin: 0 0 var(--section-gap);
        }
        .words {
            padding: 14px 4px;
            text-align: left;
        }
        .words .lbl {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .words .val {
            font-size: 11px;
            color: #495057;
            font-weight: 500;
            font-style: italic;
            line-height: 1.6;
        }

        .summary {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }
        .summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary td {
            padding: 9px 14px;
            font-size: 11px;
            color: #495057;
            border-bottom: 1px solid #eef1f5;
            vertical-align: middle;
        }
        .summary tr:last-child td { border-bottom: none; }
        .summary td:first-child {
            text-align: left;
            font-weight: 600;
            color: var(--label);
        }
        .summary td:last-child {
            text-align: right;
            font-weight: 700;
            color: var(--text);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .summary .grand td {
            background: var(--primary);
            color: #fff;
            padding: 12px 14px;
            border: none;
            font-weight: 700;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .summary .grand td:first-child {
            font-size: 12px;
            color: #fff;
        }
        .summary .grand td:last-child {
            font-size: 18px;
            color: #fff;
        }

        /* ── Terms ── */
        .terms-block {
            margin: 0 0 var(--section-gap);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }
        .terms-block h4 {
            margin: 0;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            background: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .terms-block .terms-body {
            padding: 15px;
        }
        .terms-block pre,
        .terms-block p {
            margin: 0;
            white-space: pre-wrap;
            font-family: inherit;
            font-size: 11px;
            color: #495057;
            line-height: 1.7;
        }

        .notes-block {
            margin: 0 0 var(--section-gap);
        }
        .notes-block h4 {
            margin: 0 0 6px;
            font-size: 14px;
            font-weight: 700;
            color: var(--blue);
            text-transform: uppercase;
        }
        .notes-block p {
            margin: 0;
            font-size: 11px;
            color: #495057;
            line-height: 1.7;
        }

        /* ── Bank + QR ── */
        .bank-qr {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin: 0 0 var(--section-gap);
            padding: 14px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        .bank-qr .bank {
            flex: 1;
            min-width: 0;
            font-size: 11px;
            color: #495057;
            line-height: 1.65;
        }
        .bank-qr .bank strong {
            display: block;
            color: var(--blue);
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .bank-qr .qr {
            text-align: center;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .bank-qr .qr img {
            width: 72px;
            height: 72px;
            display: block;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: #fff;
        }
        .bank-qr .qr small {
            display: block;
            margin-top: 4px;
            font-size: 9px;
            color: var(--gray);
            font-weight: 500;
        }

        /* ── Signatures ── */
        .signs {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            column-gap: 20px;
            align-items: end;
            margin-top: auto;
            padding-top: 12px;
            page-break-inside: avoid;
        }
        .sign {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            min-width: 0;
        }
        .sign-slot {
            min-height: 56px;
            width: 100%;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            margin-bottom: 4px;
        }
        .sign img.sig {
            max-height: 40px;
            max-width: 140px;
            display: block;
            object-fit: contain;
        }
        .sign img.stamp {
            max-height: 52px;
            max-width: 130px;
            display: block;
            object-fit: contain;
            opacity: 0.45;
        }
        .sign .line {
            width: 90%;
            max-width: 180px;
            border-top: 1px solid #444;
            margin: 6px auto 0;
            padding-top: 8px;
        }
        .sign .label {
            font-size: 11px;
            font-weight: 700;
            color: var(--primary);
            text-align: center;
        }
        .sign .who {
            margin-top: 3px;
            font-size: 10px;
            font-weight: 500;
            color: var(--gray);
            text-align: center;
        }

        .gen-meta {
            margin-top: 10px;
            font-size: 9px;
            color: #adb5bd;
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        /* Contact strip (above letterhead footer band) */
        .print-footer {
            display: none;
        }

        @media print {
            body { background: #fff !important; }
            .toolbar { display: none !important; }
            .sheet {
                margin: 0 !important;
                box-shadow: none !important;
                width: 210mm;
                height: 297mm;
                page-break-after: always;
            }
            .summary .grand td,
            .items thead th,
            .terms-block h4 {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        @media screen and (max-width: 900px) {
            .sheet {
                width: 100%;
                height: auto;
                min-height: 100vh;
                background-size: 100% auto;
            }
            .content {
                position: relative;
                top: auto; right: auto; bottom: auto; left: auto;
                padding: 38mm 15mm 32mm;
                min-height: 100vh;
            }
            .parties { grid-template-columns: 1fr; }
            .mid { grid-template-columns: 1fr; }
            .header-meta { grid-template-columns: 1fr; row-gap: 10px; }
            .signs { grid-template-columns: 1fr; row-gap: 20px; }
            .bank-qr { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <a class="ghost" href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>">Back</a>
    <a class="ghost" href="<?= e(BASE_URL) ?>/modules/quotations/email.php?id=<?= $id ?>">Email</a>
    <a class="ghost" target="_blank" href="<?= e(vk_quotation_whatsapp_url($pdo, $q)) ?>">WhatsApp</a>
    <button type="button" onclick="window.print()">Print / Save PDF</button>
</div>

<article class="sheet" aria-label="Quotation <?= e($q['quotation_number']) ?>">
    <?php if ($showDraftMark): ?>
        <div class="draft-mark"><?= e($statusLabel) ?></div>
    <?php endif; ?>

    <div class="content">
        <header class="title-block">
            <h1 class="header-title">Quotation</h1>
            <div class="header-meta">
                <div class="item">
                    <span class="label">Quotation No.</span>
                    <span class="value"><?= e($q['quotation_number']) ?></span>
                </div>
                <div class="item">
                    <span class="label">Date</span>
                    <span class="value"><?= e($dateDisp) ?></span>
                </div>
                <div class="item">
                    <span class="label">Valid Until</span>
                    <span class="value"><?= e($expiryDisp) ?></span>
                </div>
            </div>
        </header>

        <section class="parties">
            <div class="party-card">
                <h3>Bill To</h3>
                <div class="field name-row"><span class="k">Customer Name</span><span class="v"><?= e($customerName) ?></span></div>
                <?php if ($contactPerson !== '' && $contactPerson !== $customerName): ?>
                    <div class="field"><span class="k">Contact</span><span class="v"><?= e($contactPerson) ?></span></div>
                <?php endif; ?>
                <div class="field"><span class="k">Customer Code</span><span class="v"><?= e((string) ($q['customer_code'] ?: '—')) ?></span></div>
                <div class="field"><span class="k">Address</span><span class="v"><?= $billing !== '' ? nl2br(e($billing)) : '—' ?></span></div>
                <div class="field"><span class="k">Phone</span><span class="v"><?= e($phone !== '' ? $phone : '—') ?></span></div>
                <?php if ($mobile !== '' && $mobile !== $phone): ?>
                    <div class="field"><span class="k">Mobile</span><span class="v"><?= e($mobile) ?></span></div>
                <?php endif; ?>
                <?php if ($email !== ''): ?>
                    <div class="field"><span class="k">Email</span><span class="v"><?= e($email) ?></span></div>
                <?php endif; ?>
            </div>
            <div class="party-card">
                <h3>Sales Details</h3>
                <div class="field"><span class="k">Sales Executive</span><span class="v"><?= e((string) ($q['sales_executive_name'] ?: '—')) ?></span></div>
                <div class="field"><span class="k">Prepared By</span><span class="v"><?= e($preparedBy) ?></span></div>
                <div class="field"><span class="k">Branch</span><span class="v"><?= e((string) ($q['branch'] ?: 'Kilinochchi')) ?></span></div>
                <div class="field"><span class="k">Currency</span><span class="v"><?= e($currency) ?></span></div>
                <?php if (!empty($q['payment_terms'])): ?>
                    <div class="field"><span class="k">Payment</span><span class="v"><?= e($q['payment_terms']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($q['delivery_terms'])): ?>
                    <div class="field"><span class="k">Delivery</span><span class="v"><?= e($q['delivery_terms']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($q['reference_number'])): ?>
                    <div class="field"><span class="k">Reference</span><span class="v"><?= e($q['reference_number']) ?></span></div>
                <?php endif; ?>
            </div>
        </section>

        <div class="table-wrap">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:36px" class="ctr">#</th>
                        <th class="desc">Description</th>
                        <th style="width:52px" class="ctr">Qty</th>
                        <th style="width:52px" class="ctr">Unit</th>
                        <th style="width:78px" class="num">Unit Price</th>
                        <th style="width:68px" class="num">Discount</th>
                        <th style="width:60px" class="num">Tax</th>
                        <th style="width:80px" class="num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="8" class="ctr" style="height:48px;color:#adb5bd">No line items</td></tr>
                <?php else: $n = 1; foreach ($items as $ln): ?>
                    <tr>
                        <td class="ctr"><?= $n++ ?></td>
                        <td class="desc">
                            <?= e($ln['product_name']) ?>
                            <?php if (!empty($ln['description'])): ?><span class="item-sub"><?= e($ln['description']) ?></span><?php endif; ?>
                        </td>
                        <td class="ctr"><?= e(rtrim(rtrim(number_format((float) $ln['quantity'], 3), '0'), '.')) ?></td>
                        <td class="ctr"><?= e((string) ($ln['unit'] ?: 'pcs')) ?></td>
                        <td class="num"><?= e($money((float) $ln['unit_price'])) ?></td>
                        <td class="num"><?= (float) $ln['discount_amount'] > 0 ? e($money((float) $ln['discount_amount'])) : '—' ?></td>
                        <td class="num"><?= (float) $ln['tax_amount'] > 0 ? e($money((float) $ln['tax_amount'])) : '—' ?></td>
                        <td class="num"><?= e($money((float) $ln['line_total'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <section class="mid">
            <div class="words">
                <span class="lbl">Amount in Words</span>
                <div class="val"><?= e($amountWords) ?></div>
            </div>
            <aside class="summary">
                <table>
                    <tr><td>Subtotal</td><td><?= e($money((float) $q['subtotal'])) ?></td></tr>
                    <tr><td>Discount</td><td><?= $totalDisc > 0 ? '-' . e($money($totalDisc)) : e($money(0)) ?></td></tr>
                    <tr><td>Tax</td><td><?= e($money((float) $q['tax_total'])) ?></td></tr>
                    <?php if ((float) $q['shipping_amount'] > 0): ?>
                    <tr><td>Shipping</td><td><?= e($money((float) $q['shipping_amount'])) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $q['additional_charges'] > 0): ?>
                    <tr><td>Other Charges</td><td><?= e($money((float) $q['additional_charges'])) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $q['round_off'] != 0.0): ?>
                    <tr><td>Round Off</td><td><?= e($money((float) $q['round_off'])) ?></td></tr>
                    <?php endif; ?>
                    <tr class="grand"><td>Grand Total</td><td><?= e($currency) ?> <?= e($money((float) $q['grand_total'])) ?></td></tr>
                </table>
            </aside>
        </section>

        <?php if (!empty($q['notes'])): ?>
        <section class="notes-block">
            <h4>Customer Notes</h4>
            <p><?= nl2br(e($q['notes'])) ?></p>
        </section>
        <?php endif; ?>

        <?php if (!empty($q['terms_html'])): ?>
        <section class="terms-block">
            <h4>Terms &amp; Conditions</h4>
            <div class="terms-body"><pre><?= e($q['terms_html']) ?></pre></div>
        </section>
        <?php elseif (!empty($q['warranty_terms'])): ?>
        <section class="terms-block">
            <h4>Warranty</h4>
            <div class="terms-body"><p><?= e($q['warranty_terms']) ?></p></div>
        </section>
        <?php endif; ?>

        <?php if (!empty($q['warranty_terms']) && !empty($q['terms_html'])): ?>
        <section class="notes-block">
            <h4>Warranty</h4>
            <p><?= e($q['warranty_terms']) ?></p>
        </section>
        <?php endif; ?>

        <section class="bank-qr">
            <div class="bank">
                <strong>Bank Details</strong>
                <?= e($bankName) ?> · <?= e($bankAccountName) ?>
                <?php if ($bankAccountNumber !== ''): ?><br>A/C No : <?= e($bankAccountNumber) ?><?php endif; ?>
                <?php if ($bankBranch !== ''): ?> · Branch : <?= e($bankBranch) ?><?php endif; ?>
                <?php if (!empty($q['payment_terms'])): ?><br>Payment Terms : <?= e($q['payment_terms']) ?><?php endif; ?>
            </div>
            <div class="qr">
                <img src="<?= e($qrUrl) ?>" alt="QR verification" width="72" height="72">
                <small>Scan to Verify</small>
            </div>
        </section>

        <section class="signs">
            <div class="sign">
                <div class="sign-slot">
                    <?php if ($hasSignature): ?><img class="sig" src="<?= e($signatureUrl) ?>" alt="Digital signature"><?php endif; ?>
                </div>
                <div class="line">
                    <div class="label">Prepared By</div>
                    <div class="who"><?= e($preparedBy) ?></div>
                </div>
            </div>
            <div class="sign">
                <div class="sign-slot">
                    <?php if ($hasStamp): ?><img class="stamp" src="<?= e($stampUrl) ?>" alt="Company seal"><?php endif; ?>
                </div>
                <div class="line">
                    <div class="label">Authorized Signature</div>
                    <div class="who">VK NETWORK</div>
                </div>
            </div>
            <div class="sign">
                <div class="sign-slot" aria-hidden="true"></div>
                <div class="line">
                    <div class="label">Customer Signature</div>
                    <div class="who">Accepted &amp; Confirmed</div>
                </div>
            </div>
        </section>

        <div class="gen-meta">
            <span>Generated <?= e($generatedAt) ?> · vkitnet.info</span>
            <span>Page 1 of 1</span>
        </div>
    </div>
</article>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });</script>
<?php endif; ?>
</body>
</html>
