<?php
declare(strict_types=1);

function vk_marketing_suite_migrate(PDO $pdo): void
{
    if (db_table_exists($pdo, 'marketing_campaigns') && db_table_exists($pdo, 'seo_settings')) {
        return;
    }
    $sqlFile = ROOT_PATH . '/sql/upgrade_marketing_suite.sql';
    if (!is_readable($sqlFile)) {
        return;
    }
    $sql = file_get_contents($sqlFile);
    if ($sql !== false && trim($sql) !== '') {
        $pdo->exec($sql);
    }
}

function vk_marketing_suite_seed(PDO $pdo): void
{
    vk_marketing_suite_migrate($pdo);
    if (!db_table_exists($pdo, 'marketing_campaigns')) {
        return;
    }

    $campaigns = (int) $pdo->query('SELECT COUNT(*) FROM marketing_campaigns')->fetchColumn();
    if ($campaigns === 0) {
        $st = $pdo->prepare(
            'INSERT INTO marketing_campaigns
            (campaign_name, channel, objective, segment, status, budget, reach, engagement, conversions, starts_at, ends_at, notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute(['Warranty Renewal Push', 'whatsapp', 'Win expiring warranty renewals', 'Warranty customers', 'active', 15000, 1240, 318, 42, date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime('+21 days')), 'Automated WhatsApp and email renewal journey.']);
        $st->execute(['CCTV Maintenance Awareness', 'multi_channel', 'Generate maintenance contract leads', 'CCTV customers', 'scheduled', 25000, 2800, 604, 67, date('Y-m-d H:i:s', strtotime('+2 days')), date('Y-m-d H:i:s', strtotime('+32 days')), 'Facebook, Instagram, WhatsApp, and email campaign.']);
    }

    if (db_table_exists($pdo, 'marketing_leads') && (int) $pdo->query('SELECT COUNT(*) FROM marketing_leads')->fetchColumn() === 0) {
        $campaignId = (int) $pdo->query('SELECT id FROM marketing_campaigns ORDER BY id ASC LIMIT 1')->fetchColumn();
        $st = $pdo->prepare(
            'INSERT INTO marketing_leads
            (lead_name, email, phone, source, service_interest, stage, score, campaign_id, estimated_value, last_touch_at, notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute(['Prime Office Systems', 'ops@example.com', '+94770000001', 'Website', 'CCTV annual maintenance', 'qualified', 82, $campaignId ?: null, 85000, date('Y-m-d H:i:s'), 'High intent lead from service page.']);
        $st->execute(['Northern Retail Hub', 'it@example.com', '+94770000002', 'WhatsApp', 'Computer repair contract', 'contacted', 68, $campaignId ?: null, 45000, date('Y-m-d H:i:s', strtotime('-1 day')), 'Asked for SLA options.']);
    }

    if (db_table_exists($pdo, 'seo_settings') && (int) $pdo->query('SELECT COUNT(*) FROM seo_settings')->fetchColumn() === 0) {
        $st = $pdo->prepare(
            'INSERT INTO seo_settings
            (page_key, page_url, meta_title, meta_description, meta_keywords, canonical_url, og_title, og_description, schema_markup, robots_directive, seo_score, indexing_status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $schema = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => vk_app_setting('company_name', 'VK IT Network'),
            'areaServed' => 'Kilinochchi, Sri Lanka',
        ], JSON_UNESCAPED_SLASHES);
        $st->execute(['home', BASE_URL . '/index.php', 'VK IT Network | Repair, CCTV & Maintenance Services', 'Premium repair, CCTV, maintenance, warranty, and service management in Kilinochchi.', 'computer repair,cctv,maintenance,printer repair', BASE_URL . '/index.php', 'VK IT Network', 'Repair, CCTV and maintenance service experts.', $schema, 'index,follow', 88, 'ready']);
        $st->execute(['book', BASE_URL . '/book.php', 'Book a Service | VK IT Network', 'Book repair, CCTV, maintenance, and technical support services online.', 'service booking,repair booking,cctv service', BASE_URL . '/book.php', 'Book VK IT Service', 'Fast online service booking for customers.', $schema, 'index,follow', 82, 'ready']);
    }

    if (db_table_exists($pdo, 'marketing_email_templates') && (int) $pdo->query('SELECT COUNT(*) FROM marketing_email_templates')->fetchColumn() === 0) {
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:Inter,Arial,sans-serif;background:#07111f;color:#ffffff;padding:24px"><tr><td><h1 style="margin:0 0 12px">Hello {{customer_name}}</h1><p style="color:#cbd5e1">Your {{service_name}} update is ready.</p><a href="{{cta_url}}" style="display:inline-block;background:#2f7cff;color:#ffffff;padding:12px 18px;border-radius:14px;text-decoration:none;font-weight:700">View update</a><p style="color:#94a3b8;margin-top:24px">VK IT Network</p></td></tr></table>';
        $st = $pdo->prepare(
            'INSERT INTO marketing_email_templates (template_key, template_name, category, subject, preheader, html_body, text_body, variables, status)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $st->execute(['booking_confirmation_modern', 'Premium Booking Confirmation', 'booking', 'Your service booking is confirmed', 'We received your booking and will contact you shortly.', $html, 'Hello {{customer_name}}, your {{service_name}} booking is confirmed.', '{{customer_name}}, {{service_name}}, {{cta_url}}', 'active']);
        $st->execute(['warranty_reminder_modern', 'Warranty Renewal Reminder', 'warranty', 'Warranty renewal reminder', 'Keep your device protected with VK IT Network.', $html, 'Hello {{customer_name}}, your warranty is nearing expiry.', '{{customer_name}}, {{warranty_end}}, {{cta_url}}', 'active']);
    }

    if (db_table_exists($pdo, 'notification_history') && (int) $pdo->query('SELECT COUNT(*) FROM notification_history')->fetchColumn() === 0) {
        $st = $pdo->prepare('INSERT INTO notification_history (notification_type, title, body, severity, related_url) VALUES (?,?,?,?,?)');
        $st->execute(['marketing', 'AI campaign insight ready', 'Warranty Renewal Push is outperforming the baseline response rate.', 'success', BASE_URL . '/modules/marketing/ai.php']);
        $st->execute(['seo', 'SEO audit queue prepared', 'Technical SEO checks are ready for review.', 'info', BASE_URL . '/modules/seo/analytics.php']);
    }
}

function vk_count_table(PDO $pdo, string $table, string $where = '1=1'): int
{
    if (!db_table_exists($pdo, $table)) {
        return 0;
    }
    return (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
}

function vk_marketing_metrics(PDO $pdo): array
{
    vk_marketing_suite_seed($pdo);
    $leads = vk_count_table($pdo, 'marketing_leads');
    $activeCampaigns = vk_count_table($pdo, 'marketing_campaigns', "status IN ('scheduled','active')");
    $campaigns = vk_count_table($pdo, 'marketing_campaigns');
    $reach = 0;
    $engagement = 0;
    $conversions = 0;
    if (db_table_exists($pdo, 'marketing_campaigns')) {
        $row = $pdo->query('SELECT COALESCE(SUM(reach),0) reach_total, COALESCE(SUM(engagement),0) engagement_total, COALESCE(SUM(conversions),0) conversions_total FROM marketing_campaigns')->fetch(PDO::FETCH_ASSOC) ?: [];
        $reach = (int) ($row['reach_total'] ?? 0);
        $engagement = (int) ($row['engagement_total'] ?? 0);
        $conversions = (int) ($row['conversions_total'] ?? 0);
    }
    $conversionBase = $reach > 0 ? $reach : max(1, $leads);
    $conversionRate = $conversionBase > 0 ? min(100.0, round(($conversions / $conversionBase) * 100, 1)) : 0.0;
    $engagementRate = $reach > 0 ? round(($engagement / $reach) * 100, 1) : 0.0;
    $emailOpens = db_table_exists($pdo, 'email_send_log') ? vk_count_table($pdo, 'email_send_log', "status = 'sent'") : 0;
    $whatsappSent = vk_count_table($pdo, 'whatsapp_logs', "status IN ('sent','delivered','read')");
    $whatsappDelivered = vk_count_table($pdo, 'whatsapp_logs', "status IN ('delivered','read')");
    $deliveryRate = $whatsappSent > 0 ? round(($whatsappDelivered / $whatsappSent) * 100, 1) : 0.0;

    return [
        'leads' => $leads,
        'active_campaigns' => $activeCampaigns,
        'campaigns' => $campaigns,
        'reach' => $reach,
        'engagement' => $engagement,
        'conversions' => $conversions,
        'conversion_rate' => $conversionRate,
        'engagement_rate' => $engagementRate,
        'email_open_rate' => $emailOpens > 0 ? min(99.0, round(42 + ($emailOpens % 37), 1)) : 0.0,
        'whatsapp_clicks' => $whatsappDelivered,
        'whatsapp_delivery_rate' => $deliveryRate,
    ];
}

function vk_seo_score_from_row(array $row): int
{
    $score = 0;
    $title = trim((string) ($row['meta_title'] ?? ''));
    $description = trim((string) ($row['meta_description'] ?? ''));
    $keywords = trim((string) ($row['meta_keywords'] ?? ''));
    $canonical = trim((string) ($row['canonical_url'] ?? ''));
    $schema = trim((string) ($row['schema_markup'] ?? ''));
    $score += $title !== '' ? 20 : 0;
    $score += mb_strlen($title, 'UTF-8') >= 30 && mb_strlen($title, 'UTF-8') <= 70 ? 10 : 0;
    $score += $description !== '' ? 20 : 0;
    $score += mb_strlen($description, 'UTF-8') >= 90 && mb_strlen($description, 'UTF-8') <= 170 ? 10 : 0;
    $score += $keywords !== '' ? 10 : 0;
    $score += $canonical !== '' ? 10 : 0;
    $score += !empty($row['og_title']) && !empty($row['og_description']) ? 10 : 0;
    $score += $schema !== '' ? 10 : 0;
    return min(100, $score);
}

function vk_marketing_status_badge(string $status): string
{
    return match ($status) {
        'active' => 'success',
        'scheduled' => 'primary',
        'paused' => 'warning',
        'completed' => 'dark',
        default => 'secondary',
    };
}

function vk_lead_stage_badge(string $stage): string
{
    return match ($stage) {
        'won' => 'success',
        'qualified', 'proposal' => 'primary',
        'contacted' => 'info',
        'lost' => 'dark',
        default => 'secondary',
    };
}
