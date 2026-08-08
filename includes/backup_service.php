<?php
declare(strict_types=1);

/**
 * Enterprise database / files Backup & Restore service.
 */

function vk_backup_dir(): string
{
    $dir = ROOT_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ht = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\nDeny from all\n");
    }
    $idx = $dir . DIRECTORY_SEPARATOR . 'index.html';
    if (!is_file($idx)) {
        @file_put_contents($idx, '');
    }

    return $dir;
}

function vk_backup_manifest_path(): string
{
    return vk_backup_dir() . DIRECTORY_SEPARATOR . 'manifest.json';
}

function vk_backup_log_path(): string
{
    return vk_backup_dir() . DIRECTORY_SEPARATOR . 'backup_ops.log.jsonl';
}

/** @return list<array<string,mixed>> */
function vk_backup_manifest_load(): array
{
    $path = vk_backup_manifest_path();
    if (!is_file($path)) {
        return [];
    }
    $raw = (string) file_get_contents($path);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }

    return array_values(array_filter($data, 'is_array'));
}

/** @param list<array<string,mixed>> $items */
function vk_backup_manifest_save(array $items): void
{
    file_put_contents(
        vk_backup_manifest_path(),
        json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function vk_backup_log(string $action, string $status, string $detail = '', ?string $backupId = null): void
{
    $entry = [
        'at' => date('c'),
        'action' => $action,
        'status' => $status,
        'detail' => mb_substr($detail, 0, 500),
        'backup_id' => $backupId,
        'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'user' => (string) ($_SESSION['username'] ?? $_SESSION['user_name'] ?? 'system'),
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];
    @file_put_contents(vk_backup_log_path(), json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

    try {
        if (function_exists('vk_settings_audit') && function_exists('db')) {
            vk_settings_audit(db(), 'backup_' . $action, $backupId ?? '', $status . ' ' . $detail);
        }
    } catch (Throwable $e) {
        // ignore audit failures
    }
}

/** @return list<array<string,mixed>> */
function vk_backup_logs(int $limit = 50): array
{
    $path = vk_backup_log_path();
    if (!is_file($path)) {
        return [];
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_slice($lines, -$limit);
    $out = [];
    foreach (array_reverse($lines) as $line) {
        $row = json_decode((string) $line, true);
        if (is_array($row)) {
            $out[] = $row;
        }
    }

    return $out;
}

function vk_backup_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $units = ['KB', 'MB', 'GB', 'TB'];
    $i = (int) floor(log(max(1, $bytes), 1024)) - 1;
    $i = max(0, min($i, count($units) - 1));

    return number_format($bytes / (1024 ** ($i + 1)), 2) . ' ' . $units[$i];
}

function vk_backup_safe_id(string $id): string
{
    return preg_replace('/[^a-zA-Z0-9_\-]/', '', $id) ?? '';
}

/** @return array<string,mixed>|null */
function vk_backup_find(string $id): ?array
{
    $id = vk_backup_safe_id($id);
    foreach (vk_backup_manifest_load() as $item) {
        if ((string) ($item['id'] ?? '') === $id) {
            return $item;
        }
    }

    return null;
}

function vk_backup_absolute_path(array $item): ?string
{
    $file = basename((string) ($item['filename'] ?? ''));
    if ($file === '' || str_contains($file, '..')) {
        return null;
    }
    $path = vk_backup_dir() . DIRECTORY_SEPARATOR . $file;

    return is_file($path) ? $path : null;
}

/** @return array{enabled:bool,frequency:string,time:string,retention:int,last_run:?string,components:list<string>} */
function vk_backup_schedule_get(PDO $pdo): array
{
    if (function_exists('vk_settings_get')) {
        $enabled = vk_settings_get($pdo, 'backup_auto_enabled', '0') === '1';
        $freq = (string) vk_settings_get($pdo, 'backup_auto_frequency', 'daily');
        $time = (string) vk_settings_get($pdo, 'backup_auto_time', '02:00');
        $retention = (int) vk_settings_get($pdo, 'backup_retention', '10');
        $last = vk_settings_get($pdo, 'backup_auto_last_run', null);
        $compRaw = (string) vk_settings_get($pdo, 'backup_auto_components', 'database,uploads,config');
    } else {
        $enabled = false;
        $freq = 'daily';
        $time = '02:00';
        $retention = 10;
        $last = null;
        $compRaw = 'database,uploads,config';
    }
    if (!in_array($freq, ['daily', 'weekly', 'monthly'], true)) {
        $freq = 'daily';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        $time = '02:00';
    }
    if (!in_array($retention, [5, 10, 20, 50, 100], true)) {
        $retention = 10;
    }
    $components = array_values(array_filter(array_map('trim', explode(',', $compRaw))));

    return [
        'enabled' => $enabled,
        'frequency' => $freq,
        'time' => $time,
        'retention' => $retention,
        'last_run' => $last,
        'components' => $components ?: ['database'],
    ];
}

/** @param array<string,mixed> $data */
function vk_backup_schedule_save(PDO $pdo, array $data): void
{
    if (!function_exists('vk_settings_set')) {
        return;
    }
    $freq = (string) ($data['frequency'] ?? 'daily');
    if (!in_array($freq, ['daily', 'weekly', 'monthly'], true)) {
        $freq = 'daily';
    }
    $time = (string) ($data['time'] ?? '02:00');
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        $time = '02:00';
    }
    $retention = (int) ($data['retention'] ?? 10);
    if (!in_array($retention, [5, 10, 20, 50, 100], true)) {
        $retention = 10;
    }
    $components = $data['components'] ?? ['database'];
    if (!is_array($components)) {
        $components = ['database'];
    }
    $allowed = vk_backup_component_keys();
    $components = array_values(array_intersect($components, $allowed));
    if ($components === []) {
        $components = ['database'];
    }

    vk_settings_set($pdo, 'backup_auto_enabled', !empty($data['enabled']) ? '1' : '0');
    vk_settings_set($pdo, 'backup_auto_frequency', $freq);
    vk_settings_set($pdo, 'backup_auto_time', $time);
    vk_settings_set($pdo, 'backup_retention', (string) $retention);
    vk_settings_set($pdo, 'backup_auto_components', implode(',', $components));
}

/** @return list<string> */
function vk_backup_component_keys(): array
{
    return ['database', 'uploads', 'documents', 'images', 'config', 'logs', 'cache', 'system'];
}

/** @return array<string,string> */
function vk_backup_component_paths(): array
{
    return [
        'uploads' => ROOT_PATH . '/uploads',
        'documents' => ROOT_PATH . '/uploads',
        'images' => ROOT_PATH . '/assets/images',
        'config' => ROOT_PATH . '/config',
        'logs' => defined('ROOT_PATH') ? ROOT_PATH . '/storage/logs' : ROOT_PATH . '/storage/logs',
        'cache' => ROOT_PATH . '/cache',
        'system' => ROOT_PATH,
    ];
}

function vk_backup_find_mysqldump(): ?string
{
    $candidates = [];
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        foreach (glob('C:/wamp64/bin/mysql/mysql*/bin/mysqldump.exe') ?: [] as $p) {
            $candidates[] = $p;
        }
        $candidates[] = 'mysqldump.exe';
    } else {
        $candidates[] = '/usr/bin/mysqldump';
        $candidates[] = '/usr/local/bin/mysqldump';
        $candidates[] = 'mysqldump';
    }
    foreach ($candidates as $bin) {
        if ($bin !== 'mysqldump' && $bin !== 'mysqldump.exe' && !is_file($bin)) {
            continue;
        }
        return $bin;
    }

    return null;
}

function vk_backup_db_credentials(): array
{
    $isProduction = defined('APP_ENV') && APP_ENV === 'production';

    return [
        'host' => getenv('VK_DB_HOST') ?: ($isProduction ? 'localhost' : '127.0.0.1'),
        'name' => getenv('VK_DB_NAME') ?: ($isProduction ? '' : 'vk_billing'),
        'user' => getenv('VK_DB_USER') ?: ($isProduction ? '' : 'root'),
        'pass' => getenv('VK_DB_PASS') ?: ($isProduction ? '' : '1234'),
    ];
}

function vk_backup_dump_database_php(PDO $pdo, string $outFile): void
{
    $fh = fopen($outFile, 'wb');
    if ($fh === false) {
        throw new RuntimeException('Cannot write SQL dump file.');
    }
    $cred = vk_backup_db_credentials();
    $dbName = (string) $cred['name'];
    fwrite($fh, "-- VK Network SQL Backup\n");
    fwrite($fh, '-- Generated: ' . date('c') . "\n");
    fwrite($fh, '-- Database: ' . $dbName . "\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

    $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as $trow) {
        $table = (string) $trow[0];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            continue;
        }
        fwrite($fh, "\n-- Table `{$table}`\nDROP TABLE IF EXISTS `{$table}`;\n");
        $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_NUM);
        if ($create && isset($create[1])) {
            fwrite($fh, $create[1] . ";\n\n");
        }

        $st = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`', PDO::FETCH_ASSOC);
        if (!$st) {
            continue;
        }
        $batch = [];
        $cols = null;
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            if ($cols === null) {
                $cols = array_keys($row);
            }
            $vals = [];
            foreach ($row as $v) {
                if ($v === null) {
                    $vals[] = 'NULL';
                } else {
                    $vals[] = $pdo->quote((string) $v);
                }
            }
            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= 100) {
                $colList = '`' . implode('`,`', array_map(static fn ($c) => str_replace('`', '``', $c), $cols)) . '`';
                fwrite($fh, 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . $colList . ') VALUES ' . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch && $cols) {
            $colList = '`' . implode('`,`', array_map(static fn ($c) => str_replace('`', '``', $c), $cols)) . '`';
            fwrite($fh, 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . $colList . ') VALUES ' . implode(",\n", $batch) . ";\n");
        }
    }
    fwrite($fh, "\nSET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
}

