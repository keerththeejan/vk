<?php
declare(strict_types=1);

/** @var array<string, true> */
$GLOBALS['_vk_bootstrapped_modules'] = $GLOBALS['_vk_bootstrapped_modules'] ?? [];

/**
 * Lazy-load optional application modules (email, WhatsApp, gallery, etc.).
 */
function vk_bootstrap_module(string $module): void
{
    if (isset($GLOBALS['_vk_bootstrapped_modules'][$module])) {
        return;
    }

    $map = [
        'service_gallery' => __DIR__ . '/service_gallery.php',
        'vehicle_booking' => __DIR__ . '/vehicle_booking.php',
        'whatsapp_bridge' => __DIR__ . '/whatsapp_bridge.php',
        'mailer' => __DIR__ . '/mailer.php',
        'email_system' => __DIR__ . '/email_system.php',
        'email_imap_poll' => __DIR__ . '/email_imap_poll.php',
        'booking_automation' => __DIR__ . '/booking_automation.php',
    ];

    if (!isset($map[$module])) {
        return;
    }

    require_once $map[$module];
    $GLOBALS['_vk_bootstrapped_modules'][$module] = true;
}

/**
 * Whether auth schema / remember-me should run on this request.
 */
function vk_wants_auth_bootstrap(): bool
{
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    $cookie = $_COOKIE[SESSION_NAME] ?? '';
    if (is_string($cookie) && $cookie !== '') {
        return true;
    }

    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $authScripts = [
        'login.php', 'signup.php', 'logout.php', 'dashboard.php', 'approve_users.php',
    ];

    return in_array($script, $authScripts, true)
        || str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/modules/')
        || str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/api/')
        || str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/tech/')
        || str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/vehicle/dashboard');
}

/**
 * Escape HTML output.
 */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Build a public URL from the configured app base, similar to CodeIgniter's base_url().
 */
function base_url(string $path = ''): string
{
    $base = rtrim(BASE_URL, '/');
    $path = ltrim($path, '/');

    return $path === '' ? $base . '/' : $base . '/' . $path;
}

/** Cache-busting version from a project-relative asset path. */
function vk_asset_mtime_version(string $relativePath): string
{
    static $cache = [];
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    if (isset($cache[$relativePath])) {
        return $cache[$relativePath];
    }
    $full = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $cache[$relativePath] = is_file($full) ? (string) filemtime($full) : (string) time();

    return $cache[$relativePath];
}

function redirect(string $path): void
{
    if (str_starts_with($path, 'http')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . BASE_URL . $path);
    }
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['_flash'])) {
        return null;
    }
    $f = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $f;
}

function require_login(): void
{
    $pdo = db();
    if (function_exists('vk_auth_try_remember')) {
        vk_auth_try_remember($pdo);
    }
    if (empty($_SESSION['user_id'])) {
        flash_set('warning', 'Please sign in to continue.');
        redirect('/login.php');
    }
    if (function_exists('vk_auth_enforce_session_timeout')) {
        vk_auth_enforce_session_timeout();
    }
}

/** Block technician accounts from admin modules (they use /tech/). Sync role/status from DB. */
function require_admin(): void
{
    require_login();

    if (vk_auth_user_cache_fresh(300)) {
        $cached = vk_auth_cached_user();
        $status = (string) ($cached['status'] ?? $_SESSION['user_status'] ?? 'approved');
        if (!vk_auth_status_is_approved($status)) {
            $_SESSION = [];
            session_destroy();
            flash_set('warning', 'Your account is not approved for access.');
            redirect('/login.php');
        }
        $role = (string) ($cached['role'] ?? $_SESSION['user_role'] ?? 'viewer');
        $_SESSION['user_role'] = $role;
        if ($role === 'technician') {
            flash_set('warning', 'Use the technician mobile dashboard for your account.');
            redirect('/tech/index.php');
        }

        return;
    }

    $pdo = db();
    if (empty($_SESSION['_users_schema_ready'])) {
        require_once __DIR__ . '/users_schema.php';
        vk_ensure_users_management_schema($pdo);
        $_SESSION['_users_schema_ready'] = true;
    }

    $uid = (int) $_SESSION['user_id'];
    $user = vk_auth_load_user_cache($pdo, $uid);
    if (!$user) {
        $_SESSION = [];
        session_destroy();
        flash_set('error', 'Account not found.');
        redirect('/login.php');
    }
    $status = (string) ($user['status'] ?? 'approved');
    if (!vk_auth_status_is_approved($status)) {
        vk_auth_invalidate_user_cache();
        $_SESSION = [];
        session_destroy();
        flash_set('warning', 'Your account is not approved for access.');
        redirect('/login.php');
    }
    $role = (string) ($user['role'] ?? 'admin');
    $_SESSION['user_role'] = $role;
    if ($role === 'technician') {
        flash_set('warning', 'Use the technician mobile dashboard for your account.');
        redirect('/tech/index.php');
    }
}

