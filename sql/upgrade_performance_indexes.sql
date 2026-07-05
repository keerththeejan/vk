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

-- Accounts / customer ledger
CREATE INDEX IF NOT EXISTS idx_accounts_customer_balance ON accounts (customer_id, current_balance);
CREATE INDEX IF NOT EXISTS idx_customers_created_at ON customers (created_at);

-- Invoices: status filters + revenue
CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices (status);
CREATE INDEX IF NOT EXISTS idx_invoices_paid_amount ON invoices (paid_amount);

-- Repair jobs: customer lookups + active pipeline
CREATE INDEX IF NOT EXISTS idx_repair_jobs_customer_created ON repair_jobs (customer_id, created_at);
CREATE INDEX IF NOT EXISTS idx_repair_jobs_status_customer ON repair_jobs (status, customer_id);

-- Products list / inventory
CREATE INDEX IF NOT EXISTS idx_products_name ON products (name);
CREATE INDEX IF NOT EXISTS idx_products_active ON products (active, id);

-- Vehicle modules
CREATE INDEX IF NOT EXISTS idx_vehicle_drivers_active ON vehicle_drivers (active, id);
CREATE INDEX IF NOT EXISTS idx_vehicles_status_type ON vehicles (status, vehicle_type);

-- Marketing / WhatsApp
CREATE INDEX IF NOT EXISTS idx_marketing_leads_stage ON marketing_leads (stage, score);
CREATE INDEX IF NOT EXISTS idx_whatsapp_logs_created ON whatsapp_logs (created_at);
