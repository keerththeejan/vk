<?php
declare(strict_types=1);

/**
 * Public SEO helpers (canonical, OG, JSON-LD). Admin uses noindex via header.php.
 */
function vk_site_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

/** Absolute URL for a path under BASE_URL (e.g. /VK/book.php). */
function vk_public_absolute_url(string $path): string
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    $path = $path === '' ? '/' : $path;
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    if (str_starts_with(BASE_URL, 'http://') || str_starts_with(BASE_URL, 'https://')) {
        $base = rtrim(BASE_URL, '/');
        $basePath = (string) (parse_url($base, PHP_URL_PATH) ?: '');
        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            return vk_site_origin() . $path;
        }

        return $base . $path;
    }

    $base = BASE_URL === '' ? '' : '/' . trim(BASE_URL, '/');
    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        return vk_site_origin() . $path;
    }

    return vk_site_origin() . $base . $path;
}

/**
 * Public URL for a web_services row.
 *
 * Default: /VK/service/index.php?slug=… (works even if mod_rewrite is off).
 * Set VK_SEO_PRETTY_SERVICE_URLS to true in config when Apache rewrites /service/{slug} → service/index.php.
 */
function vk_web_service_public_path(string $slug, ?int $id = null): string
{
    $slug = trim($slug);
    if ($slug === '' && $id !== null && $id > 0) {
        return BASE_URL . '/service-details.php?id=' . $id;
    }
    if ($slug === '') {
        return BASE_URL . '/index.php';
    }

    $pretty = defined('VK_SEO_PRETTY_SERVICE_URLS') && VK_SEO_PRETTY_SERVICE_URLS;
    if ($pretty) {
        return BASE_URL . '/service/' . rawurlencode($slug);
    }

    return BASE_URL . '/service/index.php?slug=' . rawurlencode($slug);
}

/**
 * Pre-filled text for staff contacting a customer about a web booking.
 *
 * @param array<string, mixed> $b
 */
function vk_whatsapp_web_booking_message(array $b): string
{
    $name = (string) ($b['customer_name'] ?? '');
    $phone = (string) ($b['phone'] ?? '');
    $stype = str_replace('_', ' ', (string) ($b['service_type'] ?? ''));
    $lat = $b['latitude'] ?? null;
    $lng = $b['longitude'] ?? null;
    $loc = '—';
    if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng)) {
        $loc = 'https://www.google.com/maps?q=' . rawurlencode((string) $lat . ',' . (string) $lng);
    }

    return implode("\n", [
        'New Booking 🚀',
        '',
        'Customer: ' . $name,
        'Phone: ' . $phone,
        'Service: ' . $stype,
        '',
        'Location:',
        $loc,
    ]);
}

function vk_whatsapp_me_link(string $phoneRaw, string $message): string
{
    $d = preg_replace('/\D+/', '', $phoneRaw);
    if ($d === '') {
        return '#';
    }
    if (strlen($d) === 10 && str_starts_with($d, '07')) {
        $d = '94' . substr($d, 1);
    } elseif (strlen($d) === 9 && str_starts_with($d, '7')) {
        $d = '94' . $d;
    }

    return 'https://wa.me/' . $d . '?text=' . rawurlencode($message);
}

function vk_seo_default_description(): string
{
    return defined('VK_SEO_DEFAULT_DESCRIPTION')
        ? (string) VK_SEO_DEFAULT_DESCRIPTION
        : 'Professional computer, printer, CCTV, maintenance, and field repair services in Kilinochchi and across Sri Lanka — VK Network.';
}

function vk_seo_default_keywords(): string
{
    return defined('VK_SEO_DEFAULT_KEYWORDS')
        ? (string) VK_SEO_DEFAULT_KEYWORDS
        : 'computer repair, laptop service, printer repair, CCTV installation, Sri Lanka, Kilinochchi, VK Network';
}

function vk_seo_og_image_default(): string
{
    if (defined('VK_SEO_OG_IMAGE') && (string) VK_SEO_OG_IMAGE !== '') {
        $u = (string) VK_SEO_OG_IMAGE;
        if (str_starts_with($u, 'http')) {
            return $u;
        }

        return vk_public_absolute_url($u);
    }

    return vk_public_absolute_url('/assets/images/services/svc-computer.svg');
}

