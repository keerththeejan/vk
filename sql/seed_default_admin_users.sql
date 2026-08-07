-- Create default admin + tech if missing (password for both: Admin@123).
-- Run: mysql -u root -p vk_billing < sql/seed_default_admin_users.sql

SET NAMES utf8mb4;

INSERT INTO users (username, password_hash, fullname, role, technician_id)
SELECT 'admin', '$2y$10$dLw60EEU/LS0xpHi4k0Qgu3f3VHIOQBf/.dg/y/vgMyKE9WM//VPa', 'Administrator', 'admin', NULL
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin' LIMIT 1);

INSERT INTO users (username, password_hash, fullname, role, technician_id)
SELECT 'tech', '$2y$10$dLw60EEU/LS0xpHi4k0Qgu3f3VHIOQBf/.dg/y/vgMyKE9WM//VPa', 'Field Technician', 'technician', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'tech' LIMIT 1)
  AND EXISTS (SELECT 1 FROM technicians WHERE id = 1 LIMIT 1);
