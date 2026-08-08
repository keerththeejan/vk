<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
vk_bootstrap_module('warranty_service');

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'warranty_no' => trim((string) ($_GET['warranty_no'] ?? '')),
    'customer' => trim((string) ($_GET['customer'] ?? '')),
    'invoice' => trim((string) ($_GET['invoice'] ?? '')),
    'product' => trim((string) ($_GET['product'] ?? '')),
    'brand' => trim((string) ($_GET['brand'] ?? '')),
    'model' => trim((string) ($_GET['model'] ?? '')),
    'serial' => trim((string) ($_GET['serial'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? ($_GET['filter'] ?? ''))),
    'warranty_type' => trim((string) ($_GET['warranty_type'] ?? '')),
    'purchase_from' => trim((string) ($_GET['purchase_from'] ?? '')),
    'purchase_to' => trim((string) ($_GET['purchase_to'] ?? '')),
    'expiry_from' => trim((string) ($_GET['expiry_from'] ?? '')),
    'expiry_to' => trim((string) ($_GET['expiry_to'] ?? '')),
    'created_from' => trim((string) ($_GET['created_from'] ?? '')),
    'created_to' => trim((string) ($_GET['created_to'] ?? '')),
];

$report = trim((string) ($_GET['report'] ?? 'warranty'));
$idsCsv = trim((string) ($_GET['ids'] ?? ''));
$ids = [];
if ($idsCsv !== '') {
    foreach (explode(',', $idsCsv) as $part) {
        $id = (int) trim($part);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
}

if ($report === 'monthly' && $filters['purchase_from'] === '' && $filters['purchase_to'] === '') {
    $filters['purchase_from'] = date('Y-m-01');
    $filters['purchase_to'] = date('Y-m-t');
}
if ($report === 'expiry' && $filters['status'] === '') {
    $filters['status'] = 'expiring';
}

$whereExtra = '';
$paramsExtra = [];
if ($ids !== []) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $whereExtra = " AND w.id IN ({$placeholders})";
    $paramsExtra = $ids;
}

$built = vk_warranty_build_filters($filters);
$sql = "SELECT w.*, c.name AS customer_name, i.invoice_number
        FROM warranty_records w
        JOIN customers c ON c.id = w.customer_id
        LEFT JOIN invoices i ON i.id = w.invoice_id
        WHERE {$built['where']}{$whereExtra}
        ORDER BY w.end_date ASC, w.id DESC
        LIMIT 5000";
$st = $pdo->prepare($sql);
$st->execute(array_merge($built['params'], $paramsExtra));
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$filename = 'warranties_' . $report . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, [
    'Warranty No', 'Customer', 'Invoice No', 'Product Name', 'Serial', 'Brand', 'Model',
    'Warranty Type', 'Period', 'Purchase Date', 'Expiry Date', 'Days Remaining', 'Status',
    'Created Date', 'Notes',
]);

foreach ($rows as $r) {
    $status = vk_warranty_status($r);
    fputcsv($out, [
        vk_warranty_number((int) $r['id']),
        (string) $r['customer_name'],
        (string) ($r['invoice_number'] ?? ''),
        (string) $r['title'],
        '',
        '',
        '',
        (string) $r['warranty_type'],
        vk_warranty_period_label((string) $r['start_date'], (string) $r['end_date']),
        (string) $r['start_date'],
        (string) $r['end_date'],
        $status['days'] === null ? '' : (string) $status['days'],
        $status['label'],
        substr((string) ($r['created_at'] ?? ''), 0, 10),
        (string) ($r['notes'] ?? ''),
    ]);
}
fclose($out);
exit;
