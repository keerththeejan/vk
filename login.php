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

if (empty($_SESSION['login_csrf_token']) || !is_string($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals((string) $_SESSION['login_csrf_token'], $csrfToken)) {
        $error = 'Your secure sign-in session expired. Refresh and try again.';
        $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
    } elseif ($username === '' || $password === '') {
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
                    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
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

$pageTitle = 'Secure Sign In';
$loginLogo = getLogo('main');
$loginCompany = vk_app_setting('company_name', 'VK Network');
$brandInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $loginCompany) ?: 'VK', 0, 2));
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script>window.VK_BASE_URL = <?= json_encode(BASE_URL, JSON_THROW_ON_ERROR) ?>;</script>
    <style>
        :root {
            --vk-bg: #050914;
            --vk-panel: rgba(12, 20, 38, 0.74);
            --vk-panel-strong: rgba(17, 29, 54, 0.9);
            --vk-border: rgba(146, 178, 255, 0.2);
            --vk-text: #f5f8ff;
            --vk-muted: #9ba8c5;
            --vk-blue: #42a5ff;
            --vk-blue-2: #246bfe;
            --vk-cyan: #67e8f9;
            --vk-danger: #ff6b8a;
            --vk-success: #35d399;
            --vk-radius: 28px;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            font-family: Inter, Poppins, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--vk-text);
            background:
                radial-gradient(circle at 18% 15%, rgba(36, 107, 254, 0.3), transparent 26rem),
                radial-gradient(circle at 86% 78%, rgba(103, 232, 249, 0.16), transparent 28rem),
                linear-gradient(135deg, #050914 0%, #081121 44%, #060b17 100%);
            overflow-x: hidden;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
        }

        body::before {
            opacity: 0.22;
            background-image:
                linear-gradient(rgba(96, 165, 250, 0.16) 1px, transparent 1px),
                linear-gradient(90deg, rgba(96, 165, 250, 0.16) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: radial-gradient(circle at center, #000 0%, transparent 74%);
            animation: grid-drift 22s linear infinite;
        }

        body::after {
            background:
                linear-gradient(120deg, transparent 20%, rgba(66, 165, 255, 0.07) 45%, transparent 70%),
                radial-gradient(circle at 50% 50%, transparent 0%, rgba(5, 9, 20, 0.72) 78%);
        }

        @keyframes grid-drift {
            from {
                background-position: 0 0;
            }
            to {
                background-position: 64px 64px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.001ms !important;
            }
        }

        .vk-login-page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            min-height: 100svh;
            display: grid;
            place-items: center;
            padding: clamp(1rem, 3vw, 2.5rem);
        }

        .vk-login-shell {
            width: min(100%, 468px);
        }

        .vk-login-card {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--vk-border);
            border-radius: var(--vk-radius);
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.035)),
                var(--vk-panel);
            box-shadow:
                0 28px 90px rgba(0, 0, 0, 0.44),
                0 0 64px rgba(36, 107, 254, 0.25),
                inset 0 1px 0 rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(24px) saturate(142%);
            -webkit-backdrop-filter: blur(24px) saturate(142%);
        }

        .vk-login-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(135deg, rgba(103, 232, 249, 0.14), transparent 28%),
                linear-gradient(315deg, rgba(66, 165, 255, 0.16), transparent 34%);
            opacity: 0.9;
            pointer-events: none;
        }

        .vk-card-inner {
            position: relative;
            padding: clamp(1.5rem, 4.5vw, 2.65rem);
        }

        .vk-brand {
            display: grid;
            justify-items: center;
            text-align: center;
            gap: 0.9rem;
            margin-bottom: 1.75rem;
        }

        .vk-logo-mark {
            display: grid;
            place-items: center;
            width: 76px;
            height: 76px;
            border: 1px solid rgba(164, 202, 255, 0.34);
            border-radius: 22px;
            background:
                linear-gradient(145deg, rgba(66, 165, 255, 0.22), rgba(103, 232, 249, 0.08)),
                rgba(255, 255, 255, 0.06);
            box-shadow:
                0 16px 40px rgba(36, 107, 254, 0.26),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .vk-logo-mark img {
            display: block;
            max-width: 58px;
            max-height: 58px;
            object-fit: contain;
        }

        .vk-logo-fallback {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #fff;
        }

        .vk-company-name {
            margin: 0;
            font-size: clamp(1.62rem, 5vw, 2rem);
            font-weight: 800;
            letter-spacing: 0;
            line-height: 1.08;
        }

        .vk-company-name span {
            background: linear-gradient(135deg, #ffffff 10%, #bcdcff 48%, #67e8f9 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .vk-subtitle {
            margin: 0.45rem 0 0;
            color: var(--vk-muted);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .vk-secure-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: #b9c9e8;
            font-size: 0.78rem;
            margin-top: 0.7rem;
        }

        .vk-secure-row i {
            color: var(--vk-success);
        }

        .vk-alert {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            border: 1px solid rgba(255, 107, 138, 0.32);
            border-radius: 16px;
            color: #ffdce4;
            background: rgba(255, 107, 138, 0.1);
            padding: 0.9rem 1rem;
            font-size: 0.92rem;
            margin-bottom: 1rem;
        }

        .vk-alert-success {
            border-color: rgba(53, 211, 153, 0.32);
            color: #d7fff1;
            background: rgba(53, 211, 153, 0.1);
        }

        .vk-alert-warning {
            border-color: rgba(250, 204, 21, 0.32);
            color: #fff4bd;
            background: rgba(250, 204, 21, 0.1);
        }

        .vk-form {
            display: grid;
            gap: 1rem;
        }

        .vk-field {
            position: relative;
        }

        .vk-field-icon {
            position: absolute;
            left: 1rem;
            top: 30px;
            z-index: 5;
            transform: translateY(-50%);
            color: #8fb9ff;
            font-size: 1rem;
            pointer-events: none;
            transition: color 180ms ease, transform 180ms ease;
        }

        .vk-field .form-control {
            height: 60px;
            border: 1px solid rgba(164, 202, 255, 0.22);
            border-radius: 17px;
            color: var(--vk-text);
            background: rgba(5, 12, 26, 0.68);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
            padding-left: 3rem;
            padding-right: 1rem;
            transition: border-color 180ms ease, box-shadow 180ms ease, background 180ms ease, transform 180ms ease;
        }

        .vk-field .form-floating > label {
            left: 2rem;
            color: #9daccc;
            font-weight: 500;
        }

        .vk-field .form-floating > .form-control:focus,
        .vk-field .form-floating > .form-control:not(:placeholder-shown) {
            padding-top: 1.72rem;
            padding-bottom: 0.58rem;
        }

        .vk-field .form-floating > .form-control:focus ~ label,
        .vk-field .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #b9d7ff;
            opacity: 1;
            transform: scale(0.84) translateY(-0.7rem) translateX(0.18rem);
        }

        .vk-field .form-control:focus {
            border-color: rgba(103, 232, 249, 0.75);
            background: rgba(8, 17, 36, 0.88);
            box-shadow:
                0 0 0 4px rgba(66, 165, 255, 0.14),
                0 14px 36px rgba(36, 107, 254, 0.18);
            color: #fff;
            outline: 0;
        }

        .vk-field:focus-within .vk-field-icon {
            color: var(--vk-cyan);
            transform: translateY(-50%) scale(1.06);
        }

        .vk-field .form-control.is-invalid {
            border-color: rgba(255, 107, 138, 0.76);
            background-image: none;
        }

        .vk-password-input {
            padding-right: 3.25rem !important;
        }

        .vk-password-toggle {
            position: absolute;
            right: 0.7rem;
            top: 30px;
            z-index: 8;
            display: inline-grid;
            place-items: center;
            width: 2.45rem;
            height: 2.45rem;
            border: 0;
            border-radius: 13px;
            color: #adc5e9;
            background: transparent;
            transform: translateY(-50%);
            transition: color 180ms ease, background 180ms ease, transform 180ms ease;
        }

        .vk-password-toggle:hover,
        .vk-password-toggle:focus-visible {
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-50%) scale(1.03);
            outline: 0;
        }

        .invalid-feedback {
            margin: 0.45rem 0 0 0.25rem;
            color: #ffb6c6;
            font-size: 0.82rem;
        }

        .vk-submit {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            min-height: 58px;
            border: 0;
            border-radius: 18px;
            color: #fff;
            font-weight: 800;
            letter-spacing: 0;
            background: linear-gradient(135deg, #1f6fff 0%, #35b7ff 54%, #67e8f9 100%);
            box-shadow:
                0 18px 44px rgba(36, 107, 254, 0.34),
                inset 0 1px 0 rgba(255, 255, 255, 0.36);
            overflow: hidden;
            transition: transform 180ms ease, box-shadow 180ms ease, filter 180ms ease;
        }

        .vk-submit::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent, rgba(255, 255, 255, 0.32), transparent);
            transform: translateX(-120%);
            transition: transform 500ms ease;
        }

        .vk-submit:hover,
        .vk-submit:focus-visible {
            color: #fff;
            filter: saturate(1.08) brightness(1.03);
            transform: translateY(-2px);
            box-shadow:
                0 24px 58px rgba(36, 107, 254, 0.42),
                0 0 38px rgba(103, 232, 249, 0.18);
            outline: 0;
        }

        .vk-submit:hover::before,
        .vk-submit:focus-visible::before {
            transform: translateX(120%);
        }

        .vk-submit:disabled {
            cursor: wait;
            opacity: 0.88;
            transform: none;
        }

        .vk-submit .spinner-border {
            width: 1.08rem;
            height: 1.08rem;
            border-width: 0.15em;
        }

        .vk-footer {
            margin: 1.25rem 0 0;
            text-align: center;
            color: rgba(216, 226, 245, 0.66);
            font-size: 0.82rem;
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-text-fill-color: #f5f8ff !important;
            caret-color: #f5f8ff;
            box-shadow: 0 0 0 1000px rgba(8, 17, 36, 0.96) inset !important;
            transition: background-color 9999s ease-out 0s;
        }

        .form-control::selection {
            color: #041126;
            background: #95ddff;
        }

        @media (max-width: 575.98px) {
            .vk-login-page {
                padding: 0.9rem;
                align-items: center;
            }

            .vk-card-inner {
                padding: 1.35rem;
            }

            .vk-login-card {
                border-radius: 22px;
            }

            .vk-logo-mark {
                width: 66px;
                height: 66px;
                border-radius: 19px;
            }

            .vk-field .form-control {
                height: 58px;
            }

            .vk-field-icon,
            .vk-password-toggle {
                top: 29px;
            }
        }
    </style>