function vk_backup_dump_database(PDO $pdo, string $outFile): void
{
    $cred = vk_backup_db_credentials();
    $bin = vk_backup_find_mysqldump();
    if ($bin !== null && is_file($bin)) {
        $args = [
            escapeshellarg($bin),
            '-h' . escapeshellarg((string) $cred['host']),
            '-u' . escapeshellarg((string) $cred['user']),
        ];
        if ((string) $cred['pass'] !== '') {
            // No space after -p (mysqldump requirement)
            $args[] = '-p' . escapeshellarg((string) $cred['pass']);
        }
        $args[] = '--single-transaction';
        $args[] = '--routines';
        $args[] = '--triggers';
        $args[] = '--default-character-set=utf8mb4';
        $args[] = '--result-file=' . escapeshellarg($outFile);
        $args[] = escapeshellarg((string) $cred['name']);
        $cmd = implode(' ', $args);
        $output = [];
        $code = 0;
        @exec($cmd . ' 2>&1', $output, $code);
        if ($code === 0 && is_file($outFile) && filesize($outFile) > 50) {
            return;
        }
        @unlink($outFile);
    }

    vk_backup_dump_database_php($pdo, $outFile);
}

function vk_backup_gzip_file(string $src, string $dest): void
{
    $in = fopen($src, 'rb');
    $out = gzopen($dest, 'wb9');
    if ($in === false || $out === false) {
        throw new RuntimeException('GZIP compression failed.');
    }
    while (!feof($in)) {
        $chunk = fread($in, 1024 * 1024);
        if ($chunk === false) {
            break;
        }
        gzwrite($out, $chunk);
    }
    fclose($in);
    gzclose($out);
}

