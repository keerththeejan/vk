<?php
declare(strict_types=1);

/** View + request helpers for product create form. */
final class ProductForm
{
    public static function value(array $form, string $key, string $default = ''): string
    {
        $v = $form[$key] ?? $default;
        return is_scalar($v) ? (string) $v : $default;
    }

    public static function selected(array $form, string $key, string $value): string
    {
        return self::value($form, $key) === $value ? 'selected' : '';
    }

    public static function checked(array $form, string $key, bool $default = false): string
    {
        if (!array_key_exists($key, $form)) {
            return $default ? 'checked' : '';
        }
        return !empty($form[$key]) ? 'checked' : '';
    }

    public static function slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
        return trim($slug, '-') ?: 'product-' . time();
    }

    public static function num(array $source, string $key, float $default = 0): float
    {
        return is_numeric($source[$key] ?? null) ? (float) $source[$key] : $default;
    }

    public static function int(array $source, string $key, int $default = 0): int
    {
        return is_numeric($source[$key] ?? null) ? (int) $source[$key] : $default;
    }

    public static function nullableId(array $source, string $key): ?int
    {
        return is_numeric($source[$key] ?? null) ? (int) $source[$key] : null;
    }

    public static function date(array $source, string $key): ?string
    {
        $value = trim((string) ($source[$key] ?? ''));
        return $value !== '' ? $value : null;
    }

    public static function text(array $source, string $key): ?string
    {
        $value = trim((string) ($source[$key] ?? ''));
        return $value !== '' ? $value : null;
    }

    /** @return array<string, mixed> */
    public static function draftPayload(array $source): array
    {
        $keys = [
            'name', 'subtitle', 'sku', 'barcode', 'qr_code', 'classification', 'product_type', 'brand_id', 'category_id', 'subcategory_id',
            'supplier_id', 'manufacturer_id', 'unit_type', 'hsn_sac_code', 'country_of_origin', 'product_tags', 'collections',
            'visibility', 'stock_status', 'trending', 'short_description', 'description', 'seo_url', 'meta_title', 'meta_description', 'meta_keywords',
            'cost_price', 'selling_price', 'wholesale_price', 'dealer_price', 'distributor_price', 'msrp', 'currency',
            'tax_class_id', 'tax_rate', 'vat_gst', 'discount_type', 'discount_value', 'promotional_price',
            'promo_start_date', 'promo_end_date', 'price_valid_from', 'price_valid_to', 'opening_stock', 'current_stock', 'minimum_stock',
            'reorder_level', 'warehouse_id', 'rack_location', 'bin_number', 'batch_number', 'serial_number', 'stock_keeping_type', 'reserved_stock',
            'incoming_stock', 'low_stock_alert', 'inventory_tracking', 'multi_warehouse_support', 'allow_backorders',
            'manufacturing_date', 'expiry_date', 'warranty_enabled', 'warranty_type', 'warranty_period', 'warranty_unit',
            'warranty_start_date', 'warranty_coverage', 'warranty_terms', 'warranty_claim_process', 'warranty_provider', 'warranty_notes',
            'service_center_name', 'service_center_phone', 'service_center_email', 'service_center_address', 'support_contact',
            'replacement_policy', 'amc_support', 'variant_colors', 'variant_sizes', 'variant_materials', 'variant_storage', 'variant_models',
            'variant_notes', 'shipping_weight', 'shipping_length', 'shipping_width', 'shipping_height', 'shipping_class', 'delivery_sla', 'return_window',
            'fragile', 'free_shipping', 'cod_support', 'packaging_type', 'featured', 'is_digital', 'requires_shipping', 'status',
            'focus_keyword', 'canonical_url', 'cross_sell', 'upsell', 'related_products', 'campaign_tags', 'profit_margin',
        ];
        $payload = [];
        foreach ($keys as $key) {
            $payload[$key] = $source[$key] ?? '';
        }
        return $payload;
    }

    public static function completeness(array $data): int
    {
        $checks = [
            !empty(trim((string) ($data['name'] ?? ''))),
            !empty(trim((string) ($data['sku'] ?? ''))),
            !empty($data['category_id']),
            !empty($data['brand_id']),
            self::num($data, 'selling_price') > 0,
            self::int($data, 'opening_stock') >= 0,
            !empty(trim((string) ($data['short_description'] ?? ''))),
            !empty(trim((string) ($data['description'] ?? ''))),
            !empty(trim((string) ($data['meta_title'] ?? ''))),
            !empty(trim((string) ($data['meta_description'] ?? ''))),
        ];
        return (int) round((count(array_filter($checks)) / count($checks)) * 100);
    }
}
