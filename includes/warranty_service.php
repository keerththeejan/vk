<?php
declare(strict_types=1);

/**
 * Warranty Management helpers — status, filters, KPIs (no schema changes).
 */

function vk_warranty_alert_days(): int
{
    return defined('WARRANTY_ALERT_DAYS') ? max(1, (int) WARRANTY_ALERT_DAYS) : 30;
}

function vk_warranty_number(int $id): string
{
    return 'WR-' . str_pad((string) max(0, $id), 5, '0', STR_PAD_LEFT);
}

function vk_warranty_is_cancelled(?string $notes): bool
{
    return str_starts_with(ltrim((string) $notes), '[CANCELLED]');
}

function vk_warranty_is_lifetime(string $startDate, string $endDate): bool
{
    if ($endDate >= '2099-01-01') {
        return true;
    }
    $start = strtotime($startDate . ' 00:00:00');
    $end = strtotime($endDate . ' 23:59:59');
    if ($start === false || $end === false || $end < $start) {
        return false;
    }

    return (($end - $start) / 86400) >= (365 * 50);
}

/**
 * @return array{key:string,label:string,class:string,days:?int}
 */
function vk_warranty_status(array $row): array
{
    $notes = (string) ($row['notes'] ?? '');
    $start = (string) ($row['start_date'] ?? '');
    $end = (string) ($row['end_date'] ?? '');
    $days = warranty_days_remaining($end !== '' ? $end : null);

    if (vk_warranty_is_cancelled($notes)) {
        return ['key' => 'cancelled', 'label' => 'Cancelled', 'class' => 'secondary', 'days' => $days];
    }
    if ($start !== '' && $end !== '' && vk_warranty_is_lifetime($start, $end)) {
        return ['key' => 'lifetime', 'label' => 'Lifetime', 'class' => 'primary', 'days' => null];
    }
    if ($days === null) {
        return ['key' => 'unknown', 'label' => 'Unknown', 'class' => 'secondary', 'days' => null];
    }
    if ($days < 0) {
        return ['key' => 'expired', 'label' => 'Expired', 'class' => 'danger', 'days' => $days];
    }
    if ($days <= vk_warranty_alert_days()) {
        return ['key' => 'expiring', 'label' => 'Expiring Soon', 'class' => 'warning', 'days' => $days];
    }

    return ['key' => 'active', 'label' => 'Active', 'class' => 'success', 'days' => $days];
}

function vk_warranty_period_label(string $startDate, string $endDate): string
{
    $start = strtotime($startDate . ' 00:00:00');
    $end = strtotime($endDate . ' 23:59:59');
    if ($start === false || $end === false || $end < $start) {
        return '—';
    }
    if (vk_warranty_is_lifetime($startDate, $endDate)) {
        return 'Lifetime';
    }
    $days = (int) floor(($end - $start) / 86400) + 1;
    if ($days >= 365) {
        $years = (int) round($days / 365);
        return $years . ' year' . ($years === 1 ? '' : 's');
    }
    if ($days >= 28) {
        $months = (int) max(1, round($days / 30.4375));
        return $months . ' month' . ($months === 1 ? '' : 's');
    }

    return $days . ' day' . ($days === 1 ? '' : 's');
}

/**
 * @return array{where:string,params:list<mixed>}
 */
