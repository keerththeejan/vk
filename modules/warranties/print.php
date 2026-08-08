<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
vk_bootstrap_module('warranty_service');

$ids = [];
$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $ids[] = $id;
}
$idsCsv = trim((string) ($_GET['ids'] ?? ''));
if ($idsCsv !== '') {
    foreach (explode(',', $idsCsv) as $part) {
        $n = (int) trim($part);
        if ($n > 0) {
            $ids[] = $n;
        }
    }
}
$ids = array_values(array_unique($ids));
if ($ids === []) {
    flash_set('error', 'No warranty selected for print.');
    redirect('/modules/warranties/list.php');
}

$records = [];
foreach ($ids as $wid) {
    $row = vk_warranty_fetch($pdo, $wid);
    if ($row) {
        $records[] = $row;
    }
}
if ($records === []) {
    flash_set('error', 'Warranty not found.');
    redirect('/modules/warranties/list.php');
}

$autoPrint = isset($_GET['pdf']) || isset($_GET['print']);
$company = defined('APP_NAME') ? (string) APP_NAME : 'VK Network';
$logoCandidates = [
    dirname(__DIR__, 2) . '/assets/img/logo.png',
    dirname(__DIR__, 2) . '/assets/images/logo.png',
    dirname(__DIR__, 2) . '/uploads/logo.png',
];
$logoUrl = '';
foreach ($logoCandidates as $path) {
    if (is_file($path)) {
        $rel = str_replace('\\', '/', substr($path, strlen(dirname(__DIR__, 2))));
        $logoUrl = rtrim(BASE_URL, '/') . $rel;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Warranty Certificate</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            color: #1f2937;
            background: #e5e7eb;
        }
        .toolbar {
            width: 210mm;
            max-width: 100%;
            margin: 12px auto;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .toolbar button, .toolbar a {
            border: 0;
            border-radius: 8px;
            padding: 8px 14px;
            background: #0B4DBA;
            color: #fff;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 16px;
            background: #fff;
            padding: 16mm 14mm;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .12);
            page-break-after: always;
            position: relative;
        }
        .sheet:last-child { page-break-after: auto; }
        .head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #0B3D91;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand img { width: 72px; height: auto; object-fit: contain; }
        .brand h1 { margin: 0; font-size: 22px; color: #0B3D91; letter-spacing: .04em; }
        .brand p { margin: 2px 0 0; color: #64748b; font-size: 12px; }
        .doc-title {
            text-align: center;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #0B3D91;
            margin: 8px 0 18px;
        }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 18px; margin-bottom: 16px; }
        .label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .value { font-size: 14px; font-weight: 600; margin-top: 2px; }
        .box {
            border: 1px solid #dbe3f0;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }
        .box h2 { margin: 0 0 8px; font-size: 13px; color: #0B3D91; }
        .codes { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-top: 18px; }
        .codes img { display: block; }
        .terms { font-size: 11px; line-height: 1.5; color: #475569; }
        .signs {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 18px;
            margin-top: 36px;
        }
        .sign { text-align: center; }
        .sign .line { border-top: 1px solid #94a3b8; margin: 42px 12px 6px; }
        .sign .cap { font-size: 11px; color: #64748b; }
        .seal {
            width: 78px; height: 78px; margin: 0 auto;
            border: 2px dashed #0B4DBA; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #0B4DBA; font-size: 10px; font-weight: 700; text-align: center;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet { box-shadow: none; margin: 0; width: auto; min-height: auto; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <a href="<?= e(BASE_URL) ?>/modules/warranties/list.php">Back</a>
    <button type="button" onclick="window.print()">Print / Save PDF</button>
</div>
<?php foreach ($records as $row):
    $wid = (int) $row['id'];
    $wrNo = vk_warranty_number($wid);
    $status = vk_warranty_status($row);
    $period = vk_warranty_period_label((string) $row['start_date'], (string) $row['end_date']);
    $qrPayload = rawurlencode($wrNo . '|' . (string) $row['title'] . '|' . (string) $row['end_date']);
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . $qrPayload;
    $barcodeUrl = 'https://barcode.tec-it.com/barcode.ashx?data=' . rawurlencode($wrNo) . '&code=Code128&translate-esc=false';
    ?>
    <article class="sheet">
        <header class="head">
            <div class="brand">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= e($logoUrl) ?>" alt="Logo">
                <?php endif; ?>
                <div>
                    <h1>VK NETWORK</h1>
                    <p><?= e($company) ?></p>
                </div>
            </div>
            <div style="text-align:right">
                <div class="label">Certificate No</div>
                <div class="value"><?= e($wrNo) ?></div>
                <div class="label" style="margin-top:6px">Status</div>
                <div class="value"><?= e($status['label']) ?></div>
            </div>
        </header>

        <div class="doc-title">Warranty Certificate</div>

        <div class="box">
            <h2>Customer Details</h2>
            <div class="grid">
                <div><div class="label">Customer</div><div class="value"><?= e((string) $row['customer_name']) ?></div></div>
                <div><div class="label">Phone</div><div class="value"><?= e((string) ($row['customer_phone'] ?: '—')) ?></div></div>
                <div><div class="label">Email</div><div class="value"><?= e((string) ($row['customer_email'] ?: '—')) ?></div></div>
                <div><div class="label">Address</div><div class="value"><?= e((string) ($row['customer_address'] ?: '—')) ?></div></div>
            </div>
        </div>

        <div class="box">
            <h2>Product Details</h2>
            <div class="grid">
                <div><div class="label">Product / Service</div><div class="value"><?= e((string) $row['title']) ?></div></div>
                <div><div class="label">Invoice No</div><div class="value"><?= e((string) ($row['invoice_number'] ?: '—')) ?></div></div>
                <div><div class="label">Warranty Type</div><div class="value text-capitalize"><?= e((string) $row['warranty_type']) ?></div></div>
                <div><div class="label">Period</div><div class="value"><?= e($period) ?></div></div>
                <div><div class="label">Purchase / Start</div><div class="value"><?= e((string) $row['start_date']) ?></div></div>
                <div><div class="label">Expiry Date</div><div class="value"><?= e((string) $row['end_date']) ?></div></div>
            </div>
            <?php if (!empty($row['description'])): ?>
                <div class="label">Description</div>
                <div class="value" style="font-weight:500"><?= e((string) $row['description']) ?></div>
            <?php endif; ?>
        </div>

        <div class="box">
            <h2>Terms &amp; Conditions</h2>
            <div class="terms">
                This certificate confirms warranty coverage for the product/service listed above for the stated period.
                Coverage includes defects in materials and workmanship under normal use. Damage from misuse, unauthorized
                modification, power surges, accidents, or consumable parts is excluded unless otherwise agreed in writing.
                Claims require proof of purchase/invoice and this certificate.
            </div>
        </div>

        <div class="codes">
            <div>
                <div class="label">Barcode</div>
                <img src="<?= e($barcodeUrl) ?>" alt="Barcode" style="height:48px">
            </div>
            <div style="text-align:center">
                <div class="label">QR Verification</div>
                <img src="<?= e($qrUrl) ?>" alt="QR" width="110" height="110">
            </div>
            <div class="seal">COMPANY<br>SEAL</div>
        </div>

        <div class="signs">
            <div class="sign"><div class="line"></div><div class="cap">Prepared By</div></div>
            <div class="sign"><div class="line"></div><div class="cap">Authorized Signature</div></div>
            <div class="sign"><div class="line"></div><div class="cap">Customer Acknowledgement</div></div>
        </div>
    </article>
<?php endforeach; ?>
<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
</body>
</html>