/** Only role admin may access user management routes and APIs. */
function require_users_admin(PDO $pdo): void
{
    require_admin();
    if (!vk_auth_role_can_manage((string) ($_SESSION['user_role'] ?? 'viewer'))) {
        flash_set('error', 'Only administrators can manage user accounts.');
        redirect('/modules/dashboard.php');
    }
}

function require_settings_admin(): void
{
    require_admin();
    $role = strtolower((string) ($_SESSION['user_role'] ?? 'admin'));
    if (!in_array($role, ['super_admin', 'admin', 'owner'], true)) {
        flash_set('error', 'Only administrators and owners can manage site settings.');
        redirect('/modules/dashboard.php');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_verify(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['_csrf_token'])
        && is_string($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);
}

function require_csrf(?string $token): void
{
    if (!csrf_verify($token)) {
        http_response_code(419);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Security token expired. Refresh and try again.'], JSON_THROW_ON_ERROR);
        exit;
    }
}

function vk_api_require_admin(): void
{
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_THROW_ON_ERROR);
        exit;
    }
    $cached = vk_auth_cached_user();
    $role = (string) ($cached['role'] ?? $_SESSION['user_role'] ?? 'viewer');
    $status = (string) ($cached['status'] ?? $_SESSION['user_status'] ?? 'approved');
    if (!vk_auth_status_is_approved($status) || $role === 'technician') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_THROW_ON_ERROR);
        exit;
    }
}

function users_has_role_column(PDO $pdo): bool
{
    static $v = null;
    if ($v !== null) {
        return $v;
    }
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute(['users', 'role']);
    $v = (bool) $st->fetchColumn();
    return $v;
}

function current_user(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    if (vk_auth_user_cache_fresh(300)) {
        return vk_auth_cached_user();
    }

    return vk_auth_load_user_cache($pdo, (int) $_SESSION['user_id']);
}

function next_booking_number(PDO $pdo): string
{
    $prefix = 'BK-' . date('Ymd') . '-';
    $st = $pdo->prepare('SELECT booking_number FROM web_bookings WHERE booking_number LIKE ? ORDER BY id DESC LIMIT 1');
    $st->execute([$prefix . '%']);
    $last = $st->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
        $seq = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

/** Labels shown on public “track booking” page. */
function booking_public_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pending',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'delivered' => 'Completed',
        'cancelled' => 'Cancelled',
        default => str_replace('_', ' ', $status),
    };
}

/** Map web booking service → repair_jobs.device_type */
function booking_service_to_device_type(string $serviceType): string
{
    return match ($serviceType) {
        'computer' => 'computer',
        'printer' => 'printer',
        'cctv' => 'cctv_dvr',
        'maintenance' => 'computer',
        'automobile' => 'automobile',
        'ac' => 'ac',
        'electrical' => 'electrical',
        default => 'other',
    };
}

function next_customer_account_code(PDO $pdo): string
{
    $st = $pdo->query("SELECT code FROM accounts WHERE code LIKE 'CUS-%' ORDER BY id DESC LIMIT 1");
    $last = $st ? $st->fetchColumn() : false;
    $n = 1;
    if ($last && preg_match('/CUS-(\d+)$/', (string) $last, $m)) {
        $n = (int) $m[1] + 1;
    }
    return 'CUS-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
}