function vk_warranty_build_filters(array $input): array
{
    $where = ['1=1'];
    $params = [];
    $alert = vk_warranty_alert_days();

    $q = trim((string) ($input['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(w.title LIKE ? OR w.description LIKE ? OR w.notes LIKE ? OR c.name LIKE ? OR i.invoice_number LIKE ?
            OR CAST(w.id AS CHAR) LIKE ? OR CONCAT(\'WR-\', LPAD(w.id, 5, \'0\')) LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }

    $warrantyNo = trim((string) ($input['warranty_no'] ?? ''));
    if ($warrantyNo !== '') {
        $digits = preg_replace('/\D+/', '', $warrantyNo) ?? '';
        if ($digits !== '') {
            $where[] = 'w.id = ?';
            $params[] = (int) $digits;
        } else {
            $where[] = 'CONCAT(\'WR-\', LPAD(w.id, 5, \'0\')) LIKE ?';
            $params[] = '%' . $warrantyNo . '%';
        }
    }

    $customer = trim((string) ($input['customer'] ?? ''));
    if ($customer !== '') {
        $where[] = 'c.name LIKE ?';
        $params[] = '%' . $customer . '%';
    }

    $invoice = trim((string) ($input['invoice'] ?? ''));
    if ($invoice !== '') {
        $where[] = 'i.invoice_number LIKE ?';
        $params[] = '%' . $invoice . '%';
    }

    $product = trim((string) ($input['product'] ?? ''));
    if ($product !== '') {
        $where[] = '(w.title LIKE ? OR w.description LIKE ?)';
        $like = '%' . $product . '%';
        array_push($params, $like, $like);
    }

    $brand = trim((string) ($input['brand'] ?? ''));
    if ($brand !== '') {
        $where[] = '(w.description LIKE ? OR w.title LIKE ? OR w.notes LIKE ?)';
        $like = '%' . $brand . '%';
        array_push($params, $like, $like, $like);
    }

    $model = trim((string) ($input['model'] ?? ''));
    if ($model !== '') {
        $where[] = '(w.description LIKE ? OR w.title LIKE ? OR w.notes LIKE ?)';
        $like = '%' . $model . '%';
        array_push($params, $like, $like, $like);
    }

    $serial = trim((string) ($input['serial'] ?? ''));
    if ($serial !== '') {
        $where[] = '(w.description LIKE ? OR w.notes LIKE ? OR w.title LIKE ?)';
        $like = '%' . $serial . '%';
        array_push($params, $like, $like, $like);
    }

    $wtype = trim((string) ($input['warranty_type'] ?? ''));
    if (in_array($wtype, ['service', 'product'], true)) {
        $where[] = 'w.warranty_type = ?';
        $params[] = $wtype;
    }

    $status = trim((string) ($input['status'] ?? ($input['filter'] ?? '')));
    // Legacy ?filter=expiring|expired support
    if ($status === 'expiring') {
        $status = 'expiring';
    }
    if ($status !== '') {
        switch ($status) {
            case 'active':
                $where[] = "COALESCE(w.notes,'') NOT LIKE '[CANCELLED]%'
                    AND w.end_date >= CURDATE()
                    AND w.end_date > DATE_ADD(CURDATE(), INTERVAL {$alert} DAY)
                    AND w.end_date < '2099-01-01'";
                break;
            case 'expiring':
                $where[] = "COALESCE(w.notes,'') NOT LIKE '[CANCELLED]%'
                    AND w.end_date >= CURDATE()
                    AND w.end_date <= DATE_ADD(CURDATE(), INTERVAL {$alert} DAY)";
                break;
            case 'expired':
                $where[] = "COALESCE(w.notes,'') NOT LIKE '[CANCELLED]%' AND w.end_date < CURDATE()";
                break;
            case 'lifetime':
                $where[] = "COALESCE(w.notes,'') NOT LIKE '[CANCELLED]%' AND w.end_date >= '2099-01-01'";
                break;
            case 'cancelled':
                $where[] = "COALESCE(w.notes,'') LIKE '[CANCELLED]%'";
                break;
        }
    }

    $purchaseFrom = trim((string) ($input['purchase_from'] ?? ''));
    $purchaseTo = trim((string) ($input['purchase_to'] ?? ''));
    if ($purchaseFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseFrom)) {
        $where[] = 'w.start_date >= ?';
        $params[] = $purchaseFrom;
    }
    if ($purchaseTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseTo)) {
        $where[] = 'w.start_date <= ?';
        $params[] = $purchaseTo;
    }

    $expiryFrom = trim((string) ($input['expiry_from'] ?? ''));
    $expiryTo = trim((string) ($input['expiry_to'] ?? ''));
    if ($expiryFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryFrom)) {
        $where[] = 'w.end_date >= ?';
        $params[] = $expiryFrom;
    }
    if ($expiryTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryTo)) {
        $where[] = 'w.end_date <= ?';
        $params[] = $expiryTo;
    }

    $createdFrom = trim((string) ($input['created_from'] ?? ''));
    $createdTo = trim((string) ($input['created_to'] ?? ''));
    if ($createdFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $createdFrom)) {
        $where[] = 'DATE(w.created_at) >= ?';
        $params[] = $createdFrom;
    }
    if ($createdTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $createdTo)) {
        $where[] = 'DATE(w.created_at) <= ?';
        $params[] = $createdTo;
    }

    return ['where' => implode(' AND ', $where), 'params' => $params];
}

/**
 * @return array{total:int,active:int,expiring_month:int,expired:int,lifetime:int,today:int,expiring_30:int,expiring_15:int,expiring_today:int}
 */
