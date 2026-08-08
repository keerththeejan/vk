<?php
declare(strict_types=1);

/**
 * Enterprise cache layer — file-backed by default, Redis-ready via VK_CACHE_DRIVER.
 *
 * Env:
 *   VK_CACHE_DRIVER=file|redis (default file)
 *   VK_CACHE_PREFIX=vk_
 *   VK_REDIS_HOST, VK_REDIS_PORT, VK_REDIS_PASS
 */

function vk_cache_driver(): string
{
    static $driver = null;
    if ($driver !== null) {
        return $driver;
    }
    $raw = strtolower(trim((string) (getenv('VK_CACHE_DRIVER') ?: 'file')));
    $driver = $raw === 'redis' ? 'redis' : 'file';

    return $driver;
}

function vk_cache_prefix(): string
{
    return trim((string) (getenv('VK_CACHE_PREFIX') ?: 'vk_'));
}

function vk_cache_dir(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    $dir = ROOT_PATH . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

function vk_cache_file_path(string $key): string
{
    return vk_cache_dir() . DIRECTORY_SEPARATOR . hash('sha256', vk_cache_prefix() . $key) . '.cache';
}

/** @return mixed|null */
function vk_cache_get(string $key): mixed
{
    if (vk_cache_driver() === 'redis') {
        return vk_cache_redis_get($key);
    }

    $path = vk_cache_file_path($key);
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $payload = @unserialize($raw, ['allowed_classes' => false]);
    if (!is_array($payload) || !isset($payload['expires'], $payload['value'])) {
        @unlink($path);

        return null;
    }
    if ((int) $payload['expires'] > 0 && time() > (int) $payload['expires']) {
        @unlink($path);

        return null;
    }

    return $payload['value'];
}

function vk_cache_set(string $key, mixed $value, int $ttl = 300): bool
{
    if (vk_cache_driver() === 'redis') {
        return vk_cache_redis_set($key, $value, $ttl);
    }

    $path = vk_cache_file_path($key);
    $payload = serialize([
        'expires' => $ttl > 0 ? time() + $ttl : 0,
        'value' => $value,
    ]);

    return file_put_contents($path, $payload, LOCK_EX) !== false;
}

function vk_cache_delete(string $key): void
{
    if (vk_cache_driver() === 'redis') {
        vk_cache_redis_delete($key);

        return;
    }
    $path = vk_cache_file_path($key);
    if (is_file($path)) {
        @unlink($path);
    }
}

function vk_cache_remember(string $key, int $ttl, callable $resolver): mixed
{
    $cached = vk_cache_get($key);
    if ($cached !== null) {
        return $cached;
    }
    $value = $resolver();
    vk_cache_set($key, $value, $ttl);

    return $value;
}

/** Invalidate keys by prefix (file driver scans cache dir). */
function vk_cache_flush_prefix(string $prefix): void
{
    if (vk_cache_driver() === 'redis') {
        vk_cache_redis_flush_prefix($prefix);

        return;
    }
    $dir = vk_cache_dir();
    if (!is_dir($dir)) {
        return;
    }
    $needle = hash('sha256', vk_cache_prefix() . $prefix);
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.cache') ?: [] as $file) {
        if (str_starts_with(basename($file, '.cache'), substr($needle, 0, 8))) {
            @unlink($file);
        }
    }
}

function vk_cache_flush_dashboard(): void
{
    vk_cache_delete('dashboard_stats_v2');
    vk_cache_delete('dashboard_stats_v3');
    vk_cache_delete('customers_list_kpis_v1');
    vk_cache_delete('smtp_warning_v1');
    vk_cache_delete('pending_auth_regs_v1');
}

/** Invalidate common domain caches after writes (safe no-op if keys missing). */
function vk_cache_invalidate_after_write(string $domain = 'all'): void
{
    $domain = strtolower(trim($domain));
    if ($domain === 'all' || $domain === 'dashboard' || $domain === 'stats') {
        vk_cache_flush_dashboard();
    }
    if ($domain === 'all' || $domain === 'settings') {
        vk_cache_delete('settings_all_v1');
        if (function_exists('vk_settings_invalidate_cache')) {
            // Avoid recursion: only clear request cache when called from elsewhere.
            $GLOBALS['_vk_settings_cache'] = null;
        }
    }
    if ($domain === 'all' || $domain === 'auth' || $domain === 'users') {
        vk_cache_delete('pending_auth_regs_v1');
        vk_cache_delete('auth_schema_ready_v3');
    }
    if ($domain === 'all' || $domain === 'schema') {
        vk_cache_delete('schema_tables_v1');
        vk_cache_delete('schema_columns_v1');
        if (function_exists('db_table_exists_forget')) {
            db_table_exists_forget();
        }
    }
    if ($domain === 'all' || $domain === 'marketing') {
        vk_cache_delete('marketing_seeded_v1');
        vk_cache_flush_dashboard();
    }
    if ($domain === 'all' || $domain === 'public' || $domain === 'menus') {
        vk_cache_delete('public_nav_menus_v1');
    }
    if ($domain === 'all' || $domain === 'public' || $domain === 'services') {
        vk_cache_delete('public_home_services_v1');
    }
    if ($domain === 'all' || $domain === 'public' || $domain === 'staff') {
        // Known public list limits used by homepage / staff pages.
        foreach ([8, 12, 24, 50, 100, 500] as $limit) {
            vk_cache_delete('public_staff_list_v1_' . $limit);
        }
    }
}