/**
 * @param list<string> $components
 * @return array<string,mixed>
 */
function vk_backup_create(PDO $pdo, string $type, array $components, array $options = []): array
{
    $allowedTypes = ['full', 'database', 'files', 'system', 'custom'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'custom';
    }
    $allowed = vk_backup_component_keys();
    $components = array_values(array_intersect($components, $allowed));
    if ($type === 'database') {
        $components = ['database'];
    } elseif ($type === 'files') {
        $components = array_values(array_diff($components ?: ['uploads', 'images', 'config'], ['database']));
        if ($components === []) {
            $components = ['uploads', 'config'];
        }
    } elseif ($type === 'full' || $type === 'system') {
        $components = ['database', 'uploads', 'images', 'config', 'cache', 'logs'];
        if ($type === 'system') {
            $components[] = 'system';
        }
    }
    if ($components === []) {
        $components = ['database'];
    }

    $compress = !empty($options['compress']);
    $encrypt = !empty($options['encrypt']);
    $password = (string) ($options['password'] ?? '');
    if ($encrypt && $password === '') {
        throw new InvalidArgumentException('Encryption password is required.');
    }

    $id = 'bk_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    $stamp = date('Ymd_His');
    $tmpDir = vk_backup_dir() . DIRECTORY_SEPARATOR . 'tmp_' . $id;
    if (!@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
        throw new RuntimeException('Cannot create temporary backup folder.');
    }

    $meta = [
        'id' => $id,
        'name' => (string) ($options['name'] ?? ('VK Backup ' . date('Y-m-d H:i'))),
        'type' => $type,
        'components' => $components,
        'created_at' => date('c'),
        'created_by' => (string) ($_SESSION['username'] ?? $_SESSION['user_name'] ?? 'admin'),
        'created_by_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'status' => 'processing',
        'encrypted' => $encrypt,
        'php_version' => PHP_VERSION,
        'mysql_version' => (string) $pdo->query('SELECT VERSION()')->fetchColumn(),
        'db_name' => (string) (vk_backup_db_credentials()['name'] ?? ''),
        'checksum' => '',
        'size' => 0,
        'filename' => '',
        'location' => 'storage/backups',
    ];

    try {
        $filesToZip = [];
        if (in_array('database', $components, true)) {
            $sqlFile = $tmpDir . DIRECTORY_SEPARATOR . 'database.sql';
            vk_backup_dump_database($pdo, $sqlFile);
            if (!empty($options['gzip_sql']) && !$compress) {
                $gz = $tmpDir . DIRECTORY_SEPARATOR . 'database.sql.gz';
                vk_backup_gzip_file($sqlFile, $gz);
                @unlink($sqlFile);
                $filesToZip[] = $gz;
            } else {
                $filesToZip[] = $sqlFile;
            }
        }

        $pathMap = vk_backup_component_paths();
        foreach ($components as $comp) {
            if ($comp === 'database') {
                continue;
            }
            $src = $pathMap[$comp] ?? null;
            if ($src === null || !is_dir($src)) {
                continue;
            }
            // Avoid recursively packing the backups folder when system is selected
            if ($comp === 'system') {
                $exclude = [vk_backup_dir(), $tmpDir, ROOT_PATH . '/storage/backups'];
                vk_backup_copy_tree($src, $tmpDir . DIRECTORY_SEPARATOR . 'system', $exclude);
            } else {
                vk_backup_copy_tree($src, $tmpDir . DIRECTORY_SEPARATOR . $comp, [vk_backup_dir(), $tmpDir]);
            }
        }

        file_put_contents(
            $tmpDir . DIRECTORY_SEPARATOR . 'manifest.json',
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $finalName = 'vk_backup_' . $stamp . '_' . $type;
        if ($compress || count($components) > 1 || in_array('uploads', $components, true) || in_array('system', $components, true)) {
            $finalName .= '.zip';
            $finalPath = vk_backup_dir() . DIRECTORY_SEPARATOR . $finalName;
            vk_backup_zip_directory($tmpDir, $finalPath, $encrypt ? $password : null);
        } elseif (in_array('database', $components, true)) {
            $sqlSrc = is_file($tmpDir . '/database.sql.gz') ? $tmpDir . '/database.sql.gz' : $tmpDir . '/database.sql';
            $finalName .= is_file($tmpDir . '/database.sql.gz') ? '.sql.gz' : '.sql';
            $finalPath = vk_backup_dir() . DIRECTORY_SEPARATOR . $finalName;
            if (!@rename($sqlSrc, $finalPath)) {
                if (!@copy($sqlSrc, $finalPath)) {
                    throw new RuntimeException('Failed to finalize SQL backup.');
                }
            }
            if ($encrypt) {
                $encPath = $finalPath . '.enc';
                vk_backup_encrypt_file($finalPath, $encPath, $password);
                @unlink($finalPath);
                $finalPath = $encPath;
                $finalName .= '.enc';
            }
        } else {
            $finalName .= '.zip';
            $finalPath = vk_backup_dir() . DIRECTORY_SEPARATOR . $finalName;
            vk_backup_zip_directory($tmpDir, $finalPath, $encrypt ? $password : null);
        }

        $meta['filename'] = basename($finalPath);
        $meta['size'] = (int) filesize($finalPath);
        $meta['checksum'] = hash_file('sha256', $finalPath) ?: '';
        $meta['status'] = 'completed';

        $manifest = vk_backup_manifest_load();
        array_unshift($manifest, $meta);
        vk_backup_manifest_save($manifest);
        vk_backup_apply_retention($pdo);
        vk_backup_log('created', 'success', $meta['filename'], $id);
        vk_backup_rrmdir($tmpDir);

        return $meta;
    } catch (Throwable $e) {
        vk_backup_rrmdir($tmpDir);
        vk_backup_log('created', 'failed', $e->getMessage(), $id);
        throw $e;
    }
}

/** @param list<string> $excludeDirs */
function vk_backup_copy_tree(string $src, string $dest, array $excludeDirs = []): void
{
    $src = realpath($src) ?: $src;
    if (!is_dir($src)) {
        return;
    }
    $excludeReal = [];
    foreach ($excludeDirs as $ex) {
        $r = realpath($ex);
        if ($r) {
            $excludeReal[] = $r;
        }
    }
    if (!is_dir($dest)) {
        @mkdir($dest, 0755, true);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        $path = $file->getPathname();
        $real = realpath($path) ?: $path;
        $skip = false;
        foreach ($excludeReal as $ex) {
            if ($real === $ex || str_starts_with($real, $ex . DIRECTORY_SEPARATOR)) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }
        $rel = substr($real, strlen($src));
        $target = $dest . $rel;
        if ($file->isDir()) {
            if (!is_dir($target)) {
                @mkdir($target, 0755, true);
            }
        } else {
            $parent = dirname($target);
            if (!is_dir($parent)) {
                @mkdir($parent, 0755, true);
            }
            @copy($path, $target);
        }
    }
}

function vk_backup_zip_directory(string $sourceDir, string $zipPath, ?string $password = null): void
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive extension is required for compressed backups.');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create ZIP archive.');
    }
    $sourceDir = realpath($sourceDir) ?: $sourceDir;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $filePath = $file->getRealPath();
        $relative = substr($filePath, strlen($sourceDir) + 1);
        $relative = str_replace('\\', '/', $relative);
        $zip->addFile($filePath, $relative);
        if ($password !== null && $password !== '' && method_exists($zip, 'setEncryptionName')) {
            $zip->setEncryptionName($relative, ZipArchive::EM_AES_256);
        }
    }
    if ($password !== null && $password !== '') {
        $zip->setPassword($password);
    }
    $zip->close();
}