function next_invoice_number(PDO $pdo): string
{
    $prefix = 'INV-' . date('Ymd') . '-';
    $st = $pdo->prepare('SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1');
    $st->execute([$prefix . '%']);
    $last = $st->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
        $seq = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

function next_repair_job_number(PDO $pdo): string
{
    $prefix = 'RJP-' . date('Ymd') . '-';
    $st = $pdo->prepare('SELECT job_number FROM repair_jobs WHERE job_number LIKE ? ORDER BY id DESC LIMIT 1');
    $st->execute([$prefix . '%']);
    $last = $st->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
        $seq = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

function next_cctv_job_number(PDO $pdo): string
{
    $prefix = 'CCT-' . date('Ymd') . '-';
    $st = $pdo->prepare('SELECT job_number FROM cctv_installations WHERE job_number LIKE ? ORDER BY id DESC LIMIT 1');
    $st->execute([$prefix . '%']);
    $last = $st->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
        $seq = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Apply ledger movement. Customer debt: debit increases amount owed, credit decreases it.
 * Must be called inside an active transaction; locks the account row.
 */
function ledger_apply(
    PDO $pdo,
    int $accountId,
    float $debit,
    float $credit,
    string $description,
    ?int $invoiceId = null,
    ?int $paymentId = null,
    ?int $transferId = null
): void {
    if ($accountId <= 0) {
        throw new InvalidArgumentException('Invalid account.');
    }
    if ($debit < 0 || $credit < 0) {
        throw new InvalidArgumentException('Ledger amounts cannot be negative.');
    }
    if (($debit <= 0 && $credit <= 0) || ($debit > 0 && $credit > 0)) {
        throw new InvalidArgumentException('Ledger entry must contain either a debit or a credit amount.');
    }
    if (function_exists('vk_ensure_account_ledger_table') && !db_table_exists($pdo, 'account_ledger')) {
        vk_ensure_account_ledger_table($pdo);
    }

    $st = $pdo->prepare('SELECT customer_id, current_balance FROM accounts WHERE id = ? FOR UPDATE');
    $st->execute([$accountId]);
    $row = $st->fetch();
    if (!$row) {
        throw new RuntimeException('Account not found.');
    }
    $prev = (float) $row['current_balance'];
    $newBalance = $prev + $debit - $credit;
    $entryType = $debit > 0 ? 'debit' : 'credit';
    $amount = $debit > 0 ? $debit : $credit;

    $pdo->prepare('UPDATE accounts SET current_balance = ? WHERE id = ?')->execute([$newBalance, $accountId]);
    $ins = $pdo->prepare(
        'INSERT INTO account_ledger
            (account_id, customer_id, invoice_id, payment_id, transfer_id, entry_type, amount, debit, credit, balance, description)
         VALUES
            (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $ins->execute([
        $accountId,
        $row['customer_id'] !== null ? (int) $row['customer_id'] : null,
        $invoiceId,
        $paymentId,
        $transferId,
        $entryType,
        $amount,
        $debit,
        $credit,
        $newBalance,
        $description !== '' ? $description : null,
    ]);
}

function invoice_recalc_status(PDO $pdo, int $invoiceId): void
{
    $st = $pdo->prepare('SELECT grand_total, paid_amount FROM invoices WHERE id = ?');
    $st->execute([$invoiceId]);
    $inv = $st->fetch();
    if (!$inv) {
        return;
    }
    $gt = (float) $inv['grand_total'];
    $pa = (float) $inv['paid_amount'];
    $status = 'unpaid';
    if ($pa >= $gt - 0.0001 && $gt > 0) {
        $status = 'paid';
    } elseif ($pa > 0) {
        $status = 'partial';
    }
    $pdo->prepare('UPDATE invoices SET status = ? WHERE id = ?')->execute([$status, $invoiceId]);
}

/**
 * Pagination helper.
 */
function paginate(int $total, int $page, int $perPage): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;
    return ['page' => $page, 'pages' => $pages, 'offset' => $offset, 'perPage' => $perPage];
}

function system_account_id(PDO $pdo): int
{
    static $id = null;
    if ($id !== null) {
        return $id;
    }
    $st = $pdo->query("SELECT id FROM accounts WHERE code = 'SYS-MAIN' LIMIT 1");
    $row = $st ? $st->fetchColumn() : false;
    if (!$row) {
        throw new RuntimeException('System account missing. Re-run database install.');
    }
    $id = (int) $row;
    return $id;
}

/** Whether the current database has a physical table (for optional v3+ features). */
function db_table_exists(PDO $pdo, string $table): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute([$table]);
    vk_perf_mark_query();
    $cache[$table] = (bool) $st->fetchColumn();
    return $cache[$table];
}

/** Whether a column exists on a table (for gradual schema upgrades). */
function db_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $column]);
    vk_perf_mark_query();
    $cache[$key] = (bool) $st->fetchColumn();
    return $cache[$key];
}

