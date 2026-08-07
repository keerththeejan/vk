-- Smart booking: technician geo + assignment distance + optional availability.
-- Run in phpMyAdmin on existing databases. Skip statements that error with "Duplicate column".

SET NAMES utf8mb4;

-- Technician home / service base coordinates (required for auto-assign)
ALTER TABLE technicians ADD COLUMN latitude DECIMAL(10,8) DEFAULT NULL;
ALTER TABLE technicians ADD COLUMN longitude DECIMAL(11,8) DEFAULT NULL;

-- Only technicians marked available are considered (busy = excluded)
ALTER TABLE technicians ADD COLUMN availability ENUM('available','busy') NOT NULL DEFAULT 'available';

-- Distance stored at assignment time (km)
ALTER TABLE web_bookings ADD COLUMN assignment_distance_km DECIMAL(10,3) DEFAULT NULL;