function vk_warranty_kpis(PDO $pdo): array
{
    $alert = vk_warranty_alert_days();
    $sql = "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN COALESCE(notes,'') NOT LIKE '[CANCELLED]%'
                  AND end_date >= CURDATE()
                  AND end_date > DATE_ADD(CURDATE(), INTERVAL {$alert} DAY)
                  AND end_date < '2099-01-01' THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN COALESCE(notes,'') NOT LIKE '[CANCELLED]%'
                  AND end_date >= CURDATE()
                  AND end_date <= LAST_DAY(CURDATE()) THEN 1 ELSE 0 END) AS expiring_month,
        SUM(CASE WHEN COALESCE(notes,'') NOT LIKE '[CANCELLED]%' AND end_date < CURDATE() THEN 1 ELSE 0 END) AS expired,
        SUM(CASE WHEN COALESCE(notes,'') NOT LIKE '[CANCELLED]%' AND end_date >= '2099-01-01' THEN 1 ELSE 0 END) AS lifetime,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
        SUM(CASE WHEN COALESCE(notes,'') NOT LIKE '[CANCELLED]%'
                  AND end_date >= CURDATE()
                  AND end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS expiring_30,
        SUM(CASE WHEN COALESCE(notes,'') NOT LIKE '[CANCELLED]%'
                  AND end_date >= CURDATE()
                  AND end_date <= DATE_ADD(CURDATE(), INTERVAL 15 DAY) THEN 1 ELSE 0 END) AS expiring_15,
        SUM(CASE WHEN COALESCE(notes,'') NOT LIKE '[CANCELLED]%' AND end_date = CURDATE() THEN 1 ELSE 0 END) AS expiring_today
     FROM warranty_records";

    $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'active' => (int) ($row['active'] ?? 0),
        'expiring_month' => (int) ($row['expiring_month'] ?? 0),
        'expired' => (int) ($row['expired'] ?? 0),
        'lifetime' => (int) ($row['lifetime'] ?? 0),
        'today' => (int) ($row['today'] ?? 0),
        'expiring_30' => (int) ($row['expiring_30'] ?? 0),
        'expiring_15' => (int) ($row['expiring_15'] ?? 0),
        'expiring_today' => (int) ($row['expiring_today'] ?? 0),
    ];
}

/**
 * @return list<string>
 */
function vk_warranty_sort_columns(): array
{
    return [
        'id', 'customer_name', 'invoice_number', 'title', 'warranty_type',
        'start_date', 'end_date', 'created_at',
    ];
}

function vk_warranty_sort_sql(string $sort, string $dir): string
{
    $allowed = vk_warranty_sort_columns();
    if (!in_array($sort, $allowed, true)) {
        $sort = 'end_date';
    }
    $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
    $map = [
        'id' => 'w.id',
        'customer_name' => 'c.name',
        'invoice_number' => 'i.invoice_number',
        'title' => 'w.title',
        'warranty_type' => 'w.warranty_type',
        'start_date' => 'w.start_date',
        'end_date' => 'w.end_date',
        'created_at' => 'w.created_at',
    ];

    return ($map[$sort] ?? 'w.end_date') . ' ' . $dir . ', w.id DESC';
}

/**
 * @return array{sql:string,params:list<mixed>}
 */
function vk_warranty_list_query(array $filters, string $sort, string $dir, int $limit, int $offset): array
{
    $built = vk_warranty_build_filters($filters);
    $order = vk_warranty_sort_sql($sort, $dir);
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);

    $sql = "SELECT w.*,
                c.name AS customer_name,
                c.phone AS customer_phone,
                c.email AS customer_email,
                i.invoice_number,
                i.invoice_date,
                r.job_number AS repair_job_number,
                r.device_type AS repair_device_type,
                v.job_number AS cctv_job_number
            FROM warranty_records w
            JOIN customers c ON c.id = w.customer_id
            LEFT JOIN invoices i ON i.id = w.invoice_id
            LEFT JOIN repair_jobs r ON r.id = w.repair_job_id
            LEFT JOIN cctv_installations v ON v.id = w.cctv_installation_id
            WHERE {$built['where']}
            ORDER BY {$order}
            LIMIT {$limit} OFFSET {$offset}";

    return ['sql' => $sql, 'params' => $built['params']];
}

