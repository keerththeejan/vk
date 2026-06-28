-- Performance indexes for VK Network (safe to re-run; ignores duplicates).
-- Import into vk_billing after backup.

-- Web bookings: track/status lookups
CREATE INDEX IF NOT EXISTS idx_web_bookings_status_created ON web_bookings (status, created_at);
CREATE INDEX IF NOT EXISTS idx_web_bookings_number ON web_bookings (booking_number);

-- Repair jobs: dashboard aggregates
CREATE INDEX IF NOT EXISTS idx_repair_jobs_status ON repair_jobs (status);
CREATE INDEX IF NOT EXISTS idx_repair_jobs_created ON repair_jobs (created_at);

-- CCTV installations
CREATE INDEX IF NOT EXISTS idx_cctv_status ON cctv_installations (status);

-- Invoices: revenue dashboards
CREATE INDEX IF NOT EXISTS idx_invoices_date ON invoices (invoice_date);

-- Customers
CREATE INDEX IF NOT EXISTS idx_customers_name ON customers (name);

-- Web services public listing
CREATE INDEX IF NOT EXISTS idx_web_services_active_sort ON web_services (active, sort_order, id);

-- Warranty expiry alerts
CREATE INDEX IF NOT EXISTS idx_warranty_end_date ON warranty_records (end_date);

-- Maintenance reminders
CREATE INDEX IF NOT EXISTS idx_maint_contracts_next_service ON maintenance_contracts (status, next_service_date);

-- SEO settings average score
CREATE INDEX IF NOT EXISTS idx_seo_settings_score ON seo_settings (seo_score);
