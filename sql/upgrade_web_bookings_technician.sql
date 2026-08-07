-- Bring web_bookings up to date (older installs may lack these columns).
-- In phpMyAdmin: run each statement. Skip any line that errors with "Duplicate column name".

SET NAMES utf8mb4;

ALTER TABLE web_bookings ADD COLUMN is_emergency TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE web_bookings ADD COLUMN assigned_technician_id INT UNSIGNED DEFAULT NULL;
ALTER TABLE web_bookings ADD COLUMN repair_job_id INT UNSIGNED DEFAULT NULL;

-- Optional foreign keys (comment out if you get duplicate constraint errors):
-- ALTER TABLE web_bookings ADD CONSTRAINT fk_wb_assign_tech FOREIGN KEY (assigned_technician_id) REFERENCES technicians(id) ON DELETE SET NULL;
-- ALTER TABLE web_bookings ADD CONSTRAINT fk_wb_repair_job FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE SET NULL;
