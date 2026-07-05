<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
$pdo = db();
require_once dirname(__DIR__, 2) . '/includes/invoices_schema.php';
vk_ensure_invoice_items_table($pdo);

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT i.*, c.name AS customer_name, c.phone, c.email, c.address
     FROM invoices i
     JOIN customers c ON c.id = i.customer_id
     WHERE i.id = ?'
);
$st->execute([$id]);
$inv = $st->fetch();
if (!$inv) {
    http_response_code(404);
    echo 'Invoice not found.';
    exit;
}

$items = $pdo->prepare(
    'SELECT ii.*, p.name AS product_name
     FROM invoice_items ii
     LEFT JOIN products p ON p.id = ii.product_id
     WHERE ii.invoice_id = ?'
);
$items->execute([$id]);
$lines = $items->fetchAll();
$due = (float) $inv['grand_total'] - (float) $inv['paid_amount'];
$businessName = 'VK NETWORK';
$businessPhone = '+94 70 588 6782';
$businessEmail = 'info@vkitnet.info';
$businessWebsite = 'www.vkitnet.info';
$businessAddress = 'Kilinochchi, Sri Lanka';
$businessTagline = 'Connecting You to a Smarter Digital World';
$businessServices = 'VK NETWORK | Software Development | Hardware Solutions | CCTV Surveillance | Network Infrastructure';

$projectRoot = dirname(__DIR__, 2);
$signaturePath = $projectRoot . '/assets/images/digital-signature.png';
$stampPath = $projectRoot . '/assets/images/company-stamp.png';
$qrPath = $projectRoot . '/assets/images/invoice-qr.png';
$hasSignature = is_file($signaturePath);
$hasStamp = is_file($stampPath);
$signatureUrl = base_url('assets/images/digital-signature.png');
$stampUrl = base_url('assets/images/company-stamp.png');
$logoFile = $projectRoot . '/assets/images/vk-logo.png';
$headerLogoVer = is_file($logoFile) ? (string) @filemtime($logoFile) : '2';
$headerLogoUrl = base_url('assets/images/vk-logo.png?v=' . $headerLogoVer);
$showHeaderLogo = is_file($logoFile);
$watermarkUrl = $showHeaderLogo ? $headerLogoUrl : '';
$qrUrl = is_file($qrPath)
    ? base_url('assets/images/invoice-qr.png')
    : 'https://api.qrserver.com/v1/create-qr-code/?size=55x55&margin=1&data=' . rawurlencode('https://www.vkitnet.info');

$isPaid = $due <= 0.0001;
$isPartial = !$isPaid && (float) $inv['paid_amount'] > 0.0001;
$paymentLabel = $isPaid ? 'Paid' : ($isPartial ? 'Partially paid' : 'Unpaid');

$dueDateDisplay = (string) $inv['invoice_date'];
try {
    $dueDateDisplay = (new DateTime((string) $inv['invoice_date']))->modify('+30 days')->format('Y-m-d');
} catch (Throwable $e) {
    $dueDateDisplay = (string) $inv['invoice_date'];
}

require_once dirname(__DIR__, 2) . '/includes/invoice_print_settings.php';
$ipsPreview = !empty($_GET['settings_preview']);
$ipsSettings = $ipsPreview && !empty($_SESSION['invoice_print_preview_draft']) && is_array($_SESSION['invoice_print_preview_draft'])
    ? array_merge(vk_invoice_print_settings_defaults(), $_SESSION['invoice_print_preview_draft'])
    : vk_invoice_print_settings_get($pdo);

$ipsAsset = static function (string $rel) use ($projectRoot): string {
    $abs = $projectRoot . '/' . ltrim(str_replace('\\', '/', $rel), '/');
    return is_file($abs) ? $rel : '';
};

$logoRel = $ipsAsset((string) ($ipsSettings['logo_path'] ?? 'assets/images/vk-logo.png')) ?: 'assets/images/vk-logo.png';
$headerLogoUrl = vk_invoice_print_asset_url($logoRel);
$showHeaderLogo = !empty($ipsSettings['logo_enabled']) && is_file($projectRoot . '/' . ltrim($logoRel, '/'));

