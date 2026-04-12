<?php
declare(strict_types=1);

/**
 * Mail bootstrap metadata (vanilla PHP). Secrets stay in environment / .env only.
 */
if (!defined('VK_MAIL_FRAMEWORK')) {
    define('VK_MAIL_FRAMEWORK', 'vanilla-php');
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
