<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_require_perm('create');

$id = (int) ($_GET['id'] ?? 0);
$q = vk_quotation_get($pdo, $id);
if (!$q) {
    flash_set('error', 'Quotation not found.');
    redirect('/modules/quotations/list.php');
}

$items = vk_quotation_items($pdo, $id);
$lines = [];
foreach ($items as $ln) {
    $lines[] = [
        'sort_order' => (int) ($ln['sort_order'] ?? 0),
        'item_type' => $ln['item_type'] ?? 'product',
        'product_id' => !empty($ln['product_id']) ? (int) $ln['product_id'] : null,
        'product_code' => $ln['product_code'] ?? null,
        'barcode' => $ln['barcode'] ?? null,
        'product_name' => $ln['product_name'],
        'category_name' => $ln['category_name'] ?? null,
        'description' => $ln['description'] ?? null,
        'unit' => $ln['unit'] ?? 'pcs',
        'quantity' => (float) $ln['quantity'],
        'unit_price' => (float) $ln['unit_price'],
        'cost_price' => (float) ($ln['cost_price'] ?? 0),
        'discount_pct' => (float) ($ln['discount_pct'] ?? 0),
        'discount_amount' => (float) ($ln['discount_amount'] ?? 0),
        'tax_pct' => (float) ($ln['tax_pct'] ?? 0),
    ];
}

$header = [
    'customer_id' => (int) $q['customer_id'],
    'company_name' => $q['company_name'],
    'contact_person' => $q['contact_person'],
    'phone' => $q['phone'],
    'email' => $q['email'],
    'billing_address' => $q['billing_address'],
    'shipping_address' => $q['shipping_address'],
    'currency' => $q['currency'] ?? 'LKR',
    'sales_executive_id' => $q['sales_executive_id'],
    'category_id' => $q['category_id'],
    'template_id' => $q['template_id'],
    'reference_number' => $q['reference_number'] ? ($q['reference_number'] . ' (copy)') : null,
    'quotation_date' => date('Y-m-d'),
    'expiry_date' => null,
    'payment_terms' => $q['payment_terms'],
    'delivery_terms' => $q['delivery_terms'],
    'validity_days' => (int) ($q['validity_days'] ?? 30),
    'tax_method' => $q['tax_method'] ?? 'exclusive',
    'status' => 'draft',
    'approval_status' => 'none',
    'overall_discount_pct' => (float) $q['overall_discount_pct'],
    'overall_discount_amount' => (float) $q['overall_discount_amount'],
    'shipping_amount' => (float) $q['shipping_amount'],
    'additional_charges' => (float) $q['additional_charges'],
    'round_off' => (float) $q['round_off'],
    'notes' => $q['notes'],
    'internal_notes' => $q['internal_notes'],
    'terms_html' => $q['terms_html'],
    'expected_closing_date' => $q['expected_closing_date'],
    'branch' => $q['branch'],
];

try {
    $validity = max(1, (int) $header['validity_days']);
    $header['expiry_date'] = (new DateTime())->modify('+' . $validity . ' days')->format('Y-m-d');
    $newId = vk_quotation_save($pdo, $header, $lines, null);
    vk_quotation_log($pdo, $newId, 'duplicated', 'Duplicated from ' . $q['quotation_number']);
    flash_set('success', 'Quotation duplicated as draft.');
    redirect('/modules/quotations/edit.php?id=' . $newId);
} catch (Throwable $e) {
    flash_set('error', 'Could not duplicate: ' . $e->getMessage());
    redirect('/modules/quotations/view.php?id=' . $id);
}
