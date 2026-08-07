<?php
/**
 * Enterprise Inventory Management module (Add Product — Step 3).
 * Preserves all existing form field names/IDs for backend compatibility.
 *
 * @var array<string, mixed> $form
 * @var array<int, array<string, mixed>> $warehouses
 */

declare(strict_types=1);

$openingStock = studio_value($form, 'opening_stock', '0');
$currentStock = studio_value($form, 'current_stock', $openingStock !== '' ? $openingStock : '0');
?>
<section id="section-inventory" class="studio-card studio-section studio-physical-section" data-step-key="inventory" data-physical-only="true">
    <button type="button" class="studio-section-toggle" data-section-toggle="section-inventory-body">
        <div>
            <span class="studio-section-kicker">Step 3</span>
            <h2>Inventory Management</h2>
            <p>Warehouse positioning, stock levels, batch traceability, and live supply health.</p>
        </div>
        <span class="studio-section-tools">
            <span class="studio-chip">Supply</span>
            <i class="bi bi-chevron-down"></i>
        </span>
    </button>

    <div class="studio-section-body" id="section-inventory-body">
        <div id="inventoryManagementModule" class="inventory-management-module">
            <header class="card inventory-module-header border-0 shadow-sm">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h3 class="inventory-module-title mb-1"><i class="bi bi-boxes me-2"></i>Inventory Control Center</h3>
                        <p class="inventory-module-subtitle mb-0">Live stock math, reorder intelligence, and movement preview.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="inventory-status-pill" id="inventoryHealthPill">
                            <i class="bi bi-heart-pulse"></i>
                            <span id="inventoryStatusText">Healthy</span>
                        </span>
                        <span class="inventory-status-pill" id="inventoryReorderPill">
                            <i class="bi bi-arrow-repeat"></i>
                            <span id="inventoryReorderStatus">No reorder needed</span>
                        </span>
                    </div>
                </div>
            </header>

            <!-- Dashboard metric cards -->
            <div class="row g-3 inventory-metrics-row">
                <div class="col-6 col-md-4 col-xl-2">
                    <article class="card inventory-metric-card border-0 h-100">
                        <div class="card-body">
                            <span class="inventory-metric-label"><i class="bi bi-box-seam"></i> Available</span>
                            <strong class="inventory-metric-value" id="inventoryAvailableStock">0</strong>
                            <small class="text-muted">Current − reserved</small>
                        </div>
                    </article>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <article class="card inventory-metric-card border-0 h-100">
                        <div class="card-body">
                            <span class="inventory-metric-label"><i class="bi bi-graph-up"></i> Projected</span>
                            <strong class="inventory-metric-value" id="inventoryProjectedStock">0</strong>
                            <small class="text-muted">After incoming</small>
                        </div>
                    </article>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <article class="card inventory-metric-card border-0 h-100">
                        <div class="card-body">
                            <span class="inventory-metric-label"><i class="bi bi-shield-check"></i> Buffer</span>
                            <strong class="inventory-metric-value" id="inventoryBufferGap">0</strong>
                            <small class="text-muted">Above minimum</small>
                        </div>
                    </article>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <article class="card inventory-metric-card border-0 h-100">
                        <div class="card-body">
                            <span class="inventory-metric-label"><i class="bi bi-bell"></i> Reorder In</span>
                            <strong class="inventory-metric-value" id="inventoryReorderGap">0</strong>
                            <small class="text-muted">Units to trigger</small>
                        </div>
                    </article>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <article class="card inventory-metric-card border-0 h-100">
                        <div class="card-body">
                            <span class="inventory-metric-label"><i class="bi bi-percent"></i> Fill Rate</span>
                            <strong class="inventory-metric-value" id="inventoryFillRate">0%</strong>
                            <small class="text-muted">Stock vs reorder</small>
                        </div>
                    </article>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <article class="card inventory-metric-card border-0 h-100">
                        <div class="card-body">
                            <span class="inventory-metric-label"><i class="bi bi-speedometer2"></i> Health</span>
                            <strong class="inventory-metric-value inventory-metric-value--sm" id="inventoryHealthScore">100</strong>
                            <small class="text-muted">Inventory score</small>
                        </div>
                    </article>
                </div>
            </div>

            <!-- Progress bars -->
            <div class="card inventory-form-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h4 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Stock Level Progress</h4>
                </div>
                <div class="card-body">
                    <div class="inventory-progress-item">
                        <div class="d-flex justify-content-between mb-1">
                            <span><i class="bi bi-box-seam me-1"></i> Current stock level</span>
                            <strong id="inventoryProgressStockLabel">0 units</strong>
                        </div>
                        <div class="progress inventory-progress" role="progressbar" aria-label="Current stock">
                            <div class="progress-bar bg-primary" id="inventoryProgressStock" style="width: 0%"></div>
                        </div>
                        <div class="studio-mini-chart mt-2">
                            <div class="studio-bar" data-chart-bar="stock"><span></span></div>
                        </div>
                    </div>
                    <div class="inventory-progress-item mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span><i class="bi bi-shield me-1"></i> Minimum buffer</span>
                            <strong id="inventoryProgressMinLabel">0 units</strong>
                        </div>
                        <div class="progress inventory-progress" role="progressbar" aria-label="Minimum stock">
                            <div class="progress-bar bg-info" id="inventoryProgressMin" style="width: 0%"></div>
                        </div>
                        <div class="studio-mini-chart mt-2">
                            <div class="studio-bar" data-chart-bar="buffer"><span></span></div>
                        </div>
                    </div>
                    <div class="inventory-progress-item mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span><i class="bi bi-arrow-repeat me-1"></i> Reorder threshold</span>
                            <strong id="inventoryProgressReorderLabel">0 units</strong>
                        </div>
                        <div class="progress inventory-progress" role="progressbar" aria-label="Reorder level">
                            <div class="progress-bar bg-warning" id="inventoryProgressReorder" style="width: 0%"></div>
                        </div>
                        <div class="studio-mini-chart mt-2">
                            <div class="studio-bar" data-chart-bar="reorder"><span></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock quantities -->
            <div class="card inventory-form-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h4 class="mb-0"><i class="bi bi-stack me-2"></i>Stock Quantities</h4>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6 col-xl-4">
                            <div class="form-floating studio-floating inventory-field-shell">
                                <input class="form-control" type="number" min="0" step="1" id="opening_stock" name="opening_stock" placeholder="Opening stock" value="<?= e($openingStock) ?>" required data-required-label="Opening Stock">
                                <label for="opening_stock">Opening Stock <span class="studio-required">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <div class="form-floating studio-floating inventory-field-shell">
                                <input class="form-control" type="number" min="0" step="1" id="current_stock" name="current_stock" placeholder="Current stock" value="<?= e($currentStock) ?>" data-required-label="Current Stock">
                                <label for="current_stock"><i class="bi bi-box me-1"></i> Current Stock</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <div class="form-floating studio-floating inventory-field-shell">
                                <input class="form-control" type="number" min="0" step="1" id="incoming_stock" name="incoming_stock" placeholder="Incoming stock" value="<?= e(studio_value($form, 'incoming_stock', '0')) ?>">
                                <label for="incoming_stock"><i class="bi bi-truck me-1"></i> Incoming Stock</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <div class="form-floating studio-floating inventory-field-shell">
                                <input class="form-control" type="number" min="0" step="1" id="reserved_stock" name="reserved_stock" placeholder="Reserved stock" value="<?= e(studio_value($form, 'reserved_stock', '0')) ?>">
                                <label for="reserved_stock"><i class="bi bi-lock me-1"></i> Reserved Stock</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <div class="form-floating studio-floating inventory-field-shell">
                                <input class="form-control" type="number" min="0" step="1" id="minimum_stock" name="minimum_stock" placeholder="Minimum stock" value="<?= e(studio_value($form, 'minimum_stock', '5')) ?>">
                                <label for="minimum_stock">Minimum Stock</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <div class="form-floating studio-floating inventory-field-shell">
                                <input class="form-control" type="number" min="0" step="1" id="reorder_level" name="reorder_level" placeholder="Reorder level" value="<?= e(studio_value($form, 'reorder_level', '10')) ?>">
                                <label for="reorder_level">Reorder Level</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warehouse & location -->
            <div class="card inventory-form-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h4 class="mb-0"><i class="bi bi-building me-2"></i>Warehouse &amp; Location</h4>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="studio-search-select inventory-field-shell">
                                <label for="warehouse_id"><i class="bi bi-building me-1"></i> Warehouse</label>
                                <input type="search" class="form-control studio-filter-input" data-filter-target="warehouse_id" placeholder="Search warehouse" aria-label="Search warehouse">
                                <select class="form-select" id="warehouse_id" name="warehouse_id" data-placeholder="Select warehouse">
                                    <option value="">Select warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <option value="<?= e((string) $warehouse['id']) ?>" <?= studio_selected($form, 'warehouse_id', (string) $warehouse['id']) ?>><?= e((string) $warehouse['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-floating studio-floating inventory-field-shell">
                                <input class="form-control" id="rack_location" name="rack_location" placeholder="Rack location" value="<?= e(studio_value($form, 'rack_location')) ?>">
                                <label for="rack_location"><i class="bi bi-grid-3x3 me-1"></i> Rack Location</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-floating studio-floating inventory-field-shell">
                                <input class="form-control" id="bin_number" name="bin_number" placeholder="Bin number" value="<?= e(studio_value($form, 'bin_number')) ?>">
                                <label for="bin_number"><i class="bi bi-inbox me-1"></i> Bin Number</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-floating studio-floating inventory-field-shell">
                                <select class="form-select" id="stock_keeping_type" name="stock_keeping_type">
                                    <?php foreach (['standard' => 'Standard SKU', 'batch' => 'Batch managed', 'serial' => 'Serial tracked'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= studio_selected($form, 'stock_keeping_type', $value) ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="stock_keeping_type">SKU Type</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Batch & traceability -->
            <div class="card inventory-form-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h4 class="mb-0"><i class="bi bi-upc-scan me-2"></i>Batch &amp; Traceability</h4>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6 col-lg-4">
                            <div class="form-floating studio-floating inventory-field-shell">
                                <input class="form-control" id="serial_number" name="serial_number" placeholder="Serial number" value="<?= e(studio_value($form, 'serial_number')) ?>">
                                <label for="serial_number"><i class="bi bi-hash me-1"></i> Serial Number</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-floating studio-floating inventory-field-shell">
                                <input class="form-control" id="batch_number" name="batch_number" placeholder="Batch number" value="<?= e(studio_value($form, 'batch_number')) ?>">
                                <label for="batch_number"><i class="bi bi-collection me-1"></i> Batch Number</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-floating studio-floating inventory-field-shell">
                                <input class="form-control inventory-date-input" type="date" id="manufacturing_date" name="manufacturing_date" value="<?= e(studio_value($form, 'manufacturing_date')) ?>">
                                <label for="manufacturing_date"><i class="bi bi-calendar-plus me-1"></i> Manufacturing Date</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-floating studio-floating inventory-field-shell">
                                <input class="form-control inventory-date-input" type="date" id="expiry_date" name="expiry_date" value="<?= e(studio_value($form, 'expiry_date')) ?>">
                                <label for="expiry_date"><i class="bi bi-calendar-x me-1"></i> Expiry Date</label>
                            </div>
                            <div class="inventory-invalid-message" id="inventoryExpiryError" hidden>Expiry date must be after manufacturing date.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Options -->
            <div class="card inventory-form-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h4 class="mb-0"><i class="bi bi-toggles me-2"></i>Inventory Options</h4>
                </div>
                <div class="card-body">
                    <div class="studio-toggle-grid compact">
                        <label class="studio-toggle-card">
                            <input type="checkbox" name="inventory_tracking" id="inventory_tracking" <?= studio_checked($form, 'inventory_tracking', true) ?>>
                            <span>
                                <strong>Inventory Tracking</strong>
                                <small>Track movements and stock logs</small>
                            </span>
                        </label>
                        <label class="studio-toggle-card">
                            <input type="checkbox" name="multi_warehouse_support" id="multi_warehouse_support" <?= studio_checked($form, 'multi_warehouse_support') ?>>
                            <span>
                                <strong>Multi Warehouse</strong>
                                <small>Prepare transfers and split fulfillment</small>
                            </span>
                        </label>
                        <label class="studio-toggle-card">
                            <input type="checkbox" name="low_stock_alert" id="low_stock_alert" <?= studio_checked($form, 'low_stock_alert', true) ?>>
                            <span>
                                <strong>Low Stock Alerts</strong>
                                <small>Notify ops before stockout risk</small>
                            </span>
                        </label>
                        <label class="studio-toggle-card">
                            <input type="checkbox" name="allow_backorders" id="allow_backorders" <?= studio_checked($form, 'allow_backorders') ?>>
                            <span>
                                <strong>Allow Backorders</strong>
                                <small>Continue sales when stock reaches zero</small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Stock movement history (live preview for new product setup) -->
            <div class="card inventory-form-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h4 class="mb-0"><i class="bi bi-clock-history me-2"></i>Stock Movement History</h4>
                    <span class="inventory-chip">Live preview</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table inventory-history-table mb-0" aria-label="Stock movement history preview">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Type</th>
                                    <th>Qty</th>
                                    <th>Before</th>
                                    <th>After</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody id="inventoryMovementHistory">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Enter stock values to preview movement timeline.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
