-- Run only if service_gallery already exists from an older schema (missing columns/index).
-- Requires web_services(id) for FK — import web_services first.
-- If you see "Duplicate column" / "Duplicate key name", the patch is already applied; ignore.

SET NAMES utf8mb4;

ALTER TABLE service_gallery
  ADD COLUMN original_filename VARCHAR(255) NULL DEFAULT NULL AFTER title;

CREATE INDEX idx_service_gallery_created ON service_gallery (created_at);