function vk_backup_encrypt_file(string $src, string $dest, string $password): void
{
    $data = (string) file_get_contents($src);
    $iv = random_bytes(16);
    $key = hash('sha256', $password, true);
    $cipher = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new RuntimeException('Encryption failed.');
    }
    file_put_contents($dest, 'VKENC1' . $iv . $cipher);
}

function vk_backup_decrypt_file(string $src, string $dest, string $password): void
{
    $raw = (string) file_get_contents($src);
    if (!str_starts_with($raw, 'VKENC1') || strlen($raw) < 23) {
        throw new RuntimeException('Invalid encrypted backup.');
    }
    $iv = substr($raw, 6, 16);
    $cipher = substr($raw, 22);
    $key = hash('sha256', $password, true);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($plain === false) {
        throw new RuntimeException('Decryption failed. Check password.');
    }
    file_put_contents($dest, $plain);
}

function vk_backup_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            vk_backup_rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function vk_backup_apply_retention(PDO $pdo): void
{
    $schedule = vk_backup_schedule_get($pdo);
    $keep = (int) $schedule['retention'];
    $manifest = vk_backup_manifest_load();
    if (count($manifest) <= $keep) {
        return;
    }
    $keepItems = array_slice($manifest, 0, $keep);
    $drop = array_slice($manifest, $keep);
    foreach ($drop as $item) {
        $path = vk_backup_absolute_path($item);
        if ($path) {
            @unlink($path);
        }
        vk_backup_log('deleted', 'success', 'retention:' . ($item['filename'] ?? ''), (string) ($item['id'] ?? ''));
    }
    vk_backup_manifest_save($keepItems);
}

