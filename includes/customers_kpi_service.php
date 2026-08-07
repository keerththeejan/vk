<?php
declare(strict_types=1);

/**
 * Cached KPI bundle for customers list — reduces 12+ queries to 3–4 per cold request.
 *
 * @return array{
 *   total:int,new_month:int,new_last_month:int,active_repairs:int,maint:int,cctv:int,
 *   whatsapp:int,outstanding:float,revenue:float,vip:int,quotations:int,growth_pct:float
 * }
 */
function vk_customers_list_kpis(PDO $pdo): array
{
    return vk_cache_remember('customers_list_kpis_v1', 60, static function () use ($pdo) {
        return vk_customers_list_kpis_compute($pdo);
    });
}

/**
 * @return array{
 *   total:int,new_month:int,new_last_month:int,active_repairs:int,maint:int,cctv:int,
 *   whatsapp:int,outstanding:float,revenue:float,vip:int,quotations:int,growth_pct:float
 * }
 */
function vk_customers_list_kpis_compute(PDO $pdo): array
{
    $monthStart = date('Y-m-01');
    $lastMonthStart = date('Y-m-01', strtotime('-1 month'));

    $kpi = [
        'total' => 0,
        'new_month' => 0,
        'new_last_month' => 0,
        'active_repairs' => 0,
        'maint' => 0,
        'cctv' => 0,
        'whatsapp' => 0,
        'outstanding' => 0.0,
        'revenue' => 0.0,
        'vip' => 0,
        'quotations' => 0,
        'growth_pct' => 0.0,
    ];

    try {
        $row = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(created_at >= '{$monthStart}') AS new_month,
                SUM(created_at >= '{$lastMonthStart}' AND created_at < '{$monthStart}') AS new_last_month,
                SUM(phone IS NOT NULL AND phone != '') AS whatsapp
             FROM customers"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        vk_perf_mark_query();
        $kpi['total'] = (int) ($row['total'] ?? 0);
        $kpi['new_month'] = (int) ($row['new_month'] ?? 0);
        $kpi['new_last_month'] = (int) ($row['new_last_month'] ?? 0);
        $kpi['whatsapp'] = (int) ($row['whatsapp'] ?? 0);
    } catch (Throwable) {
    }

    try {
        $acct = $pdo->query(
            'SELECT
                COALESCE(SUM(CASE WHEN customer_id IS NOT NULL AND current_balance > 0 THEN current_balance ELSE 0 END), 0) AS outstanding,
                SUM(CASE WHEN customer_id IS NOT NULL AND current_balance > 50000 THEN 1 ELSE 0 END) AS vip
             FROM accounts'
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        vk_perf_mark_query();
        $kpi['outstanding'] = (float) ($acct['outstanding'] ?? 0);
        $kpi['vip'] = (int) ($acct['vip'] ?? 0);
    } catch (Throwable) {
    }

    if (db_table_exists($pdo, 'repair_jobs')) {
        try {
            $kpi['active_repairs'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM repair_jobs WHERE status NOT IN ('delivered')"
            )->fetchColumn();
            vk_perf_mark_query();
        } catch (Throwable) {
        }
    }

    if (db_table_exists($pdo, 'maintenance_contracts')) {
        try {
            $kpi['maint'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM maintenance_contracts WHERE status = 'active'"
            )->fetchColumn();
            vk_perf_mark_query();
        } catch (Throwable) {
        }
    }

    try {
        $kpi['cctv'] = (int) $pdo->query('SELECT COUNT(*) FROM cctv_installations')->fetchColumn();
        vk_perf_mark_query();
    } catch (Throwable) {
    }

    if (db_table_exists($pdo, 'invoices')) {
        try {
            $inv = $pdo->query(
                "SELECT
                    COALESCE(SUM(paid_amount), 0) AS revenue,
                    SUM(status IN ('draft','sent')) AS quotations
                 FROM invoices"
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            vk_perf_mark_query();
            $kpi['revenue'] = (float) ($inv['revenue'] ?? 0);
            $kpi['quotations'] = (int) ($inv['quotations'] ?? 0);
        } catch (Throwable) {
        }
    }

    $kpi['growth_pct'] = $kpi['new_last_month'] > 0
        ? round((($kpi['new_month'] - $kpi['new_last_month']) / $kpi['new_last_month']) * 100, 1)
        : ($kpi['new_month'] > 0 ? 100.0 : 0.0);

    return $kpi;
}
