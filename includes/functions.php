<?php
declare(strict_types=1);

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
    if (empty($_SESSION['user_id'])) {
        flash_set('warning', 'Please sign in to continue.');
        redirect('/login.php');
    }
}

/** Block technician accounts from admin modules (they use /tech/). Sync role/status from DB. */
function require_admin(): void
{
    require_login();
    $pdo = db();
    require_once __DIR__ . '/users_schema.php';
    vk_ensure_users_management_schema($pdo);

    $uid = (int) $_SESSION['user_id'];
    $st = $pdo->prepare('SELECT role, status FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $_SESSION = [];
        session_destroy();
        flash_set('error', 'Account not found.');
        redirect('/login.php');
    }
    $status = isset($row['status']) ? (string) $row['status'] : 'active';
    if ($status === 'inactive') {
        $_SESSION = [];
        session_destroy();
        flash_set('warning', 'Your account is inactive.');
        redirect('/login.php');
    }
    $role = (string) ($row['role'] ?? 'admin');
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
    if (($_SESSION['user_role'] ?? 'admin') !== 'admin') {
        flash_set('error', 'Only administrators can manage user accounts.');
        redirect('/modules/dashboard.php');
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
    if (users_has_role_column($pdo)) {
        $extra = '';
        if (db_column_exists($pdo, 'users', 'email')) {
            $extra .= ', email, phone, status';
        }
        $st = $pdo->prepare(
            'SELECT id, username, fullname, role, technician_id' . $extra . ' FROM users WHERE id = ? LIMIT 1'
        );
    } else {
        $st = $pdo->prepare('SELECT id, username, fullname FROM users WHERE id = ? LIMIT 1');
    }
    $st->execute([(int) $_SESSION['user_id']]);
    $u = $st->fetch();
    if ($u && !isset($u['role'])) {
        $u['role'] = 'admin';
        $u['technician_id'] = null;
    }
    if ($u && !isset($u['status'])) {
        $u['status'] = 'active';
    }
    return $u ?: null;
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
 * Apply ledger movement. Customer debt: credit increases amount owed, debit decreases (e.g. payment).
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
    $st = $pdo->prepare('SELECT current_balance FROM accounts WHERE id = ? FOR UPDATE');
    $st->execute([$accountId]);
    $row = $st->fetch();
    if (!$row) {
        throw new RuntimeException('Account not found.');
    }
    $prev = (float) $row['current_balance'];
    $newBalance = $prev + $credit - $debit;

    $pdo->prepare('UPDATE accounts SET current_balance = ? WHERE id = ?')->execute([$newBalance, $accountId]);
    $ins = $pdo->prepare(
        'INSERT INTO account_ledger (account_id, debit, credit, balance, description, invoice_id, payment_id, transfer_id)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $ins->execute([$accountId, $debit, $credit, $newBalance, $description, $invoiceId, $paymentId, $transferId]);
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