function vk_backup_delete(string $id): bool
{
    $id = vk_backup_safe_id($id);
    $manifest = vk_backup_manifest_load();
    $kept = [];
    $deleted = false;
    foreach ($manifest as $item) {
        if ((string) ($item['id'] ?? '') === $id) {
            $path = vk_backup_absolute_path($item);
            if ($path) {
                @unlink($path);
            }
            $deleted = true;
            vk_backup_log('deleted', 'success', (string) ($item['filename'] ?? ''), $id);
            continue;
        }
        $kept[] = $item;
    }
    if ($deleted) {
        vk_backup_manifest_save($kept);
    }

    return $deleted;
}

function vk_backup_rename(string $id, string $name): bool
{
    $id = vk_backup_safe_id($id);
    $name = trim($name);
    if ($name === '') {
        return false;
    }
    $manifest = vk_backup_manifest_load();
    $changed = false;
    foreach ($manifest as &$item) {
        if ((string) ($item['id'] ?? '') === $id) {
            $item['name'] = mb_substr($name, 0, 120);
            $changed = true;
            break;
        }
    }
    unset($item);
    if ($changed) {
        vk_backup_manifest_save($manifest);
        vk_backup_log('renamed', 'success', $name, $id);
    }

    return $changed;
}

/** @return array{ok:bool,message:string,checks:array<string,bool>} */
function vk_backup_verify(string $id): array
{
    $item = vk_backup_find($id);
    if (!$item) {
        return ['ok' => false, 'message' => 'Backup not found.', 'checks' => []];
    }
    $path = vk_backup_absolute_path($item);
    $checks = [
        'file_exists' => $path !== null,
        'checksum' => false,
        'readable' => false,
        'structure' => false,
    ];
    if ($path === null) {
        return ['ok' => false, 'message' => 'Backup file missing on disk.', 'checks' => $checks];
    }
    $checks['readable'] = is_readable($path);
    $hash = hash_file('sha256', $path) ?: '';
    $checks['checksum'] = $hash !== '' && hash_equals((string) ($item['checksum'] ?? ''), $hash);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'zip' && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $checks['structure'] = $zip->open($path) === true;
        if ($checks['structure']) {
            $zip->close();
        }
    } elseif (in_array($ext, ['sql', 'gz'], true) || str_ends_with(strtolower($path), '.sql.gz')) {
        $checks['structure'] = filesize($path) > 10;
    } else {
        $checks['structure'] = filesize($path) > 0;
    }
    $ok = $checks['file_exists'] && $checks['readable'] && $checks['checksum'] && $checks['structure'];
    vk_backup_log('verified', $ok ? 'success' : 'failed', $hash, $id);

    return ['ok' => $ok, 'message' => $ok ? 'Backup verified successfully.' : 'Verification failed.', 'checks' => $checks];
}

