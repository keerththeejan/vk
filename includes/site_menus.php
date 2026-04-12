<?php
declare(strict_types=1);

function vk_site_menus_table_exists(PDO $pdo): bool
{
    return db_table_exists($pdo, 'menus');
}

function vk_site_menus_ensure_schema(PDO $pdo): void
{
    if (vk_site_menus_table_exists($pdo)) {
        vk_site_menus_seed_defaults($pdo);
        return;
    }
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS menus (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(100) NOT NULL,
              slug VARCHAR(100) NOT NULL,
              url VARCHAR(255) NOT NULL,
              icon VARCHAR(100) DEFAULT NULL,
              sort_order INT NOT NULL DEFAULT 0,
              status ENUM('active','inactive') NOT NULL DEFAULT 'active',
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_menus_slug (slug),
              INDEX idx_menus_status_sort (status, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        vk_site_menus_seed_defaults($pdo);
    } catch (Throwable $e) {
        error_log('vk_site_menus_ensure_schema: ' . $e->getMessage());
    }
}

/**
 * @return list<array{name:string,slug:string,url:string,icon:?string,sort_order:int}>
 */
function vk_site_menus_default_rows(): array
{
    return [
        ['name' => 'Home', 'slug' => 'home', 'url' => 'index.php', 'icon' => 'lucide:home', 'sort_order' => 10],
        ['name' => 'Book Service', 'slug' => 'book', 'url' => 'book.php', 'icon' => 'lucide:calendar-plus', 'sort_order' => 20],
        ['name' => 'Vehicle Booking', 'slug' => 'vehicle', 'url' => 'vehicle/index.php', 'icon' => 'lucide:car-front', 'sort_order' => 30],
        ['name' => 'Track Status', 'slug' => 'track', 'url' => 'track.php', 'icon' => 'lucide:search', 'sort_order' => 40],
        ['name' => 'Our Work', 'slug' => 'portfolio', 'url' => 'portfolio.php', 'icon' => 'lucide:images', 'sort_order' => 50],
    ];
}

function vk_site_menus_seed_defaults(PDO $pdo): void
{
    if (!vk_site_menus_table_exists($pdo)) {
        return;
    }
    try {
        $n = (int) $pdo->query('SELECT COUNT(*) FROM menus')->fetchColumn();
        if ($n > 0) {
            return;
        }
        $st = $pdo->prepare(
            'INSERT INTO menus (name, slug, url, icon, sort_order, status) VALUES (?,?,?,?,?,?)'
        );
        foreach (vk_site_menus_default_rows() as $r) {
            $st->execute([
                $r['name'],
                $r['slug'],
                $r['url'],
                $r['icon'],
                $r['sort_order'],
                'active',
            ]);
        }
    } catch (Throwable $e) {
        error_log('vk_site_menus_seed_defaults: ' . $e->getMessage());
    }
}

/** Allow only internal relative paths (no scheme, no //). */
function vk_site_menus_sanitize_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return 'index.php';
    }
    if (preg_match('#^(javascript|data|vbscript):#i', $url)) {
        return 'index.php';
    }
    if (str_contains($url, "\n") || str_contains($url, "\r")) {
        return 'index.php';
    }
    if (preg_match('#^https?://#i', $url)) {
        $path = parse_url($url, PHP_URL_PATH);
        $q = parse_url($url, PHP_URL_QUERY);
        if (!is_string($path) || $path === '') {
            return 'index.php';
        }
        $path = '/' . ltrim($path, '/');
        $base = '/' . trim(BASE_URL, '/') . '/';
        if (!str_starts_with(strtolower($path), strtolower($base))) {
            return 'index.php';
        }
        $rest = substr($path, strlen($base) - 1);
        $rest = ltrim($rest, '/');
        return $q !== null && $q !== '' ? $rest . '?' . $q : $rest;
    }
    $url = ltrim($url, '/');
    if (preg_match('#^//#', $url)) {
        return 'index.php';
    }
    if (!preg_match('#^[a-zA-Z0-9._/?=&\-]+$#', $url)) {
        return 'index.php';
    }

    return $url;
}

function vk_site_menus_href(string $storedUrl): string
{
    $u = vk_site_menus_sanitize_url($storedUrl);
    if (str_starts_with($u, '/')) {
        return $u;
    }

    return BASE_URL . '/' . $u;
}

/**
 * @return list<array{id:int,name:string,slug:string,url:string,icon:?string,sort_order:int}>
 */
function vk_site_menus_for_public_nav(PDO $pdo): array
{
    vk_site_menus_ensure_schema($pdo);
    if (!vk_site_menus_table_exists($pdo)) {
        $out = [];
        foreach (vk_site_menus_default_rows() as $i => $r) {
            $out[] = [
                'id' => -1 - $i,
                'name' => $r['name'],
                'slug' => $r['slug'],
                'url' => $r['url'],
                'icon' => $r['icon'],
                'sort_order' => $r['sort_order'],
            ];
        }

        return $out;
    }
    try {
        $rows = $pdo->query(
            "SELECT id, name, slug, url, icon, sort_order FROM menus WHERE status = 'active' ORDER BY sort_order ASC, id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            return array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'slug' => (string) ($row['slug'] ?? ''),
                    'url' => (string) ($row['url'] ?? ''),
                    'icon' => isset($row['icon']) ? (string) $row['icon'] : null,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ];
            }, $rows);
        }
    } catch (Throwable $e) {
        error_log('vk_site_menus_for_public_nav: ' . $e->getMessage());
    }

    return vk_site_menus_for_public_nav_fallback();
}

/**
 * @return list<array{id:int,name:string,slug:string,url:string,icon:?string,sort_order:int}>
 */
function vk_site_menus_for_public_nav_fallback(): array
{
    $out = [];
    foreach (vk_site_menus_default_rows() as $i => $r) {
        $out[] = [
            'id' => -1 - $i,
            'name' => $r['name'],
            'slug' => $r['slug'],
            'url' => $r['url'],
            'icon' => $r['icon'],
            'sort_order' => $r['sort_order'],
        ];
    }

    return $out;
}

function vk_site_menus_icon_html(?string $icon): string
{
    $icon = trim((string) $icon);
    if ($icon === '') {
        return '';
    }
    if (preg_match('/^bi[\s\-]/i', $icon) || str_starts_with($icon, 'bi-')) {
        $cls = str_contains($icon, 'bi ') ? $icon : ('bi ' . $icon);

        return '<span class="vk-lucide-nav me-2 d-inline-flex align-items-center" aria-hidden="true"><i class="' . e($cls) . '"></i></span>';
    }
    if (str_starts_with($icon, 'lucide:')) {
        $name = substr($icon, 7);

        return '<span class="vk-lucide-nav me-2 d-inline-flex align-items-center" aria-hidden="true"><i data-lucide="' . e($name) . '"></i></span>';
    }

    return '<span class="vk-lucide-nav me-2 d-inline-flex align-items-center" aria-hidden="true"><i data-lucide="' . e($icon) . '"></i></span>';
}
