<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';
require_admin();
$pdo = db();
vk_marketing_suite_seed($pdo);

$type = (string) ($_GET['type'] ?? 'campaigns');
$filename = $type === 'leads' ? 'marketing-leads.csv' : 'marketing-campaigns.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$out = fopen('php://output', 'w');
if ($type === 'leads') {
    fputcsv($out, ['Name', 'Email', 'Phone', 'Source', 'Interest', 'Stage', 'Score', 'Estimated Value', 'Created']);
    $rows = $pdo->query('SELECT * FROM marketing_leads ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        fputcsv($out, [$r['lead_name'], $r['email'], $r['phone'], $r['source'], $r['service_interest'], $r['stage'], $r['score'], $r['estimated_value'], $r['created_at']]);
    }
} else {
    fputcsv($out, ['Campaign', 'Channel', 'Objective', 'Segment', 'Status', 'Budget', 'Reach', 'Engagement', 'Conversions']);
    $rows = $pdo->query('SELECT * FROM marketing_campaigns ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        fputcsv($out, [$r['campaign_name'], $r['channel'], $r['objective'], $r['segment'], $r['status'], $r['budget'], $r['reach'], $r['engagement'], $r['conversions']]);
    }
}
fclose($out);
