<?php
declare(strict_types=1);

/**
 * Load KEY=value pairs from .env into getenv/$_ENV when not already set.
 * Server-level environment variables take precedence.
 */
function vk_load_dotenv(?string $path = null): void
{
    if ($path === null) {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    }
    if (!is_readable($path)) {
        return;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return;
    }
    $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        if ($name === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            continue;
        }
        $value = trim($value);
        if ($value !== '' && (($value[0] ?? '') === '"' || ($value[0] ?? '') === "'")) {
            $q = $value[0];
            if (str_ends_with($value, $q) && strlen($value) >= 2) {
                $value = stripcslashes(substr($value, 1, -1));
            }
        }
        // Only skip if a non-empty value is already set (empty env var still allows .env to fill it).
        $existing = getenv($name);
        if ($existing !== false && trim((string) $existing) !== '') {
            // Duplicate keys in .env: first wins. Track password conflicts for diagnostics.
            if (in_array($name, ['VK_SMTP_PASS', 'MAIL_PASSWORD'], true) && !hash_equals((string) $existing, $value)) {
                error_log('[env] Duplicate ' . $name . ' in .env with different values — first occurrence is used. Remove duplicates.');
            }
            continue;
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