function vk_warranty_count(PDO $pdo, array $filters): int
{
    $built = vk_warranty_build_filters($filters);
    $sql = "SELECT COUNT(*)
            FROM warranty_records w
            JOIN customers c ON c.id = w.customer_id
            LEFT JOIN invoices i ON i.id = w.invoice_id
            WHERE {$built['where']}";
    $st = $pdo->prepare($sql);
    $st->execute($built['params']);

    return (int) $st->fetchColumn();
}

/**
 * @return array<string, mixed>|null
 */
function vk_warranty_fetch(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $st = $pdo->prepare(
        "SELECT w.*,
            c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, c.address AS customer_address,
            i.invoice_number, i.invoice_date, i.grand_total AS invoice_total,
            r.job_number AS repair_job_number, r.device_type AS repair_device_type, r.problem_description AS repair_problem,
            v.job_number AS cctv_job_number, v.location AS cctv_location
         FROM warranty_records w
         JOIN customers c ON c.id = w.customer_id
         LEFT JOIN invoices i ON i.id = w.invoice_id
         LEFT JOIN repair_jobs r ON r.id = w.repair_job_id
         LEFT JOIN cctv_installations v ON v.id = w.cctv_installation_id
         WHERE w.id = ?
         LIMIT 1"
    );
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function vk_warranty_mark_cancelled(PDO $pdo, int $id): bool
{
    $row = vk_warranty_fetch($pdo, $id);
    if (!$row) {
        return false;
    }
    $notes = (string) ($row['notes'] ?? '');
    if (vk_warranty_is_cancelled($notes)) {
        return true;
    }
    $newNotes = '[CANCELLED] ' . ltrim($notes);
    $st = $pdo->prepare('UPDATE warranty_records SET notes = ? WHERE id = ?');

    return $st->execute([$newNotes, $id]);
}

function vk_warranty_renew(PDO $pdo, int $id, ?int $extraDays = null): bool
{
    $row = vk_warranty_fetch($pdo, $id);
    if (!$row) {
        return false;
    }
    $start = strtotime((string) $row['start_date'] . ' 00:00:00');
    $end = strtotime((string) $row['end_date'] . ' 00:00:00');
    if ($start === false || $end === false) {
        return false;
    }
    $span = max(1, (int) floor(($end - $start) / 86400));
    $days = $extraDays !== null && $extraDays > 0 ? $extraDays : $span;
    $base = max($end, strtotime('today'));
    $newEnd = date('Y-m-d', strtotime('+' . $days . ' days', $base));
    $notes = (string) ($row['notes'] ?? '');
    if (vk_warranty_is_cancelled($notes)) {
        $notes = trim(preg_replace('/^\[CANCELLED\]\s*/i', '', $notes) ?? '');
    }
    $st = $pdo->prepare('UPDATE warranty_records SET end_date = ?, notes = ? WHERE id = ?');

    return $st->execute([$newEnd, $notes !== '' ? $notes : null, $id]);
}

function vk_warranty_email(PDO $pdo, int $id): array
{
    $row = vk_warranty_fetch($pdo, $id);
    if (!$row) {
        return ['ok' => false, 'message' => 'Warranty not found.'];
    }
    $email = trim((string) ($row['customer_email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Customer has no valid email address.'];
    }

    vk_bootstrap_module('mailer');
    if (!function_exists('vk_mailer_send')) {
        return ['ok' => false, 'message' => 'Mailer is not available.'];
    }

    $wrNo = vk_warranty_number((int) $row['id']);
    $status = vk_warranty_status($row);
    $subject = 'Warranty Certificate ' . $wrNo . ' — VK Network';
    $body = "Dear " . (string) $row['customer_name'] . ",\n\n"
        . "Your warranty details:\n"
        . "Warranty No: {$wrNo}\n"
        . "Product/Service: " . (string) $row['title'] . "\n"
        . "Type: " . (string) $row['warranty_type'] . "\n"
        . "Start: " . (string) $row['start_date'] . "\n"
        . "Expiry: " . (string) $row['end_date'] . "\n"
        . "Status: " . $status['label'] . "\n\n"
        . "Thank you for choosing VK Network.\n";

    $result = vk_mailer_send(
        $pdo,
        $email,
        $subject,
        $body,
        (string) $row['customer_name'],
        ['template_type' => 'warranty_notice', 'max_retries' => 1, 'smtp_timeout' => 10]
    );

    if (!empty($result['ok']) || !empty($result['success'])) {
        return ['ok' => true, 'message' => 'Warranty emailed to ' . $email];
    }

    return ['ok' => false, 'message' => (string) ($result['error'] ?? $result['message'] ?? 'Email failed.')];
}
