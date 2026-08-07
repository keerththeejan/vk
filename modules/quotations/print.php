<?php
declare(strict_types=1);
/**
 * Enterprise Quotation Print / PDF — layout-only polish.
 * Letterhead background · printable area top 145 / bottom 120 / sides 55.
 * Business logic, calculations, and data queries unchanged.
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
            --gray: #6C757D;
            --border: #DEE2E6;
            --text: #212529;
            --alt-row: #FAFAFA;
            --pad-top: 145px;
            --pad-bottom: 120px;
            --pad-left: 55px;
            --pad-right: 55px;
            --section-gap: 25px;
        }
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 0; }
        html, body {
            margin: 0;
            padding: 0;
            background: #d5dde8;
            color: var(--text);
            font-family: Poppins, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
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

        /* ── Title ── */
        .title-block {
            text-align: center;
            margin-top: 10px;
            margin-bottom: 0;
        }
        .header-title {
            margin: 10px 0 20px;
            text-align: center;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #123C7A;
            text-transform: uppercase;
        }
        .header-meta {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 60px;
            margin-top: 10px;
            margin-bottom: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        .header-meta .item {
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .header-meta .label {
            font-size: 11px;
            font-weight: 600;
            color: #666666;
        }
        .header-meta .value {
            font-size: 12px;
            font-weight: 700;
            color: #123C7A;
        }

        /* ── Bill To / Sales — 48% + 4% + 48% ── */
        .parties {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 4%;
            margin-top: 0;
            margin-bottom: var(--section-gap);
        }
        .party {
            width: 48%;
        }
        .party h3 {
            margin: 0 0 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .party .name {
            font-size: 12px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }
        .party p {
            margin: 0 0 3px;
            font-size: 11px;
            color: #495057;
            line-height: 1.55;
        }

        /* ── Product table ── */
        .table-wrap {
            width: 100%;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid var(--border);
            margin-bottom: var(--section-gap);
        }
        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .items thead th {
            height: 42px;
            background: var(--primary);
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            padding: 10px;
            text-align: left;
            border: 0;
            vertical-align: middle;
        }
        .items thead th.num,
        .items tbody td.num { text-align: right; }
        .items thead th.ctr,
        .items tbody td.ctr { text-align: center; }
        .items tbody td {
            height: 38px;
            padding: 10px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            font-size: 11px;
            color: #343a40;
            word-wrap: break-word;
        }
        .items tbody tr:last-child td { border-bottom: 0; }
        .items tbody tr:nth-child(even) td { background: var(--alt-row); }
        .item-sub {
            display: block;
            margin-top: 2px;
            font-size: 10px;
            color: var(--gray);
            font-weight: 400;
        }

        /* ── Amount in words + Summary (equal height) ── */
        .mid {
            display: flex;
            align-items: stretch;
            gap: 16px;
            margin-bottom: 0;
        }
        .words {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #F1F3F5;
            border-left: 4px solid var(--secondary);
            border-radius: 0 10px 10px 0;
            padding: 20px;
            min-height: 100%;
        }
        .words .lbl {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .words .val {
            font-size: 11px;
            color: #495057;
            font-weight: 500;
            line-height: 1.6;
        }

        .summary {
            width: 320px;
            flex-shrink: 0;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(18, 60, 122, .08);
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .summary table { width: 100%; border-collapse: collapse; }
        .summary td {
            padding: 6px 0;
            font-size: 11px;
            color: #495057;
        }
        .summary td:last-child {
            text-align: right;
            font-weight: 600;
            color: var(--primary);
        }
        .summary .grand td {
            border-top: 2px solid var(--primary);
            padding-top: 12px;
            font-size: 22px;
            font-weight: 700;
            color: var(--secondary);
            line-height: 1.2;
        }
        .summary .grand td:first-child {
            font-size: 12px;
            font-weight: 700;
            color: var(--primary);
            vertical-align: middle;
        }

        /* ── Terms ── */
        .terms-block {
            margin-top: 25px;
            margin-bottom: 30px;
        }
        .terms-block h4 {
            margin: 0 0 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .terms-block pre,
        .terms-block p {
            margin: 0;
            white-space: pre-wrap;
            font-family: inherit;
            font-size: 11px;
            color: #495057;
            line-height: 1.8;
        }

        .notes-block {
            margin-bottom: 16px;
        }
        .notes-block h4 {
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
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
            gap: 16px;
            margin-bottom: var(--section-gap);
            padding: 15px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        .bank-qr .bank {
            flex: 1;
            font-size: 11px;
            color: #495057;
            line-height: 1.65;
        }
        .bank-qr .bank strong {
            display: block;
            color: var(--primary);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .bank-qr .qr {
            text-align: center;
            flex-shrink: 0;
            padding: 0 4px;
        }
        .bank-qr .qr img {
            width: 80px;
            height: 80px;
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

        /* ── Signatures — 33.33% equal columns ── */
        .signs {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: auto;
            page-break-inside: avoid;
        }
        .sign {
            width: 33.33%;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            min-height: 110px;
        }
        .sign img.sig {
            max-height: 42px;
            max-width: 150px;
            margin: 0 auto 8px;
            display: block;
            object-fit: contain;
        }
        .sign img.stamp {
            max-height: 54px;
            max-width: 120px;
            margin: 0 auto 8px;
            display: block;
            object-fit: contain;
            opacity: .92;
        }
        .sign .line {
            width: 180px;
            max-width: 100%;
            border-top: 1px solid #adb5bd;
            margin-top: 45px;
            padding-top: 8px;
        }
        .sign.has-img .line { margin-top: 8px; }
        .sign .label {
            font-size: 11px;
            font-weight: 700;
            color: var(--primary);
        }
        .sign .who {
            margin-top: 3px;
            font-size: 10px;
            font-weight: 500;
            color: var(--gray);
        }

        .gen-meta {
            margin-top: 12px;
            font-size: 9px;
            color: #adb5bd;
            display: flex;
            justify-content: space-between;
            gap: 12px;
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
                padding: 145px 55px 120px;
                min-height: 100vh;
            }
            .parties { flex-direction: column; gap: 16px; }
            .party { width: 100%; }
            .mid { flex-direction: column; }
            .summary { width: 100%; }
            .signs { flex-direction: column; align-items: center; }
            .sign { width: 100%; min-height: 90px; }
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
                    <span class="label">Quotation No :</span>
                    <span class="value"><?= e($q['quotation_number']) ?></span>
                </div>
                <div class="item">
                    <span class="label">Date :</span>
                    <span class="value"><?= e($dateDisp) ?></span>
                </div>
                <div class="item">
                    <span class="label">Validity :</span>
                    <span class="value"><?= e($expiryDisp) ?></span>
                </div>
            </div>
        </header>

        <section class="parties">
            <div class="party">
                <h3>Bill To</h3>
                <div class="name"><?= e($customerName) ?></div>
                <?php if ($contactPerson !== '' && $contactPerson !== $customerName): ?>
                    <p><?= e($contactPerson) ?></p>
                <?php endif; ?>
                <?php if (!empty($q['customer_code'])): ?><p>Code : <?= e($q['customer_code']) ?></p><?php endif; ?>
                <?php if ($billing !== ''): ?><p><?= nl2br(e($billing)) ?></p><?php endif; ?>
                <?php if ($phone !== ''): ?><p>Phone : <?= e($phone) ?></p><?php endif; ?>
                <?php if ($mobile !== '' && $mobile !== $phone): ?><p>Mobile : <?= e($mobile) ?></p><?php endif; ?>
                <?php if ($email !== ''): ?><p>Email : <?= e($email) ?></p><?php endif; ?>
                <?php if (!empty($q['tax_number'])): ?><p>TIN/VAT : <?= e($q['tax_number']) ?></p><?php endif; ?>
            </div>
            <div class="party">
                <h3>Sales Details</h3>
                <p>Sales Executive : <?= e((string) ($q['sales_executive_name'] ?: '—')) ?></p>
                <p>Prepared By : <?= e($preparedBy) ?></p>
                <p>Branch : <?= e((string) ($q['branch'] ?: 'Kilinochchi')) ?></p>
                <p>Currency : <?= e($currency) ?></p>
                <?php if (!empty($q['payment_terms'])): ?><p>Payment : <?= e($q['payment_terms']) ?></p><?php endif; ?>
                <?php if (!empty($q['delivery_terms'])): ?><p>Delivery : <?= e($q['delivery_terms']) ?></p><?php endif; ?>
                <?php if (!empty($q['customer_po_number'])): ?><p>Customer PO : <?= e($q['customer_po_number']) ?></p><?php endif; ?>
                <?php if (!empty($q['reference_number'])): ?><p>Reference : <?= e($q['reference_number']) ?></p><?php endif; ?>
            </div>
        </section>

        <div class="table-wrap">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:34px" class="ctr">#</th>
                        <th>Product / Description</th>
                        <th style="width:52px" class="ctr">Qty</th>
                        <th style="width:48px" class="ctr">Unit</th>
                        <th style="width:78px" class="num">Unit Price</th>
                        <th style="width:64px" class="num">Discount</th>
                        <th style="width:56px" class="num">Tax</th>
                        <th style="width:78px" class="num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="8" class="ctr" style="height:48px;color:#adb5bd">No line items</td></tr>
                <?php else: $n = 1; foreach ($items as $ln): ?>
                    <tr>
                        <td class="ctr"><?= $n++ ?></td>
                        <td>
                            <?= e($ln['product_name']) ?>
                            <?php if (!empty($ln['product_code'])): ?><span class="item-sub"><?= e($ln['product_code']) ?></span><?php endif; ?>
                            <?php if (!empty($ln['description'])): ?><span class="item-sub"><?= e($ln['description']) ?></span><?php endif; ?>
                        </td>
                        <td class="ctr"><?= e(rtrim(rtrim(number_format((float) $ln['quantity'], 3), '0'), '.')) ?></td>
                        <td class="ctr"><?= e((string) ($ln['unit'] ?: 'pcs')) ?></td>
                        <td class="num"><?= e(number_format((float) $ln['unit_price'], 2)) ?></td>
                        <td class="num"><?= (float) $ln['discount_amount'] > 0 ? e(number_format((float) $ln['discount_amount'], 2)) : '—' ?></td>
                        <td class="num"><?= (float) $ln['tax_amount'] > 0 ? e(number_format((float) $ln['tax_amount'], 2)) : '—' ?></td>
                        <td class="num"><?= e(number_format((float) $ln['line_total'], 2)) ?></td>
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
                    <tr><td>Subtotal</td><td><?= e(number_format((float) $q['subtotal'], 2)) ?></td></tr>
                    <?php if ($totalDisc > 0): ?>
                    <tr><td>Discount</td><td>-<?= e(number_format($totalDisc, 2)) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $q['tax_total'] > 0): ?>
                    <tr><td>Tax</td><td><?= e(number_format((float) $q['tax_total'], 2)) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $q['shipping_amount'] > 0): ?>
                    <tr><td>Shipping</td><td><?= e(number_format((float) $q['shipping_amount'], 2)) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $q['additional_charges'] > 0): ?>
                    <tr><td>Other Charges</td><td><?= e(number_format((float) $q['additional_charges'], 2)) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $q['round_off'] != 0.0): ?>
                    <tr><td>Round Off</td><td><?= e(number_format((float) $q['round_off'], 2)) ?></td></tr>
                    <?php endif; ?>
                    <tr class="grand"><td>Grand Total</td><td><?= e($currency) ?> <?= e(number_format((float) $q['grand_total'], 2)) ?></td></tr>
                </table>
            </aside>
        </section>

        <?php if (!empty($q['notes'])): ?>
        <section class="notes-block" style="margin-top:20px">
            <h4>Customer Notes</h4>
            <p><?= nl2br(e($q['notes'])) ?></p>
        </section>
        <?php endif; ?>

        <?php if (!empty($q['terms_html'])): ?>
        <section class="terms-block">
            <h4>Terms &amp; Conditions</h4>
            <pre><?= e($q['terms_html']) ?></pre>
        </section>
        <?php elseif (!empty($q['warranty_terms'])): ?>
        <section class="terms-block">
            <h4>Warranty</h4>
            <p><?= e($q['warranty_terms']) ?></p>
        </section>
        <?php else: ?>
        <div style="margin-bottom:30px"></div>
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
                <img src="<?= e($qrUrl) ?>" alt="QR verification" width="80" height="80">
                <small>Scan to Verify</small>
            </div>
        </section>

        <section class="signs">
            <div class="sign<?= $hasSignature ? ' has-img' : '' ?>">
                <?php if ($hasSignature): ?><img class="sig" src="<?= e($signatureUrl) ?>" alt="Digital signature"><?php endif; ?>
                <div class="line">
                    <div class="label">Prepared By</div>
                    <div class="who"><?= e($preparedBy) ?></div>
                </div>
            </div>
            <div class="sign<?= $hasStamp ? ' has-img' : '' ?>">
                <?php if ($hasStamp): ?><img class="stamp" src="<?= e($stampUrl) ?>" alt="Company seal"><?php endif; ?>
                <div class="line">
                    <div class="label">Authorized Signature</div>
                    <div class="who">VK NETWORK</div>
                </div>
            </div>
            <div class="sign">
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
