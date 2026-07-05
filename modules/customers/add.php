<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    if ($name === '') {
        flash_set('error', 'Name is required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) && $email !== '') {
        flash_set('error', 'Invalid email address.');
    } else {
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare('INSERT INTO customers (name, phone, email, address) VALUES (?,?,?,?)');
            $st->execute([$name, $phone ?: null, $email ?: null, $address ?: null]);
            $cid = (int) $pdo->lastInsertId();
            $code = next_customer_account_code($pdo);
            $accName = $name . ' — Account';
            $st2 = $pdo->prepare(
                'INSERT INTO accounts (code, name, account_type, customer_id, current_balance) VALUES (?,?,?,?,0)'
            );
            $st2->execute([$code, $accName, 'customer', $cid]);
            $pdo->commit();
            vk_cache_flush_dashboard();
            flash_set('success', 'Customer and linked account created.');
            redirect('/modules/customers/list.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not save customer.');
            redirect('/modules/customers/add.php');
        }
    }
}

$pageTitle = 'Add customer';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/customers/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<h1 class="h3 mb-3">Add customer</h1>
<div class="card vk-card" style="max-width: 640px;">
    <div class="card-body">
        <form method="post" data-loading>
            <div class="mb-3">
                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                <input class="form-control" id="name" name="name" required maxlength="255" value="<?= e($_POST['name'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" maxlength="64" value="<?= e($_POST['phone'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" maxlength="255" value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="address">Address</label>
                <textarea class="form-control" id="address" name="address" rows="3"><?= e($_POST['address'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save customer</button>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
