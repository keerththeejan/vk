-- Extend users for management UI (email, phone, status, staff role). Backup first.
SET NAMES utf8mb4;

ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL DEFAULT NULL AFTER username;
ALTER TABLE users ADD COLUMN phone VARCHAR(32) NULL DEFAULT NULL AFTER email;

ALTER TABLE users MODIFY COLUMN role ENUM('admin','staff','technician') NOT NULL DEFAULT 'admin';

ALTER TABLE users ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER technician_id;

CREATE UNIQUE INDEX uq_users_email ON users (email);

-- If a line fails with "Duplicate column" / "Duplicate key", skip it; partial installs can use includes/users_schema.php auto-migrate.