/**
 * Echo-safe meta block for public_header.php — uses globals set per page.
 */
function vk_public_seo_head(): void
{
    $dbBrand = vk_app_setting('site_name');
    $brand = ($dbBrand !== null && $dbBrand !== '') ? $dbBrand : 'VK Network';
    $pageLabel = isset($GLOBALS['pageTitle']) ? (string) $GLOBALS['pageTitle'] : 'Home';
    $fullTitle = $GLOBALS['seoFullTitle'] ?? null;
    if ($fullTitle === null || $fullTitle === '') {
        $seoTitle = $GLOBALS['seoTitle'] ?? null;
        $fullTitle = $seoTitle !== null && $seoTitle !== ''
            ? (string) $seoTitle
            : $brand . ' | ' . $pageLabel;
    }

    if (isset($GLOBALS['seoDescription']) && (string) $GLOBALS['seoDescription'] !== '') {
        $desc = (string) $GLOBALS['seoDescription'];
    } else {
        $dbDesc = vk_app_setting('seo_meta_description');
        $desc = ($dbDesc !== null && $dbDesc !== '') ? $dbDesc : vk_seo_default_description();
    }

    if (isset($GLOBALS['seoKeywords']) && (string) $GLOBALS['seoKeywords'] !== '') {
        $keywords = (string) $GLOBALS['seoKeywords'];
    } else {
        $dbKw = vk_app_setting('seo_meta_keywords');
        $keywords = ($dbKw !== null && $dbKw !== '') ? $dbKw : vk_seo_default_keywords();
    }

    $canonicalRel = $GLOBALS['seoCanonicalPath'] ?? ($_SERVER['REQUEST_URI'] ?? '/');
    if (($pos = strpos($canonicalRel, '?')) !== false) {
        $canonicalRel = substr($canonicalRel, 0, $pos);
    }
    $canonicalAbs = vk_public_absolute_url($canonicalRel);

    if (isset($GLOBALS['seoOgImage']) && (string) $GLOBALS['seoOgImage'] !== '') {
        $ogImage = (string) $GLOBALS['seoOgImage'];
    } else {
        $dbOg = vk_app_setting('seo_og_image');
        $ogImage = ($dbOg !== null && $dbOg !== '') ? $dbOg : vk_seo_og_image_default();
    }
    if ($ogImage !== '' && !str_starts_with($ogImage, 'http')) {
        $ogImage = vk_public_absolute_url($ogImage);
    }

    $twitterCard = $GLOBALS['seoTwitterCard'] ?? 'summary_large_image';

    echo '<meta name="description" content="' . e((string) $desc) . '">' . "\n";
    echo '<meta name="keywords" content="' . e((string) $keywords) . '">' . "\n";
    echo '<link rel="canonical" href="' . e($canonicalAbs) . '">' . "\n";
    echo '<meta property="og:title" content="' . e($fullTitle) . '">' . "\n";
    echo '<meta property="og:description" content="' . e((string) $desc) . '">' . "\n";
    echo '<meta property="og:url" content="' . e($canonicalAbs) . '">' . "\n";
    echo '<meta property="og:image" content="' . e($ogImage) . '">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:site_name" content="' . e($brand) . '">' . "\n";
    echo '<meta name="twitter:card" content="' . e((string) $twitterCard) . '">' . "\n";
    echo '<meta name="twitter:title" content="' . e($fullTitle) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . e((string) $desc) . '">' . "\n";
}

