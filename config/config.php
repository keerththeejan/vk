<?php
/**
 * Application configuration — adjust BASE_URL to match your WAMP virtual folder.
 */
declare(strict_types=1);

require_once __DIR__ . '/env_bootstrap.php';
vk_load_dotenv();
require_once __DIR__ . '/mail.php';

define('APP_NAME', 'VK IT Network — Service & Billing');
define('ROOT_PATH', dirname(__DIR__));

function vk_config_bool(string $name, bool $default): bool
{
    $value = getenv($name);
    if ($value === false || trim((string) $value) === '') {
        return $default;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

$hostName = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$isLocalHost = $hostName === 'localhost'
    || str_starts_with($hostName, 'localhost:')
    || $hostName === '127.0.0.1'
    || str_starts_with($hostName, '127.0.0.1:')
    || $hostName === '::1'
    || str_starts_with($hostName, '[::1]:');

$configuredEnv = strtolower(trim((string) (getenv('APP_ENV') ?: '')));
$appEnv = $configuredEnv !== '' ? $configuredEnv : ($isLocalHost ? 'local' : 'production');
define('APP_ENV', $appEnv);

// Public base URL/path without a trailing slash. Examples:
//   https://vkintet.info
//   https://vkintet.info/vk
//   /VK
//
// Keep production domains in APP_BASE_URL. When it is not set, infer the
// local folder so localhost installs do not emit production asset URLs.
$configuredBaseUrl = trim((string) (getenv('APP_BASE_URL') ?: ''));
$baseUrl = rtrim($configuredBaseUrl, '/');
if ($baseUrl === '') {
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = $scriptName !== '' ? str_replace('\\', '/', dirname($scriptName)) : '';
    $rootFolder = trim($scriptDir, '/');
    if ($rootFolder !== '' && $rootFolder !== '.') {
        $firstSegment = strtok($rootFolder, '/');
        $baseUrl = $firstSegment !== false ? '/' . $firstSegment : '';
    }
}
define('BASE_URL', $baseUrl);

// Session cookie name
define('SESSION_NAME', 'vk_billing_sess');

// Display errors in development only — set false on production
define('APP_DEBUG', vk_config_bool('APP_DEBUG', APP_ENV !== 'production'));

// Dashboard / warranty highlight: days ahead to treat as “expiring soon”
define('WARRANTY_ALERT_DAYS', 30);

// Google Maps JavaScript API key (optional — for booking map + admin location preview)
define('GOOGLE_MAPS_API_KEY', '');

// Smart booking: nearest technician + optional local WhatsApp bridge (Node). See node-service/whatsapp-bridge/
if (!defined('VK_ASSIGN_MAX_RADIUS_KM')) {
    define('VK_ASSIGN_MAX_RADIUS_KM', 20.0);
}
if (!defined('VK_WHATSAPP_BRIDGE_URL')) {
    define('VK_WHATSAPP_BRIDGE_URL', ''); // e.g. http://127.0.0.1:3999/send-message
}
if (!defined('VK_WHATSAPP_BRIDGE_SECRET')) {
    define('VK_WHATSAPP_BRIDGE_SECRET', '');
}
if (!defined('VK_WHATSAPP_ADMIN_PHONE')) {
    define('VK_WHATSAPP_ADMIN_PHONE', ''); // optional duplicate notify (digits / local format)
}

// Public SEO & WhatsApp (floating button, wa.me links)
if (!defined('VK_SEO_DEFAULT_DESCRIPTION')) {
    define(
        'VK_SEO_DEFAULT_DESCRIPTION',
        'Professional repair and IT services in Sri Lanka — computer, printer, CCTV, maintenance, and field support. VK Network.'
    );
}
if (!defined('VK_SEO_DEFAULT_KEYWORDS')) {
    define(
        'VK_SEO_DEFAULT_KEYWORDS',
        'computer repair, laptop service, printer repair, CCTV, Sri Lanka, Kilinochchi, field service, VK Network'
    );
}
if (!defined('VK_SEO_OG_IMAGE')) {
    define('VK_SEO_OG_IMAGE', '/assets/images/services/svc-computer.svg');
}
if (!defined('VK_PUBLIC_WHATSAPP_NUMBER')) {
    define('VK_PUBLIC_WHATSAPP_NUMBER', '94778870135'); // wa.me (digits, country code)
}
if (!defined('VK_BUSINESS_NAME')) {
    define('VK_BUSINESS_NAME', 'VK Network');
}
if (!defined('VK_BUSINESS_PHONE_E164')) {
    define('VK_BUSINESS_PHONE_E164', '+94778870135');
}
if (!defined('VK_BUSINESS_STREET')) {
    define('VK_BUSINESS_STREET', '26/3 Thiruvaiyaru');
}
if (!defined('VK_BUSINESS_CITY')) {
    define('VK_BUSINESS_CITY', 'Kilinochchi');
}
if (!defined('VK_BUSINESS_REGION')) {
    define('VK_BUSINESS_REGION', 'Northern Province');
}
if (!defined('VK_BUSINESS_COUNTRY')) {
    define('VK_BUSINESS_COUNTRY', 'LK');
}

// true = links use /VK/service/my-slug (requires mod_rewrite + .htaccess). false = /VK/service/index.php?slug=my-slug (always works).
if (!defined('VK_SEO_PRETTY_SERVICE_URLS')) {
    define('VK_SEO_PRETTY_SERVICE_URLS', false);
}

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
