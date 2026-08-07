-- Thumbnail path for service template cards (run once if missing)
-- Run after: upgrade_service_template_image_column.sql
SET NAMES utf8mb4;

ALTER TABLE service_templates
  ADD COLUMN image_thumb VARCHAR(255) DEFAULT NULL AFTER image;