function vk_json_ld_local_business(): string
{
    $nameDb = vk_app_setting('site_name');
    $name = ($nameDb !== null && $nameDb !== '') ? $nameDb : (defined('VK_BUSINESS_NAME') ? (string) VK_BUSINESS_NAME : 'VK Network');
    $phone = defined('VK_BUSINESS_PHONE_E164') ? (string) VK_BUSINESS_PHONE_E164 : '+94778870135';
    $street = defined('VK_BUSINESS_STREET') ? (string) VK_BUSINESS_STREET : '26/3 Thiruvaiyaru';
    $city = defined('VK_BUSINESS_CITY') ? (string) VK_BUSINESS_CITY : 'Kilinochchi';
    $region = defined('VK_BUSINESS_REGION') ? (string) VK_BUSINESS_REGION : 'Northern Province';
    $country = defined('VK_BUSINESS_COUNTRY') ? (string) VK_BUSINESS_COUNTRY : 'LK';

    $ldDesc = vk_app_setting('seo_meta_description');
    $ldDesc = ($ldDesc !== null && $ldDesc !== '') ? $ldDesc : vk_seo_default_description();

    $services = [
        'Computer repair',
        'Laptop service',
        'Printer repair',
        'CCTV installation',
        'Maintenance',
    ];
    try {
        if (function_exists('db')) {
            $pdo = db();
            if (db_table_exists($pdo, 'web_services')) {
                $svcRows = $pdo->query('SELECT name FROM web_services WHERE active = 1 ORDER BY sort_order ASC, id ASC LIMIT 10')->fetchAll();
                $names = [];
                foreach ($svcRows as $r) {
                    $n = trim((string) ($r['name'] ?? ''));
                    if ($n !== '') {
                        $names[] = $n;
                    }
                }
                if ($names) {
                    $services = $names;
                }
            }
        }
    } catch (Throwable $e) {
        // keep fallback service list
    }

    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $name,
        'url' => vk_public_absolute_url('/index.php'),
        'telephone' => $phone,
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $street,
            'addressLocality' => $city,
            'addressRegion' => $region,
            'addressCountry' => $country,
        ],
        'areaServed' => [
            ['@type' => 'City', 'name' => 'Jaffna'],
            ['@type' => 'City', 'name' => 'Kilinochchi'],
            ['@type' => 'City', 'name' => 'Mullaitivu'],
            ['@type' => 'City', 'name' => 'Vavuniya'],
        ],
        'openingHoursSpecification' => [
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], 'opens' => '08:30', 'closes' => '18:00'],
        ],
        'serviceType' => $services,
        'description' => $ldDesc,
    ];

    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

/** @return array<int, string> */
function vk_northern_locations(): array
{
    return ['Jaffna', 'Kilinochchi', 'Mullaitivu', 'Vavuniya'];
}

/** Build rotating local keyword set for titles/meta/H1. */
function vk_local_keyword_pack(string $baseService): array
{
    $service = trim($baseService) !== '' ? trim($baseService) : 'IT repair';
    $locs = vk_northern_locations();
    return [
        $service . ' Jaffna',
        $service . ' Kilinochchi',
        $service . ' Vavuniya',
        str_replace('service', 'repair', $service) . ' Mullaitivu',
    ];
}

function vk_local_meta_description(string $service): string
{
    $k = vk_local_keyword_pack($service);
    return 'Trusted ' . strtolower($service) . ' across Northern Sri Lanka. '
        . $k[0] . ', ' . $k[1] . ', ' . $k[2] . ', and ' . $k[3]
        . '. Fast booking, transparent pricing, and local field support.';
}

function vk_geo_meta_tags(): string
{
    return '<meta name="geo.region" content="LK-NP">' . "\n"
        . '<meta name="geo.placename" content="Jaffna">' . "\n";
}

function vk_social_share_links(string $url, string $title, string $message = ''): array
{
    $u = rawurlencode($url);
    $t = rawurlencode($title);
    $m = rawurlencode($message !== '' ? $message : $title . ' ' . $url);
    return [
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $u,
        'whatsapp' => 'https://wa.me/?text=' . $m,
        'telegram' => 'https://t.me/share/url?url=' . $u . '&text=' . $t,
    ];
}

function vk_plausible_script(): string
{
    $domain = vk_app_setting('analytics_domain');
    if ($domain === null || $domain === '') {
        return '';
    }
    $src = vk_app_setting('analytics_script_src', 'https://plausible.io/js/script.js');
    return '<script defer data-domain="' . e($domain) . '" src="' . e((string) $src) . '"></script>';
}
