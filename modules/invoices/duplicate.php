<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/invoices_service.php';
vk_ensure_finance_schemas($pdo);
vk_invoice_require_perm('create');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invoice not found.');
    redirect('/modules/invoices/list.php');
}

try {
    $newId = vk_invoice_duplicate($pdo, $id);
    flash_set('success', 'Invoice duplicated as draft.');
    redirect('/modules/invoices/edit.php?id=' . $newId);
} catch (Throwable $e) {
    flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not duplicate invoice.');
    redirect('/modules/invoices/view.php?id=' . $id);
}
