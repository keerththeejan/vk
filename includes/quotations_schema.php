<?php
declare(strict_types=1);

/**
 * Quotation Management schema — auto-migrates on first module use.
 */
function vk_ensure_quotations_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec('SET NAMES utf8mb4');
    } catch (Throwable $e) {
        // ignore
    }

    $tables = [
        'quotation_categories' => "CREATE TABLE IF NOT EXISTS quotation_categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(128) NOT NULL,
            slug VARCHAR(128) NOT NULL,
            description VARCHAR(512) DEFAULT NULL,
            color VARCHAR(32) DEFAULT '#0B4DBA',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_qcat_slug (slug),
            INDEX idx_qcat_active (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'quotation_templates' => "CREATE TABLE IF NOT EXISTS quotation_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            category_id INT UNSIGNED DEFAULT NULL,
            description TEXT,
            payment_terms VARCHAR(255) DEFAULT NULL,
            delivery_terms VARCHAR(255) DEFAULT NULL,
            validity_days INT UNSIGNED NOT NULL DEFAULT 30,
            tax_method ENUM('exclusive','inclusive','none') NOT NULL DEFAULT 'exclusive',
            default_tax_pct DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            terms_html MEDIUMTEXT,
            notes TEXT,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_qtmpl_active (is_active, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'quotation_terms' => "CREATE TABLE IF NOT EXISTS quotation_terms (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(191) NOT NULL,
            body MEDIUMTEXT NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'quotations' => "CREATE TABLE IF NOT EXISTS quotations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quotation_number VARCHAR(64) NOT NULL,
            revision_no INT UNSIGNED NOT NULL DEFAULT 0,
            parent_quotation_id INT UNSIGNED DEFAULT NULL,
            customer_id INT UNSIGNED NOT NULL,
            company_name VARCHAR(255) DEFAULT NULL,
            contact_person VARCHAR(191) DEFAULT NULL,
            phone VARCHAR(64) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            billing_address TEXT,
            shipping_address TEXT,
            currency VARCHAR(8) NOT NULL DEFAULT 'LKR',
            sales_executive_id INT UNSIGNED DEFAULT NULL,
            category_id INT UNSIGNED DEFAULT NULL,
            template_id INT UNSIGNED DEFAULT NULL,
            reference_number VARCHAR(128) DEFAULT NULL,
            quotation_date DATE NOT NULL,
            expiry_date DATE DEFAULT NULL,
            payment_terms VARCHAR(255) DEFAULT NULL,
            delivery_terms VARCHAR(255) DEFAULT NULL,
            validity_days INT UNSIGNED DEFAULT 30,
            tax_method ENUM('exclusive','inclusive','none') NOT NULL DEFAULT 'exclusive',
            status ENUM(
                'draft','pending_approval','approved','rejected','cancelled',
                'expired','accepted','converted_so','converted_invoice'
            ) NOT NULL DEFAULT 'draft',
            approval_status ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
            approval_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
            subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            item_discount_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            overall_discount_pct DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            overall_discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            tax_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            shipping_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            additional_charges DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            round_off DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            grand_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            estimated_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            net_profit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            profit_margin_pct DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            notes TEXT,
            internal_notes TEXT,
            terms_html MEDIUMTEXT,
            customer_response TEXT,
            expected_closing_date DATE DEFAULT NULL,
            converted_invoice_id INT UNSIGNED DEFAULT NULL,
            converted_at DATETIME DEFAULT NULL,
            branch VARCHAR(128) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            updated_by INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_quotations_number (quotation_number),
            INDEX idx_quotations_customer (customer_id),
            INDEX idx_quotations_status (status),
            INDEX idx_quotations_date (quotation_date),
            INDEX idx_quotations_expiry (expiry_date),
            INDEX idx_quotations_exec (sales_executive_id),
            INDEX idx_quotations_approval (approval_status),
            INDEX idx_quotations_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'quotation_items' => "CREATE TABLE IF NOT EXISTS quotation_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quotation_id INT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            item_type ENUM('product','service','custom') NOT NULL DEFAULT 'product',
            product_id INT UNSIGNED DEFAULT NULL,
            product_code VARCHAR(128) DEFAULT NULL,
            barcode VARCHAR(128) DEFAULT NULL,
            product_name VARCHAR(255) NOT NULL,
            category_name VARCHAR(128) DEFAULT NULL,
            description TEXT,
            unit VARCHAR(32) DEFAULT 'pcs',
            quantity DECIMAL(14,3) NOT NULL DEFAULT 1.000,
            unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            cost_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            discount_pct DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            tax_pct DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            line_subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            image_path VARCHAR(512) DEFAULT NULL,
            INDEX idx_qitems_quotation (quotation_id, sort_order),
            INDEX idx_qitems_product (product_id),
            CONSTRAINT fk_qitems_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'quotation_approvals' => "CREATE TABLE IF NOT EXISTS quotation_approvals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quotation_id INT UNSIGNED NOT NULL,
            level TINYINT UNSIGNED NOT NULL DEFAULT 1,
            role_label VARCHAR(64) NOT NULL,
            approver_id INT UNSIGNED DEFAULT NULL,
            action ENUM('pending','approved','rejected','skipped') NOT NULL DEFAULT 'pending',
            notes TEXT,
            acted_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_qappr_quotation (quotation_id, level),
            CONSTRAINT fk_qappr_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'quotation_revisions' => "CREATE TABLE IF NOT EXISTS quotation_revisions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quotation_id INT UNSIGNED NOT NULL,
            revision_no INT UNSIGNED NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            change_summary VARCHAR(512) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_qrev (quotation_id, revision_no),
            CONSTRAINT fk_qrev_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'quotation_followups' => "CREATE TABLE IF NOT EXISTS quotation_followups (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quotation_id INT UNSIGNED NOT NULL,
            reminder_date DATE NOT NULL,
            reminder_status ENUM('pending','done','missed','cancelled') NOT NULL DEFAULT 'pending',
            followup_notes TEXT,
            customer_response TEXT,
            expected_closing_date DATE DEFAULT NULL,
            assigned_to INT UNSIGNED DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_qfu_date (reminder_date, reminder_status),
            INDEX idx_qfu_quotation (quotation_id),
            CONSTRAINT fk_qfu_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'quotation_notes' => "CREATE TABLE IF NOT EXISTS quotation_notes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quotation_id INT UNSIGNED NOT NULL,
            note_type ENUM('general','internal','customer','system') NOT NULL DEFAULT 'general',
            body TEXT NOT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_qnotes_quotation (quotation_id),
            CONSTRAINT fk_qnotes_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'quotation_attachments' => "CREATE TABLE IF NOT EXISTS quotation_attachments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quotation_id INT UNSIGNED NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(512) NOT NULL,
            mime_type VARCHAR(128) DEFAULT NULL,
            file_size INT UNSIGNED DEFAULT 0,
            uploaded_by INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_qatt_quotation (quotation_id),
            CONSTRAINT fk_qatt_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'quotation_activity_logs' => "CREATE TABLE IF NOT EXISTS quotation_activity_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quotation_id INT UNSIGNED DEFAULT NULL,
            user_id INT UNSIGNED DEFAULT NULL,
            action VARCHAR(64) NOT NULL,
            details TEXT,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_qlog_quotation (quotation_id, created_at),
            INDEX idx_qlog_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'quotation_email_tracking' => "CREATE TABLE IF NOT EXISTS quotation_email_tracking (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quotation_id INT UNSIGNED NOT NULL,
            tracking_token VARCHAR(64) NOT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            sent_at DATETIME DEFAULT NULL,
            delivered_at DATETIME DEFAULT NULL,
            opened_at DATETIME DEFAULT NULL,
            download_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_download_at DATETIME DEFAULT NULL,
            status ENUM('queued','sent','failed','bounced') NOT NULL DEFAULT 'queued',
            error_message VARCHAR(512) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_qemail_token (tracking_token),
            INDEX idx_qemail_quotation (quotation_id),
            CONSTRAINT fk_qemail_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'quotation_settings' => "CREATE TABLE IF NOT EXISTS quotation_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(128) NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_qset_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($tables as $name => $sql) {
        if (!db_table_exists($pdo, $name)) {
            try {
                $pdo->exec($sql);
                db_table_exists_forget($name);
            } catch (Throwable $e) {
                error_log('vk_ensure_quotations_schema create ' . $name . ': ' . $e->getMessage());
            }
        }
    }

    vk_quotations_seed_defaults($pdo);
    vk_quotations_ensure_erp_columns($pdo);
}

/**
 * Extra columns for premium ERP quotation create screen.
 */
function vk_quotations_ensure_erp_columns(PDO $pdo): void
{
    if (!db_table_exists($pdo, 'quotations')) {
        return;
    }

    $quoteCols = [
        'customer_po_number' => 'ALTER TABLE quotations ADD COLUMN customer_po_number VARCHAR(128) DEFAULT NULL',
        'department' => 'ALTER TABLE quotations ADD COLUMN department VARCHAR(128) DEFAULT NULL',
        'warehouse' => 'ALTER TABLE quotations ADD COLUMN warehouse VARCHAR(128) DEFAULT NULL',
        'exchange_rate' => 'ALTER TABLE quotations ADD COLUMN exchange_rate DECIMAL(18,6) NOT NULL DEFAULT 1.000000',
        'mobile' => 'ALTER TABLE quotations ADD COLUMN mobile VARCHAR(64) DEFAULT NULL',
        'tax_number' => 'ALTER TABLE quotations ADD COLUMN tax_number VARCHAR(64) DEFAULT NULL',
        'credit_limit' => 'ALTER TABLE quotations ADD COLUMN credit_limit DECIMAL(14,2) NOT NULL DEFAULT 0.00',
        'warranty_terms' => 'ALTER TABLE quotations ADD COLUMN warranty_terms TEXT NULL',
        'customer_code' => 'ALTER TABLE quotations ADD COLUMN customer_code VARCHAR(64) DEFAULT NULL',
    ];
    foreach ($quoteCols as $col => $sql) {
        if (!db_column_exists($pdo, 'quotations', $col)) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                error_log('vk_quotations_ensure_erp_columns quotations.' . $col . ': ' . $e->getMessage());
            }
        }
    }

    if (db_table_exists($pdo, 'quotation_items')) {
        $itemCols = [
            'warehouse' => 'ALTER TABLE quotation_items ADD COLUMN warehouse VARCHAR(128) DEFAULT NULL',
            'stock_available' => 'ALTER TABLE quotation_items ADD COLUMN stock_available DECIMAL(14,3) DEFAULT NULL',
        ];
        foreach ($itemCols as $col => $sql) {
            if (!db_column_exists($pdo, 'quotation_items', $col)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable $e) {
                    error_log('vk_quotations_ensure_erp_columns items.' . $col . ': ' . $e->getMessage());
                }
            }
        }
    }

    // Prefer QT-YYYY-000001 numbering for new installs / legacy QTN prefix
    try {
        $st = $pdo->prepare('SELECT setting_value FROM quotation_settings WHERE setting_key = ? LIMIT 1');
        $st->execute(['prefix']);
        $prefix = (string) ($st->fetchColumn() ?: '');
        if ($prefix === '' || $prefix === 'QTN') {
            $pdo->prepare(
                'INSERT INTO quotation_settings (setting_key, setting_value) VALUES (?,?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            )->execute(['prefix', 'QT']);
        }
        $assets = [
            'letterhead_path' => 'assets/images/vk-letterhead.png',
            'signature_path' => 'assets/images/digital-signature.png',
            'stamp_path' => 'assets/images/company-stamp.png',
        ];
        foreach ($assets as $k => $v) {
            $st->execute([$k]);
            if (!$st->fetchColumn()) {
                $pdo->prepare('INSERT INTO quotation_settings (setting_key, setting_value) VALUES (?,?)')->execute([$k, $v]);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
}

function vk_quotations_seed_defaults(PDO $pdo): void
{
    if (!db_table_exists($pdo, 'quotation_categories')) {
        return;
    }
    try {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM quotation_categories')->fetchColumn();
        if ($count === 0) {
            $ins = $pdo->prepare('INSERT INTO quotation_categories (name, slug, description, color, sort_order) VALUES (?,?,?,?,?)');
            $seed = [
                ['Hardware', 'hardware', 'Computers, parts, peripherals', '#0B4DBA', 10],
                ['Software', 'software', 'Licenses and development', '#0d9488', 20],
                ['CCTV', 'cctv', 'Surveillance packages', '#7c3aed', 30],
                ['Networking', 'networking', 'Network infrastructure', '#0369a1', 40],
                ['Service', 'service', 'Repair and maintenance services', '#b45309', 50],
                ['AMC', 'amc', 'Annual maintenance contracts', '#047857', 60],
            ];
            foreach ($seed as $row) {
                $ins->execute($row);
            }
        }
    } catch (Throwable $e) {
        error_log('vk_quotations_seed categories: ' . $e->getMessage());
    }

    if (db_table_exists($pdo, 'quotation_terms')) {
        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM quotation_terms')->fetchColumn();
            if ($count === 0) {
                $pdo->prepare(
                    'INSERT INTO quotation_terms (title, body, is_default, sort_order) VALUES (?,?,1,1)'
                )->execute([
                    'Standard Terms',
                    "1. Prices are valid until the quotation expiry date.\n2. Payment terms as stated on this quotation.\n3. Delivery timelines commence after confirmation and advance payment (if applicable).\n4. Goods remain the property of VK Network until full payment is received.\n5. Warranty terms apply as per product/service specifications.\n6. VK Network reserves the right to revise prices if costs change after expiry.",
                ]);
            }
        } catch (Throwable $e) {
            error_log('vk_quotations_seed terms: ' . $e->getMessage());
        }
    }

    if (db_table_exists($pdo, 'quotation_settings')) {
        $defaults = [
            'prefix' => 'QT',
            'default_validity_days' => '30',
            'default_currency' => 'LKR',
            'default_tax_pct' => '0',
            'default_tax_method' => 'exclusive',
            'require_approval' => '1',
            'approval_levels' => 'sales_executive,manager,finance,director',
            'auto_expire' => '1',
            'letterhead_path' => 'assets/images/vk-letterhead.png',
            'signature_path' => 'assets/images/digital-signature.png',
            'stamp_path' => 'assets/images/company-stamp.png',
            'bank_name' => 'Commercial Bank',
            'bank_account_name' => 'VK Network',
            'bank_account_number' => '',
            'bank_branch' => 'Kilinochchi',
            'whatsapp_template' => "Hello {customer_name},\n\nPlease find quotation *{quotation_number}* for LKR {grand_total}.\nValid until: {expiry_date}\n\nView/Print: {print_url}\n\nThank you,\nVK Network",
            'email_subject' => 'Quotation {quotation_number} from VK Network',
        ];
        $chk = $pdo->prepare('SELECT 1 FROM quotation_settings WHERE setting_key = ? LIMIT 1');
        $ins = $pdo->prepare('INSERT INTO quotation_settings (setting_key, setting_value) VALUES (?,?)');
        foreach ($defaults as $k => $v) {
            try {
                $chk->execute([$k]);
                if (!$chk->fetchColumn()) {
                    $ins->execute([$k, $v]);
                }
            } catch (Throwable $e) {
                // ignore
            }
        }
    }
}