/**
 * @return array{ok:bool,message:string,log:list<string>}
 */
function vk_backup_restore_database_from_sql(PDO $pdo, string $sqlFile): array
{
    $log = [];
    if (!is_file($sqlFile)) {
        return ['ok' => false, 'message' => 'SQL file missing.', 'log' => $log];
    }
    $log[] = 'Reading SQL dump…';
    $sql = '';
    if (str_ends_with(strtolower($sqlFile), '.gz')) {
        $gz = gzopen($sqlFile, 'rb');
        if ($gz === false) {
            return ['ok' => false, 'message' => 'Cannot read GZIP SQL.', 'log' => $log];
        }
        while (!gzeof($gz)) {
            $sql .= gzread($gz, 1024 * 1024);
        }
        gzclose($gz);
    } else {
        $sql = (string) file_get_contents($sqlFile);
    }
    if (trim($sql) === '') {
        return ['ok' => false, 'message' => 'Empty SQL dump.', 'log' => $log];
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $statements = vk_backup_split_sql($sql);
    $log[] = 'Executing ' . count($statements) . ' statements…';
    $done = 0;
    try {
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '' || str_starts_with($statement, '--') || str_starts_with($statement, '/*')) {
                continue;
            }
            $pdo->exec($statement);
            $done++;
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $log[] = "Restored {$done} statements.";
        return ['ok' => true, 'message' => 'Database restored successfully.', 'log' => $log];
    } catch (Throwable $e) {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $e2) {
        }
        $log[] = 'Error: ' . $e->getMessage();
        return ['ok' => false, 'message' => 'Restore failed: ' . $e->getMessage(), 'log' => $log];
    }
}

/** @return list<string> */
function vk_backup_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';
        if (!$inString && $ch === '-' && $next === '-') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }
        if (!$inString && ($ch === '"' || $ch === "'" || $ch === '`')) {
            $inString = true;
            $stringChar = $ch;
            $buffer .= $ch;
            continue;
        }
        if ($inString) {
            $buffer .= $ch;
            if ($ch === '\\' && $next !== '') {
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($ch === $stringChar) {
                $inString = false;
            }
            continue;
        }
        if ($ch === ';') {
            $statements[] = $buffer;
            $buffer = '';
            continue;
        }
        $buffer .= $ch;
    }
    if (trim($buffer) !== '') {
        $statements[] = $buffer;
    }

    return $statements;
}

