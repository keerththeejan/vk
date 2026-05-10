CREATE TABLE IF NOT EXISTS seo_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(120) NOT NULL UNIQUE,
    page_url VARCHAR(500) NOT NULL,
    meta_title VARCHAR(255) NOT NULL DEFAULT '',
    meta_description TEXT NULL,
    meta_keywords TEXT NULL,
    canonical_url VARCHAR(500) NULL,
    og_title VARCHAR(255) NULL,
    og_description TEXT NULL,
    og_image VARCHAR(500) NULL,
    twitter_card VARCHAR(40) NOT NULL DEFAULT 'summary_large_image',
    schema_markup JSON NULL,
    robots_directive VARCHAR(80) NOT NULL DEFAULT 'index,follow',
    seo_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    indexing_status VARCHAR(40) NOT NULL DEFAULT 'unknown',
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_seo_page_url (page_url),
    INDEX idx_seo_score (seo_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_audit_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(120) NOT NULL,
    check_type VARCHAR(80) NOT NULL,
    status ENUM('pass','warning','fail') NOT NULL DEFAULT 'warning',
    message VARCHAR(500) NOT NULL,
    score_delta SMALLINT NOT NULL DEFAULT 0,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_seo_audit_page (page_key),
    INDEX idx_seo_audit_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_name VARCHAR(180) NOT NULL,
    channel ENUM('email','whatsapp','sms','facebook','instagram','multi_channel') NOT NULL DEFAULT 'multi_channel',
    objective VARCHAR(160) NOT NULL DEFAULT '',
    segment VARCHAR(160) NOT NULL DEFAULT 'All customers',
    status ENUM('draft','scheduled','active','paused','completed') NOT NULL DEFAULT 'draft',
    budget DECIMAL(12,2) NOT NULL DEFAULT 0,
    reach INT UNSIGNED NOT NULL DEFAULT 0,
    engagement INT UNSIGNED NOT NULL DEFAULT 0,
    conversions INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_campaign_status (status),
    INDEX idx_campaign_channel (channel),
    INDEX idx_campaign_dates (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_name VARCHAR(180) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(60) NULL,
    source VARCHAR(120) NOT NULL DEFAULT 'Website',
    service_interest VARCHAR(160) NULL,
    stage ENUM('new','contacted','qualified','proposal','won','lost') NOT NULL DEFAULT 'new',
    score TINYINT UNSIGNED NOT NULL DEFAULT 50,
    campaign_id INT NULL,
    estimated_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    last_touch_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_leads_stage (stage),
    INDEX idx_leads_source (source),
    INDEX idx_leads_campaign (campaign_id),
    CONSTRAINT fk_leads_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(120) NOT NULL UNIQUE,
    template_name VARCHAR(180) NOT NULL,
    category ENUM('welcome','booking','invoice','completion','warranty','payment','newsletter','campaign') NOT NULL DEFAULT 'campaign',
    subject VARCHAR(255) NOT NULL,
    preheader VARCHAR(255) NULL,
    html_body MEDIUMTEXT NOT NULL,
    text_body MEDIUMTEXT NULL,
    variables TEXT NULL,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email_templates_category (category),
    INDEX idx_email_templates_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NULL,
    campaign_id INT NULL,
    phone VARCHAR(60) NOT NULL,
    template_name VARCHAR(160) NULL,
    message_preview VARCHAR(500) NOT NULL,
    direction ENUM('outbound','inbound') NOT NULL DEFAULT 'outbound',
    status ENUM('queued','sent','delivered','read','failed') NOT NULL DEFAULT 'queued',
    provider_message_id VARCHAR(190) NULL,
    sent_at DATETIME NULL,
    delivered_at DATETIME NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_whatsapp_status (status),
    INDEX idx_whatsapp_campaign (campaign_id),
    INDEX idx_whatsapp_phone (phone),
    CONSTRAINT fk_whatsapp_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL,
    channel VARCHAR(80) NOT NULL,
    metric_key VARCHAR(120) NOT NULL,
    metric_value DECIMAL(14,4) NOT NULL DEFAULT 0,
    campaign_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_marketing_metric (metric_date, channel, metric_key, campaign_id),
    INDEX idx_marketing_metric_date (metric_date),
    CONSTRAINT fk_analytics_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_type VARCHAR(80) NOT NULL,
    title VARCHAR(180) NOT NULL,
    body VARCHAR(700) NULL,
    severity ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    related_url VARCHAR(500) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_read (is_read),
    INDEX idx_notifications_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    report_period VARCHAR(60) NOT NULL,
    impressions INT UNSIGNED NOT NULL DEFAULT 0,
    clicks INT UNSIGNED NOT NULL DEFAULT 0,
    opens INT UNSIGNED NOT NULL DEFAULT 0,
    leads INT UNSIGNED NOT NULL DEFAULT 0,
    conversions INT UNSIGNED NOT NULL DEFAULT 0,
    revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
    report_json JSON NULL,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campaign_reports_campaign (campaign_id),
    CONSTRAINT fk_reports_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
