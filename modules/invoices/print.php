<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
$pdo = db();
require_once dirname(__DIR__, 2) . '/includes/invoices_schema.php';
require_once dirname(__DIR__, 2) . '/includes/invoices_service.php';
vk_ensure_invoices_schema($pdo);

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
$itemDiscTotal = (float) ($inv['item_discount_total'] ?? 0);
$invDiscAmt = (float) ($inv['invoice_discount_amount'] ?? $inv['discount'] ?? 0);
$shippingAmt = (float) ($inv['shipping_amount'] ?? 0);
$adjustAmt = (float) ($inv['adjustment_amount'] ?? 0);
$roundOffAmt = (float) ($inv['round_off'] ?? 0);
$amountWords = vk_invoice_amount_in_words((float) $inv['grand_total'], (string) ($inv['currency'] ?? 'LKR'));
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

$dueDateDisplay = (string) ($inv['due_date'] ?? '');
if ($dueDateDisplay === '') {
    $dueDateDisplay = (string) $inv['invoice_date'];
    try {
        $dueDateDisplay = (new DateTime((string) $inv['invoice_date']))->modify('+30 days')->format('Y-m-d');
    } catch (Throwable $e) {
        $dueDateDisplay = (string) $inv['invoice_date'];
    }
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
            --page-x: 12mm;
            --section-gap: 18px;

            --stamp-width: 70mm;
            --stamp-height: 24mm;
            <?= $ipsCssVars ?>
        }

        * { box-sizing: border-box; }

        @page {
            size: <?= e($ipsAtPage) ?>;
            margin-top: var(--ips-page-margin-top, 10mm);
            margin-right: var(--ips-page-margin-right, 10mm);
            margin-bottom: var(--ips-page-margin-bottom, 10mm);
            margin-left: var(--ips-page-margin-left, 10mm);
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
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 0;
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
            margin: 0;
            padding: 14px var(--page-x);
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

        .letterhead-services--primary {
            white-space: nowrap;
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
            display: flex;
            flex-direction: column;
            padding: 20px var(--page-x) 16px;
            min-height: 0;
        }

        .invoice-heading {
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: start;
            column-gap: 24px;
            padding-top: var(--ips-invoice-title-margin-top, 8px);
            margin-bottom: var(--section-gap);
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
            grid-template-columns: minmax(110px, 42%) 1fr;
            column-gap: 12px;
            row-gap: 6px;
            font-size: 12px;
            align-self: start;
            width: 100%;
            margin: 0;
        }

        .invoice-meta-grid dt {
            font-weight: 600;
            color: var(--vk-muted);
            margin: 0;
            text-align: left;
            min-height: 22px;
            line-height: 22px;
        }

        .invoice-meta-grid dd {
            margin: 0;
            font-weight: 600;
            color: var(--vk-text);
            text-align: right;
            min-height: 22px;
            line-height: 22px;
            word-break: break-word;
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
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 24px;
            align-items: start;
            margin-bottom: var(--section-gap);
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .customer-col {
            min-width: 0;
        }

        .section-title {
            font-size: var(--ips-customer-label-size, 10pt);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--vk-blue);
            margin: 0 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid var(--vk-border);
        }

        .customer-block p {
            margin: 0 0 4px;
            font-size: var(--ips-customer-address-size, 11pt);
            line-height: 1.45;
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
            margin-bottom: var(--section-gap);
            font-size: var(--ips-table-body-size, 10.5pt);
        }

        .invoice-items thead {
            display: table-header-group;
        }

        .invoice-items th {
            background: var(--ips-table-header-bg, var(--vk-table-head));
            color: var(--ips-table-header-color, #fff);
            font-weight: 700;
            font-size: var(--ips-table-header-size, 9pt);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 10px 8px;
            border: 1px solid var(--ips-table-border, #094099);
            text-align: left;
            vertical-align: middle;
        }

        .invoice-items th.col-no { width: 6%; text-align: center; }
        .invoice-items th.col-desc { width: 34%; text-align: left; }
        .invoice-items th.col-qty { width: 8%; text-align: center; }
        .invoice-items th.col-price,
        .invoice-items th.col-disc,
        .invoice-items th.col-tax,
        .invoice-items th.col-amt { width: 13%; text-align: right; }

        .invoice-items th.num,
        .invoice-items td.num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .invoice-items th.center,
        .invoice-items td.center {
            text-align: center;
            white-space: nowrap;
        }

        .invoice-items th.left,
        .invoice-items td.left {
            text-align: left;
        }

        .invoice-items td {
            padding: 14px 8px;
            border: 1px solid var(--ips-table-border, var(--vk-border));
            vertical-align: middle;
            line-height: 1.4;
        }

        .invoice-items td.col-no {
            text-align: center;
            color: var(--vk-muted);
        }

        .invoice-items td.col-desc {
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
            padding-left: 12px;
            padding-top: 14px;
            padding-bottom: 14px;
            vertical-align: middle;
        }

        .invoice-items tbody tr:nth-child(even) td {
            background: #fafbfc;
        }

        .invoice-items .dash {
            color: #9ca3af;
        }

        /* ── Totals summary box ── */
        .totals-wrap {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            margin: 0 0 var(--section-gap);
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .totals-box {
            width: 100%;
            max-width: 300px;
            border: 1px solid var(--vk-border);
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
        }

        .totals-table td {
            padding: 9px 14px;
            font-size: 11pt;
            line-height: 1.2;
            vertical-align: middle;
            border-bottom: 1px solid #eef1f5;
        }

        .totals-table tr:last-child td {
            border-bottom: none;
        }

        .totals-table td:first-child {
            text-align: left;
            color: var(--vk-muted);
            font-weight: 600;
            width: 58%;
        }

        .totals-table td:last-child {
            text-align: right;
            font-weight: 700;
            color: var(--vk-text);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .totals-table .total-row td {
            background: var(--ips-total-bg, #0B4DBA);
            color: var(--ips-total-color, #fff);
            padding: 11px 14px;
            border: none;
            font-weight: var(--ips-total-weight, 700);
        }

        .totals-table .total-row td:first-child {
            font-size: var(--ips-total-label-size, 12px);
            color: var(--ips-total-color, #fff);
        }

        .totals-table .total-row td:last-child {
            font-size: var(--ips-total-size, 15px);
            color: var(--ips-total-color, #fff);
        }

        .totals-table .balance-row td {
            background: #f3f6fb;
            font-weight: 800;
        }

        .invoice-barcode {
            margin-top: 10px;
            text-align: center;
            width: 100%;
            max-width: 300px;
        }

        .invoice-barcode img {
            display: block;
            margin: 0 auto;
            height: 36px;
            max-width: 100%;
        }

        .invoice-notes {
            font-size: 10pt;
            color: var(--vk-muted);
            margin: 0 0 8px;
            line-height: 1.45;
            page-break-inside: avoid;
        }

        .invoice-notes-block {
            margin-bottom: var(--section-gap);
        }

        /* ── Footer ── */
        .footer.letterhead-footer {
            flex-shrink: 0;
            margin-top: auto;
            padding: 10px var(--page-x) 8px;
            border-top: 2px solid #0B4DBA;
            background: #fff;
            width: 100%;
            box-sizing: border-box;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            min-height: 55px;
        }

        .footer-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            flex: 1 1 auto;
            min-width: 0;
        }

        .footer-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            white-space: nowrap;
            line-height: 1;
        }

        .footer-item .footer-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-flex;
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
            display: block;
        }

        .footer-qr {
            flex: 0 0 55px;
            margin-left: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            align-self: center;
        }

        .footer-qr img {
            width: 55px;
            height: 55px;
            display: block;
            image-rendering: -webkit-optimize-contrast;
        }

        .footer-bottom {
            margin: 8px 0 0;
            padding-top: 6px;
            border-top: 1px solid #d9d9d9;
            text-align: center;
            font-size: var(--ips-footer-font-size, 10px);
            color: #555;
            line-height: 1.3;
        }

        /* ── Signature block (bottom of last page) ── */
        .signature-block {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 40px;
            align-items: end;
            margin-top: auto;
            padding-top: 28px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .signature-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            min-width: 0;
            text-align: center;
        }

        .signature-col .digital-signature {
            width: var(--ips-signature-width, 120px);
            height: auto;
            max-height: 48px;
            display: block;
            object-fit: contain;
            opacity: var(--ips-signature-opacity, 1);
            transform: rotate(var(--ips-signature-rotation, 0deg));
            margin: 0 auto 4px;
        }

        .signature-col .company-stamp {
            width: var(--stamp-width) !important;
            height: var(--stamp-height) !important;
            max-width: 100%;
            display: block;
            margin: 0 auto 6px;
            object-fit: contain;
            object-position: center;
            opacity: 0.45;
            transform: rotate(var(--stamp-rotation, 0deg));
            filter: contrast(var(--stamp-contrast, 1.5)) saturate(var(--stamp-saturation, 1.4)) brightness(var(--stamp-brightness, 1.08));
            image-rendering: -webkit-optimize-contrast;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .signature-stamp-slot {
            min-height: var(--stamp-height);
            display: flex;
            align-items: flex-end;
            justify-content: center;
            width: 100%;
            margin-bottom: 4px;
        }

        .signature-line {
            width: 85%;
            max-width: 200px;
            border-top: 1px solid #444;
            margin: 8px auto 6px;
        }

        .approval-label {
            font-size: var(--ips-approval-label-size, 10px);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #333;
            text-align: center;
            margin: 0;
            line-height: 1.2;
        }

        .body-spacer { height: 0; }

        @media print {
            body {
                background: #fff;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .no-print { display: none !important; }

            .invoice-page {
                width: 100%;
                min-height: auto;
                margin: 0;
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
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 10px var(--page-x);
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
                margin-top: 46mm;
                margin-bottom: 28mm;
                padding: 16px var(--page-x) 12px;
            }

            .invoice-heading {
                padding-top: 4px;
            }

            .footer.letterhead-footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                margin-top: 0;
                padding: 8px var(--page-x) 6px;
                z-index: 100;
                background: #fff;
                box-sizing: border-box;
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

            .signature-block {
                page-break-inside: avoid;
                break-inside: avoid;
                margin-top: 24px;
                padding-top: 20px;
            }

            .signature-col .company-stamp {
                opacity: 0.45;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .signature-col .digital-signature {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
                image-rendering: -webkit-optimize-contrast;
            }

            .customer-block,
            .invoice-heading,
            .totals-wrap,
            .signature-block,
            .invoice-notes,
            .invoice-notes-block {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .invoice-items tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .totals-table .total-row td {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
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
                <p class="letterhead-services letterhead-services--primary">Software Development | Hardware Solutions</p>
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
            <div class="invoice-heading-title">
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
            <div class="customer-col">
                <h3 class="section-title">Customer Details</h3>
                <p class="customer-name"><?= e($inv['customer_name']) ?></p>
                <?php if ($inv['phone']): ?><p><?= e($inv['phone']) ?></p><?php endif; ?>
                <?php if ($inv['email']): ?><p><?= e($inv['email']) ?></p><?php endif; ?>
            </div>
            <div class="customer-col">
                <h3 class="section-title">Billing Address</h3>
                <p><?= $inv['address'] ? nl2br(e($inv['address'])) : '—' ?></p>
            </div>
        </div>

        <table class="invoice-items">
            <thead>
                <tr>
                    <th class="col-no center">#</th>
                    <th class="col-desc left">Description</th>
                    <th class="col-qty center">Qty</th>
                    <th class="col-price num">Unit Price</th>
                    <th class="col-disc num">Discount</th>
                    <th class="col-tax num">Tax</th>
                    <th class="col-amt num">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php $i = 1; foreach ($lines as $ln): ?>
                <?php
                $desc = ($ln['item_type'] ?? 'product') === 'service'
                    ? (string) ($ln['line_description'] ?? '')
                    : (string) ($ln['line_description'] ?? $ln['product_name'] ?? '');
                $lineDisc = (float) ($ln['discount_amount'] ?? 0);
                $lineTax = (float) ($ln['tax_amount'] ?? 0);
                $lineNet = (float) ($ln['net_amount'] ?? $ln['line_total'] ?? 0);
                ?>
                <tr>
                    <td class="col-no center"><?= $i++ ?></td>
                    <td class="col-desc left"><?= e($desc) ?></td>
                    <td class="col-qty center"><?= (int) $ln['quantity'] ?></td>
                    <td class="col-price num"><?= e(formatCurrency($ln['unit_price'])) ?></td>
                    <td class="col-disc num"><?= $lineDisc > 0 ? e(formatCurrency($lineDisc)) : '<span class="dash">—</span>' ?></td>
                    <td class="col-tax num"><?= $lineTax > 0 ? e(formatCurrency($lineTax)) : '<span class="dash">—</span>' ?></td>
                    <td class="col-amt num"><?= e(formatCurrency($lineNet)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="invoice-notes-block">
            <?php if ($inv['notes']): ?>
                <p class="invoice-notes"><strong>Notes:</strong> <?= nl2br(e($inv['notes'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($inv['terms'])): ?>
                <p class="invoice-notes"><strong>Terms &amp; Conditions:</strong> <?= nl2br(e((string) $inv['terms'])) ?></p>
            <?php endif; ?>
            <p class="invoice-notes"><strong>Amount in words:</strong> <?= e($amountWords) ?></p>
        </div>

        <div class="totals-wrap">
            <div class="totals-box">
                <table class="totals-table">
                    <tr>
                        <td>Subtotal</td>
                        <td><?= e(formatCurrency($inv['subtotal'])) ?></td>
                    </tr>
                    <tr>
                        <td>Item Discount</td>
                        <td><?= $itemDiscTotal > 0.0001 ? '-' . e(formatCurrency($itemDiscTotal)) : e(formatCurrency(0)) ?></td>
                    </tr>
                    <?php if ($invDiscAmt > 0.0001): ?>
                    <tr>
                        <td>Invoice Discount</td>
                        <td>-<?= e(formatCurrency($invDiscAmt)) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (abs($shippingAmt) > 0.0001): ?>
                    <tr>
                        <td>Shipping</td>
                        <td><?= e(formatCurrency($shippingAmt)) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (abs($adjustAmt) > 0.0001): ?>
                    <tr>
                        <td>Adjustment</td>
                        <td><?= e(formatCurrency($adjustAmt)) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ((float) $inv['tax'] > 0.0001): ?>
                    <tr>
                        <td>Tax</td>
                        <td><?= e(formatCurrency($inv['tax'])) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (abs($roundOffAmt) > 0.0001): ?>
                    <tr>
                        <td>Round Off</td>
                        <td><?= e(formatCurrency($roundOffAmt)) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td>Grand Total</td>
                        <td><?= e(formatCurrency($inv['grand_total'])) ?></td>
                    </tr>
                    <tr>
                        <td>Paid</td>
                        <td><?= e(formatCurrency($inv['paid_amount'])) ?></td>
                    </tr>
                    <tr class="balance-row">
                        <td>Balance</td>
                        <td><?= e(formatCurrency($due)) ?></td>
                    </tr>
                </table>
            </div>
            <div class="invoice-barcode">
                <img src="https://barcode.tec-it.com/barcode.ashx?data=<?= rawurlencode((string) $inv['invoice_number']) ?>&code=Code128&translate-esc=on&dpi=96" alt="Barcode">
            </div>
        </div>

        <div class="signature-block">
            <div class="signature-col signature-col--auth">
                <div class="signature-stamp-slot">
                    <?php if ($hasStamp): ?>
                    <img src="<?= e($stampUrl) ?>" class="company-stamp" alt="Company Seal" decoding="sync">
                    <?php endif; ?>
                </div>
                <?php if ($hasSignature): ?>
                <img src="<?= e($signatureUrl) ?>" class="digital-signature" alt="Digital Signature" decoding="sync">
                <?php endif; ?>
                <div class="signature-line"></div>
                <div class="approval-label">Authorized Signature</div>
            </div>
            <div class="signature-col signature-col--customer">
                <div class="signature-stamp-slot" aria-hidden="true"></div>
                <div class="signature-line"></div>
                <div class="approval-label">Customer Signature</div>
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
    if (img) {
        img.addEventListener('error', function () {
            console.error('VK invoice header logo failed to load:', img.getAttribute('src') || img.src);
        });
        if (img.complete && img.naturalWidth === 0) {
            console.error('VK invoice header logo failed to load:', img.getAttribute('src') || img.src);
        }
    }
    <?php if (!empty($_GET['download'])): ?>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 400);
    });
    <?php endif; ?>
})();
</script>
</body>
</html>
