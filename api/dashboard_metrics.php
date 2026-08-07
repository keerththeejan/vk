<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap_core.php';
vk_api_require_admin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=30');

require_once dirname(__DIR__) . '/includes/marketing_suite.php';
$pdo = db();
$metrics = vk_marketing_metrics($pdo);
$seoAverage = 0;

if (db_table_exists($pdo, 'seo_settings')) {
    try {
        $seoAverage = (int) $pdo->query('SELECT COALESCE(ROUND(AVG(seo_score)),0) FROM seo_settings')->fetchColumn();
        vk_perf_mark_query();
    } catch (Throwable $e) {
        $seoAverage = 0;
    }
}

echo json_encode([
    'ok' => true,
    'seo_average' => $seoAverage,
    'marketing' => $metrics,
], JSON_THROW_ON_ERROR);
