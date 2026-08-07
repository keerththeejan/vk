-- VK Network Enterprise Authentication Fix
-- Run once on existing databases if you do not want to rely on the PHP auto-migrator.

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_key VARCHAR(64) NOT NULL UNIQUE,
  role_name VARCHAR(96) NOT NULL,
  priority TINYINT UNSIGNED NOT NULL DEFAULT 50,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (role_key, role_name, priority) VALUES
('super_admin', 'Super Admin', 100),
('admin', 'Admin', 90),
('manager', 'Manager', 70),
('technician', 'Technician', 50),
('staff', 'Staff', 40),
('viewer', 'Viewer', 10)
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), priority = VALUES(priority);

ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin','manager','technician','staff','viewer') NOT NULL DEFAULT 'viewer';
ALTER TABLE users MODIFY COLUMN status ENUM('pending','approved','active','rejected','suspended','inactive') NOT NULL DEFAULT 'pending';

ALTER TABLE users ADD COLUMN IF NOT EXISTS department VARCHAR(128) NULL DEFAULT NULL AFTER fullname;
ALTER TABLE users ADD COLUMN IF NOT EXISTS user_uid VARCHAR(32) NULL DEFAULT NULL AFTER department;
ALTER TABLE users ADD COLUMN IF NOT EXISTS approved TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_by INT UNSIGNED NULL DEFAULT NULL AFTER approved;
ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL DEFAULT NULL AFTER approved_by;
ALTER TABLE users ADD COLUMN IF NOT EXISTS rejected_at DATETIME NULL DEFAULT NULL AFTER approved_at;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL DEFAULT NULL AFTER rejected_at;
ALTER TABLE users ADD COLUMN IF NOT EXISTS registration_ip VARCHAR(45) NULL DEFAULT NULL AFTER last_login_at;
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE users SET approved = 1 WHERE status IN ('approved','active');
UPDATE users SET status = 'approved' WHERE status = 'active' AND approved = 1;

CREATE INDEX IF NOT EXISTS idx_users_status_role ON users (status, role);
CREATE UNIQUE INDEX IF NOT EXISTS uq_users_uid ON users (user_uid);

CREATE TABLE IF NOT EXISTS login_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  username VARCHAR(150) DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  status ENUM('success','failed','blocked','logout') NOT NULL,
  failure_reason VARCHAR(160) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_logs_user (user_id, created_at),
  KEY idx_login_logs_lookup (username, ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approvals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  action ENUM('registered','approved','rejected','suspended','reactivated','role_changed','password_reset') NOT NULL,
  actor_id INT UNSIGNED DEFAULT NULL,
  from_status VARCHAR(32) DEFAULT NULL,
  to_status VARCHAR(32) DEFAULT NULL,
  from_role VARCHAR(64) DEFAULT NULL,
  to_role VARCHAR(64) DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_approvals_user (user_id, created_at),
  KEY idx_approvals_actor (actor_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  actor_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(96) NOT NULL,
  entity_type VARCHAR(64) DEFAULT NULL,
  entity_id BIGINT UNSIGNED DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  metadata JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_actor (actor_id, created_at),
  KEY idx_activity_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  recipient VARCHAR(191) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  template VARCHAR(96) DEFAULT NULL,
  status ENUM('sent','failed','skipped') NOT NULL,
  error_message TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_logs_user (user_id, created_at),
  KEY idx_email_logs_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) DEFAULT NULL,
  requested_by INT UNSIGNED DEFAULT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_password_resets_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
