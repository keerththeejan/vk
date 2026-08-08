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

$footerQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&margin=0&data=' . rawurlencode('https://www.vkitnet.info');

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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --vk-brand: #0B4DBA;
            --vk-navy: #0A2F7A;
            --vk-brand-deep: #0B3D91;
            --vk-brand-accent: #1E5CC6;
            --vk-text: #222222;
            --vk-muted: #555555;
            --vk-border: #D9E3F0;
            --vk-alt: #F5F8FC;
            --page-x: 12mm;
            --page-y: 12mm;
            --radius: 6px;
            --header-height: 95px;
            --footer-height: 24mm;
            --lh-gap: 12px;
            --lh-divider: #D5D5D5;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        html, body {
            background: #e8edf3;
            color: var(--vk-text);
            font-family: Poppins, Montserrat, 'Open Sans', Arial, Helvetica, sans-serif;
            font-size: 10.5px;
            line-height: 1.35;
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

        /* ── A4 canvas — grows with content, min one page ── */
        .quote-page {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 210mm;
            min-height: 297mm;
            height: auto;
            margin: 0 auto 12px;
            padding: 0;
            background: #fff;
            box-shadow: 0 10px 40px rgba(15, 23, 42, .12);
            overflow: visible;
        }

        .page-watermark {
            position: absolute;
            top: 48%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 300px;
            opacity: 0.035;
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
            top: 46%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-28deg);
            font-size: 52px;
            font-weight: 700;
            color: rgba(11, 77, 186, .055);
            letter-spacing: .12em;
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
        }

        /* ── HEADER — balanced A4 corporate letterhead (20 / 55 / 25) ── */
        .letterhead-header {
            position: relative;
            z-index: 2;
            flex: 0 0 auto;
            min-height: var(--header-height);
            height: auto;
            margin-top: 0;
            background: #fff;
            border-bottom: 2.5px solid var(--vk-brand);
            box-sizing: border-box;
            overflow: visible;
        }
        .lh-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: flex-start;
            width: 100%;
            min-height: var(--header-height);
            height: auto;
            padding: 12px var(--page-x);
            box-sizing: border-box;
            gap: 0;
        }
        .lh-col {
            display: flex;
            align-items: center;
            min-width: 0;
            box-sizing: border-box;
            overflow: hidden;
        }
        .lh-col-3,
        .lh-col--logo {
            flex: 0 0 20%;
            width: 20%;
            max-width: 20%;
            justify-content: center;
            padding-right: var(--lh-gap);
        }
        .lh-col-6 {
            position: relative;
            flex: 0 0 55%;
            width: 55%;
            max-width: 55%;
            justify-content: center;
            padding: 0 16px;
            overflow: hidden;
        }
        .lh-col-6::before,
        .lh-col-6::after {
            content: '';
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 1px;
            height: 80px;
            background: var(--lh-divider);
            pointer-events: none;
        }
        .lh-col-6::before { left: 0; }
        .lh-col-6::after { right: 0; }
        .lh-col--contact {
            flex: 0 0 25%;
            width: 25%;
            max-width: 25%;
            justify-content: flex-end;
            padding-left: var(--lh-gap);
            overflow: hidden;
        }

        .company-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }
        .company-logo img {
            display: block;
            width: 85px;
            max-width: 90px;
            height: auto;
            max-height: 70px;
            object-fit: contain;
            object-position: center center;
        }

        .letterhead-col--center {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0;
            overflow: hidden;
        }
        .letterhead-company {
            font-family: 'Poppins', 'Montserrat', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--vk-brand-deep);
            letter-spacing: 2px;
            line-height: 1.15;
            text-transform: uppercase;
            text-align: center;
            margin: 0 0 6px;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .letterhead-services-wrap {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            text-align: center;
        }
        .letterhead-services {
            font-family: 'Poppins', 'Montserrat', sans-serif;
            font-size: 15px;
            font-weight: 600;
            color: #222222;
            margin: 0;
            line-height: 1.35;
            text-align: center;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .letterhead-services .svc-sep {
            display: inline-block;
            margin: 0 0.4em;
            font-weight: 600;
            color: #222222;
        }
        .letterhead-tagline {
            font-family: 'Poppins', 'Montserrat', sans-serif;
            font-size: 13px;
            font-style: italic;
            font-weight: 500;
            color: var(--vk-brand-accent);
            letter-spacing: 0.2px;
            margin: 6px 0 0;
            line-height: 1.3;
            text-align: center;
            max-width: 100%;
            width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .letterhead-contact {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-end;
            gap: 8px;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }
        .letterhead-contact-item {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
            gap: 8px;
            max-width: 100%;
            white-space: nowrap;
            font-family: 'Poppins', 'Montserrat', sans-serif;
            font-size: 12px;
            font-weight: 500;
            color: #222222;
            line-height: 22px;
            margin: 0;
            overflow: hidden;
        }
        .letterhead-contact-item > span:last-child {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .icon-circle {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--vk-brand);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .icon-circle svg {
            width: 8px;
            height: 8px;
            fill: #fff;
            display: block;
        }

        /* ── Body — grows with items; footer stays at bottom via flex ── */
        .quote-body {
            position: relative;
            z-index: 1;
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            padding: 5mm var(--page-x) 4mm;
            min-height: 0;
            overflow: visible;
        }

        .doc-title {
            text-align: center;
            margin: 0 0 5mm;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 2.5px;
            color: var(--vk-navy);
            text-transform: uppercase;
            line-height: 1;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Info cards */
        .cards {
            display: grid;
            gap: 3mm;
            margin-bottom: 3.5mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .cards--3 { grid-template-columns: repeat(3, 1fr); }
        .cards--2 { grid-template-columns: repeat(2, 1fr); }
        .info-card {
            border: 1px solid var(--vk-border);
            border-radius: var(--radius);
            padding: 2.5mm 3mm;
            min-height: 0;
            height: auto;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #fff;
        }
        .info-card__lbl {
            font-size: 8px;
            font-weight: 600;
            color: #6B7A90;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 1px;
        }
        .info-card__val {
            font-size: 11px;
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
            margin-bottom: 3.5mm;
            page-break-inside: auto;
            break-inside: auto;
        }
        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .items col.c-no { width: 28px; }
        .items col.c-qty { width: 42px; }
        .items col.c-unit { width: 42px; }
        .items col.c-price { width: 70px; }
        .items col.c-disc { width: 58px; }
        .items col.c-tax { width: 52px; }
        .items col.c-amt { width: 70px; }
        .items thead th {
            height: 28px;
            background: var(--vk-navy);
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            padding: 0 5px;
            border: 1px solid #06204f;
            vertical-align: middle;
            text-align: center;
        }
        .items thead th.desc { text-align: left; padding-left: 7px; }
        .items thead { display: table-header-group; }
        .items tbody td {
            height: 24px;
            padding: 2px 5px;
            border: 1px solid var(--vk-border);
            vertical-align: middle;
            font-size: 9.5px;
            color: #333;
        }
        .items tbody td.desc { text-align: left; padding-left: 7px; }
        .items .ctr { text-align: center; }
        .items .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .items tbody tr:nth-child(even) td { background: var(--vk-alt); }
        .item-sub {
            display: block;
            font-size: 7.5px;
            color: #6B7A90;
            margin-top: 1px;
        }

        /* Words + totals */
        .totals-row {
            display: grid;
            grid-template-columns: 1fr 210px;
            gap: 4mm;
            align-items: start;
            margin-bottom: 3.5mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .words__lbl {
            display: block;
            font-size: 8px;
            font-weight: 700;
            color: var(--vk-navy);
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .words__val {
            font-size: 9.5px;
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
            height: 22px;
            padding: 0 8px;
            font-size: 9.5px;
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
            height: 28px;
            background: var(--vk-navy);
            color: #fff;
            border: 0;
        }
        .summary .grand td:first-child { color: #fff; font-size: 10px; }
        .summary .grand td:last-child { color: #fff; font-size: 12px; }

        /* Terms & Conditions — full width, natural height */
        .bottom {
            display: block;
            width: 100%;
            margin-bottom: 4mm;
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
            min-height: 0;
            width: 100%;
        }
        .panel__head {
            padding: 4px 10px;
            font-size: 9px;
            font-weight: 700;
            color: #fff;
            background: var(--vk-navy);
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .panel__body {
            padding: 3mm 3.5mm;
            flex: 0 0 auto;
        }
        .terms-text {
            font-family: inherit;
            font-size: 9px;
            color: #445;
            line-height: 1.45;
            white-space: pre-wrap;
            word-break: break-word;
            margin: 0;
            max-height: none;
            overflow: visible;
        }

        /* Signatures — three equal columns under terms */
        .signs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            column-gap: 8mm;
            align-items: end;
            margin: 0 0 2mm;
            padding-top: 1mm;
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
            height: 12mm;
            width: 100%;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }
        .sign img.sig {
            max-height: 11mm;
            max-width: 36mm;
            object-fit: contain;
            display: block;
        }
        .sign img.stamp {
            max-height: 11mm;
            max-width: 24mm;
            object-fit: contain;
            opacity: .4;
            display: block;
        }
        .sign__line {
            width: 42mm;
            max-width: 92%;
            border-top: 1px solid #444;
            margin-top: 2px;
            padding-top: 3px;
        }
        .sign__label {
            font-size: 9px;
            font-weight: 700;
            color: var(--vk-navy);
        }
        .sign__who {
            margin-top: 1px;
            font-size: 8px;
            color: #6B7A90;
        }

        /* ── FOOTER — pinned to bottom of A4 page ── */
        .footer.letterhead-footer {
            position: relative;
            z-index: 2;
            flex: 0 0 var(--footer-height);
            height: var(--footer-height);
            max-height: var(--footer-height);
            margin-top: auto;
            margin-bottom: 0;
            padding: 2.5mm var(--page-x) 2mm;
            border-top: 2px solid var(--vk-brand);
            background: #fff;
            width: 100%;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: stretch;
            overflow: hidden;
        }
        .footer-content {
            position: relative;
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 14mm;
            height: 14mm;
            padding-right: 16mm;
        }
        .footer-contacts {
            display: flex;
            flex-wrap: nowrap;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 0;
            width: 100%;
            height: 100%;
        }
        .footer-item {
            display: inline-flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
            gap: 5px;
            font-size: 8.5px;
            font-weight: 500;
            white-space: nowrap;
            line-height: 1;
            color: #333333;
            padding: 0 10px;
            height: 100%;
        }
        .footer-sep {
            display: block;
            flex: 0 0 1px;
            width: 1px;
            height: 4.5mm;
            background: #D0D0D0;
            align-self: center;
        }
        .footer-icon {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            background: var(--vk-brand);
            flex-shrink: 0;
        }
        .footer-icon svg {
            width: 8px;
            height: 8px;
            fill: #fff;
            display: block;
        }
        .footer-item-text {
            display: inline-block;
            line-height: 1.15;
            vertical-align: middle;
        }
        .footer-qr {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 13mm;
            height: 13mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .footer-qr img {
            width: 13mm;
            height: 13mm;
            display: block;
            object-fit: contain;
        }
        .footer-bottom {
            margin: 2mm 0 0;
            padding: 0 16mm 0 0;
            text-align: center;
            font-size: 9pt;
            font-weight: 500;
            color: #666666;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            letter-spacing: 0.01em;
        }

        @media print {
            html, body { background: #fff !important; }
            .toolbar { display: none !important; }
            .quote-page {
                width: 210mm;
                min-height: 297mm;
                height: auto;
                margin: 0 !important;
                box-shadow: none !important;
                overflow: visible;
            }
            .letterhead-header {
                flex: 0 0 auto;
                min-height: var(--header-height);
                height: auto;
            }
            .footer.letterhead-footer {
                flex: 0 0 var(--footer-height);
                height: var(--footer-height);
                margin-top: auto;
                margin-bottom: 0;
            }
            .quote-body {
                padding: 5mm var(--page-x) 4mm;
                overflow: visible;
            }
            .page-watermark {
                position: absolute;
                opacity: 0.035;
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
            .totals-row,
            .bottom,
            .signs,
            .footer.letterhead-footer {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .items tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }

        @media screen and (max-width: 900px) {
            .quote-page {
                width: 100%;
                height: auto;
                min-height: auto;
            }
            .letterhead-header {
                height: auto;
                max-height: none;
                flex: 0 0 auto;
            }
            .lh-row {
                flex-wrap: wrap;
                height: auto;
                padding: 12px var(--page-x);
            }
            .lh-col-3,
            .lh-col--logo,
            .lh-col-6,
            .lh-col--contact {
                flex: 0 0 100%;
                width: 100%;
                max-width: 100%;
                border: 0;
                padding: 8px 0;
                justify-content: center;
            }
            .lh-col-6::before,
            .lh-col-6::after { display: none; }
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
                padding: 12px var(--page-x);
            }
            .footer-content {
                height: auto;
                min-height: 0;
                padding-right: 0;
                flex-wrap: wrap;
                gap: 10px;
            }
            .footer-contacts {
                flex-wrap: wrap;
                row-gap: 8px;
                height: auto;
                justify-content: center;
            }
            .footer-item { height: auto; padding: 4px 8px; }
            .footer-sep { display: none; }
            .footer-qr {
                position: static;
                transform: none;
                margin: 0 auto;
            }
            .footer-bottom {
                white-space: normal;
                margin-top: 8px;
                padding-right: 0;
                font-size: 8.5pt;
            }
            .cards--3,
            .cards--2,
            .totals-row { grid-template-columns: 1fr; }
            .signs { grid-template-columns: 1fr; row-gap: 12px; }
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
                    <img src="<?= e($logoUrl) ?>" alt="VK Network Logo" width="85" height="68">
                    <?php endif; ?>
                </div>
            </div>
            <div class="lh-col lh-col-6">
                <div class="letterhead-col--center">
                    <h2 class="letterhead-company"><?= e($businessName) ?></h2>
                    <div class="letterhead-services-wrap">
                        <p class="letterhead-services">Software Development <span class="svc-sep" aria-hidden="true">|</span> Hardware Solutions</p>
                        <p class="letterhead-services">CCTV Surveillance <span class="svc-sep" aria-hidden="true">|</span> Network Infrastructure</p>
                    </div>
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
            <div class="footer-contacts">
                <div class="footer-item">
                    <span class="footer-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 2C8.1 2 5 5.1 5 9c0 5.2 7 13 7 13s7-7.8 7-13c0-3.9-3.1-7-7-7zm0 9.5c-1.4 0-2.5-1.1-2.5-2.5S10.6 6.5 12 6.5s2.5 1.1 2.5 2.5S13.4 11.5 12 11.5z"/></svg></span>
                    <span class="footer-item-text"><?= e($businessAddress) ?></span>
                </div>
                <span class="footer-sep" aria-hidden="true"></span>
                <div class="footer-item">
                    <span class="footer-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6.6 10.8c1.4 2.8 3.7 5.1 6.5 6.5l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.5.6.6 0 1 .4 1 1V21c0 .6-.4 1-1 1C10.3 22 2 13.7 2 3c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.5.1.3 0 .7-.2 1L6.6 10.8z"/></svg></span>
                    <span class="footer-item-text"><?= e($businessPhone) ?></span>
                </div>
                <span class="footer-sep" aria-hidden="true"></span>
                <div class="footer-item">
                    <span class="footer-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5L4 8V6l8 5 8-5v2z"/></svg></span>
                    <span class="footer-item-text"><?= e($businessEmail) ?></span>
                </div>
                <span class="footer-sep" aria-hidden="true"></span>
                <div class="footer-item">
                    <span class="footer-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.9 9H15.8a15.7 15.7 0 0 0-1.2-5.1A8 8 0 0 1 19.9 11zM12 4c.9 1.2 1.6 2.6 2 4.1H10c.4-1.5 1.1-2.9 2-4.1zM8.4 5.9A15.7 15.7 0 0 0 7.2 11H4.1a8 8 0 0 1 4.3-5.1zM4.1 13h3.1c.3 1.8.8 3.5 1.2 5.1A8 8 0 0 1 4.1 13zm7.9 7c-.9-1.2-1.6-2.6-2-4.1h4c-.4 1.5-1.1 2.9-2 4.1zm3.5-1.9c.5-1.6.9-3.3 1.2-5.1h3.9a8 8 0 0 1-5.1 5.1z"/></svg></span>
                    <span class="footer-item-text"><?= e($businessWebsite) ?></span>
                </div>
            </div>
            <div class="footer-qr">
                <img src="<?= e($footerQrUrl) ?>" alt="QR Code — www.vkitnet.info" width="49" height="49">
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
