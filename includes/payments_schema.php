<?php
declare(strict_types=1);

/**
 * Ensure payments table exists (partial installs / restored DBs without full install.sql).
 * Requires accounts, invoices, repair_jobs, cctv_installations.
 */
function vk_ensure_payments_table(PDO $pdo): void
{
    if (db_table_exists($pdo, 'payments')) {
        return;
    }
    try {
        $pdo->exec('SET NAMES utf8mb4');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS payments (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              invoice_id INT UNSIGNED DEFAULT NULL,
              repair_job_id INT UNSIGNED DEFAULT NULL,
              cctv_job_id INT UNSIGNED DEFAULT NULL,
              customer_account_id INT UNSIGNED NOT NULL,
              amount DECIMAL(14,2) NOT NULL,
              method ENUM(\'cash\',\'card\',\'bank\',\'online\') NOT NULL DEFAULT \'cash\',
              paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              note VARCHAR(255) DEFAULT NULL,
              INDEX idx_payments_invoice (invoice_id),
              INDEX idx_payments_repair (repair_job_id),
              INDEX idx_payments_cctv (cctv_job_id),
              CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
              CONSTRAINT fk_payments_repair FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE RESTRICT,
              CONSTRAINT fk_payments_cctv FOREIGN KEY (cctv_job_id) REFERENCES cctv_installations(id) ON DELETE RESTRICT,
              CONSTRAINT fk_payments_account FOREIGN KEY (customer_account_id) REFERENCES accounts(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable $e) {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Throwable $e2) {
        }
        error_log('vk_ensure_payments_table: ' . $e->getMessage());
    }
}
