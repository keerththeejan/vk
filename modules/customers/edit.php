<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
    flash_set('error', 'Customer not found.');
    redirect('/modules/customers/list.php');
}

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
        $pdo->prepare('UPDATE customers SET name=?, phone=?, email=?, address=? WHERE id=?')
            ->execute([$name, $phone ?: null, $email ?: null, $address ?: null, $id]);
        $pdo->prepare('UPDATE accounts SET name = ? WHERE customer_id = ?')->execute([$name . ' — Account', $id]);
        vk_cache_flush_dashboard();
        flash_set('success', 'Customer updated.');
        redirect('/modules/customers/list.php');
    }
}

$pageTitle = 'Edit customer';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/customers/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<h1 class="h3 mb-3">Edit customer</h1>
<div class="card vk-card" style="max-width: 640px;">
    <div class="card-body">
        <form method="post" data-loading>
            <div class="mb-3">
                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                <input class="form-control" id="name" name="name" required maxlength="255" value="<?= e($_POST['name'] ?? $row['name']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" maxlength="64" value="<?= e($_POST['phone'] ?? ($row['phone'] ?? '')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" maxlength="255" value="<?= e($_POST['email'] ?? ($row['email'] ?? '')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="address">Address</label>
                <textarea class="form-control" id="address" name="address" rows="3"><?= e($_POST['address'] ?? ($row['address'] ?? '')) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
