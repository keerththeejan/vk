<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
$pdo = db();
require_once dirname(__DIR__, 2) . '/includes/invoices_schema.php';
require_once dirname(__DIR__, 2) . '/includes/invoices_service.php';
vk_ensure_invoices_schema($pdo);

if (!function_exists('vk_invoice_code128_svg')) {
    function vk_invoice_code128_svg(string $value): string
    {
        $patterns = [
            '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
            '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
            '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
            '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
            '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
            '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
            '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
            '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
            '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
            '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
            '114131','311141','411131','211412','211214','211232','2331112',
        ];
        $value = trim($value);
        if ($value === '') {
            $value = '0';
        }
        $codes = [];
        $sum = 104;
        $i = 1;
        foreach (str_split($value) as $ch) {
            $code = ord($ch) - 32;
            if ($code < 0 || $code > 95) {
                $code = 0;
            }
            $codes[] = $code;
            $sum += $i * $code;
            $i++;
        }
        $seq = array_merge([104], $codes, [$sum % 103]);
        $x = 10;
        $rects = '';
        $draw = static function (string $pattern) use (&$x, &$rects): void {
            foreach (str_split($pattern) as $idx => $module) {
                $w = (int) $module;
                if ($idx % 2 === 0) {
                    $rects .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="46" fill="#0c2340"/>';
                }
                $x += $w;
            }
        };
        foreach ($seq as $code) {
            $draw($patterns[$code]);
        }
        $draw($patterns[106]);
        $width = $x + 10;
        $label = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' 68" role="img" aria-label="Barcode ' . $label . '">'
            . $rects
            . '<text x="' . ($width / 2) . '" y="64" text-anchor="middle" font-family="Inter, Segoe UI, Arial, sans-serif" font-size="11" fill="#142033">' . $label . '</text>'
            . '</svg>';
    }
}

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
$paymentLabel = $isPaid ? 'PAID' : ($isPartial ? 'PARTIALLY PAID' : 'UNPAID');
$fmtAmount = static function ($amount): string {
    return number_format((float) $amount, 2, '.', ',');
};
$barcodeSvg = vk_invoice_code128_svg((string) $inv['invoice_number']);

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

