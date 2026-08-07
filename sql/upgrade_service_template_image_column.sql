-- Add single service template cover image (run once on existing databases)
SET NAMES utf8mb4;

ALTER TABLE service_templates
  ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER description;