/**
 * Remove expired file-cache entries and old temp artifacts. Runs at most once per hour.
 */
function vk_cache_cleanup_expired(): int
{
    $removed = 0;
    $dir = vk_cache_dir();
    if (!is_dir($dir)) {
        return 0;
    }
    $now = time();
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.cache') ?: [] as $file) {
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            @unlink($file);
            $removed++;
            continue;
        }
        $payload = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($payload) || !isset($payload['expires'])) {
            @unlink($file);
            $removed++;
            continue;
        }
        $expires = (int) $payload['expires'];
        if ($expires > 0 && $now > $expires) {
            @unlink($file);
            $removed++;
        }
    }

    return $removed;
}

/**
 * Lightweight housekeeping: expired cache, old logs, temp PDFs — never touches uploads/user data.
 */
function vk_maintenance_cleanup(): void
{
    if (vk_cache_get('maintenance_cleanup_lock') === '1') {
        return;
    }
    vk_cache_set('maintenance_cleanup_lock', '1', 3600);

    try {
        vk_cache_cleanup_expired();
    } catch (Throwable) {
    }

    $roots = [
        ROOT_PATH . DIRECTORY_SEPARATOR . 'tmp',
        ROOT_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp',
        ROOT_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'exports',
        ROOT_PATH . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'pdf',
    ];
    $cutoff = time() - 86400 * 7;
    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $fileInfo) {
                /** @var SplFileInfo $fileInfo */
                if (!$fileInfo->isFile()) {
                    continue;
                }
                $mtime = (int) $fileInfo->getMTime();
                if ($mtime > 0 && $mtime < $cutoff) {
                    @unlink($fileInfo->getPathname());
                }
            }
        } catch (Throwable) {
        }
    }

    // Trim oversized PHP error log if present (keep last ~1MB).
    $log = ROOT_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'php-error.log';
    if (is_file($log) && filesize($log) > 2_000_000) {
        $tail = @file_get_contents($log, false, null, -1_000_000);
        if (is_string($tail) && $tail !== '') {
            @file_put_contents($log, $tail, LOCK_EX);
        }
    }
}

/** @return mixed|null */
function vk_cache_redis_get(string $key): mixed
{
    if (!class_exists('Redis')) {
        return null;
    }
    try {
        $redis = vk_cache_redis_connection();
        if (!$redis instanceof Redis) {
            return null;
        }
        $raw = $redis->get(vk_cache_prefix() . $key);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    } catch (Throwable) {
        return null;
    }
}

function vk_cache_redis_set(string $key, mixed $value, int $ttl): bool
{
    if (!class_exists('Redis')) {
        return false;
    }
    try {
        $redis = vk_cache_redis_connection();
        if (!$redis instanceof Redis) {
            return false;
        }
        $payload = json_encode($value, JSON_THROW_ON_ERROR);
        if ($ttl > 0) {
            return (bool) $redis->setex(vk_cache_prefix() . $key, $ttl, $payload);
        }

        return (bool) $redis->set(vk_cache_prefix() . $key, $payload);
    } catch (Throwable) {
        return false;
    }
}

function vk_cache_redis_delete(string $key): void
{
    if (!class_exists('Redis')) {
        return;
    }
    try {
        $redis = vk_cache_redis_connection();
        if ($redis instanceof Redis) {
            $redis->del(vk_cache_prefix() . $key);
        }
    } catch (Throwable) {
    }
}

function vk_cache_redis_flush_prefix(string $prefix): void
{
    if (!class_exists('Redis')) {
        return;
    }
    try {
        $redis = vk_cache_redis_connection();
        if (!$redis instanceof Redis) {
            return;
        }
        $pattern = vk_cache_prefix() . $prefix . '*';
        $it = null;
        while ($keys = $redis->scan($it, $pattern, 100)) {
            if ($keys !== false && $keys !== []) {
                $redis->del(...$keys);
            }
        }
    } catch (Throwable) {
    }
}

function vk_cache_redis_connection(): ?Redis
{
    static $conn = null;
    if ($conn instanceof Redis) {
        return $conn;
    }
    if (!class_exists('Redis')) {
        return null;
    }
    $host = getenv('VK_REDIS_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('VK_REDIS_PORT') ?: 6379);
    $pass = getenv('VK_REDIS_PASS');
    $redis = new Redis();
    if (!$redis->connect($host, $port, 1.5)) {
        return null;
    }
    if (is_string($pass) && $pass !== '' && !$redis->auth($pass)) {
        return null;
    }
    $conn = $redis;

    return $conn;
}
