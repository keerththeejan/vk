<?php
declare(strict_types=1);

/**
 * Enterprise mail bootstrap. Secrets should live in .env / hosting environment.
 *
 * Supported variables:
 * VK_SMTP_HOST=smtp.gmail.com or smtp.hostinger.com/mail.yourdomain.com
 * VK_SMTP_PORT=587
 * VK_SMTP_USER=your mailbox
 * VK_SMTP_PASS=app password / mailbox password
 * VK_SMTP_SECURE=tls or ssl
 * VK_MAIL_FROM=no-reply@yourdomain.com
 * VK_MAIL_FROM_NAME="VK Network"
 * VK_ADMIN_EMAIL=admin@yourdomain.com
 */
if (!defined('VK_MAIL_FRAMEWORK')) {
    define('VK_MAIL_FRAMEWORK', 'vanilla-php');
}

if (!defined('VK_DEFAULT_SMTP_HOST')) {
    define('VK_DEFAULT_SMTP_HOST', (string) (getenv('VK_SMTP_HOST') ?: getenv('MAIL_HOST') ?: ''));
}
if (!defined('VK_DEFAULT_SMTP_PORT')) {
    define('VK_DEFAULT_SMTP_PORT', (int) (getenv('VK_SMTP_PORT') ?: getenv('MAIL_PORT') ?: 587));
}
if (!defined('VK_DEFAULT_SMTP_USER')) {
    define('VK_DEFAULT_SMTP_USER', (string) (getenv('VK_SMTP_USER') ?: getenv('MAIL_USERNAME') ?: ''));
}
if (!defined('VK_DEFAULT_SMTP_PASS')) {
    define('VK_DEFAULT_SMTP_PASS', (string) (getenv('VK_SMTP_PASS') ?: getenv('MAIL_PASSWORD') ?: ''));
}
if (!defined('VK_DEFAULT_SMTP_SECURE')) {
    $vkMailSecure = strtolower((string) (getenv('VK_SMTP_SECURE') ?: getenv('MAIL_ENCRYPTION') ?: 'tls'));
    define('VK_DEFAULT_SMTP_SECURE', in_array($vkMailSecure, ['ssl', 'smtps'], true) ? 'ssl' : 'tls');
}
if (!defined('VK_DEFAULT_MAIL_FROM')) {
    define('VK_DEFAULT_MAIL_FROM', (string) (getenv('VK_MAIL_FROM') ?: getenv('MAIL_FROM_ADDRESS') ?: VK_DEFAULT_SMTP_USER));
}
if (!defined('VK_DEFAULT_MAIL_FROM_NAME')) {
    define('VK_DEFAULT_MAIL_FROM_NAME', (string) (getenv('VK_MAIL_FROM_NAME') ?: getenv('MAIL_FROM_NAME') ?: 'VK Network'));
}

function vk_mail_default_config(): array
{
    return [
        'smtp_host' => VK_DEFAULT_SMTP_HOST,
        'smtp_port' => VK_DEFAULT_SMTP_PORT,
        'smtp_user' => VK_DEFAULT_SMTP_USER,
        'smtp_pass' => VK_DEFAULT_SMTP_PASS,
        'smtp_secure' => VK_DEFAULT_SMTP_SECURE,
        'from_email' => VK_DEFAULT_MAIL_FROM,
        'from_name' => VK_DEFAULT_MAIL_FROM_NAME,
        'admin_email' => (string) (getenv('VK_ADMIN_EMAIL') ?: getenv('MAIL_ADMIN_EMAIL') ?: ''),
        'configured' => VK_DEFAULT_SMTP_HOST !== '' && VK_DEFAULT_MAIL_FROM !== '',
    ];
}

/**
 * Detect stack — this project is core PHP; hook kept for tooling / future bridges.
 */
function vk_mail_detect_framework(): string
{
    if (defined('VK_MAIL_FRAMEWORK')) {
        return (string) VK_MAIL_FRAMEWORK;
    }
    return 'vanilla-php';
}

/**
 * Suggested host/port preset when the mailbox domain is vkitnet.info (no credentials).
 *
 * @return array{smtp_host:string,smtp_port:int,smtp_secure:string,imap_host:string,imap_port:int,pop3_port:int}
 */
function vk_mail_preset_vkitnet(): array
{
    return [
        'smtp_host' => 'vkitnet.info',
        'smtp_port' => 465,
        'smtp_secure' => 'ssl',
        'imap_host' => 'vkitnet.info',
        'imap_port' => 993,
        'pop3_port' => 995,
    ];
}
