<?php
declare(strict_types=1);

/**
 * Create public portfolio tables if missing (idempotent).
 * Requires repair_jobs and cctv_installations for foreign keys.
 */
function vk_ensure_web_portfolio_tables(PDO $pdo): void
{
    $hasPosts = db_table_exists($pdo, 'web_portfolio_posts');
    $hasImages = db_table_exists($pdo, 'web_portfolio_images');
    if ($hasPosts && $hasImages) {
        return;
    }

    try {
        $pdo->exec('SET NAMES utf8mb4');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        if (!$hasPosts) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS web_portfolio_posts (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  title VARCHAR(255) NOT NULL,
                  description TEXT,
                  published TINYINT(1) NOT NULL DEFAULT 0,
                  display_date DATE NOT NULL,
                  repair_job_id INT UNSIGNED DEFAULT NULL,
                  cctv_job_id INT UNSIGNED DEFAULT NULL,
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                  INDEX idx_portfolio_pub (published, display_date),
                  CONSTRAINT fk_portfolio_repair FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE SET NULL,
                  CONSTRAINT fk_portfolio_cctv FOREIGN KEY (cctv_job_id) REFERENCES cctv_installations(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        if (!db_table_exists($pdo, 'web_portfolio_images')) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS web_portfolio_images (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  post_id INT UNSIGNED NOT NULL,
                  image_path VARCHAR(512) NOT NULL,
                  caption VARCHAR(255) DEFAULT NULL,
                  image_role ENUM(\'before\',\'after\',\'general\') NOT NULL DEFAULT \'general\',
                  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                  CONSTRAINT fk_portfolio_img_post FOREIGN KEY (post_id) REFERENCES web_portfolio_posts(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable $e) {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Throwable $e2) {
        }
        error_log('vk_ensure_web_portfolio_tables: ' . $e->getMessage());
    }
}
