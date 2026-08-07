<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/init.php';
vk_bootstrap_module('vehicle_booking');
$pdo = db();
vk_vehicle_auto_migrate($pdo);

if (vk_vehicle_customer()) {
    redirect('/vehicle/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = (string) ($_POST['mode'] ?? 'login');
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if ($mode === 'register') {
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $now = time();
        $lastRegAt = (int) ($_SESSION['vk_vehicle_reg_last'] ?? 0);
        if ($lastRegAt > 0 && ($now - $lastRegAt) < 10) {
            flash_set('error', 'Please wait a few seconds before registering again.');
            redirect('/vehicle/login.php');
        }
        if ($name === '' || $phone === '' || $email === '') {
            flash_set('error', 'Please fill all required fields.');
            redirect('/vehicle/login.php');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Please enter a valid email address.');
            redirect('/vehicle/login.php');
        }
        try {
            $plainPassword = vk_vehicle_generate_password(10);
            $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO vehicle_customers (full_name, phone, email, address, password_hash) VALUES (?,?,?,?,?)')
                ->execute([$name, $phone, $email, ($address !== '' ? $address : null), $hash]);

            $_SESSION['vk_vehicle_reg_last'] = $now;
            $emailRes = vk_vehicle_send_credentials_email($pdo, $email, $name, $plainPassword);
            $waRes = vk_vehicle_send_credentials_whatsapp($phone, $name, $email, $plainPassword);

            $parts = ['Registration successful.'];
            $parts[] = $emailRes['ok'] ? 'Credentials sent by email.' : 'Email delivery pending (check SMTP settings).';
            $parts[] = $waRes['ok'] ? 'WhatsApp sent.' : 'WhatsApp delivery pending.';
            flash_set('success', implode(' ', $parts) . ' You can now login.');
        } catch (Throwable $e) {
            flash_set('error', 'This email is already registered.');
        }
        redirect('/vehicle/login.php');
    }

    $st = $pdo->prepare('SELECT id, password_hash FROM vehicle_customers WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $user = $st->fetch();
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        flash_set('error', 'Invalid email or password.');
        redirect('/vehicle/login.php');
    }
    $_SESSION['vk_vehicle_customer_id'] = (int) $user['id'];
    flash_set('success', 'Welcome back.');
    redirect('/vehicle/dashboard.php');
}

$pageTitle = 'Vehicle Customer Login';
$navActive = '';
$seoCanonicalPath = BASE_URL . '/vehicle/login.php';
require dirname(__DIR__) . '/includes/public_header.php';
?>
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="vk-pub-service-card p-4 h-100">
                    <h2 class="h5 mb-3">Login</h2>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="mode" value="login">
                        <div class="col-12"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
                        <div class="col-12"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div>
                        <div class="col-12"><button class="btn btn-primary w-100" type="submit">Login</button></div>
                    </form>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="vk-pub-service-card p-4 h-100">
                    <h2 class="h5 mb-3">Create account</h2>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="mode" value="register">
                        <div class="col-12"><label class="form-label">Full name</label><input class="form-control" name="full_name" required></div>
                        <div class="col-12"><label class="form-label">Phone (with country code)</label><input class="form-control" name="phone" placeholder="9477xxxxxxx" required></div>
                        <div class="col-12"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
                        <div class="col-12"><label class="form-label">Address (optional)</label><input class="form-control" name="address"></div>
                        <div class="col-12"><div class="small text-muted">Password is auto-generated and sent to your email + WhatsApp.</div></div>
                        <div class="col-12"><button class="btn btn-outline-primary w-100" type="submit">Register</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/public_footer.php'; ?>
