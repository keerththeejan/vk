-- Portfolio tables for admin list / public portfolio.php (idempotent).
-- Requires repair_jobs and cctv_installations. Backup first.
-- Run: mysql -u root -p vk_billing < sql/patch_portfolio_if_missing.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS web_portfolio_posts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS web_portfolio_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(512) NOT NULL,
  caption VARCHAR(255) DEFAULT NULL,
  image_role ENUM('before','after','general') NOT NULL DEFAULT 'general',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_portfolio_img_post FOREIGN KEY (post_id) REFERENCES web_portfolio_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
