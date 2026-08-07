<?php
/**
 * Enterprise Pricing Intelligence module (Add Product — Step 2).
 * Preserves all existing form field names/IDs for backend compatibility.
 *
 * @var array<string, mixed> $form
 * @var array<int, array<string, mixed>> $taxClasses
 */

declare(strict_types=1);

$currencies = [
    'USD' => ['label' => 'US Dollar', 'symbol' => '$'],
    'EUR' => ['label' => 'Euro', 'symbol' => '€'],
    'GBP' => ['label' => 'British Pound', 'symbol' => '£'],
    'LKR' => ['label' => 'Sri Lankan Rupee', 'symbol' => 'Rs'],
    'INR' => ['label' => 'Indian Rupee', 'symbol' => '₹'],
    'AED' => ['label' => 'UAE Dirham', 'symbol' => 'AED'],
];

$priceFields = [
    ['id' => 'cost_price', 'label' => 'Cost Price', 'icon' => 'bi-receipt', 'required' => true],
    ['id' => 'selling_price', 'label' => 'Selling Price', 'icon' => 'bi-tag', 'required' => true],
    ['id' => 'wholesale_price', 'label' => 'Wholesale Price', 'icon' => 'bi-boxes', 'required' => false],
    ['id' => 'dealer_price', 'label' => 'Dealer Price', 'icon' => 'bi-shop', 'required' => false],
    ['id' => 'distributor_price', 'label' => 'Distributor Price', 'icon' => 'bi-truck', 'required' => false],
    ['id' => 'msrp', 'label' => 'MSRP', 'icon' => 'bi-award', 'required' => false],
];
?>
<section id="section-pricing" class="studio-card studio-section" data-step-key="pricing">
    <button type="button" class="studio-section-toggle" data-section-toggle="section-pricing-body">
        <div>
            <span class="studio-section-kicker">Step 2</span>
            <h2>Pricing Intelligence</h2>
            <p>Enterprise price architecture, tax engine, margin analytics, and promotional windows.</p>
        </div>
        <span class="studio-section-tools">
            <span class="studio-chip">Finance</span>
            <i class="bi bi-chevron-down"></i>
        </span>
    </button>

    <div class="studio-section-body" id="section-pricing-body">
        <input type="hidden" id="profit_margin" name="profit_margin" value="<?= e(studio_value($form, 'profit_margin', '0')) ?>">

        <div id="pricingIntelligenceModule" class="pricing-intelligence-module">
            <script type="application/json" id="pricingTaxClassData"><?= htmlspecialchars(json_encode($taxClasses, JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8') ?></script>

            <header class="card pricing-module-header border-0 shadow-sm">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h3 class="pricing-module-title mb-1"><i class="bi bi-cash-stack me-2"></i>Pricing Intelligence</h3>
                        <p class="pricing-module-subtitle mb-0">Live margin, tax, and revenue calculations update as you type.</p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <div class="pricing-currency-pill" id="pricingCurrencyPill">
                            <i class="bi bi-currency-exchange"></i>
                            <span id="pricingCurrencyLabel"><?= e(studio_value($form, 'currency', 'USD')) ?></span>
                        </div>
                        <span class="pricing-validation-badge" id="pricingValidationBadge">
                            <i class="bi bi-shield-check"></i> Validating…
                        </span>
                    </div>
                </div>
            </header>

            <!-- Live intelligence strip -->
            <div class="row g-3 pricing-metrics-row">
                <div class="col-6 col-md-4 col-xl-2">
                    <article class="card pricing-metric-card border-0 h-100">
                        <div class="card-body">
                            <span class="pricing-metric-label"><i class="bi bi-percent"></i> Margin %</span>
                            <strong class="pricing-metric-value" id="profitMarginValue">0%</strong>
                            <small class="text-muted">Live margin</small>
                        </div>
                    </article>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <article class="card pricing-metric-card border-0 h-100">
                        <div class="card-body">
                            <span class="pricing-metric-label"><i class="bi bi-graph-up-arrow"></i> Profit</span>
                            <strong class="pricing-metric-value" id="liveProfitValue">$0.00</strong>
                            <small class="text-muted">Live profit</small>
                        </div>
                    </article>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <article class="card pricing-metric-card border-0 h-100">
                        <div class="card-body">
                            <span class="pricing-metric-label"><i class="bi bi-calculator"></i> Tax Incl.</span>
                            <strong class="pricing-metric-value" id="taxInclusiveValue">$0.00</strong>
                            <small class="text-muted">Tax inclusive price</small>
                        </div>
                    </article>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <article class="card pricing-metric-card border-0 h-100">
                        <div class="card-body">
                            <span class="pricing-metric-label"><i class="bi bi-bar-chart"></i> Revenue</span>
                            <strong class="pricing-metric-value" id="inlineRevenueEstimate">$0.00</strong>
                            <small class="text-muted">Revenue estimate</small>
                        </div>
                    </article>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <article class="card pricing-metric-card border-0 h-100">
                        <div class="card-body">
                            <span class="pricing-metric-label"><i class="bi bi-lightbulb"></i> Recommended</span>
                            <strong class="pricing-metric-value" id="recommendedSellingPrice">$0.00</strong>
                            <small class="text-muted">Suggested sell price</small>
                        </div>
                    </article>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <article class="card pricing-metric-card border-0 h-100">
                        <div class="card-body">
                            <span class="pricing-metric-label"><i class="bi bi-megaphone"></i> Promo</span>
                            <strong class="pricing-metric-value pricing-metric-value--sm" id="promoRecommendation">—</strong>
                            <small class="text-muted">Promo insight</small>
                        </div>
                    </article>
                </div>
            </div>

            <!-- Price architecture -->
            <div class="card pricing-form-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h4 class="mb-0"><i class="bi bi-layers me-2"></i>Price Architecture</h4>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <?php foreach ($priceFields as $field): ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="pricing-field-shell">
                                    <label class="form-label" for="<?= e($field['id']) ?>">
                                        <i class="bi <?= e($field['icon']) ?> me-1"></i><?= e($field['label']) ?><?= !empty($field['required']) ? ' <span class="studio-required">*</span>' : '' ?>
                                    </label>
                                    <div class="input-group pricing-input-group">
                                        <span class="input-group-text pricing-currency-prefix" data-currency-prefix><?= e($currencies[studio_value($form, 'currency', 'USD')]['symbol'] ?? '$') ?></span>
                                        <input
                                            class="form-control studio-money-field pricing-money-input"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            inputmode="decimal"
                                            id="<?= e($field['id']) ?>"
                                            name="<?= e($field['id']) ?>"
                                            placeholder="0.00"
                                            value="<?= e(studio_value($form, $field['id'], '0')) ?>"
                                            <?= !empty($field['required']) ? 'required data-required-label="' . e($field['label']) . '"' : '' ?>
                                            aria-describedby="<?= e($field['id']) ?>Help"
                                        >
                                    </div>
                                </div>
                                <div class="pricing-field-meta" id="<?= e($field['id']) ?>Help">
                                    <span>Auto-formatted on blur</span>
                                    <span class="pricing-formatted-hint" data-formatted-for="<?= e($field['id']) ?>">—</span>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="col-md-6 col-xl-4">
                            <div class="form-floating studio-floating pricing-field-shell">
                                <select class="form-select" id="currency" name="currency" data-placeholder="Select currency">
                                    <?php foreach ($currencies as $code => $meta): ?>
                                        <option value="<?= e($code) ?>" data-symbol="<?= e($meta['symbol']) ?>" <?= studio_selected($form, 'currency', $code) ?>><?= e($code) ?> — <?= e($meta['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="currency"><i class="bi bi-currency-exchange me-1"></i> Currency</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tax & discount -->
            <div class="card pricing-form-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h4 class="mb-0"><i class="bi bi-receipt-cutoff me-2"></i>Tax &amp; Discount Engine</h4>
                    <button type="button" class="btn btn-sm pricing-mini-btn" id="pricingRecalculateTax">
                        <i class="bi bi-arrow-repeat"></i> Recalculate tax
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="studio-search-select pricing-field-shell">
                                <label for="tax_class_id"><i class="bi bi-journal-text me-1"></i> Tax Class</label>
                                <input type="search" class="form-control studio-filter-input" data-filter-target="tax_class_id" placeholder="Search tax class" aria-label="Search tax class">
                                <select class="form-select" id="tax_class_id" name="tax_class_id" data-placeholder="Select tax class">
                                    <option value="">Select tax class</option>
                                    <?php foreach ($taxClasses as $taxClass): ?>
                                        <option
                                            value="<?= e((string) $taxClass['id']) ?>"
                                            data-rate="<?= e((string) ($taxClass['rate'] ?? '0')) ?>"
                                            data-tax-type="<?= e((string) ($taxClass['tax_type'] ?? 'vat')) ?>"
                                            <?= studio_selected($form, 'tax_class_id', (string) $taxClass['id']) ?>
                                        ><?= e((string) $taxClass['name']) ?><?= isset($taxClass['rate']) ? ' (' . e((string) $taxClass['rate']) . '%)' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-floating studio-floating pricing-field-shell">
                                <input class="form-control pricing-percent-input" type="number" step="0.01" min="0" max="100" id="tax_rate" name="tax_rate" placeholder="Tax rate" value="<?= e(studio_value($form, 'tax_rate', '0')) ?>">
                                <label for="tax_rate">Tax Rate %</label>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-floating studio-floating pricing-field-shell">
                                <input class="form-control pricing-percent-input" type="number" step="0.01" min="0" max="100" id="vat_gst" name="vat_gst" placeholder="VAT/GST" value="<?= e(studio_value($form, 'vat_gst', '0')) ?>">
                                <label for="vat_gst">VAT / GST %</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="pricing-tax-breakdown card border-0" id="pricingTaxBreakdown" aria-live="polite">
                                <div class="card-body py-3">
                                    <div class="row g-2 text-center">
                                        <div class="col-4">
                                            <span class="d-block small text-muted">Base price</span>
                                            <strong id="taxCalcBase">$0.00</strong>
                                        </div>
                                        <div class="col-4">
                                            <span class="d-block small text-muted">Tax amount</span>
                                            <strong id="taxCalcAmount">$0.00</strong>
                                        </div>
                                        <div class="col-4">
                                            <span class="d-block small text-muted">Combined rate</span>
                                            <strong id="taxCalcRate">0%</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-floating studio-floating pricing-field-shell">
                                <select class="form-select" id="discount_type" name="discount_type">
                                    <?php foreach (['none' => 'No discount', 'fixed' => 'Fixed amount', 'percentage' => 'Percentage'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= studio_selected($form, 'discount_type', $value) ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="discount_type"><i class="bi bi-tag-fill me-1"></i> Discount Type</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="pricing-field-shell">
                                <label class="form-label" for="discount_value"><i class="bi bi-tag-fill me-1"></i> Discount Value</label>
                                <div class="input-group pricing-input-group">
                                    <span class="input-group-text pricing-discount-prefix" id="discountValuePrefix">—</span>
                                    <input class="form-control pricing-money-input" type="number" step="0.01" min="0" id="discount_value" name="discount_value" placeholder="0.00" value="<?= e(studio_value($form, 'discount_value', '0')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Promotions & validity -->
            <div class="card pricing-form-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h4 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Promotions &amp; Price Validity</h4>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6 col-lg-4">
                            <div class="pricing-field-shell">
                                <label class="form-label" for="promotional_price"><i class="bi bi-megaphone me-1"></i> Promotional Price</label>
                                <div class="input-group pricing-input-group">
                                    <span class="input-group-text pricing-currency-prefix" data-currency-prefix><?= e($currencies[studio_value($form, 'currency', 'USD')]['symbol'] ?? '$') ?></span>
                                    <input class="form-control studio-money-field pricing-money-input" type="number" step="0.01" min="0" id="promotional_price" name="promotional_price" placeholder="0.00" value="<?= e(studio_value($form, 'promotional_price', '0')) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                            <div class="form-floating studio-floating pricing-field-shell">
                                <input class="form-control pricing-date-input" type="date" id="price_valid_from" name="price_valid_from" value="<?= e(studio_value($form, 'price_valid_from')) ?>">
                                <label for="price_valid_from"><i class="bi bi-calendar-plus me-1"></i> Price Valid From</label>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                            <div class="form-floating studio-floating pricing-field-shell">
                                <input class="form-control pricing-date-input" type="date" id="price_valid_to" name="price_valid_to" value="<?= e(studio_value($form, 'price_valid_to')) ?>">
                                <label for="price_valid_to"><i class="bi bi-calendar-check me-1"></i> Price Valid To</label>
                            </div>
                            <div class="pricing-invalid-message" id="priceValidRangeError" hidden>Price valid to must be on or after valid from.</div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                            <div class="form-floating studio-floating pricing-field-shell">
                                <input class="form-control pricing-date-input" type="date" id="promo_start_date" name="promo_start_date" value="<?= e(studio_value($form, 'promo_start_date')) ?>">
                                <label for="promo_start_date"><i class="bi bi-megaphone me-1"></i> Promotion Start</label>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                            <div class="form-floating studio-floating pricing-field-shell">
                                <input class="form-control pricing-date-input" type="date" id="promo_end_date" name="promo_end_date" value="<?= e(studio_value($form, 'promo_end_date')) ?>">
                                <label for="promo_end_date"><i class="bi bi-megaphone-fill me-1"></i> Promotion End</label>
                            </div>
                            <div class="pricing-invalid-message" id="promoRangeError" hidden>Promotion end must be on or after promotion start.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Intelligence summary -->
            <div class="card pricing-form-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h4 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Price Intelligence Summary</h4>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <article class="pricing-summary-tile">
                                <span>Margin Health</span>
                                <strong id="inlineMarginHealth">Neutral</strong>
                            </article>
                        </div>
                        <div class="col-sm-4">
                            <article class="pricing-summary-tile">
                                <span>Discount Pressure</span>
                                <strong id="inlineDiscountPressure">Low</strong>
                            </article>
                        </div>
                        <div class="col-sm-4">
                            <article class="pricing-summary-tile">
                                <span>Effective Sell Price</span>
                                <strong id="pricingEffectivePrice">$0.00</strong>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