function next_maintenance_contract_number(PDO $pdo): string
{
    $prefix = 'AMC-' . date('Ymd') . '-';
    $st = $pdo->prepare('SELECT contract_number FROM maintenance_contracts WHERE contract_number LIKE ? ORDER BY id DESC LIMIT 1');
    $st->execute([$prefix . '%']);
    $last = $st->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
        $seq = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

/** Human label for repair device_type (includes legacy values after partial upgrades). */
function repair_device_type_label(string $type): string
{
    $map = [
        'computer' => 'Computer',
        'printer' => 'Printer',
        'cctv_dvr' => 'CCTV / DVR',
        'automobile' => 'Automobile / breakdown',
        'ac' => 'AC repair',
        'electrical' => 'Electrical (DC wiring)',
        'other' => 'Other',
        'laptop' => 'Computer (laptop)',
        'desktop' => 'Computer (desktop)',
        'cctv' => 'CCTV / DVR',
        'dvr' => 'CCTV / DVR',
    ];
    return $map[$type] ?? str_replace('_', ' ', $type);
}

/** Bootstrap text-bg-* class for repair job status. */
function repair_status_badge_class(string $status): string
{
    return match ($status) {
        'pending' => 'secondary',
        'diagnosing' => 'info',
        'in_progress' => 'primary',
        'completed' => 'success',
        'delivered' => 'dark',
        default => 'secondary',
    };
}

/** Days until end date (negative = expired). */
function warranty_days_remaining(?string $endDate): ?int
{
    if ($endDate === null || $endDate === '') {
        return null;
    }
    $end = strtotime($endDate . ' 23:59:59');
    if ($end === false) {
        return null;
    }
    $start = strtotime('today');
    return (int) floor(($end - $start) / 86400);
}

/** Bootstrap badge class for warranty end date row. */
function warranty_expiry_badge_class(?string $endDate): string
{
    $days = warranty_days_remaining($endDate);
    if ($days === null) {
        return 'secondary';
    }
    if ($days < 0) {
        return 'dark';
    }
    $alertDays = defined('WARRANTY_ALERT_DAYS') ? (int) WARRANTY_ALERT_DAYS : 30;
    if ($days <= $alertDays) {
        return 'warning';
    }
    return 'success';
}

/**
 * Normalize stored web-relative paths: strip scheme/host, leading slashes, accidental BASE_URL prefix.
 */
function vk_normalize_upload_relative_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://[^/]+(/.*)?$#i', $path, $m)) {
        $path = ltrim((string) ($m[1] ?? ''), '/');
    }
    $path = ltrim($path, '/');
    $base = trim(BASE_URL, '/');
    if ($base !== '' && str_starts_with(strtolower($path), strtolower($base) . '/')) {
        $path = substr($path, strlen($base) + 1);
    }

    return $path;
}

/** Public static asset URL (e.g. assets/images/... or uploads/...). */
function public_asset_url(string $relativePath): string
{
    $relativePath = vk_normalize_upload_relative_path($relativePath);
    if ($relativePath === '') {
        return BASE_URL . '/';
    }

    return BASE_URL . '/' . $relativePath;
}

/** True if a file exists under project root at the given web-relative path. */
function public_asset_file_exists(string $relativePath): bool
{
    $relativePath = vk_normalize_upload_relative_path($relativePath);
    if ($relativePath === '') {
        return false;
    }
    $full = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    return is_file($full);
}

/**
 * @return list<array{icon: string, text: string}>
 */
function web_service_features_decode(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }
    $out = [];
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $icon = isset($row['icon']) ? (string) $row['icon'] : '';
        $text = isset($row['text']) ? (string) $row['text'] : '';
        if ($icon === '' || $text === '') {
            continue;
        }
        $out[] = ['icon' => $icon, 'text' => $text];
    }
    return $out;
}
