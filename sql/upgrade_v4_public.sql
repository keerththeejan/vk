-- Public website, bookings, portfolio, technician users, map & emergency fields
-- Backup first. Run on vk_billing after prior upgrades.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS web_bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_number VARCHAR(32) NOT NULL UNIQUE,
  customer_name VARCHAR(255) NOT NULL,
  phone VARCHAR(64) NOT NULL,
  email VARCHAR(255) DEFAULT NULL,
  address TEXT,
  service_type ENUM('computer','printer','cctv','maintenance','automobile','ac','electrical','other') NOT NULL DEFAULT 'other',
  problem_description TEXT NOT NULL,
  preferred_date DATE DEFAULT NULL,
  image_path VARCHAR(512) DEFAULT NULL,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  is_emergency TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('pending','in_progress','completed','delivered','cancelled') NOT NULL DEFAULT 'pending',
  technician_notes TEXT,
  estimated_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  assigned_technician_id INT UNSIGNED DEFAULT NULL,
  repair_job_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_web_booking_status (status),
  INDEX idx_web_booking_emergency (is_emergency),
  CONSTRAINT fk_upg_wb_tech FOREIGN KEY (assigned_technician_id) REFERENCES technicians(id) ON DELETE SET NULL,
  CONSTRAINT fk_upg_wb_repair FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT fk_upg_pf_repair FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE SET NULL,
  CONSTRAINT fk_upg_pf_cctv FOREIGN KEY (cctv_job_id) REFERENCES cctv_installations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS web_portfolio_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(512) NOT NULL,
  caption VARCHAR(255) DEFAULT NULL,
  image_role ENUM('before','after','general') NOT NULL DEFAULT 'general',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_upg_pimg_post FOREIGN KEY (post_id) REFERENCES web_portfolio_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  repair_job_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(512) NOT NULL,
  kind ENUM('before','after','other') NOT NULL DEFAULT 'other',
  uploaded_by_user_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_upg_ja_job FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE CASCADE,
  CONSTRAINT fk_upg_ja_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN role ENUM('admin','technician') NOT NULL DEFAULT 'admin' AFTER fullname;
ALTER TABLE users ADD COLUMN technician_id INT UNSIGNED NULL AFTER role;
ALTER TABLE users ADD CONSTRAINT fk_upg_users_tech FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL;

ALTER TABLE repair_jobs MODIFY COLUMN device_type ENUM('computer','printer','cctv_dvr','automobile','ac','electrical','other') NOT NULL DEFAULT 'other';
ALTER TABLE repair_jobs ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL AFTER invoice_id;
ALTER TABLE repair_jobs ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL AFTER latitude;
ALTER TABLE repair_jobs ADD COLUMN emergency_priority TINYINT(1) NOT NULL DEFAULT 0 AFTER longitude;
ALTER TABLE repair_jobs ADD COLUMN field_status ENUM('assigned','on_way','in_progress','completed') DEFAULT NULL AFTER emergency_priority;
ALTER TABLE repair_jobs ADD INDEX idx_repair_emergency (emergency_priority);
ALTER TABLE repair_jobs ADD INDEX idx_repair_field (field_status);

INSERT INTO users (username, password_hash, fullname, role, technician_id)
SELECT 'tech', '$2y$10$dLw60EEU/LS0xpHi4k0Qgu3f3VHIOQBf/.dg/y/vgMyKE9WM//VPa', 'Field Technician', 'technician', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'tech' LIMIT 1);

UPDATE users SET role = 'admin' WHERE role IS NULL OR username = 'admin';

SET FOREIGN_KEY_CHECKS = 1;
