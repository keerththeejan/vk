-- Service template map location (run once on existing databases)
SET NAMES utf8mb4;

ALTER TABLE service_templates
  ADD COLUMN latitude DECIMAL(10,8) DEFAULT NULL AFTER image_thumb,
  ADD COLUMN longitude DECIMAL(11,8) DEFAULT NULL AFTER latitude,
  ADD COLUMN address TEXT DEFAULT NULL AFTER longitude;
