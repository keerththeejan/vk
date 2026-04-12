<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/users_schema.php';

$pdoBoot = db();
vk_ensure_users_management_schema($pdoBoot);
unset($pdoBoot);

if (!empty($_SESSION['user_id'])) {
    $dest = (($_SESSION['user_role'] ?? 'admin') === 'technician')
        ? BASE_URL . '/tech/index.php'
        : BASE_URL . '/modules/dashboard.php';
    header('Location: ' . $dest);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Enter username and password.';
    } else {
        try {
            $pdo = db();
            vk_ensure_users_management_schema($pdo);
            $cols = users_has_role_column($pdo)
                ? 'id, password_hash, role, technician_id, status'
                : 'id, password_hash';
            if (!str_contains($cols, 'status') && db_column_exists($pdo, 'users', 'status')) {
                $cols .= ', status';
            }
            $st = $pdo->prepare("SELECT $cols FROM users WHERE username = ? LIMIT 1");
            $st->execute([$username]);
            $row = $st->fetch();
            if ($row && password_verify($password, (string) $row['password_hash'])) {
                if (isset($row['status']) && (string) $row['status'] === 'inactive') {
                    $error = 'This account is inactive. Contact an administrator.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $row['id'];
                    $_SESSION['user_role'] = $row['role'] ?? 'admin';
                    $_SESSION['technician_id'] = isset($row['technician_id']) && $row['technician_id'] !== null
                        ? (int) $row['technician_id']
                        : null;
                    if (($_SESSION['user_role'] ?? 'admin') === 'technician') {
                        header('Location: ' . BASE_URL . '/tech/index.php');
                    } else {
                        header('Location: ' . BASE_URL . '/modules/dashboard.php');
                    }
                    exit;
                }
            }
        } catch (Throwable $e) {
            if (APP_DEBUG) {
                $error = 'Database error: ' . $e->getMessage();
            } else {
                $error = 'Unable to connect. Check configuration.';
            }
        }
        if ($error === '') {
            $error = 'Invalid credentials.';
        }
    }
}
$pageTitle = 'Sign in';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= htmlspecialchars(BASE_URL) ?>/assets/css/style.css" rel="stylesheet">
    <script>window.VK_BASE_URL = <?= json_encode(BASE_URL, JSON_THROW_ON_ERROR) ?>;</script>
</head>
<body>
<div class="vk-login-wrap d-flex align-items-center justify-content-center p-3">
    <div class="card shadow-lg border-0 vk-card" style="max-width: 420px; width: 100%;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center gap-2 mb-2">
                    <span class="vk-logo-sm rounded-circle d-flex align-items-center justify-content-center fw-bold text-white">VK</span>
                    <span class="vk-brand-red fw-bold fs-4">IT Network</span>
                </div>
                <p class="text-muted small mb-0 text-uppercase" style="letter-spacing: 0.12em;">Service &amp; billing — sign in</p>
            </div>
            <?php
            $flash = flash_get();
            if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'success' ? 'success' : 'warning') ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" action="" autocomplete="off" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control form-control-lg" type="text" name="username" id="username" required maxlength="64" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control form-control-lg" type="password" name="password" id="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100" style="background: linear-gradient(90deg, #0a2a5c, #134a9e); border: none;">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign in
                </button>
            </form>
                <p class="small text-muted mt-4 mb-0 text-center">Admin: <code>admin</code> / <code>Admin@123</code> · Tech: <code>tech</code> / <code>Admin@123</code> (change after import)</p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= htmlspecialchars(BASE_URL) ?>/assets/js/app.js"></script>
</body>
</html>
