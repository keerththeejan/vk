<?php
declare(strict_types=1);

/**
 * Apply known-safe secondary indexes once per environment (cached).
 * Never drops indexes or rewrites data.
 */
function vk_ensure_performance_indexes(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (function_exists('vk_cache_get') && vk_cache_get('perf_indexes_v2') === '1') {
        return;
    }

    $statements = [
        'CREATE INDEX idx_web_bookings_status_created ON web_bookings (status, created_at)',
        'CREATE INDEX idx_web_bookings_number ON web_bookings (booking_number)',
        'CREATE INDEX idx_repair_jobs_status ON repair_jobs (status)',
        'CREATE INDEX idx_repair_jobs_created ON repair_jobs (created_at)',
        'CREATE INDEX idx_repair_jobs_customer_created ON repair_jobs (customer_id, created_at)',
        'CREATE INDEX idx_repair_jobs_status_customer ON repair_jobs (status, customer_id)',
        'CREATE INDEX idx_cctv_status ON cctv_installations (status)',
        'CREATE INDEX idx_invoices_date ON invoices (invoice_date)',
        'CREATE INDEX idx_invoices_status ON invoices (status)',
        'CREATE INDEX idx_customers_name ON customers (name)',
        'CREATE INDEX idx_customers_email ON customers (email)',
        'CREATE INDEX idx_customers_created_at ON customers (created_at)',
        'CREATE INDEX idx_web_services_active_sort ON web_services (active, sort_order, id)',
        'CREATE INDEX idx_warranty_end_date ON warranty_records (end_date)',
        'CREATE INDEX idx_maint_contracts_next_service ON maintenance_contracts (status, next_service_date)',
        'CREATE INDEX idx_accounts_customer_balance ON accounts (customer_id, current_balance)',
        'CREATE INDEX idx_products_name ON products (name)',
        'CREATE INDEX idx_products_active ON products (active, id)',
        'CREATE INDEX idx_quotations_status ON quotations (status)',
        'CREATE INDEX idx_quotations_date ON quotations (quotation_date)',
        'CREATE INDEX idx_quotations_customer ON quotations (customer_id)',
    ];

    foreach ($statements as $sql) {
        try {
            $pdo->exec(str_starts_with($sql, 'CREATE INDEX ') ? 'CREATE INDEX IF NOT EXISTS ' . substr($sql, strlen('CREATE INDEX ')) : $sql);
        } catch (Throwable) {
            // Duplicate index name / missing table on older installs — ignore.
        }
    }

    if (function_exists('vk_cache_set')) {
        vk_cache_set('perf_indexes_v2', '1', 604800);
    }
}
