<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
$pdo = db();
require_once dirname(__DIR__, 2) . '/includes/invoices_schema.php';
vk_ensure_invoice_items_table($pdo);
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invalid product.');
    redirect('/modules/products/list.php');
}
$st = $pdo->prepare('SELECT COUNT(*) FROM invoice_items WHERE product_id = ?');
$st->execute([$id]);
if ((int) $st->fetchColumn() > 0) {
    flash_set('error', 'Cannot delete product used on invoices.');
    redirect('/modules/products/list.php');
}
$pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
flash_set('success', 'Product removed.');
redirect('/modules/products/list.php');