/**
 * @param array<string,mixed> $options
 * @return array{ok:bool,message:string,log:list<string>}
 */
function vk_backup_restore(PDO $pdo, string $id, string $mode = 'everything', array $options = []): array
{
    $item = vk_backup_find($id);
    if (!$item) {
        return ['ok' => false, 'message' => 'Backup not found.', 'log' => []];
    }
    $path = vk_backup_absolute_path($item);
    if ($path === null) {
        return ['ok' => false, 'message' => 'Backup file missing.', 'log' => []];
    }

    $verify = vk_backup_verify($id);
    if (!$verify['ok'] && empty($options['force'])) {
        return ['ok' => false, 'message' => 'Backup validation failed. ' . $verify['message'], 'log' => []];
    }

    $work = vk_backup_dir() . DIRECTORY_SEPARATOR . 'restore_' . $id;
    vk_backup_rrmdir($work);
    @mkdir($work, 0755, true);
    $log = ['Starting restore (' . $mode . ')…'];
    $password = (string) ($options['password'] ?? '');

    try {
        $workFile = $path;
        if (str_ends_with(strtolower($path), '.enc')) {
            if ($password === '') {
                throw new RuntimeException('Password required for encrypted backup.');
            }
            $dec = $work . DIRECTORY_SEPARATOR . 'decrypted.bin';
            vk_backup_decrypt_file($path, $dec, $password);
            $workFile = $dec;
            $log[] = 'Decrypted backup.';
        }

        $sqlFile = null;
        $ext = strtolower(pathinfo($workFile, PATHINFO_EXTENSION));
        if ($ext === 'zip') {
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException('ZipArchive required.');
            }
            $zip = new ZipArchive();
            if ($zip->open($workFile) !== true) {
                throw new RuntimeException('Cannot open ZIP backup.');
            }
            if ($password !== '') {
                $zip->setPassword($password);
            }
            $zip->extractTo($work);
            $zip->close();
            $log[] = 'Extracted archive.';
            foreach (['database.sql', 'database.sql.gz', 'database/database.sql'] as $cand) {
                if (is_file($work . DIRECTORY_SEPARATOR . $cand)) {
                    $sqlFile = $work . DIRECTORY_SEPARATOR . $cand;
                    break;
                }
            }
            if ($sqlFile === null) {
                $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS));
                foreach ($rii as $f) {
                    if ($f->isFile() && preg_match('/\.sql(\.gz)?$/i', $f->getFilename())) {
                        $sqlFile = $f->getPathname();
                        break;
                    }
                }
            }
        } elseif ($ext === 'sql' || str_ends_with(strtolower($workFile), '.sql.gz') || $ext === 'gz') {
            $sqlFile = $workFile;
        }

        $ok = true;
        $message = 'Restore completed.';

        if (in_array($mode, ['database', 'everything'], true)) {
            if ($sqlFile === null) {
                if ($mode === 'database') {
                    throw new RuntimeException('No SQL dump found in backup.');
                }
                $log[] = 'No SQL dump found; skipped database restore.';
            } else {
                $dbRes = vk_backup_restore_database_from_sql($pdo, $sqlFile);
                $log = array_merge($log, $dbRes['log']);
                if (!$dbRes['ok']) {
                    $ok = false;
                    $message = $dbRes['message'];
                }
            }
        }

        if ($ok && in_array($mode, ['files', 'everything'], true) && is_dir($work)) {
            $map = [
                'uploads' => ROOT_PATH . '/uploads',
                'images' => ROOT_PATH . '/assets/images',
                'config' => ROOT_PATH . '/config',
                'cache' => ROOT_PATH . '/cache',
                'logs' => ROOT_PATH . '/storage/logs',
                'documents' => ROOT_PATH . '/uploads',
            ];
            foreach ($map as $comp => $dest) {
                $src = $work . DIRECTORY_SEPARATOR . $comp;
                if (is_dir($src)) {
                    vk_backup_copy_tree($src, $dest, [vk_backup_dir()]);
                    $log[] = 'Restored ' . $comp;
                }
            }
        }

        vk_backup_rrmdir($work);
        vk_backup_log('restored', $ok ? 'success' : 'failed', $message, $id);

        return ['ok' => $ok, 'message' => $message, 'log' => $log];
    } catch (Throwable $e) {
        vk_backup_rrmdir($work);
        vk_backup_log('restored', 'failed', $e->getMessage(), $id);

        return ['ok' => false, 'message' => $e->getMessage(), 'log' => $log];
    }
}

