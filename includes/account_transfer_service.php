<?php
declare(strict_types=1);

/**
 * Account transfer voucher helpers (UI + optional metadata).
 * Core double-entry posting still uses ledger_apply() unchanged.
 */

function vk_ensure_account_transfers_schema(PDO $pdo): void
{
    if (!db_table_exists($pdo, 'account_transfers')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS account_transfers (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              from_account_id INT UNSIGNED NOT NULL,
              to_account_id INT UNSIGNED NOT NULL,
              amount DECIMAL(14,2) NOT NULL,
              note VARCHAR(512) DEFAULT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_xfer_from (from_account_id),
              INDEX idx_xfer_to (to_account_id),
              INDEX idx_xfer_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    $cols = [
        'reference_no' => "ALTER TABLE account_transfers ADD COLUMN reference_no VARCHAR(64) NULL DEFAULT NULL AFTER note",
        'voucher_date' => "ALTER TABLE account_transfers ADD COLUMN voucher_date DATE NULL DEFAULT NULL AFTER reference_no",
        'transaction_type' => "ALTER TABLE account_transfers ADD COLUMN transaction_type VARCHAR(64) NOT NULL DEFAULT 'Account Transfer' AFTER voucher_date",
        'branch' => "ALTER TABLE account_transfers ADD COLUMN branch VARCHAR(120) NULL DEFAULT NULL AFTER transaction_type",
        'department' => "ALTER TABLE account_transfers ADD COLUMN department VARCHAR(120) NULL DEFAULT NULL AFTER branch",
        'cost_centre' => "ALTER TABLE account_transfers ADD COLUMN cost_centre VARCHAR(120) NULL DEFAULT NULL AFTER department",
        'currency' => "ALTER TABLE account_transfers ADD COLUMN currency VARCHAR(8) NOT NULL DEFAULT 'LKR' AFTER cost_centre",
        'from_narration' => "ALTER TABLE account_transfers ADD COLUMN from_narration VARCHAR(512) NULL DEFAULT NULL AFTER currency",
        'to_narration' => "ALTER TABLE account_transfers ADD COLUMN to_narration VARCHAR(512) NULL DEFAULT NULL AFTER from_narration",
        'status' => "ALTER TABLE account_transfers ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'posted' AFTER to_narration",
        'prepared_by' => "ALTER TABLE account_transfers ADD COLUMN prepared_by VARCHAR(120) NULL DEFAULT NULL AFTER status",
        'approved_by' => "ALTER TABLE account_transfers ADD COLUMN approved_by VARCHAR(120) NULL DEFAULT NULL AFTER prepared_by",
        'approved_at' => "ALTER TABLE account_transfers ADD COLUMN approved_at DATETIME NULL DEFAULT NULL AFTER approved_by",
        'created_by' => "ALTER TABLE account_transfers ADD COLUMN created_by INT UNSIGNED NULL DEFAULT NULL AFTER approved_at",
        'modified_by' => "ALTER TABLE account_transfers ADD COLUMN modified_by INT UNSIGNED NULL DEFAULT NULL AFTER created_by",
        'modified_at' => "ALTER TABLE account_transfers ADD COLUMN modified_at DATETIME NULL DEFAULT NULL AFTER modified_by",
        'attachments_json' => "ALTER TABLE account_transfers ADD COLUMN attachments_json LONGTEXT NULL DEFAULT NULL AFTER modified_at",
        'remarks' => "ALTER TABLE account_transfers ADD COLUMN remarks VARCHAR(1000) NULL DEFAULT NULL AFTER attachments_json",
    ];
    foreach ($cols as $col => $ddl) {
        if (!db_column_exists($pdo, 'account_transfers', $col)) {
            try {
                $pdo->exec($ddl);
            } catch (Throwable $e) {
                // ignore race / unsupported
            }
        }
    }

    foreach ([
        'CREATE INDEX idx_xfer_status ON account_transfers (status, created_at)',
        'CREATE INDEX idx_xfer_ref ON account_transfers (reference_no)',
        'CREATE INDEX idx_xfer_date ON account_transfers (voucher_date)',
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
        }
    }
}

function vk_transfer_voucher_no(int $id): string
{
    return 'TRF-' . str_pad((string) max(0, $id), 6, '0', STR_PAD_LEFT);
}

/** @return list<array<string,mixed>> */
function vk_transfer_accounts_list(PDO $pdo): array
{
    return $pdo->query(
        'SELECT a.id, a.code, a.name, a.account_type, a.current_balance,
                c.name AS customer_name
         FROM accounts a
         LEFT JOIN customers c ON c.id = a.customer_id
         ORDER BY a.account_type DESC, a.code ASC'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string,mixed>|null */
function vk_transfer_get(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT t.*,
                fa.code AS from_code, fa.name AS from_name, fa.account_type AS from_type, fa.current_balance AS from_balance,
                ta.code AS to_code, ta.name AS to_name, ta.account_type AS to_type, ta.current_balance AS to_balance
         FROM account_transfers t
         JOIN accounts fa ON fa.id = t.from_account_id
         JOIN accounts ta ON ta.id = t.to_account_id
         WHERE t.id = ?
         LIMIT 1'
    );
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * @param array<string,mixed> $filters
 * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int}
 */
function vk_transfer_search(PDO $pdo, array $filters, int $page = 1, int $perPage = 25): array
{
    $where = ['1=1'];
    $params = [];

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . $q . '%';
        $parts = ['CAST(t.id AS CHAR) LIKE ?', 'fa.code LIKE ?', 'fa.name LIKE ?', 'ta.code LIKE ?', 'ta.name LIKE ?', 't.note LIKE ?'];
        $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
        if (db_column_exists($pdo, 'account_transfers', 'reference_no')) {
            $parts[] = 't.reference_no LIKE ?';
            $params[] = $like;
        }
        if (db_column_exists($pdo, 'account_transfers', 'prepared_by')) {
            $parts[] = 't.prepared_by LIKE ?';
            $params[] = $like;
        }
        if (preg_match('/^TRF-?0*(\d+)$/i', $q, $m)) {
            $parts[] = 't.id = ?';
            $params[] = (int) $m[1];
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    if (!empty($filters['status']) && db_column_exists($pdo, 'account_transfers', 'status')) {
        $where[] = 't.status = ?';
        $params[] = (string) $filters['status'];
    }
    if (!empty($filters['from_date'])) {
        $where[] = 'DATE(COALESCE(t.voucher_date, t.created_at)) >= ?';
        $params[] = (string) $filters['from_date'];
    }
    if (!empty($filters['to_date'])) {
        $where[] = 'DATE(COALESCE(t.voucher_date, t.created_at)) <= ?';
        $params[] = (string) $filters['to_date'];
    }
    if (isset($filters['amount_min']) && $filters['amount_min'] !== '' && $filters['amount_min'] !== null) {
        $where[] = 't.amount >= ?';
        $params[] = (float) $filters['amount_min'];
    }
    if (isset($filters['amount_max']) && $filters['amount_max'] !== '' && $filters['amount_max'] !== null) {
        $where[] = 't.amount <= ?';
        $params[] = (float) $filters['amount_max'];
    }
    if (!empty($filters['account_id'])) {
        $where[] = '(t.from_account_id = ? OR t.to_account_id = ?)';
        $params[] = (int) $filters['account_id'];
        $params[] = (int) $filters['account_id'];
    }

    $sqlWhere = implode(' AND ', $where);
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM account_transfers t JOIN accounts fa ON fa.id = t.from_account_id JOIN accounts ta ON ta.id = t.to_account_id WHERE {$sqlWhere}");
    $cnt->execute($params);
    $total = (int) $cnt->fetchColumn();
    $pag = paginate($total, $page, $perPage);

    $st = $pdo->prepare(
        "SELECT t.*, fa.code AS from_code, fa.name AS from_name, ta.code AS to_code, ta.name AS to_name
         FROM account_transfers t
         JOIN accounts fa ON fa.id = t.from_account_id
         JOIN accounts ta ON ta.id = t.to_account_id
         WHERE {$sqlWhere}
         ORDER BY t.id DESC
         LIMIT {$pag['perPage']} OFFSET {$pag['offset']}"
    );
    $st->execute($params);

    return [
        'rows' => $st->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'total' => $total,
        'page' => $pag['page'],
        'pages' => $pag['pages'],
    ];
}

/**
 * @param array<string,mixed> $data
 * @return array{ok:bool,error?:string,id?:int,voucher_no?:string}
 */
function vk_transfer_validate(array $data, PDO $pdo, bool $checkBalance = true): array
{
    $from = (int) ($data['from_account_id'] ?? 0);
    $to = (int) ($data['to_account_id'] ?? 0);
    $amount = (float) ($data['amount'] ?? 0);
    $debit = (float) ($data['debit_amount'] ?? $amount);
    $credit = (float) ($data['credit_amount'] ?? $amount);

    if ($from <= 0 || $to <= 0) {
        return ['ok' => false, 'error' => 'Select source and destination accounts.'];
    }
    if ($from === $to) {
        return ['ok' => false, 'error' => 'Source and destination accounts must be different.'];
    }
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Transfer amount must be greater than zero.'];
    }
    if (abs($debit - $credit) > 0.0001 || abs($debit - $amount) > 0.0001) {
        return ['ok' => false, 'error' => 'Debit amount must equal credit amount.'];
    }

    $st = $pdo->prepare('SELECT id, current_balance FROM accounts WHERE id = ?');
    $st->execute([$from]);
    $aFrom = $st->fetch(PDO::FETCH_ASSOC);
    $st->execute([$to]);
    $aTo = $st->fetch(PDO::FETCH_ASSOC);
    if (!$aFrom || !$aTo) {
        return ['ok' => false, 'error' => 'Source or destination account does not exist.'];
    }
    if ($checkBalance && (float) $aFrom['current_balance'] < $amount - 0.0001) {
        return ['ok' => false, 'error' => 'Source account balance is insufficient.'];
    }

    return ['ok' => true];
}

/**
 * @param list<array{name:string,path:string,type:string}> $attachments
 * @return array{ok:bool,error?:string,id?:int,voucher_no?:string}
 */
function vk_transfer_save_draft(PDO $pdo, array $data, int $actorId, string $actorName, array $attachments = []): array
{
    $v = vk_transfer_validate($data, $pdo, false);
    if (!$v['ok']) {
        return $v;
    }

    $meta = vk_transfer_meta_from_request($data, $actorName);
    $meta['status'] = 'pending';
    $meta['created_by'] = $actorId > 0 ? $actorId : null;
    $meta['attachments_json'] = $attachments !== [] ? json_encode($attachments, JSON_UNESCAPED_UNICODE) : null;

    $id = vk_transfer_insert_row($pdo, $data, $meta);
    return ['ok' => true, 'id' => $id, 'voucher_no' => vk_transfer_voucher_no($id)];
}

/**
 * Posts a transfer using the original VK double-entry rules:
 * - credit source (reduce balance)
 * - debit destination (increase balance)
 *
 * @param list<array{name:string,path:string,type:string}> $attachments
 * @return array{ok:bool,error?:string,id?:int,voucher_no?:string}
 */
function vk_transfer_post(PDO $pdo, array $data, int $actorId, string $actorName, array $attachments = [], ?int $existingDraftId = null): array
{
    $v = vk_transfer_validate($data, $pdo, true);
    if (!$v['ok']) {
        return $v;
    }

    $from = (int) $data['from_account_id'];
    $to = (int) $data['to_account_id'];
    $amount = round((float) $data['amount'], 2);
    $note = trim((string) ($data['note'] ?? $data['remarks'] ?? ''));
    $fromNarr = trim((string) ($data['from_narration'] ?? ''));
    $toNarr = trim((string) ($data['to_narration'] ?? ''));
    $outDesc = 'Transfer out' . ($fromNarr !== '' ? ': ' . $fromNarr : ($note !== '' ? ': ' . $note : ''));
    $inDesc = 'Transfer in' . ($toNarr !== '' ? ': ' . $toNarr : ($note !== '' ? ': ' . $note : ''));

    $meta = vk_transfer_meta_from_request($data, $actorName);
    $meta['status'] = 'posted';
    $meta['approved_by'] = $actorName !== '' ? $actorName : ($meta['approved_by'] ?? null);
    $meta['approved_at'] = date('Y-m-d H:i:s');
    $meta['created_by'] = $actorId > 0 ? $actorId : null;
    $meta['modified_by'] = $actorId > 0 ? $actorId : null;
    $meta['modified_at'] = date('Y-m-d H:i:s');
    if ($attachments !== []) {
        $meta['attachments_json'] = json_encode($attachments, JSON_UNESCAPED_UNICODE);
    }

    try {
        $pdo->beginTransaction();

        // Same locking / balance check as original transfer.php
        $st = $pdo->prepare('SELECT id, current_balance FROM accounts WHERE id = ? FOR UPDATE');
        $st->execute([$from]);
        $aFrom = $st->fetch(PDO::FETCH_ASSOC);
        $st->execute([$to]);
        $aTo = $st->fetch(PDO::FETCH_ASSOC);
        if (!$aFrom || !$aTo) {
            throw new RuntimeException('Invalid account.');
        }
        if ((float) $aFrom['current_balance'] < $amount - 0.0001) {
            throw new RuntimeException('Source account balance is insufficient.');
        }

        if ($existingDraftId !== null && $existingDraftId > 0) {
            $xferId = $existingDraftId;
            vk_transfer_update_row($pdo, $xferId, $data, $meta);
        } else {
            $xferId = vk_transfer_insert_row($pdo, $data, $meta);
        }

        // Preserve original ledger semantics exactly
        ledger_apply($pdo, $from, 0, $amount, $outDesc, null, null, $xferId);
        ledger_apply($pdo, $to, $amount, 0, $inDesc, null, null, $xferId);

        $pdo->commit();
        return ['ok' => true, 'id' => $xferId, 'voucher_no' => vk_transfer_voucher_no($xferId)];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Transfer failed.'];
    }
}

/**
 * @param array<string,mixed> $data
 * @return array<string,mixed>
 */
function vk_transfer_meta_from_request(array $data, string $actorName): array
{
    return [
        'reference_no' => trim((string) ($data['reference_no'] ?? '')) ?: null,
        'voucher_date' => trim((string) ($data['voucher_date'] ?? '')) ?: date('Y-m-d'),
        'transaction_type' => trim((string) ($data['transaction_type'] ?? 'Account Transfer')) ?: 'Account Transfer',
        'branch' => trim((string) ($data['branch'] ?? '')) ?: null,
        'department' => trim((string) ($data['department'] ?? '')) ?: null,
        'cost_centre' => trim((string) ($data['cost_centre'] ?? '')) ?: null,
        'currency' => trim((string) ($data['currency'] ?? 'LKR')) ?: 'LKR',
        'from_narration' => trim((string) ($data['from_narration'] ?? '')) ?: null,
        'to_narration' => trim((string) ($data['to_narration'] ?? '')) ?: null,
        'remarks' => trim((string) ($data['remarks'] ?? $data['note'] ?? '')) ?: null,
        'prepared_by' => trim((string) ($data['prepared_by'] ?? $actorName)) ?: null,
        'approved_by' => trim((string) ($data['approved_by'] ?? '')) ?: null,
        'status' => trim((string) ($data['status'] ?? 'posted')) ?: 'posted',
    ];
}

/**
 * @param array<string,mixed> $data
 * @param array<string,mixed> $meta
 */
function vk_transfer_insert_row(PDO $pdo, array $data, array $meta): int
{
    $from = (int) $data['from_account_id'];
    $to = (int) $data['to_account_id'];
    $amount = round((float) $data['amount'], 2);
    $note = trim((string) ($meta['remarks'] ?? $data['note'] ?? '')) ?: null;

    if (!db_column_exists($pdo, 'account_transfers', 'reference_no')) {
        $pdo->prepare(
            'INSERT INTO account_transfers (from_account_id, to_account_id, amount, note) VALUES (?,?,?,?)'
        )->execute([$from, $to, $amount, $note]);
        return (int) $pdo->lastInsertId();
    }

    $pdo->prepare(
        'INSERT INTO account_transfers
            (from_account_id, to_account_id, amount, note, reference_no, voucher_date, transaction_type,
             branch, department, cost_centre, currency, from_narration, to_narration, status,
             prepared_by, approved_by, approved_at, created_by, modified_by, modified_at, attachments_json, remarks)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $from,
        $to,
        $amount,
        $note,
        $meta['reference_no'] ?? null,
        $meta['voucher_date'] ?? date('Y-m-d'),
        $meta['transaction_type'] ?? 'Account Transfer',
        $meta['branch'] ?? null,
        $meta['department'] ?? null,
        $meta['cost_centre'] ?? null,
        $meta['currency'] ?? 'LKR',
        $meta['from_narration'] ?? null,
        $meta['to_narration'] ?? null,
        $meta['status'] ?? 'posted',
        $meta['prepared_by'] ?? null,
        $meta['approved_by'] ?? null,
        $meta['approved_at'] ?? null,
        $meta['created_by'] ?? null,
        $meta['modified_by'] ?? null,
        $meta['modified_at'] ?? null,
        $meta['attachments_json'] ?? null,
        $meta['remarks'] ?? $note,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @param array<string,mixed> $data
 * @param array<string,mixed> $meta
 */
function vk_transfer_update_row(PDO $pdo, int $id, array $data, array $meta): void
{
    if (!db_column_exists($pdo, 'account_transfers', 'reference_no')) {
        $pdo->prepare(
            'UPDATE account_transfers SET from_account_id=?, to_account_id=?, amount=?, note=? WHERE id=?'
        )->execute([
            (int) $data['from_account_id'],
            (int) $data['to_account_id'],
            round((float) $data['amount'], 2),
            trim((string) ($meta['remarks'] ?? $data['note'] ?? '')) ?: null,
            $id,
        ]);
        return;
    }

    $pdo->prepare(
        'UPDATE account_transfers SET
            from_account_id=?, to_account_id=?, amount=?, note=?, reference_no=?, voucher_date=?, transaction_type=?,
            branch=?, department=?, cost_centre=?, currency=?, from_narration=?, to_narration=?, status=?,
            prepared_by=?, approved_by=?, approved_at=?, modified_by=?, modified_at=?,
            attachments_json=COALESCE(?, attachments_json), remarks=?
         WHERE id=?'
    )->execute([
        (int) $data['from_account_id'],
        (int) $data['to_account_id'],
        round((float) $data['amount'], 2),
        trim((string) ($meta['remarks'] ?? $data['note'] ?? '')) ?: null,
        $meta['reference_no'] ?? null,
        $meta['voucher_date'] ?? date('Y-m-d'),
        $meta['transaction_type'] ?? 'Account Transfer',
        $meta['branch'] ?? null,
        $meta['department'] ?? null,
        $meta['cost_centre'] ?? null,
        $meta['currency'] ?? 'LKR',
        $meta['from_narration'] ?? null,
        $meta['to_narration'] ?? null,
        $meta['status'] ?? 'posted',
        $meta['prepared_by'] ?? null,
        $meta['approved_by'] ?? null,
        $meta['approved_at'] ?? null,
        $meta['modified_by'] ?? null,
        $meta['modified_at'] ?? null,
        $meta['attachments_json'] ?? null,
        $meta['remarks'] ?? null,
        $id,
    ]);
}

/**
 * @return list<array{name:string,path:string,type:string}>
 */
function vk_transfer_handle_uploads(array $files): array
{
    $out = [];
    if (empty($files['name']) || !is_array($files['name'])) {
        return $out;
    }
    $dir = ROOT_PATH . '/storage/transfers';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'doc', 'docx', 'xls', 'xlsx'];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            continue;
        }
        $orig = (string) ($files['name'][$i] ?? 'file');
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            continue;
        }
        $tmp = (string) ($files['tmp_name'][$i] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            continue;
        }
        $safe = 'trf_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . '/' . $safe;
        if (!@move_uploaded_file($tmp, $dest)) {
            continue;
        }
        $type = match ($ext) {
            'pdf' => 'invoice',
            'png', 'jpg', 'jpeg', 'webp', 'gif' => 'receipt',
            default => 'supporting',
        };
        $out[] = [
            'name' => $orig,
            'path' => 'storage/transfers/' . $safe,
            'type' => $type,
        ];
    }
    return $out;
}

function vk_transfer_status_badge(string $status): string
{
    return match (strtolower($status)) {
        'posted', 'approved' => 'success',
        'pending', 'draft' => 'warning',
        'rejected' => 'danger',
        'cancelled' => 'secondary',
        default => 'primary',
    };
}

/**
 * @param array<string,mixed> $filters
 * @return list<array<string,mixed>>
 */
function vk_transfer_report_rows(PDO $pdo, array $filters): array
{
    $res = vk_transfer_search($pdo, $filters, 1, 5000);
    return $res['rows'];
}
