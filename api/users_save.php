<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_admin();

$pdo = db();
require_once dirname(__DIR__) . '/includes/users_schema.php';
vk_ensure_users_management_schema($pdo);

if (($_SESSION['user_role'] ?? 'admin') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON'], JSON_THROW_ON_ERROR);
    exit;
}

$id = (int) ($data['id'] ?? 0);
$username = trim((string) ($data['username'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$phone = trim((string) ($data['phone'] ?? ''));
$fullname = trim((string) ($data['fullname'] ?? ''));
$password = (string) ($data['password'] ?? '');
$role = strtolower(trim((string) ($data['role'] ?? 'staff')));
$status = strtolower(trim((string) ($data['status'] ?? 'active')));
$technicianId = isset($data['technician_id']) && $data['technician_id'] !== '' && $data['technician_id'] !== null
    ? (int) $data['technician_id']
    : null;

if (!in_array($role, ['admin', 'staff', 'technician'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid role.'], JSON_THROW_ON_ERROR);
    exit;
}
if (!in_array($status, ['active', 'inactive'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid status.'], JSON_THROW_ON_ERROR);
    exit;
}
if ($username === '' || !preg_match('/^[a-zA-Z0-9._-]{1,64}$/', $username)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Username: 1–64 letters, numbers, dot, underscore, hyphen.'], JSON_THROW_ON_ERROR);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid email address.'], JSON_THROW_ON_ERROR);
    exit;
}
if (mb_strlen($fullname) > 128) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Name is too long.'], JSON_THROW_ON_ERROR);
    exit;
}
if ($role === 'technician' && ($technicianId === null || $technicianId <= 0)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Technician role requires a linked technician profile.'], JSON_THROW_ON_ERROR);
    exit;
}
if ($role !== 'technician') {
    $technicianId = null;
}

$chkTech = $pdo->prepare('SELECT id FROM technicians WHERE id = ? AND active = 1 LIMIT 1');
if ($technicianId !== null) {
    $chkTech->execute([$technicianId]);
    if (!$chkTech->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid technician.'], JSON_THROW_ON_ERROR);
        exit;
    }
}

$sessionId = (int) ($_SESSION['user_id'] ?? 0);

if ($id <= 0) {
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Password must be at least 8 characters.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $uq = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $uq->execute([$username]);
    if ($uq->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Username already taken.'], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($email !== '') {
        $eq = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $eq->execute([$email]);
        if ($eq->fetchColumn()) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Email already in use.'], JSON_THROW_ON_ERROR);
            exit;
        }
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare(
        'INSERT INTO users (username, email, phone, password_hash, fullname, role, technician_id, status)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $username,
        $email === '' ? null : $email,
        $phone === '' ? null : $phone,
        $hash,
        $fullname === '' ? null : $fullname,
        $role,
        $technicianId,
        $status,
    ]);
    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()], JSON_THROW_ON_ERROR);
    exit;
}

$old = $pdo->prepare('SELECT id, username, role, status FROM users WHERE id = ? LIMIT 1');
$old->execute([$id]);
$oldRow = $old->fetch(PDO::FETCH_ASSOC);
if (!$oldRow) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'User not found.'], JSON_THROW_ON_ERROR);
    exit;
}

$wasActiveAdmin = ((string) ($oldRow['role'] ?? '') === 'admin') && ((string) ($oldRow['status'] ?? 'active') === 'active');
$willBeActiveAdmin = ($role === 'admin' && $status === 'active');
if ($wasActiveAdmin && !$willBeActiveAdmin) {
    $cnt = $pdo->prepare(
        "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id != ?"
    );
    $cnt->execute([$id]);
    if ((int) $cnt->fetchColumn() === 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Cannot remove the last active administrator.'], JSON_THROW_ON_ERROR);
        exit;
    }
}

$uq = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
$uq->execute([$username, $id]);
if ($uq->fetchColumn()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Username already taken.'], JSON_THROW_ON_ERROR);
    exit;
}
if ($email !== '') {
    $eq = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
    $eq->execute([$email, $id]);
    if ($eq->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Email already in use.'], JSON_THROW_ON_ERROR);
        exit;
    }
}

if ($password !== '') {
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Password must be at least 8 characters.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare(
        'UPDATE users SET username = ?, email = ?, phone = ?, password_hash = ?, fullname = ?, role = ?, technician_id = ?, status = ? WHERE id = ?'
    )->execute([
        $username,
        $email === '' ? null : $email,
        $phone === '' ? null : $phone,
        $hash,
        $fullname === '' ? null : $fullname,
        $role,
        $technicianId,
        $status,
        $id,
    ]);
} else {
    $pdo->prepare(
        'UPDATE users SET username = ?, email = ?, phone = ?, fullname = ?, role = ?, technician_id = ?, status = ? WHERE id = ?'
    )->execute([
        $username,
        $email === '' ? null : $email,
        $phone === '' ? null : $phone,
        $fullname === '' ? null : $fullname,
        $role,
        $technicianId,
        $status,
        $id,
    ]);
}

if ($id === $sessionId) {
    $_SESSION['user_role'] = $role;
    if ($role === 'technician') {
        $_SESSION['technician_id'] = $technicianId;
    } else {
        $_SESSION['technician_id'] = null;
    }
}

echo json_encode(['ok' => true, 'id' => $id], JSON_THROW_ON_ERROR);
