<?php
declare(strict_types=1);

/**
 * Global Sri Lankan Rupee (LKR) display formatting.
 * Storage and calculations remain numeric — this layer is presentation only.
 */

if (!defined('VK_CURRENCY_CODE')) {
    define('VK_CURRENCY_CODE', 'LKR');
}
if (!defined('VK_CURRENCY_SYMBOL')) {
    define('VK_CURRENCY_SYMBOL', 'Rs.');
}
if (!defined('VK_CURRENCY_NAME')) {
    define('VK_CURRENCY_NAME', 'Sri Lankan Rupee');
}
if (!defined('VK_CURRENCY_LOCALE')) {
    define('VK_CURRENCY_LOCALE', 'en_LK');
}

/**
 * Format a numeric amount as Sri Lankan Rupees for display.
 * Example: 1250 → "Rs. 1,250.00"
 *
 * @param mixed $amount Numeric amount (int|float|string|null)
 */
function formatCurrency($amount): string
{
    if ($amount === null || $amount === '') {
        $amount = 0;
    }
    if (is_string($amount)) {
        $amount = str_replace([',', ' '], '', $amount);
    }

    return 'Rs. ' . number_format((float) $amount, 2, '.', ',');
}

/** Preferred VK alias. */
function vk_format_currency($amount): string
{
    return formatCurrency($amount);
}

/** Short template alias. */
function money($amount): string
{
    return formatCurrency($amount);
}

/**
 * Format for HTML output (escaped).
 */
function e_money($amount): string
{
    return e(formatCurrency($amount));
}

/**
 * Strip display formatting back to a float for form handling (never for DB writes of symbols).
 */
function vk_parse_currency_input($value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }
    if (is_numeric($value)) {
        return round((float) $value, 2);
    }
    $raw = preg_replace('/[^\d.\-]/', '', str_replace(',', '', (string) $value));
    if ($raw === null || $raw === '' || $raw === '-' || $raw === '.') {
        return 0.0;
    }

    return round((float) $raw, 2);
}
