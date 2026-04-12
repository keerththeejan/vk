-- Fix default admin/tech passwords to match documented credentials: Admin@123
-- Run once if login with admin / Admin@123 fails after an older install.sql import.
SET NAMES utf8mb4;

UPDATE users SET password_hash = '$2y$10$dLw60EEU/LS0xpHi4k0Qgu3f3VHIOQBf/.dg/y/vgMyKE9WM//VPa'
WHERE username IN ('admin', 'tech');