</head>
<body>
<main class="vk-login-page">
    <section class="vk-login-shell" aria-label="Secure sign in">
        <div class="vk-login-card">
            <div class="vk-card-inner">
                <header class="vk-brand">
                    <div class="vk-logo-mark" aria-hidden="true">
                        <?php if ($loginLogo !== ''): ?>
                            <img src="<?= e($loginLogo) ?>" alt="">
                        <?php else: ?>
                            <span class="vk-logo-fallback"><?= e($brandInitials) ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h1 class="vk-company-name"><span><?= e((string) $loginCompany) ?></span></h1>
                        <p class="vk-subtitle">Secure Service &amp; Billing Portal</p>
                        <div class="vk-secure-row">
                            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                            <span>Encrypted access gateway</span>
                        </div>
                    </div>
                </header>

                <?php
                $flash = flash_get();
                if ($flash):
                    $flashType = $flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'success' ? 'success' : 'warning');
                    $flashClass = $flashType === 'danger' ? '' : ' vk-alert-' . $flashType;
                ?>
                    <div class="vk-alert<?= $flashClass ?>" role="alert">
                        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($flash['message']) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="vk-alert" role="alert">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form class="vk-form needs-validation" method="post" action="" autocomplete="off" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['login_csrf_token']) ?>">

                    <div class="vk-field">
                        <i class="bi bi-person-badge vk-field-icon" aria-hidden="true"></i>
                        <div class="form-floating">
                            <input
                                class="form-control"
                                type="text"
                                name="username"
                                id="username"
                                placeholder="Username"
                                required
                                maxlength="64"
                                inputmode="text"
                                autocomplete="username"
                                autocapitalize="none"
                                spellcheck="false"
                                value="<?= htmlspecialchars($username) ?>"
                                aria-describedby="usernameFeedback"
                            >
                            <label for="username">Username</label>
                            <div class="invalid-feedback" id="usernameFeedback">Enter your username.</div>
                        </div>
                    </div>

                    <div class="vk-field">
                        <i class="bi bi-key vk-field-icon" aria-hidden="true"></i>
                        <div class="form-floating">
                            <input
                                class="form-control vk-password-input"
                                type="password"
                                name="password"
                                id="password"
                                placeholder="Password"
                                required
                                autocomplete="current-password"
                                aria-describedby="passwordFeedback"
                            >
                            <label for="password">Password</label>
                            <button class="vk-password-toggle" type="button" aria-label="Show password" aria-controls="password">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                            <div class="invalid-feedback" id="passwordFeedback">Enter your password.</div>
                        </div>
                    </div>

                    <button class="btn vk-submit w-100" type="submit">
                        <span class="vk-submit-icon"><i class="bi bi-lock-fill" aria-hidden="true"></i></span>
                        <span class="vk-submit-text">Sign In</span>
                        <span class="spinner-border d-none" role="status" aria-hidden="true"></span>
                    </button>
                </form>

                <p class="vk-footer">&copy; 2026 VK Network. All rights reserved.</p>
            </div>
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (() => {
        const form = document.querySelector('.vk-form');
        const submit = form?.querySelector('.vk-submit');
        const submitText = submit?.querySelector('.vk-submit-text');
        const submitIcon = submit?.querySelector('.vk-submit-icon');
        const spinner = submit?.querySelector('.spinner-border');
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.querySelector('.vk-password-toggle');

        passwordToggle?.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            passwordToggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            passwordToggle.querySelector('i')?.classList.toggle('bi-eye', !isPassword);
            passwordToggle.querySelector('i')?.classList.toggle('bi-eye-slash', isPassword);
            passwordInput.focus();
        });

        form?.addEventListener('submit', (event) => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                form.classList.add('was-validated');
                const firstInvalid = form.querySelector(':invalid');
                firstInvalid?.focus();
                return;
            }

            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
            if (submitText) {
                submitText.textContent = 'Authenticating';
            }
            submitIcon?.classList.add('d-none');
            spinner?.classList.remove('d-none');
        });
    })();
</script>
</body>
</html>