$watermarkRel = (string) ($ipsSettings['watermark_path'] ?? 'assets/images/vk-logo.png');
$watermarkUrl = !empty($ipsSettings['watermark_enabled']) && $ipsAsset($watermarkRel) !== ''
    ? vk_invoice_print_asset_url($watermarkRel)
    : '';

$signatureRel = (string) ($ipsSettings['signature_path'] ?? 'assets/images/digital-signature.png');
$signaturePath = $projectRoot . '/' . ltrim($signatureRel, '/');
$hasSignature = !empty($ipsSettings['signature_enabled']) && is_file($signaturePath);
$signatureUrl = $hasSignature ? vk_invoice_print_asset_url($signatureRel) : $signatureUrl;

$stampRel = (string) ($ipsSettings['stamp_path'] ?? 'assets/images/company-stamp.png');
$stampPath = $projectRoot . '/' . ltrim($stampRel, '/');
$hasStamp = !empty($ipsSettings['stamp_enabled']) && is_file($stampPath);
$stampUrl = $hasStamp ? vk_invoice_print_asset_url($stampRel) : $stampUrl;

$ipsCssVars = vk_invoice_print_settings_css_vars($ipsSettings);
$ipsPageSize = (string) ($ipsSettings['page_size'] ?? 'A4');
$ipsOrientation = (string) ($ipsSettings['page_orientation'] ?? 'portrait');
$ipsAtPage = strtolower($ipsPageSize) . ' ' . strtolower($ipsOrientation);
$showFooterQr = !empty($ipsSettings['footer_qr_enabled']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice <?= e($inv['invoice_number']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --vk-brand: #0B4DBA;
            --vk-blue: #0B4DBA;
            --vk-blue-dark: #0B4DBA;
            --vk-blue-line: #0B4DBA;
            --vk-text: #1a1a1a;
            --vk-muted: #555;
            --vk-border: #d8dce3;
            --vk-divider: #D8D8D8;
            --vk-table-head: #0B4DBA;
            --page-x: 30px;

            --stamp-width: 85mm;
            --stamp-height: 30mm;
            <?= $ipsCssVars ?>
        }

        * { box-sizing: border-box; }

        @page {
            size: <?= e($ipsAtPage) ?>;
            margin: var(--ips-page-margin-top, 10mm) var(--ips-page-margin-right, 10mm) var(--ips-page-margin-bottom, 10mm) var(--ips-page-margin-left, 10mm);
        }

        body {
            margin: 0;
            font-family: var(--ips-global-font, Inter, "Segoe UI", Arial, sans-serif);
            font-size: var(--ips-global-font-size, 11pt);
            color: var(--vk-text);
            background: #e8edf3;
            line-height: 1.3;
        }

        .print-toolbar {
            max-width: 210mm;
            margin: 12px auto 0;
            padding: 0 12px;
            text-align: right;
        }

        .print-toolbar button {
            background: var(--vk-blue);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 0.55rem 1.1rem;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .invoice-page {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 190mm;
            min-height: 277mm;
            margin: 0 auto;
            background: #fff;
            box-sizing: border-box;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.12);
            overflow-x: hidden;
        }

        /* ── Letterhead header ── */
        .letterhead-header {
            flex-shrink: 0;
            background: #fff;
            overflow: visible !important;
            padding: 0;
            margin: 0;
            border-bottom: var(--ips-header-line-thickness, 3px) solid var(--ips-header-line-color, #0B4DBA);
        }

        .header {
            display: grid;
            grid-template-columns: 160px minmax(0, 1fr) 210px;
            align-items: center;
            column-gap: 14px;
            width: 100%;
            max-width: 190mm;
            margin: 0 auto;
            padding: 16px 8mm;
            box-sizing: border-box;
            overflow: visible !important;
        }

        .company-logo {
            width: 160px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: visible !important;
            margin: 0;
            padding: 0;
        }

        .company-logo img {
            display: block;
            width: auto;
            height: auto;
            max-width: var(--ips-logo-width, 160px);
            max-height: var(--ips-logo-height, 90px);
            object-fit: contain;
            object-position: center;
            overflow: visible;
            border: 0;
            margin: 0;
            padding: 0;
            transform: none;
            clip-path: none;
        }

        .letterhead-col--center {
            text-align: center;
            padding: 0 4px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 0;
            overflow: visible;
        }

        .letterhead-col--contact {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            min-width: 210px;
            overflow: visible;
            flex-shrink: 0;
        }

        .letterhead-company {
            font-family: var(--ips-global-font, Arial, Helvetica, "Segoe UI", sans-serif);
            font-size: var(--ips-company-name-size, 32px);
            font-weight: var(--ips-company-name-weight, 700);
            color: #0B4DBA;
            letter-spacing: 1px;
            line-height: 1.08;
            margin: 0 0 4px;
            text-transform: uppercase;
            white-space: nowrap;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .letterhead-services {
            font-family: var(--ips-global-font, Arial, Helvetica, "Segoe UI", sans-serif);
            font-size: var(--ips-company-desc-size, 13px);
            font-weight: 500;
            color: var(--ips-company-desc-color, #222);
            margin: 0;
            line-height: 1.45;
        }

        .letterhead-tagline {
            font-family: Arial, Helvetica, "Segoe UI", sans-serif;
            font-size: 12px;
            font-style: italic;
            font-weight: 400;
            color: var(--vk-brand);
            margin: 4px 0 0;
            line-height: 1.35;
        }

        .letterhead-contact {
            font-family: Arial, Helvetica, "Segoe UI", sans-serif;
            font-size: 12px;
            font-weight: 400;
            color: #222;
            line-height: 1.35;
        }

        .letterhead-contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            white-space: nowrap;
            font-size: 11px;
        }

        .letterhead-contact-item:last-child {
            margin-bottom: 0;
        }

        .icon-circle {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--vk-brand);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .icon-circle svg {
            width: 14px;
            height: 14px;
            fill: #fff;
        }

        .header-rule {
            display: block;
            height: var(--ips-header-line-thickness, 3px);
            min-height: 3px;
            width: 100%;
            margin: 0;
            border: none;
            padding: 0;
            background: var(--ips-header-line-color, #0B4DBA);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            flex-shrink: 0;
        }

        /* ── Watermark ── */
        .page-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(var(--ips-watermark-rotation, 0deg));
            width: var(--ips-watermark-width, 380px);
            opacity: var(--ips-watermark-opacity, 0.03);
            z-index: 0;
            pointer-events: none;
            user-select: none;
        }

        .page-watermark img {
            width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        /* ── Invoice body ── */
        .invoice-body {
            position: relative;
            z-index: 1;
            flex: 1 1 auto;
            padding: 25px var(--page-x) 10px;
        }

        .invoice-heading {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            padding-top: var(--ips-invoice-title-margin-top, 25px);
            margin-bottom: 12px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .invoice-heading h1 {
            margin: 0 0 0 var(--ips-invoice-title-margin-left, 0);
            font-size: var(--ips-invoice-title-size, 28px);
            font-weight: var(--ips-invoice-title-weight, 700);
            color: var(--ips-invoice-title-color, #0B4DBA);
            letter-spacing: 0.08em;
            line-height: 1.2;
        }

        .invoice-meta-grid {
            display: grid;
            grid-template-columns: 130px 180px;
            gap: 0;
            font-size: 12px;
            align-self: flex-start;
        }

        .invoice-meta-grid dt {
            font-weight: 600;
            color: var(--vk-muted);
            margin: 0;
            text-align: left;
            min-height: 26px;
            line-height: 26px;
        }

        .invoice-meta-grid dd {
            margin: 0;
            font-weight: 600;
            color: var(--vk-text);
            text-align: right;
            min-height: 26px;
            line-height: 26px;
        }

        .payment-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .payment-status--paid { background: #dcfce7; color: #166534; }
        .payment-status--partial { background: #fef9c3; color: #854d0e; }
        .payment-status--unpaid { background: #fee2e2; color: #991b1b; }

        .customer-block {
            margin-bottom: 10px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .section-title {
            font-size: var(--ips-customer-label-size, 10pt);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--vk-blue);
            margin: 0 0 4px;
        }

        .customer-block p {
            margin: 0 0 2px;
            font-size: var(--ips-customer-address-size, 11pt);
        }

        .customer-name {
            font-weight: 700;
            font-size: var(--ips-customer-name-size, 12pt);
        }

        /* ── Items table ── */
        .invoice-items {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: var(--ips-table-body-size, 11pt);
        }

        .invoice-items thead {
            display: table-header-group;
        }

        .invoice-items th {
            background: var(--ips-table-header-bg, var(--vk-table-head));
            color: var(--ips-table-header-color, #fff);
            font-weight: 700;
            font-size: var(--ips-table-header-size, 9.5pt);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: var(--ips-table-row-padding, 6px) 8px;
            border: 1px solid var(--ips-table-border, #094099);
            text-align: left;
        }

        .invoice-items th.num,
        .invoice-items td.num {
            text-align: right;
            white-space: nowrap;
        }

        .invoice-items th.center,
        .invoice-items td.center {
            text-align: center;
            white-space: nowrap;
        }

        .invoice-items td {
            padding: var(--ips-table-row-padding, 6px) 8px;
            border: 1px solid var(--ips-table-border, var(--vk-border));
            vertical-align: middle;
        }

        .invoice-items tbody tr:nth-child(even) td {
            background: #fafbfc;
        }

        .invoice-items .dash {
            color: #9ca3af;
            text-align: center;
        }

        /* ── Totals & approval ── */
        .totals-approval-block {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .totals-wrap {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 0;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .totals-table {
            width: 280px;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 4px;
            overflow: hidden;
        }

        .totals-table .total-row td {
            background: var(--ips-total-bg, #0B4DBA);
            color: var(--ips-total-color, #fff);
            padding: 10px 15px;
            border: none;
            font-weight: var(--ips-total-weight, 700);
        }

        .totals-table .total-row td:first-child {
            font-size: var(--ips-total-label-size, 13px);
        }

        .totals-table .total-row td:last-child {
            font-size: var(--ips-total-size, 16px);
            text-align: right;
            min-width: 100px;
        }

        .invoice-notes {
            font-size: 10pt;
            color: var(--vk-muted);
            margin: 0 0 10px;
            page-break-inside: avoid;
        }

        /* ── Footer ── */
        .footer.letterhead-footer {
            flex-shrink: 0;
            margin-top: 10px;
            padding: 8px 20px 6px;
            border-top: 2px solid #0B4DBA;
            background: #fff;
            width: 100%;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .footer-left {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: nowrap;
            flex: 1 1 auto;
            min-width: 0;
        }

        .footer-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            white-space: nowrap;
            line-height: 1.2;
        }

        .footer-item .footer-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
            color: #fff;
            background: #0B4DBA;
        }

        .footer-item .footer-icon svg {
            width: 10px;
            height: 10px;
            fill: #fff;
        }

        .footer-qr {
            flex: 0 0 55px;
            margin-left: auto;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .footer-qr img {
            width: 55px;
            height: 55px;
            display: block;
            image-rendering: -webkit-optimize-contrast;
        }

        .footer-bottom {
            margin-top: 6px;
            padding-top: 5px;
            border-top: 1px solid #d9d9d9;
            text-align: center;
            font-size: var(--ips-footer-font-size, 10px);
            color: #555;
            line-height: 1.3;
        }

        /* ── Authorized By approval section ── */
        .approval-section {
            width: var(--stamp-width);
            min-width: var(--stamp-width);
            max-width: 100%;
            margin-top: 15px;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .approval-section .digital-signature {
            width: var(--ips-signature-width, 120px);
            height: auto;
            display: block;
            object-fit: contain;
            opacity: var(--ips-signature-opacity, 1);
            transform: rotate(var(--ips-signature-rotation, 0deg));
        }

        .signature-line {
            width: 120px;
            border-top: 1px solid #444;
            margin: 4px auto;
        }

        .approval-label {
            font-size: var(--ips-approval-label-size, 10px);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #333;
            text-align: center;
            margin: 2px 0;
            line-height: 1;
        }

        .approval-section .company-stamp {
            width: var(--stamp-width) !important;
            height: var(--stamp-height) !important;
            min-width: var(--stamp-width);
            min-height: var(--stamp-height);
            max-width: var(--stamp-width);
            max-height: var(--stamp-height);
            display: block;
            flex-shrink: 0;
            margin: 0 auto;
            object-fit: contain;
            object-position: center;
            opacity: var(--stamp-opacity, 1);
            transform: rotate(var(--stamp-rotation, 0deg));
            filter: contrast(var(--stamp-contrast, 1.5)) saturate(var(--stamp-saturation, 1.4)) brightness(var(--stamp-brightness, 1.08));
            image-rendering: -webkit-optimize-contrast;
            image-rendering: high-quality;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Screen spacer for footer clearance ── */
        .body-spacer { height: 0; }

        @media print {
            body {
                background: #fff;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .no-print { display: none !important; }

            .invoice-page {
                width: 190mm;
                min-height: 277mm;
                margin: 0 auto;
                box-shadow: none;
                border: none;
                overflow-x: hidden;
            }

            .letterhead-header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                padding: 0;
                z-index: 100;
                background: #fff;
                overflow: visible !important;
                border-bottom: var(--ips-header-line-thickness, 3px) solid var(--ips-header-line-color, #0B4DBA);
            }

            .header {
                max-width: 190mm;
                margin-left: auto;
                margin-right: auto;
                padding: 12px 10mm;
                box-sizing: border-box;
                overflow: visible !important;
            }

            .company-logo,
            .company-logo img {
                overflow: visible !important;
            }

            .header-rule {
                margin-top: 0;
                width: 100%;
                min-height: 3px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .letterhead-company {
                color: #0B4DBA;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .invoice-body {
                margin-top: 48mm;
                margin-bottom: 24mm;
                padding: 28px var(--page-x) 8px;
            }

            .invoice-heading {
                padding-top: 25px;
            }

            .footer.letterhead-footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                margin-top: 0;
                padding: 8px 20px 6px;
                z-index: 100;
                background: #fff;
            }

            .page-watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                opacity: 0.03;
                z-index: 0;
            }

            .body-spacer { display: none; }

            .approval-section {
                page-break-inside: avoid;
                break-inside: avoid;
                margin-top: 15px;
                margin-bottom: 15px;
            }

            .totals-approval-block {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .approval-section .company-stamp {
                width: var(--stamp-width) !important;
                height: var(--stamp-height) !important;
                min-width: var(--stamp-width);
                min-height: var(--stamp-height);
                max-width: var(--stamp-width);
                max-height: var(--stamp-height);
                opacity: 1;
                transform: none;
                filter: contrast(1.5) saturate(1.4) brightness(1.08);
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
                image-rendering: -webkit-optimize-contrast;
                image-rendering: crisp-edges;
            }

            .approval-section .digital-signature {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
                image-rendering: -webkit-optimize-contrast;
            }

            .customer-block,
            .invoice-heading,
            .totals-wrap,
            .totals-approval-block,
            .approval-section,
            .invoice-notes {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .invoice-items tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
<div class="print-toolbar no-print">
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="invoice-page">
    <?php if ($watermarkUrl !== ''): ?>
    <div class="page-watermark" aria-hidden="true">
        <img src="<?= e($watermarkUrl) ?>" alt="">
    </div>
    <?php endif; ?>

    <header class="letterhead-header">
        <div class="header">
            <?php if ($showHeaderLogo): ?>
            <div class="company-logo">
                <img id="invoiceHeaderLogo" src="<?= e($headerLogoUrl) ?>" alt="VK Network Logo">
            </div>
            <?php else: ?>
            <div class="company-logo" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="letterhead-col letterhead-col--center">
                <h2 class="letterhead-company"><?= e($businessName) ?></h2>
                <p class="letterhead-services">Software Development | Hardware Solutions</p>
                <p class="letterhead-services">CCTV Surveillance | Network Infrastructure</p>
                <p class="letterhead-tagline"><?= e($businessTagline) ?></p>
            </div>
            <div class="letterhead-col letterhead-col--contact">
                <div class="letterhead-contact">
                    <div class="letterhead-contact-item">
                        <span class="icon-circle"><svg viewBox="0 0 24 24"><path d="M6.6 10.8c1.4 2.8 3.7 5.1 6.5 6.5l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.5.6.6 0 1 .4 1 1V21c0 .6-.4 1-1 1C10.3 22 2 13.7 2 3c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.5.1.3 0 .7-.2 1L6.6 10.8z"/></svg></span>
                        <span><?= e($businessPhone) ?></span>
                    </div>
                    <div class="letterhead-contact-item">
                        <span class="icon-circle"><svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5L4 8V6l8 5 8-5v2z"/></svg></span>
                        <span><?= e($businessEmail) ?></span>
                    </div>
                    <div class="letterhead-contact-item">
                        <span class="icon-circle"><svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.9 9H15.8a15.7 15.7 0 0 0-1.2-5.1A8 8 0 0 1 19.9 11zM12 4c.9 1.2 1.6 2.6 2 4.1H10c.4-1.5 1.1-2.9 2-4.1zM8.4 5.9A15.7 15.7 0 0 0 7.2 11H4.1a8 8 0 0 1 4.3-5.1zM4.1 13h3.1c.3 1.8.8 3.5 1.2 5.1A8 8 0 0 1 4.1 13zm7.9 7c-.9-1.2-1.6-2.6-2-4.1h4c-.4 1.5-1.1 2.9-2 4.1zm3.5-1.9c.5-1.6.9-3.3 1.2-5.1h3.9a8 8 0 0 1-5.1 5.1z"/></svg></span>
                        <span><?= e($businessWebsite) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="invoice-body">
        <div class="invoice-heading">
            <div>
                <h1>INVOICE</h1>
            </div>
            <dl class="invoice-meta-grid">
                <dt>Invoice Number</dt>
                <dd><?= e($inv['invoice_number']) ?></dd>
                <dt>Invoice Date</dt>
                <dd><?= e($inv['invoice_date']) ?></dd>
                <dt>Due Date</dt>
                <dd><?= e($dueDateDisplay) ?></dd>
                <dt>Payment Status</dt>
                <dd><span class="payment-status payment-status--<?= $isPaid ? 'paid' : ($isPartial ? 'partial' : 'unpaid') ?>"><?= e($paymentLabel) ?></span></dd>
            </dl>
        </div>

        <div class="customer-block">
            <h3 class="section-title">Customer Details</h3>
            <p class="customer-name"><?= e($inv['customer_name']) ?></p>
            <?php if ($inv['phone']): ?><p><?= e($inv['phone']) ?></p><?php endif; ?>
            <?php if ($inv['email']): ?><p><?= e($inv['email']) ?></p><?php endif; ?>
            <h3 class="section-title" style="margin-top:6px;">Billing Address</h3>
            <p><?= $inv['address'] ? nl2br(e($inv['address'])) : '—' ?></p>
        </div>

        <table class="invoice-items">
            <thead>
                <tr>
                    <th style="width:2rem;">#</th>
                    <th>Description</th>
                    <th class="center" style="width:8%;">Qty</th>
                    <th class="num" style="width:12%;">Unit Price</th>
                    <th class="center" style="width:10%;">Discount</th>
                    <th class="center" style="width:8%;">Tax</th>
                    <th class="num" style="width:12%;">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php $i = 1; foreach ($lines as $ln): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= e(($ln['item_type'] ?? 'product') === 'service' ? (string) ($ln['line_description'] ?? '') : (string) ($ln['product_name'] ?? '')) ?></td>
                    <td class="center"><?= (int) $ln['quantity'] ?></td>
                    <td class="num"><?= e(number_format((float) $ln['unit_price'], 2)) ?></td>
                    <td class="center dash">—</td>
                    <td class="center dash">—</td>
                    <td class="num"><?= e(number_format((float) $ln['line_total'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($inv['notes']): ?>
            <p class="invoice-notes"><strong>Notes:</strong> <?= nl2br(e($inv['notes'])) ?></p>
        <?php endif; ?>

        <div class="totals-approval-block">
        <div class="totals-wrap">
            <table class="totals-table">
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td><?= e(number_format((float) $inv['grand_total'], 2)) ?></td>
                </tr>
            </table>
        </div>

        <div class="approval-section">
            <?php if ($hasSignature): ?>
            <img src="<?= e($signatureUrl) ?>" class="digital-signature" alt="Digital Signature" decoding="sync">
            <?php endif; ?>
            <div class="signature-line"></div>
            <div class="approval-label">AUTHORIZED BY</div>
            <?php if ($hasStamp): ?>
            <img src="<?= e($stampUrl) ?>" class="company-stamp" alt="Company Stamp" decoding="sync">
            <?php endif; ?>
        </div>
        </div>

        <div class="body-spacer"></div>
    </main>

    <footer class="footer letterhead-footer">
        <div class="footer-content">
            <div class="footer-left">
                <div class="footer-item">
                    <span class="footer-icon"><svg viewBox="0 0 24 24"><path d="M12 2C8.1 2 5 5.1 5 9c0 5.2 7 13 7 13s7-7.8 7-13c0-3.9-3.1-7-7-7zm0 9.5c-1.4 0-2.5-1.1-2.5-2.5S10.6 6.5 12 6.5s2.5 1.1 2.5 2.5S13.4 11.5 12 11.5z"/></svg></span>
                    <span><?= e($businessAddress) ?></span>
                </div>
                <div class="footer-item">
                    <span class="footer-icon"><svg viewBox="0 0 24 24"><path d="M6.6 10.8c1.4 2.8 3.7 5.1 6.5 6.5l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.5.6.6 0 1 .4 1 1V21c0 .6-.4 1-1 1C10.3 22 2 13.7 2 3c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.5.1.3 0 .7-.2 1L6.6 10.8z"/></svg></span>
                    <span><?= e($businessPhone) ?></span>
                </div>
                <div class="footer-item">
                    <span class="footer-icon"><svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5L4 8V6l8 5 8-5v2z"/></svg></span>
                    <span><?= e($businessEmail) ?></span>
                </div>
                <div class="footer-item">
                    <span class="footer-icon"><svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.9 9H15.8a15.7 15.7 0 0 0-1.2-5.1A8 8 0 0 1 19.9 11zM12 4c.9 1.2 1.6 2.6 2 4.1H10c.4-1.5 1.1-2.9 2-4.1zM8.4 5.9A15.7 15.7 0 0 0 7.2 11H4.1a8 8 0 0 1 4.3-5.1zM4.1 13h3.1c.3 1.8.8 3.5 1.2 5.1A8 8 0 0 1 4.1 13zm7.9 7c-.9-1.2-1.6-2.6-2-4.1h4c-.4 1.5-1.1 2.9-2 4.1zm3.5-1.9c.5-1.6.9-3.3 1.2-5.1h3.9a8 8 0 0 1-5.1 5.1z"/></svg></span>
                    <span><?= e($businessWebsite) ?></span>
                </div>
            </div>
            <?php if ($showFooterQr): ?>
            <div class="footer-qr">
                <img src="<?= e($qrUrl) ?>" alt="QR Code — www.vkitnet.info" width="55" height="55">
            </div>
            <?php endif; ?>
        </div>
        <p class="footer-bottom"><?= e($businessServices) ?></p>
    </footer>
</div>
<script>
(function () {
    var img = document.getElementById('invoiceHeaderLogo');
    if (!img) {
        return;
    }
    img.addEventListener('error', function () {
        console.error('VK invoice header logo failed to load:', img.getAttribute('src') || img.src);
    });
    if (!img.complete) {
        return;
    }
    if (img.naturalWidth === 0) {
        console.error('VK invoice header logo failed to load:', img.getAttribute('src') || img.src);
    }
})();
</script>
</body>
</html>
