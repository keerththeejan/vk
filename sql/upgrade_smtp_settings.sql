-- SMTP settings table for dynamic mail configuration
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS smtp_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  smtp_host VARCHAR(191) NOT NULL DEFAULT '',
  smtp_port INT UNSIGNED NOT NULL DEFAULT 587,
  smtp_user VARCHAR(191) NOT NULL DEFAULT '',
  smtp_pass TEXT,
  smtp_secure ENUM('tls','ssl') NOT NULL DEFAULT 'tls',
  from_email VARCHAR(191) NOT NULL DEFAULT '',
  from_name VARCHAR(191) NOT NULL DEFAULT '',
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