$ipsCssVars = implode("\n", array_map(
    static fn (string $line): string => rtrim($line) . ';',
    preg_split('/\r\n|\r|\n/', trim(vk_invoice_print_settings_css_vars($ipsSettings))) ?: []
));
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #0c2340;
            --blue: #163e7a;
            --ink: #142033;
            --muted: #5c6d80;
            --line: #e3eaf2;
            --wash: #f5f8fc;
            --warm: #7a4e12;
            --warm-bg: #fbf6ee;
            <?= $ipsCssVars ?>
        }
        * { box-sizing: border-box; }
        @page { size: <?= e($ipsAtPage) ?>; margin: 0; }
        html, body { margin: 0; padding: 0; background: #e6edf5; color: var(--ink); font-family: Inter, "Segoe UI", "Helvetica Neue", Helvetica, Arial, sans-serif; }
        .print-toolbar { width: 210mm; margin: 12px auto 0; text-align: right; }
        .print-toolbar button { font-family: inherit; background: var(--navy); color: #fff; border: 0; border-radius: 4px; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .invoice-page {
            width: 210mm; min-height: 297mm; margin: 12px auto 20px; background: #fff;
            padding: 11mm 12mm 8mm; display: flex; flex-direction: column;
            box-shadow: 0 10px 36px rgba(12, 35, 64, 0.14); position: relative;
        }
        .page-watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 0; }
        .page-watermark img { width: 70mm; opacity: 0.035; }
        .header, .rule, .print-repeat, .footer { position: relative; z-index: 1; }
        .header { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; column-gap: 4.2mm; align-items: center; }
        .company-logo img { height: 15.2mm; width: auto; max-width: 22mm; display: block; object-fit: contain; }
        .brand-name { margin: 0; font-size: 15pt; line-height: 1.05; font-weight: 700; letter-spacing: 0.045em; color: var(--navy); }
        .brand-lines { margin: 1.1mm 0 0; font-size: 8pt; line-height: 1.38; font-weight: 500; color: #3a4c60; }
        .brand-tag { color: var(--blue); font-weight: 600; }
        .contacts { display: flex; flex-direction: column; gap: 1.15mm; }
        .contacts div { display: flex; align-items: center; gap: 1.7mm; font-size: 8.4pt; font-weight: 500; color: #1c2c3e; white-space: nowrap; }
        .contacts svg, .foot-contacts svg, .ico svg { flex: 0 0 auto; }
        .contacts svg { width: 3.4mm; height: 3.4mm; }
        .rule { height: 0; border: 0; border-top: 1.6px solid var(--blue); margin: 3.6mm 0 0; }
        .print-repeat { width: 100%; border-collapse: collapse; flex: 1 1 auto; }
        .print-repeat td { padding: 0; border: 0; vertical-align: top; }
        .print-repeat-spacer--head, .print-repeat-spacer--foot { height: 0; }
        @media screen {
            .print-repeat-head, .print-repeat-foot { display: none; }
            .print-repeat, .print-repeat > tbody, .print-repeat > tbody > tr, .print-repeat > tbody > tr > td {
                display: flex; flex: 1 1 auto; flex-direction: column; width: 100%; min-height: 0;
            }
        }
        .invoice-body { display: flex; flex-direction: column; flex: 1 1 auto; padding-top: 4.2mm; }
        .hero { display: grid; grid-template-columns: minmax(0, 1fr) 78mm; gap: 6mm; align-items: center; }
        .titleline { display: flex; align-items: center; gap: 3.4mm; }
        h1 { margin: 0; font-size: 24pt; line-height: 0.95; font-weight: 700; letter-spacing: 0.055em; color: var(--navy); }
        .vbar { width: 1px; height: 7.2mm; background: #c5d0de; }
        .invno { font-size: 11.5pt; font-weight: 600; color: var(--blue); }
        .subtitle { margin-top: 2.1mm; display: flex; align-items: center; gap: 2.2mm; font-size: 9.5pt; font-weight: 500; color: #3e5166; }
        .dot { width: 1.05mm; height: 1.05mm; border-radius: 50%; background: #9aabbe; }
        table.meta { width: 100%; border-collapse: separate; border-spacing: 0; background: var(--wash); border: 1px solid var(--line); border-radius: 2mm; overflow: hidden; }
        table.meta th, table.meta td { padding: 1.25mm 3mm; font-size: 8pt; border-bottom: 1px solid #e8eef5; vertical-align: middle; }
        table.meta tr:last-child th, table.meta tr:last-child td { border-bottom: 0; }
        table.meta th { width: 34mm; text-align: left; font-weight: 500; color: var(--muted); }
        table.meta td { text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }
        .badge { display: inline-block; font-size: 7pt; font-weight: 600; letter-spacing: 0.07em; border-radius: 99px; padding: 0.45mm 2mm 0.55mm; line-height: 1.35; }
        .badge--paid { color: #166534; background: #e8f6ee; border: 1px solid #b7e0c8; }
        .badge--partial { color: #8a6230; background: #f8f1de; border: 1px solid #ead9b4; }
        .badge--unpaid { color: #8a3b2a; background: #f8ece8; border: 1px solid #ead4cc; }
        .cards { margin-top: 4mm; display: grid; grid-template-columns: 1fr 1fr; gap: 3.6mm; }
        .card { background: var(--wash); border: 1px solid var(--line); border-radius: 2mm; padding: 2.6mm 3.6mm 2.8mm; min-height: 16.5mm; }
        .card h2 { margin: 0 0 1.8mm; display: flex; align-items: center; gap: 1.8mm; font-size: 7.3pt; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--blue); }
        .ico { width: 5.2mm; height: 5.2mm; border-radius: 50%; background: #e7f0fa; display: inline-flex; align-items: center; justify-content: center; }
        .ico svg { width: 3mm; height: 3mm; display: block; }
        .card .name { font-size: 11pt; font-weight: 600; line-height: 1.2; }
        .card .sub { margin-top: 0.7mm; font-size: 9pt; font-weight: 500; color: #3e5166; }
        .card .empty { font-size: 12pt; font-weight: 500; color: #8b9aab; }
        .kicker { margin: 4mm 0 1.5mm; text-align: right; font-size: 7.4pt; font-weight: 500; letter-spacing: 0.04em; color: var(--muted); }
        table.items { width: 100%; border-collapse: separate; border-spacing: 0; table-layout: fixed; }
        table.items col.q { width: 12mm; } table.items col.u { width: 26mm; } table.items col.c { width: 24mm; } table.items col.t { width: 16mm; } table.items col.n { width: 26mm; }
        table.items thead th { background: var(--navy); color: #fff; font-size: 7.2pt; font-weight: 600; letter-spacing: 0.07em; padding: 2.15mm 2.2mm; text-align: right; }
        table.items thead th:first-child { text-align: left; border-radius: 1.4mm 0 0 0; padding-left: 2.8mm; }
        table.items thead th:nth-child(2) { text-align: center; }
        table.items thead th:last-child { border-radius: 0 1.4mm 0 0; padding-right: 2.8mm; }
        table.items td { padding: 1.9mm 2.2mm; font-size: 8.4pt; line-height: 1.32; vertical-align: middle; border-bottom: 1px solid #e7eef5; }
        table.items tbody tr:nth-child(even) td { background: #f7fafc; }
        table.items tbody tr:last-child td { border-bottom: 1px solid #d5e0eb; }
        table.items td.desc { padding-left: 2.8mm; overflow-wrap: anywhere; }
        table.items td.qty { text-align: center; font-weight: 600; font-variant-numeric: tabular-nums; }
        table.items td.num { text-align: right; font-weight: 500; font-variant-numeric: tabular-nums; }
        table.items td.net { padding-right: 2.8mm; font-weight: 600; }
        .kind { display: inline-block; margin-right: 1.4mm; padding: 0.15mm 1.35mm 0.25mm; font-size: 6.8pt; font-weight: 600; color: var(--blue); background: #e7f0fa; border-radius: 0.7mm; vertical-align: 0.2mm; }
        .notes { margin-top: 3mm; font-size: 8pt; color: #3e5166; line-height: 1.4; }
        .notes p { margin: 0 0 1mm; }
        .settle { margin-top: 4mm; display: grid; grid-template-columns: minmax(0, 1fr) 82mm; gap: 5mm; align-items: stretch; }
        .words { background: var(--wash); border: 1px solid var(--line); border-left: 2.4px solid var(--blue); border-radius: 0 1.6mm 1.6mm 0; padding: 3.2mm 3.8mm; display: flex; flex-direction: column; justify-content: center; }
        .words .label { font-size: 7.1pt; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--blue); }
        .words p { margin: 1.5mm 0 0; font-size: 10.2pt; font-weight: 500; line-height: 1.4; }
        .totals { border: 1px solid var(--line); border-radius: 1.6mm; overflow: hidden; }
        .totals .row { display: flex; justify-content: space-between; align-items: baseline; gap: 4mm; padding: 1.25mm 3.2mm; font-size: 8.5pt; border-bottom: 1px solid #eef2f6; }
        .totals .row span:first-child { font-weight: 500; color: #314256; }
        .totals .row span:last-child { font-weight: 600; font-variant-numeric: tabular-nums; min-width: 28mm; text-align: right; }
        .totals .grand { background: var(--navy); border-bottom: 0; padding: 1.85mm 3.2mm; }
        .totals .grand span { color: #fff !important; font-weight: 700; font-size: 10.4pt; }
        .totals .balance { background: var(--warm-bg); border-bottom: 0; padding: 1.6mm 3.2mm; }
        .totals .balance span { color: var(--warm) !important; font-weight: 700; font-size: 9.6pt; }
        .signs { margin-top: auto; padding-top: 4mm; display: grid; grid-template-columns: 1fr 1fr; gap: 10mm; align-items: end; }
        .sign-art { min-height: 28mm; display: flex; flex-direction: column; justify-content: flex-end; align-items: flex-start; gap: 1.6mm; }
        .company-stamp { width: 44mm; height: auto; max-height: 16mm; object-fit: contain; object-position: left center; display: block; }
        .digital-signature { width: 32mm; height: auto; max-height: 14mm; object-fit: contain; object-position: left center; display: block; }
        .sign-line { margin-top: 1.2mm; border-top: 1px solid #9aabbe; width: 68mm; }
        .sign-label { margin-top: 1.3mm; font-size: 7pt; font-weight: 600; letter-spacing: 0.12em; color: var(--muted); }
        .footer { margin-top: 3.2mm; padding-top: 2.6mm; border-top: 1.5px solid var(--navy); display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 4mm; align-items: end; }
        .foot-contacts { display: grid; grid-template-columns: max-content max-content; column-gap: 5.5mm; row-gap: 1.15mm; font-size: 8pt; font-weight: 500; color: #243446; }
        .foot-contacts span { display: inline-flex; align-items: center; gap: 1.4mm; }
        .foot-contacts svg { width: 3.1mm; height: 3.1mm; }
        .foot-brand { margin-top: 1.5mm; font-size: 7.3pt; line-height: 1.4; font-weight: 500; color: #5c6d80; }
        .codes { display: flex; align-items: flex-end; gap: 2.6mm; }
        .codes .bc { width: 44mm; height: 16mm; display: block; }
        .codes .qr { width: 15.5mm; height: 15.5mm; display: block; object-fit: contain; }
        @media print {
            html, body { background: #fff; }
            .no-print { display: none !important; }
            .invoice-page { margin: 0; box-shadow: none; width: 210mm; min-height: 297mm; }
            .letterhead-header { position: running(invhead); }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="print-toolbar no-print">
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
</div>
<article class="invoice-page">
    <?php if ($watermarkUrl !== ''): ?>
    <div class="page-watermark" aria-hidden="true"><img src="<?= e($watermarkUrl) ?>" alt=""></div>
    <?php endif; ?>
    <header class="header letterhead-header">
        <?php if ($showHeaderLogo): ?>
        <div class="company-logo"><img id="invoiceHeaderLogo" src="<?= e($headerLogoUrl) ?>" alt="VK NETWORK"></div>
        <?php else: ?>
        <div class="company-logo" aria-hidden="true"></div>
        <?php endif; ?>
        <div>
            <p class="brand-name"><?= e($businessName) ?></p>
            <p class="brand-lines">
                Software Development | Hardware Solutions<br>
                CCTV Surveillance | Network Infrastructure<br>
                <span class="brand-tag"><?= e($businessTagline) ?></span>
            </p>
        </div>
        <div class="contacts">
            <div>
                <svg viewBox="0 0 24 24" fill="none" stroke="#163e7a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 3.5h3l1.5 3.5-2 1.2a12 12 0 0 0 5.8 5.8l1.2-2 3.5 1.5v3A2 2 0 0 1 17.5 18 14.5 14.5 0 0 1 5 5.5a2 2 0 0 1 1.5-2z"/></svg>
                <span><?= e($businessPhone) ?></span>
            </div>
            <div>
                <svg viewBox="0 0 24 24" fill="none" stroke="#163e7a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5.5" width="17" height="13" rx="1.6"/><path d="m4 7 8 6 8-6"/></svg>
                <span><?= e($businessEmail) ?></span>
            </div>
            <div>
                <svg viewBox="0 0 24 24" fill="none" stroke="#163e7a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8.2"/><path d="M3.8 12h16.4M12 3.8c2.2 2.4 3.3 5.2 3.3 8.2s-1.1 5.8-3.3 8.2c-2.2-2.4-3.3-5.2-3.3-8.2s1.1-5.8 3.3-8.2z"/></svg>
                <span><?= e($businessWebsite) ?></span>
            </div>
        </div>
    </header>
    <hr class="rule">
    <table class="print-repeat">
        <thead class="print-repeat-head"><tr><td><div class="print-repeat-spacer print-repeat-spacer--head"></div></td></tr></thead>
        <tfoot class="print-repeat-foot"><tr><td><div class="print-repeat-spacer print-repeat-spacer--foot"></div></td></tr></tfoot>
        <tbody><tr><td>
            <main class="invoice-body">
                <section class="hero">
                    <div>
                        <div class="titleline">
                            <h1>INVOICE</h1>
                            <span class="vbar" aria-hidden="true"></span>
                            <div class="invno"><?= e($inv['invoice_number']) ?></div>
                        </div>
                        <div class="subtitle">
                            <span><?= e($inv['invoice_date']) ?></span>
                            <span class="dot" aria-hidden="true"></span>
                            <span><?= e($inv['customer_name']) ?></span>
                        </div>
                    </div>
                    <table class="meta">
                        <tbody>
                            <tr><th>Invoice Number</th><td><?= e($inv['invoice_number']) ?></td></tr>
                            <tr><th>Invoice Date</th><td><?= e($inv['invoice_date']) ?></td></tr>
                            <tr><th>Due Date</th><td><?= e($dueDateDisplay) ?></td></tr>
                            <tr><th>Payment Status</th><td><span class="badge badge--<?= $isPaid ? 'paid' : ($isPartial ? 'partial' : 'unpaid') ?>"><?= e($paymentLabel) ?></span></td></tr>
                        </tbody>
                    </table>
                </section>
                <section class="cards">
                    <div class="card">
                        <h2><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="#163e7a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 19.2c.8-3 3.2-4.7 6.5-4.7s5.7 1.7 6.5 4.7"/></svg></span>Customer Details</h2>
                        <div class="name"><?= e($inv['customer_name']) ?></div>
                        <?php if (!empty($inv['phone'])): ?><div class="sub"><?= e($inv['phone']) ?></div><?php endif; ?>
                        <?php if (!empty($inv['email'])): ?><div class="sub"><?= e($inv['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="card">
                        <h2><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="#163e7a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10z"/><circle cx="12" cy="11" r="1.7"/></svg></span>Billing Address</h2>
                        <?php if (!empty($inv['address'])): ?>
                        <div class="sub"><?= nl2br(e((string) $inv['address'])) ?></div>
                        <?php else: ?>
                        <div class="empty">—</div>
                        <?php endif; ?>
                    </div>
                </section>
                <div class="kicker">All amounts in Sri Lankan Rupees (LKR)</div>
                <table class="items">
                    <colgroup><col><col class="q"><col class="u"><col class="c"><col class="t"><col class="n"></colgroup>
                    <thead>
                        <tr>
                            <th>DESCRIPTION</th><th>QTY</th><th>UNIT PRICE</th><th>DISCOUNT</th><th>TAX</th><th>NET</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $ln): ?>
                        <?php
                        $isService = ($ln['item_type'] ?? 'product') === 'service';
                        $desc = $isService
                            ? (string) ($ln['line_description'] ?? '')
                            : (string) ($ln['line_description'] ?? $ln['product_name'] ?? '');
                        $lineNet = (float) ($ln['net_amount'] ?? $ln['line_total'] ?? 0);
                        ?>
                        <tr>
                            <td class="desc"><?php if ($isService): ?><span class="kind">Service</span><?php endif; ?><?= e($desc) ?></td>
                            <td class="qty"><?= rtrim(rtrim(number_format((float) $ln['quantity'], 2, '.', ''), '0'), '.') ?></td>
                            <td class="num"><?= e($fmtAmount($ln['unit_price'])) ?></td>
                            <td class="num"><?= e($fmtAmount($ln['discount_amount'] ?? 0)) ?></td>
                            <td class="num"><?= e($fmtAmount($ln['tax_amount'] ?? 0)) ?></td>
                            <td class="num net"><?= e($fmtAmount($lineNet)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!empty($inv['notes']) || !empty($inv['terms'])): ?>
                <div class="notes">
                    <?php if (!empty($inv['notes'])): ?><p><strong>Notes:</strong> <?= nl2br(e((string) $inv['notes'])) ?></p><?php endif; ?>
                    <?php if (!empty($inv['terms'])): ?><p><strong>Terms:</strong> <?= nl2br(e((string) $inv['terms'])) ?></p><?php endif; ?>
                </div>
                <?php endif; ?>
                <section class="settle">
                    <div class="words">
                        <div class="label">Amount in words</div>
                        <p><?= e($amountWords) ?></p>
                    </div>
                    <div class="totals">
                        <div class="row"><span>Subtotal</span><span><?= e($fmtAmount($inv['subtotal'])) ?></span></div>
                        <div class="row"><span>Item Discount</span><span><?= e($fmtAmount($itemDiscTotal)) ?></span></div>
                        <div class="row"><span>Invoice Discount</span><span><?= e($fmtAmount($invDiscAmt)) ?></span></div>
                        <?php if (abs($shippingAmt) > 0.0001): ?><div class="row"><span>Shipping</span><span><?= e($fmtAmount($shippingAmt)) ?></span></div><?php endif; ?>
                        <?php if (abs($adjustAmt) > 0.0001): ?><div class="row"><span>Adjustment</span><span><?= e($fmtAmount($adjustAmt)) ?></span></div><?php endif; ?>
                        <div class="row"><span>Tax</span><span><?= e($fmtAmount($inv['tax'])) ?></span></div>
                        <?php if (abs($roundOffAmt) > 0.0001): ?><div class="row"><span>Round Off</span><span><?= e($fmtAmount($roundOffAmt)) ?></span></div><?php endif; ?>
                        <div class="row grand"><span>Grand Total</span><span><?= e($fmtAmount($inv['grand_total'])) ?></span></div>
                        <div class="row"><span>Paid</span><span><?= e($fmtAmount($inv['paid_amount'])) ?></span></div>
                        <div class="row balance"><span>Balance</span><span><?= e($fmtAmount($due)) ?></span></div>
                    </div>
                </section>
                <section class="signs">
                    <div>
                        <div class="sign-art">
                            <?php if ($hasStamp): ?><img class="company-stamp" src="<?= e($stampUrl) ?>" alt="VK IT Network"><?php endif; ?>
                            <?php if ($hasSignature): ?><img class="digital-signature" src="<?= e($signatureUrl) ?>" alt="Authorized signature"><?php endif; ?>
                        </div>
                        <div class="sign-line"></div>
                        <div class="sign-label">AUTHORIZED SIGNATURE</div>
                    </div>
                    <div>
                        <div class="sign-art"></div>
                        <div class="sign-line"></div>
                        <div class="sign-label">CUSTOMER SIGNATURE</div>
                    </div>
                </section>
            </main>
        </td></tr></tbody>
    </table>
    <footer class="footer letterhead-footer">
        <div>
            <div class="foot-contacts">
                <span><svg viewBox="0 0 24 24" fill="none" stroke="#163e7a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10z"/><circle cx="12" cy="11" r="1.7"/></svg><?= e($businessAddress) ?></span>
                <span><svg viewBox="0 0 24 24" fill="none" stroke="#163e7a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 3.5h3l1.5 3.5-2 1.2a12 12 0 0 0 5.8 5.8l1.2-2 3.5 1.5v3A2 2 0 0 1 17.5 18 14.5 14.5 0 0 1 5 5.5a2 2 0 0 1 1.5-2z"/></svg><?= e($businessPhone) ?></span>
                <span><svg viewBox="0 0 24 24" fill="none" stroke="#163e7a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5.5" width="17" height="13" rx="1.6"/><path d="m4 7 8 6 8-6"/></svg><?= e($businessEmail) ?></span>
                <span><svg viewBox="0 0 24 24" fill="none" stroke="#163e7a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8.2"/><path d="M3.8 12h16.4M12 3.8c2.2 2.4 3.3 5.2 3.3 8.2s-1.1 5.8-3.3 8.2c-2.2-2.4-3.3-5.2-3.3-8.2s1.1-5.8 3.3-8.2z"/></svg><?= e($businessWebsite) ?></span>
            </div>
            <div class="foot-brand">
                VK NETWORK | Software Development | Hardware Solutions<br>
                CCTV Surveillance | Network Infrastructure
            </div>
        </div>
        <div class="codes">
            <div class="bc"><?= $barcodeSvg ?></div>
            <?php if ($showFooterQr): ?><img class="qr" src="<?= e($qrUrl) ?>" alt="QR code for www.vkitnet.info"><?php endif; ?>
        </div>
    </footer>
</article>
<script>
(function () {
    var img = document.getElementById('invoiceHeaderLogo');
    if (img && img.complete && img.naturalWidth === 0) {
        console.error('VK invoice header logo failed to load:', img.src);
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