function vk_backup_db_size_bytes(PDO $pdo): int
{
    try {
        $db = (string) (vk_backup_db_credentials()['name'] ?? '');
        $st = $pdo->prepare(
            'SELECT COALESCE(SUM(data_length + index_length),0)
             FROM information_schema.TABLES WHERE table_schema = ?'
        );
        $st->execute([$db]);

        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function vk_backup_dir_size(string $dir): int
{
    if (!is_dir($dir)) {
        return 0;
    }
    $size = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile()) {
            $size += (int) $file->getSize();
        }
    }

    return $size;
}

/** @return array<string,mixed> */
function vk_backup_dashboard(PDO $pdo): array
{
    $manifest = vk_backup_manifest_load();
    $latest = $manifest[0] ?? null;
    $storage = vk_backup_dir_size(vk_backup_dir());
    $schedule = vk_backup_schedule_get($pdo);
    $free = @disk_free_space(ROOT_PATH);
    $total = @disk_total_space(ROOT_PATH);

    return [
        'total_backups' => count($manifest),
        'latest_backup' => $latest,
        'latest_label' => $latest ? ((string) ($latest['created_at'] ?? '')) : 'Never',
        'latest_size' => $latest ? vk_backup_format_bytes((int) ($latest['size'] ?? 0)) : '—',
        'storage_used' => vk_backup_format_bytes($storage),
        'storage_used_bytes' => $storage,
        'database_size' => vk_backup_format_bytes(vk_backup_db_size_bytes($pdo)),
        'database_version' => (string) $pdo->query('SELECT VERSION()')->fetchColumn(),
        'php_version' => PHP_VERSION,
        'auto_backup' => $schedule,
        'backup_folder' => 'storage/backups',
        'free_space' => is_float($free) || is_int($free) ? vk_backup_format_bytes((int) $free) : '—',
        'server_storage' => is_float($total) || is_int($total) ? vk_backup_format_bytes((int) $total) : '—',
        'zip_supported' => class_exists('ZipArchive'),
        'openssl' => extension_loaded('openssl'),
    ];
}

function vk_backup_maybe_run_scheduled(PDO $pdo): ?array
{
    $schedule = vk_backup_schedule_get($pdo);
    if (!$schedule['enabled']) {
        return null;
    }
    $now = time();
    $todayTime = strtotime(date('Y-m-d') . ' ' . $schedule['time']);
    if ($todayTime === false || $now < $todayTime) {
        return null;
    }
    $last = $schedule['last_run'] ? strtotime($schedule['last_run']) : false;
    $due = false;
    if ($last === false) {
        $due = true;
    } else {
        $due = match ($schedule['frequency']) {
            'weekly' => ($now - $last) >= 6 * 86400,
            'monthly' => ($now - $last) >= 27 * 86400,
            default => date('Y-m-d', $last) !== date('Y-m-d'),
        };
    }
    if (!$due) {
        return null;
    }
    $meta = vk_backup_create($pdo, 'full', $schedule['components'], [
        'compress' => true,
        'name' => 'Auto backup ' . date('Y-m-d H:i'),
    ]);
    if (function_exists('vk_settings_set')) {
        vk_settings_set($pdo, 'backup_auto_last_run', date('c'));
    }

    return $meta;
}

function vk_backup_stream_download(string $id): void
{
    $item = vk_backup_find($id);
    if (!$item) {
        http_response_code(404);
        echo 'Backup not found';
        exit;
    }
    $path = vk_backup_absolute_path($item);
    if ($path === null) {
        http_response_code(404);
        echo 'File missing';
        exit;
    }
    $filename = basename($path);
    $mime = 'application/octet-stream';
    if (str_ends_with(strtolower($filename), '.zip')) {
        $mime = 'application/zip';
    } elseif (str_ends_with(strtolower($filename), '.sql')) {
        $mime = 'application/sql';
    } elseif (str_ends_with(strtolower($filename), '.gz')) {
        $mime = 'application/gzip';
    }
    vk_backup_log('downloaded', 'success', $filename, (string) $item['id']);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        http_response_code(500);
        echo 'Cannot read file';
        exit;
    }
    while (!feof($fh)) {
        echo fread($fh, 1024 * 1024);
        flush();
    }
    fclose($fh);
    exit;
}
