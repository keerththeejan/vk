<?php
declare(strict_types=1);
/**
 * Enterprise Quotation Print / PDF — VK NETWORK letterhead layout.
 * Letterhead + footer match invoice branding. Body logic unchanged.
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

$logoRel = 'assets/images/vk-logo.png';
$logoAbs = $projectRoot . '/' . $logoRel;
if (!is_file($logoAbs) && is_file($projectRoot . '/assets/images/vk-network-logo.png')) {
    $logoRel = 'assets/images/vk-network-logo.png';
    $logoAbs = $projectRoot . '/' . $logoRel;
}
$showLogo = is_file($logoAbs);
$logoUrl = $showLogo ? base_url($logoRel . '?v=' . (string) filemtime($logoAbs)) : '';
$watermarkUrl = $logoUrl;

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

$verifyUrl = 'https://vkitnet.info/quote/' . rawurlencode((string) $q['quotation_number']) . '?id=' . $id;
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&margin=0&data=' . rawurlencode($verifyUrl);
$footerQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&margin=0&data=' . rawurlencode('https://www.vkitnet.info');

$bankName = vk_quotation_setting($pdo, 'bank_name', 'Commercial Bank');
$bankAccountName = vk_quotation_setting($pdo, 'bank_account_name', 'VK Network');
$bankAccountNumber = vk_quotation_setting($pdo, 'bank_account_number', '');
$bankBranch = vk_quotation_setting($pdo, 'bank_branch', 'Kilinochchi');
$bankSwift = vk_quotation_setting($pdo, 'bank_swift', '');

$businessName = 'VK NETWORK';
$businessPhone = '+94 70 588 6782';
$businessEmail = 'info@vkitnet.info';
$businessWebsite = 'www.vkitnet.info';
$businessAddress = 'Kilinochchi, Sri Lanka';
$businessTagline = 'Connecting You to a Smarter Digital World';
$businessServices = 'VK NETWORK | Software Development • Hardware Solutions • CCTV Surveillance • Network Infrastructure';

$currency = (string) ($q['currency'] ?? 'LKR');
$customerName = (string) ($q['company_name'] ?: $q['customer_name']);
$phone = (string) ($q['phone'] ?: $q['customer_phone_db'] ?: '');
$amountWords = vk_quotation_amount_in_words((float) $q['grand_total'], $currency);
$preparedBy = (string) ($q['created_by_name'] ?: $q['sales_executive_name'] ?: 'VK Network');
$showDraftMark = ((string) $q['status'] === 'draft');
$autoPrint = isset($_GET['autoprint']);
$download = isset($_GET['download']);
$totalDisc = (float) $q['item_discount_total'] + (float) $q['overall_discount_amount'];

$defaultTerms = "1. This quotation is valid until the date shown above.\n"
    . "2. Prices are in {$currency} and exclusive of unforeseen taxes unless stated.\n"
    . "3. Payment terms as agreed with VK Network.\n"
    . "4. Delivery subject to stock availability and confirmation.\n"
    . "5. Warranty applies as per manufacturer / company policy.";
$termsText = trim((string) ($q['terms_html'] ?: $q['warranty_terms'] ?: $defaultTerms));

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
            --vk-brand: #0B4DBA;
            --vk-navy: #0A2F7A;
            --vk-text: #222222;
            --vk-muted: #555555;
            --vk-border: #D9E3F0;
            --vk-alt: #F5F8FC;
            --page-x: 12mm;
            --radius: 6px;
            --header-pad-y: 20px;
            --header-height: 128px;
            --footer-height: 78px;
            --lh-gap: 16px;
            --lh-divider: #D0DAE8;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        html, body {
            background: #e8edf3;
            color: var(--vk-text);
            font-family: Poppins, Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .toolbar {
            width: 210mm;
            max-width: 100%;
            margin: 10px auto;
            padding: 0 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }
        .toolbar a,
        .toolbar button {
            appearance: none;
            border: 0;
            border-radius: 6px;
            padding: 8px 12px;
            font: 600 12px Poppins, Arial, sans-serif;
            cursor: pointer;
            text-decoration: none;
            background: var(--vk-brand);
            color: #fff;
        }
        .toolbar .ghost {
            background: #fff;
            color: var(--vk-navy);
            border: 1px solid var(--vk-border);
        }

        /* ── Page shell — exact A4 canvas ── */
        .quote-page {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 210mm;
            height: 297mm;
            min-height: 297mm;
            max-height: 297mm;
            margin: 0 auto 12px;
            padding: 0;
            background: #fff;
            box-shadow: 0 10px 40px rgba(15, 23, 42, .12);
            overflow: hidden;
        }

        .page-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 360px;
            opacity: 0.04;
            z-index: 0;
            pointer-events: none;
            user-select: none;
        }
        .page-watermark img {
            width: 100%;
            height: auto;
            display: block;
        }

        .draft-mark {
            position: absolute;
            top: 48%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-28deg);
            font-size: 56px;
            font-weight: 700;
            color: rgba(11, 77, 186, .06);
            letter-spacing: .12em;
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
        }

        /* ── HEADER — Bootstrap 3/6/3, fixed height ── */
        .letterhead-header {
            position: relative;
            z-index: 2;
            flex: 0 0 var(--header-height);
            height: var(--header-height);
            max-height: var(--header-height);
            background: #fff;
            border-bottom: 3px solid var(--vk-brand);
            box-sizing: border-box;
        }
        .lh-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            width: 100%;
            height: 100%;
            padding: var(--header-pad-y) var(--page-x);
            box-sizing: border-box;
        }
        .lh-col {
            display: flex;
            align-items: center;
            min-width: 0;
            height: 100%;
            box-sizing: border-box;
        }
        .lh-col-3 {
            flex: 0 0 25%;
            max-width: 25%;
        }
        .lh-col-6 {
            flex: 0 0 50%;
            max-width: 50%;
            justify-content: center;
            border-left: 1px solid var(--lh-divider);
            border-right: 1px solid var(--lh-divider);
            padding: 0 var(--lh-gap);
        }
        .lh-col--logo {
            justify-content: flex-start;
            padding-right: var(--lh-gap);
        }
        .lh-col--contact {
            justify-content: flex-end;
            padding-left: var(--lh-gap);
        }

        .company-logo {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            width: 100%;
            height: 100%;
        }
        .company-logo img {
            display: block;
            width: auto;
            height: 88px;
            max-width: 100%;
            max-height: 88px;
            object-fit: contain;
            object-position: left center;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        .letterhead-col--center {
            width: 100%;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
        }
        .letterhead-company {
            font-family: Poppins, Arial, Helvetica, sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--vk-brand);
            letter-spacing: 1px;
            line-height: 1.1;
            text-transform: uppercase;
            margin: 0;
            white-space: nowrap;
        }
        .letterhead-services {
            font-family: Poppins, Arial, Helvetica, sans-serif;
            font-size: 16px;
            font-weight: 500;
            color: #222;
            margin: 0;
            line-height: 1.25;
            white-space: nowrap;
        }
        .letterhead-tagline {
            font-family: Poppins, Arial, Helvetica, sans-serif;
            font-size: 12px;
            font-style: italic;
            font-weight: 400;
            color: var(--vk-brand);
            margin: 2px 0 0;
            line-height: 1.25;
            text-align: center;
            white-space: nowrap;
        }

        .letterhead-contact {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-end;
            gap: 8px;
            width: 100%;
        }
        .letterhead-contact-item {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
            gap: 8px;
            white-space: nowrap;
            font-size: 12px;
            color: #222;
            line-height: 1;
            margin: 0;
        }
        .icon-circle {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--vk-brand);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .icon-circle svg {
            width: 11px;
            height: 11px;
            fill: #fff;
            display: block;
        }

        /* ── Body — fills space between fixed header & footer ── */
        .quote-body {
            position: relative;
            z-index: 1;
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            padding: 8px var(--page-x) 6px;
            min-height: 0;
            overflow: hidden;
        }

        .doc-title {
            text-align: center;
            margin: 4px 0 10px;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 3px;
            color: var(--vk-navy);
            text-transform: uppercase;
            line-height: 1;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Info cards */
        .cards {
            display: grid;
            gap: 8px;
            margin-bottom: 8px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .cards--3 { grid-template-columns: repeat(3, 1fr); }
        .cards--2 { grid-template-columns: repeat(2, 1fr); }
        .info-card {
            border: 1px solid var(--vk-border);
            border-radius: var(--radius);
            padding: 8px 12px;
            min-height: 48px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #fff;
        }
        .info-card__lbl {
            font-size: 9px;
            font-weight: 600;
            color: #6B7A90;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 2px;
        }
        .info-card__val {
            font-size: 12px;
            font-weight: 700;
            color: var(--vk-navy);
            line-height: 1.25;
            word-break: break-word;
        }

        /* Product table */
        .table-wrap {
            border: 1px solid var(--vk-border);
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 8px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .items col.c-no { width: 30px; }
        .items col.c-qty { width: 46px; }
        .items col.c-unit { width: 46px; }
        .items col.c-price { width: 74px; }
        .items col.c-disc { width: 62px; }
        .items col.c-tax { width: 56px; }
        .items col.c-amt { width: 74px; }
        .items thead th {
            height: 32px;
            background: var(--vk-navy);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 0 6px;
            border: 1px solid #06204f;
            vertical-align: middle;
            text-align: center;
        }
        .items thead th.desc { text-align: left; padding-left: 8px; }
        .items tbody td {
            height: 26px;
            padding: 2px 6px;
            border: 1px solid var(--vk-border);
            vertical-align: middle;
            font-size: 10px;
            color: #333;
        }
        .items tbody td.desc { text-align: left; padding-left: 8px; }
        .items .ctr { text-align: center; }
        .items .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .items tbody tr:nth-child(even) td { background: var(--vk-alt); }
        .item-sub {
            display: block;
            font-size: 8px;
            color: #6B7A90;
            margin-top: 1px;
        }

        /* Words + totals */
        .totals-row {
            display: grid;
            grid-template-columns: 1fr 220px;
            gap: 12px;
            align-items: start;
            margin-bottom: 8px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .words__lbl {
            display: block;
            font-size: 9px;
            font-weight: 700;
            color: var(--vk-navy);
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .words__val {
            font-size: 10px;
            color: #445;
            font-style: italic;
            line-height: 1.35;
        }
        .summary {
            border: 1px solid var(--vk-border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .summary table { width: 100%; border-collapse: collapse; }
        .summary td {
            height: 24px;
            padding: 0 10px;
            font-size: 10px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: middle;
        }
        .summary tr:last-child td { border-bottom: 0; }
        .summary td:first-child { text-align: left; font-weight: 600; color: #6B7A90; }
        .summary td:last-child {
            text-align: right;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .summary .grand td {
            height: 30px;
            background: var(--vk-navy);
            color: #fff;
            border: 0;
        }
        .summary .grand td:first-child { color: #fff; font-size: 11px; }
        .summary .grand td:last-child { color: #fff; font-size: 13px; }

        /* Terms + Bank — equal height */
        .bottom {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            align-items: stretch;
            margin-bottom: 8px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .panel {
            border: 1px solid var(--vk-border);
            border-radius: var(--radius);
            overflow: hidden;
            background: #fff;
            display: flex;
            flex-direction: column;
            min-height: 98px;
            height: 100%;
        }
        .panel__head {
            padding: 5px 10px;
            font-size: 9.5px;
            font-weight: 700;
            color: #fff;
            background: var(--vk-navy);
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .panel__body {
            padding: 8px 10px;
            flex: 1;
        }
        .terms-text {
            font-family: inherit;
            font-size: 9px;
            color: #445;
            line-height: 1.35;
            white-space: pre-wrap;
            word-break: break-word;
            margin: 0;
            max-height: 72px;
            overflow: hidden;
        }
        .bank {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            height: 100%;
        }
        .bank__lines {
            flex: 1;
            min-width: 0;
            font-size: 9.5px;
            color: #445;
            line-height: 1.4;
        }
        .bank__lines b { color: #222; font-weight: 600; }
        .bank__qr {
            flex-shrink: 0;
            width: 62px;
        }
        .bank__qr img {
            width: 62px;
            height: 62px;
            display: block;
            border: 1px solid var(--vk-border);
            border-radius: 3px;
            background: #fff;
        }

        /* Signatures */
        .signs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            column-gap: 16px;
            align-items: end;
            margin: 4px 0 8px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .sign {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .sign__slot {
            height: 30px;
            width: 100%;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }
        .sign img.sig {
            max-height: 26px;
            max-width: 110px;
            object-fit: contain;
            display: block;
        }
        .sign img.stamp {
            max-height: 28px;
            max-width: 80px;
            object-fit: contain;
            opacity: .4;
            display: block;
        }
        .sign__line {
            width: 140px;
            max-width: 90%;
            border-top: 1px solid #444;
            margin-top: 2px;
            padding-top: 4px;
        }
        .sign__label {
            font-size: 9.5px;
            font-weight: 700;
            color: var(--vk-navy);
        }
        .sign__who {
            margin-top: 1px;
            font-size: 8.5px;
            color: #6B7A90;
        }

        /* ── FOOTER — fixed height at bottom of A4 ── */
        .footer.letterhead-footer {
            position: relative;
            z-index: 2;
            flex: 0 0 var(--footer-height);
            height: var(--footer-height);
            max-height: var(--footer-height);
            margin-top: auto;
            margin-bottom: 0;
            padding: 8px var(--page-x) 6px;
            border-top: 2px solid var(--vk-brand);
            background: #fff;
            width: 100%;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .footer-content {
            display: flex;
            flex-wrap: nowrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            width: 100%;
            min-height: 44px;
        }
        .footer-left {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: flex-start;
            gap: 0;
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
        }
        .footer-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            white-space: nowrap;
            line-height: 1;
            color: #333;
            padding: 0 12px;
        }
        .footer-item:first-child { padding-left: 0; }
        .footer-item + .footer-item {
            border-left: 1px solid #d0d0d0;
        }
        .footer-icon {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            background: var(--vk-brand);
            flex-shrink: 0;
        }
        .footer-icon svg {
            width: 9px;
            height: 9px;
            fill: #fff;
            display: block;
        }
        .footer-qr {
            flex: 0 0 44px;
            width: 44px;
            height: 44px;
            margin-left: auto;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .footer-qr img {
            width: 44px;
            height: 44px;
            display: block;
        }
        .footer-bottom {
            margin: 6px 0 0;
            padding-top: 5px;
            border-top: 1px solid #d9d9d9;
            text-align: center;
            font-size: 9px;
            color: #555;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media print {
            html, body { background: #fff !important; }
            .toolbar { display: none !important; }
            .quote-page {
                width: 210mm;
                height: 297mm;
                min-height: 297mm;
                max-height: 297mm;
                margin: 0 !important;
                box-shadow: none !important;
                overflow: hidden;
            }
            .letterhead-header {
                flex: 0 0 var(--header-height);
                height: var(--header-height);
            }
            .footer.letterhead-footer {
                flex: 0 0 var(--footer-height);
                height: var(--footer-height);
                margin-top: auto;
                margin-bottom: 0;
            }
            .quote-body {
                padding: 6px var(--page-x) 4px;
                overflow: hidden;
            }
            .page-watermark {
                position: fixed;
                opacity: 0.04;
            }
            .letterhead-company,
            .icon-circle,
            .footer-icon,
            .items thead th,
            .panel__head,
            .summary .grand td,
            .letterhead-header,
            .footer.letterhead-footer {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .letterhead-header,
            .doc-title,
            .cards,
            .table-wrap,
            .totals-row,
            .bottom,
            .signs,
            .footer.letterhead-footer {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }

        @media screen and (max-width: 900px) {
            .quote-page {
                width: 100%;
                height: auto;
                min-height: auto;
                max-height: none;
            }
            .letterhead-header {
                height: auto;
                max-height: none;
                flex: 0 0 auto;
            }
            .lh-row {
                flex-wrap: wrap;
                height: auto;
            }
            .lh-col-3,
            .lh-col-6 {
                flex: 0 0 100%;
                max-width: 100%;
                border: 0;
                padding: 8px 0;
                justify-content: center;
            }
            .lh-col--logo,
            .lh-col--contact { justify-content: center; padding: 8px 0; }
            .letterhead-contact { align-items: center; }
            .letterhead-services,
            .letterhead-company,
            .letterhead-tagline { white-space: normal; }
            .footer.letterhead-footer {
                height: auto;
                max-height: none;
                flex: 0 0 auto;
            }
            .footer-left { flex-wrap: wrap; }
            .footer-item + .footer-item { border-left: 0; }
            .footer-bottom { white-space: normal; }
            .cards--3,
            .cards--2,
            .totals-row,
            .bottom,
            .signs { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <a class="ghost" href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>">Back</a>
    <a class="ghost" href="<?= e(BASE_URL) ?>/modules/quotations/email.php?id=<?= $id ?>">Email</a>
    <a class="ghost" target="_blank" href="<?= e(vk_quotation_whatsapp_url($pdo, $q)) ?>">WhatsApp</a>
    <button type="button" onclick="window.print()"><?= $download ? 'Download PDF' : 'Print / Save PDF' ?></button>
</div>

<div class="quote-page">
    <?php if ($watermarkUrl !== ''): ?>
    <div class="page-watermark" aria-hidden="true"><img src="<?= e($watermarkUrl) ?>" alt=""></div>
    <?php endif; ?>
    <?php if ($showDraftMark): ?>
    <div class="draft-mark">DRAFT</div>
    <?php endif; ?>

    <header class="letterhead-header">
        <div class="lh-row">
            <div class="lh-col lh-col-3 lh-col--logo">
                <div class="company-logo">
                    <?php if ($showLogo): ?>
                    <img src="<?= e($logoUrl) ?>" alt="VK Network Logo" width="150" height="88">
                    <?php endif; ?>
                </div>
            </div>
            <div class="lh-col lh-col-6">
                <div class="letterhead-col--center">
                    <h2 class="letterhead-company"><?= e($businessName) ?></h2>
                    <p class="letterhead-services">Software Development | Hardware Solutions</p>
                    <p class="letterhead-services">CCTV Surveillance | Network Infrastructure</p>
                    <p class="letterhead-tagline"><?= e($businessTagline) ?></p>
                </div>
            </div>
            <div class="lh-col lh-col-3 lh-col--contact">
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

    <main class="quote-body">
        <h1 class="doc-title">Quotation</h1>

        <div class="cards cards--3">
            <div class="info-card">
                <div class="info-card__lbl">Quotation Number</div>
                <div class="info-card__val"><?= e($q['quotation_number']) ?></div>
            </div>
            <div class="info-card">
                <div class="info-card__lbl">Date</div>
                <div class="info-card__val"><?= e($dateDisp) ?></div>
            </div>
            <div class="info-card">
                <div class="info-card__lbl">Valid Until</div>
                <div class="info-card__val"><?= e($expiryDisp) ?></div>
            </div>
        </div>
        <div class="cards cards--2">
            <div class="info-card">
                <div class="info-card__lbl">Customer Name</div>
                <div class="info-card__val"><?= e($customerName) ?></div>
            </div>
            <div class="info-card">
                <div class="info-card__lbl">Phone Number</div>
                <div class="info-card__val"><?= e($phone !== '' ? $phone : '—') ?></div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="items">
                <colgroup>
                    <col class="c-no"><col class="c-desc"><col class="c-qty"><col class="c-unit">
                    <col class="c-price"><col class="c-disc"><col class="c-tax"><col class="c-amt">
                </colgroup>
                <thead>
                    <tr>
                        <th class="ctr">#</th>
                        <th class="desc">Description</th>
                        <th class="ctr">Qty</th>
                        <th class="ctr">Unit</th>
                        <th class="num">Unit Price</th>
                        <th class="num">Discount</th>
                        <th class="num">Tax</th>
                        <th class="num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="8" class="ctr" style="color:#adb5bd">No line items</td></tr>
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

        <section class="totals-row">
            <div class="words">
                <span class="words__lbl">Amount in Words</span>
                <div class="words__val"><?= e($amountWords) ?></div>
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

        <div class="bottom">
            <section class="panel">
                <div class="panel__head">Terms &amp; Conditions</div>
                <div class="panel__body"><pre class="terms-text"><?= e($termsText) ?></pre></div>
            </section>
            <section class="panel">
                <div class="panel__head">Bank Details</div>
                <div class="panel__body">
                    <div class="bank">
                        <div class="bank__lines">
                            <div><b>Bank Name:</b> <?= e($bankName) ?></div>
                            <div><b>Account Name:</b> <?= e($bankAccountName) ?></div>
                            <?php if ($bankBranch !== ''): ?><div><b>Branch:</b> <?= e($bankBranch) ?></div><?php endif; ?>
                            <?php if ($bankAccountNumber !== ''): ?><div><b>Account No:</b> <?= e($bankAccountNumber) ?></div><?php endif; ?>
                            <?php if ($bankSwift !== ''): ?><div><b>Swift:</b> <?= e($bankSwift) ?></div><?php endif; ?>
                        </div>
                        <div class="bank__qr">
                            <img src="<?= e($qrUrl) ?>" alt="Quotation QR">
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section class="signs">
            <div class="sign">
                <div class="sign__slot">
                    <?php if ($hasSignature): ?><img class="sig" src="<?= e($signatureUrl) ?>" alt=""><?php endif; ?>
                </div>
                <div class="sign__line">
                    <div class="sign__label">Prepared By</div>
                    <div class="sign__who"><?= e($preparedBy) ?></div>
                </div>
            </div>
            <div class="sign">
                <div class="sign__slot">
                    <?php if ($hasStamp): ?><img class="stamp" src="<?= e($stampUrl) ?>" alt=""><?php endif; ?>
                </div>
                <div class="sign__line">
                    <div class="sign__label">Authorized Signature</div>
                    <div class="sign__who">VK NETWORK</div>
                </div>
            </div>
            <div class="sign">
                <div class="sign__slot" aria-hidden="true"></div>
                <div class="sign__line">
                    <div class="sign__label">Customer Signature</div>
                    <div class="sign__who">Accepted &amp; Confirmed</div>
                </div>
            </div>
        </section>
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
            <div class="footer-qr">
                <img src="<?= e($footerQrUrl) ?>" alt="QR Code — www.vkitnet.info" width="48" height="48">
            </div>
        </div>
        <p class="footer-bottom"><?= e($businessServices) ?></p>
    </footer>
</div>

<?php if ($autoPrint || $download): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
