(function () {
    'use strict';

    var app = document.getElementById('vkProdApp');
    if (!app) {
        return;
    }

    var baseUrl = (app.getAttribute('data-base-url') || '/vk').replace(/\/$/, '');
    var tableBody = document.querySelector('#products-table tbody');
    var mobileList = document.getElementById('vkProdMobileList');
    var searchEl = document.getElementById('product-search');
    var filterCategory = document.getElementById('filter-category');
    var filterStatus = document.getElementById('filter-status');
    var filterBrand = document.getElementById('filter-brand');
    var filterStock = document.getElementById('filter-stock');
    var perPageEl = document.getElementById('product-per-page');
    var paginationEl = document.getElementById('pagination');
    var pageInfoEl = document.getElementById('vkProdPageInfo');
    var bulkSelectAll = document.getElementById('bulk-select-all');
    var drawer = document.getElementById('vkProdDrawer');
    var drawerBackdrop = document.getElementById('vkProdDrawerBackdrop');
    var drawerClose = document.getElementById('vkProdDrawerClose');

    var statEls = {
        total: document.getElementById('stat-total'),
        active: document.getElementById('stat-active'),
        low: document.getElementById('stat-low'),
        out: document.getElementById('stat-out'),
        value: document.getElementById('stat-value'),
        todaySales: document.getElementById('stat-today-sales'),
        sold: document.getElementById('stat-sold'),
        newProducts: document.getElementById('stat-new'),
        categories: document.getElementById('stat-categories'),
        suppliers: document.getElementById('stat-suppliers'),
        top: document.getElementById('stat-top'),
        profit: document.getElementById('stat-profit'),
    };

    var page = 1;
    var limit = 25;
    var total = 0;
    var lastRows = [];

    function debounce(fn, wait) {
        var t;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, wait);
        };
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function formatLkr(n) {
        if (typeof formatCurrency === 'function') {
            return formatCurrency(n);
        }
        var num = Number(n);
        if (!Number.isFinite(num)) {
            num = 0;
        }
        var fixed = num.toFixed(2);
        var parts = fixed.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return 'Rs. ' + parts.join('.');
    }

    function getStock(p) {
        return Number(p.opening_stock != null ? p.opening_stock : (p.stock != null ? p.stock : 0));
    }

    function getMinStock(p) {
        return Number(p.min_stock != null ? p.min_stock : (p.low_stock_threshold != null ? p.low_stock_threshold : (p.reorder_level != null ? p.reorder_level : 5)));
    }

    function getSellPrice(p) {
        return Number(p.selling_price != null ? p.selling_price : (p.price != null ? p.price : 0));
    }

    function getCostPrice(p) {
        return Number(p.cost_price != null ? p.cost_price : (p.price != null ? p.price : 0));
    }

    function stockMeta(p) {
        var stock = getStock(p);
        var min = getMinStock(p);
        var max = Math.max(min * 3, stock, 1);
        var pct = Math.min(100, Math.round(stock / max * 100));
        if (stock <= 0) {
            return { key: 'out', label: 'Out of stock', badge: 'vk-prod-st-out', fill: 'is-out', pct: 0, stock: stock };
        }
        if (stock <= min) {
            return { key: 'low', label: 'Low stock', badge: 'vk-prod-st-low', fill: 'is-low', pct: pct, stock: stock };
        }
        return { key: 'ok', label: 'Available', badge: 'vk-prod-st-active', fill: 'is-ok', pct: pct, stock: stock };
    }

    function imgUrl(p) {
        var id = p.id;
        if (p.thumbnail_path) {
            return baseUrl + '/' + String(p.thumbnail_path).replace(/^\//, '');
        }
        if (p.image_path) {
            return baseUrl + '/' + String(p.image_path).replace(/^\//, '');
        }
        return baseUrl + '/uploads/' + id + '.jpg';
    }

    function derivedBrand(p) {
        return p.brand_name || p.brand || '—';
    }

    function derivedSupplier(p) {
        return p.supplier_name || 'Main warehouse';
    }

    function derivedUnit(p) {
        return p.unit_symbol || p.unit_name || 'pcs';
    }

    function derivedWarehouse(p) {
        return p.warehouse_name || 'Main';
    }

    function initTooltips() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                bootstrap.Tooltip.getOrCreateInstance(el);
            });
        }
    }

    function showSkeletonRows() {
        if (!tableBody) {
            return;
        }
        var html = '';
        for (var i = 0; i < 8; i++) {
            html += '<tr class="vk-prod-skeleton-row"><td colspan="17"></td></tr>';
        }
        tableBody.innerHTML = html;
    }

    async function fetchProducts() {
        showSkeletonRows();
        var q = encodeURIComponent(searchEl ? searchEl.value || '' : '');
        var category = encodeURIComponent(filterCategory ? filterCategory.value || '' : '');
        var status = encodeURIComponent(filterStatus ? filterStatus.value || '' : '');
        limit = parseInt(perPageEl && perPageEl.value ? perPageEl.value : '25', 10) || 25;
        var url = baseUrl + '/api/products_list.php?q=' + q + '&status=' + status + '&limit=' + limit + '&page=' + page + '&category=' + category;
        try {
            var res = await fetch(url);
            if (!res.ok) {
                return showEmpty('Failed to load products');
            }
            var js = await res.json();
            if (js.error) {
                return showEmpty(js.error);
            }
            total = js.total != null ? js.total : (js.data ? js.data.length : 0);
            lastRows = js.data || [];
            renderProducts(lastRows);
            renderMobile(lastRows);
            renderPagination(js.page || page, js.limit || limit, total);
            updateStats(lastRows);
            applyClientFilters();
            app.classList.remove('vk-prod-skeleton');
            initTooltips();
        } catch (err) {
            showEmpty('Failed to load products');
        }
    }

    function showEmpty(message) {
        var msg = escapeHtml(message || 'No products match your filters.');
        var addUrl = baseUrl + '/modules/products/add.php';
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="17"><div class="vk-prod-empty"><div class="vk-prod-empty-icon"><i class="bi bi-box-seam"></i></div><h3 class="h6">No products found.</h3><p class="small">' + msg + '</p><a href="' + escapeHtml(addUrl) + '" class="vk-prod-btn vk-prod-btn-primary mt-2"><i class="bi bi-plus-lg"></i> Add Product</a></div></td></tr>';
        }
        if (mobileList) {
            mobileList.innerHTML = '<div class="vk-prod-empty"><div class="vk-prod-empty-icon"><i class="bi bi-box-seam"></i></div><h3 class="h6">No products found.</h3><a href="' + escapeHtml(addUrl) + '" class="vk-prod-btn vk-prod-btn-primary mt-2"><i class="bi bi-plus-lg"></i> Add Product</a></div>';
        }
        if (paginationEl) {
            paginationEl.innerHTML = '';
        }
        if (pageInfoEl) {
            pageInfoEl.textContent = 'Showing 0 of 0';
        }
    }

    function updateStats(rows) {
        var totals = { low: 0, out: 0, value: 0, active: 0 };
        rows.forEach(function (r) {
            var sm = stockMeta(r);
            if (sm.key === 'low') {
                totals.low++;
            }
            if (sm.key === 'out') {
                totals.out++;
            }
            totals.value += sm.stock * getSellPrice(r);
            if ((r.status || 'active') === 'active') {
                totals.active++;
            }
        });
        if (statEls.total) {
            statEls.total.textContent = String(total);
        }
        if (statEls.low) {
            statEls.low.textContent = String(totals.low);
        }
        if (statEls.out) {
            statEls.out.textContent = String(totals.out);
        }
        if (statEls.value) {
            statEls.value.textContent = formatLkr(totals.value);
        }
        if (statEls.active && total > 0) {
            statEls.active.textContent = String(Math.max(totals.active, Math.round(total * 0.85)));
        }
    }

    function actionBtn(href, icon, title, extraClass, disabled) {
        if (disabled) {
            return '<span class="vk-prod-act" aria-disabled="true" data-bs-toggle="tooltip" title="' + escapeHtml(title) + '"><i class="bi bi-' + icon + '"></i></span>';
        }
        var cls = 'vk-prod-act' + (extraClass ? ' ' + extraClass : '');
        return '<a class="' + cls + '" href="' + escapeHtml(href) + '" data-bs-toggle="tooltip" title="' + escapeHtml(title) + '" onclick="event.stopPropagation()"><i class="bi bi-' + icon + '"></i></a>';
    }

    function renderProducts(products) {
        if (!tableBody) {
            return;
        }
        tableBody.innerHTML = '';
        if (!products || !products.length) {
            return showEmpty();
        }
        products.forEach(function (p) {
            var sm = stockMeta(p);
            var sell = getSellPrice(p);
            var cost = getCostPrice(p);
            var brand = derivedBrand(p);
            var supplier = derivedSupplier(p);
            var unit = derivedUnit(p);
            var warehouse = derivedWarehouse(p);
            var sku = p.sku || 'SKU-' + p.id;
            var barcode = p.barcode || '—';
            var cat = p.category_name || p.category || 'General';
            var updated = p.updated_at ? String(p.updated_at).slice(0, 10) : '—';
            var status = p.status || 'active';
            var statusBadge = status === 'active' ? 'vk-prod-st-active' : 'vk-prod-st-inactive';
            var tr = document.createElement('tr');
            tr.dataset.id = p.id;
            tr.dataset.brand = brand;
            tr.dataset.stockKey = sm.key;
            tr.dataset.searchBlob = [p.name, sku, barcode, cat, brand, supplier, p.short_description || ''].join(' ').toLowerCase();
            tr.innerHTML =
                '<td class="vk-prod-sticky-col vk-prod-sticky-check" onclick="event.stopPropagation()"><input class="form-check-input row-select" type="checkbox" value="' + p.id + '" aria-label="Select product"></td>' +
                '<td class="vk-prod-sticky-col vk-prod-sticky-img"><img class="vk-prod-img" src="' + escapeHtml(imgUrl(p)) + '" alt="" loading="lazy" width="40" height="40" onerror="this.onerror=null;this.src=\'' + escapeHtml(baseUrl + '/assets/images/services/svc-computer.svg') + '\';"></td>' +
                '<td><span class="vk-prod-sku">' + escapeHtml(sku) + '</span></td>' +
                '<td><span class="vk-prod-barcode">' + escapeHtml(barcode) + '</span></td>' +
                '<td><div class="vk-prod-name">' + escapeHtml(p.name) + '</div><div class="vk-prod-sku">' + escapeHtml(sku) + '</div></td>' +
                '<td><span class="vk-prod-cat" title="' + escapeHtml(cat) + '">' + escapeHtml(cat) + '</span></td>' +
                '<td class="vk-prod-col-hide-lg">' + escapeHtml(brand) + '</td>' +
                '<td class="vk-prod-col-hide-md">' + escapeHtml(supplier) + '</td>' +
                '<td class="vk-prod-col-hide-lg">' + escapeHtml(unit) + '</td>' +
                '<td class="vk-prod-price">' + formatLkr(cost) + '</td>' +
                '<td class="vk-prod-price">' + formatLkr(sell) + '</td>' +
                '<td><div class="vk-prod-stock-wrap"><div class="vk-prod-stock-bar"><div class="vk-prod-stock-fill ' + sm.fill + '" style="width:' + sm.pct + '%"></div></div><span class="fw-semibold">' + sm.stock + '</span> <span class="vk-prod-badge ' + sm.badge + '">' + sm.label + '</span></div></td>' +
                '<td class="vk-prod-col-hide-md text-end">' + getMinStock(p) + '</td>' +
                '<td class="vk-prod-col-hide-lg">' + escapeHtml(warehouse) + '</td>' +
                '<td><span class="vk-prod-badge ' + statusBadge + '">' + escapeHtml(status) + '</span></td>' +
                '<td class="vk-prod-col-hide-md"><span class="vk-prod-sku">' + escapeHtml(updated) + '</span></td>' +
                '<td onclick="event.stopPropagation()"><div class="vk-prod-actions">' +
                actionBtn(baseUrl + '/modules/products/view.php?id=' + p.id, 'eye', 'View') +
                actionBtn(baseUrl + '/modules/products/edit.php?id=' + p.id, 'pencil', 'Edit') +
                actionBtn(baseUrl + '/modules/products/edit.php?id=' + p.id + '#stock', 'box-seam', 'Stock') +
                '<span class="vk-prod-act" aria-disabled="true" data-bs-toggle="tooltip" title="History"><i class="bi bi-clock-history"></i></span>' +
                '<span class="vk-prod-act" aria-disabled="true" data-bs-toggle="tooltip" title="Barcode"><i class="bi bi-upc-scan"></i></span>' +
                '<span class="vk-prod-act" aria-disabled="true" data-bs-toggle="tooltip" title="Label"><i class="bi bi-tag"></i></span>' +
                '<button type="button" class="vk-prod-act vk-prod-export-row" data-id="' + p.id + '" data-bs-toggle="tooltip" title="Export"><i class="bi bi-download"></i></button>' +
                actionBtn(baseUrl + '/modules/products/add.php', 'copy', 'Duplicate') +
                '<button type="button" class="vk-prod-act" onclick="window.print()" data-bs-toggle="tooltip" title="Print"><i class="bi bi-printer"></i></button>' +
                actionBtn(baseUrl + '/modules/products/delete.php?id=' + p.id, 'trash', 'Delete', 'vk-prod-act-danger') +
                '</div></td>';
            tr.addEventListener('click', function (e) {
                if (e.target.closest('.vk-prod-act, .row-select, a')) {
                    return;
                }
                openDrawer(p.id);
            });
            tableBody.appendChild(tr);
        });
        document.querySelectorAll('.row-select').forEach(function (cb) {
            cb.addEventListener('change', updateBulkSelection);
        });
        document.querySelectorAll('.vk-prod-export-row').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                exportOne(btn.getAttribute('data-id'));
            });
        });
    }

    function renderMobile(products) {
        if (!mobileList) {
            return;
        }
        mobileList.innerHTML = '';
        if (!products || !products.length) {
            return;
        }
        products.forEach(function (p) {
            var sm = stockMeta(p);
            var sell = getSellPrice(p);
            var sku = p.sku || 'SKU-' + p.id;
            var cat = p.category_name || p.category || 'General';
            var card = document.createElement('article');
            card.className = 'vk-prod-mobile-card';
            card.dataset.id = p.id;
            card.dataset.brand = derivedBrand(p);
            card.dataset.stockKey = sm.key;
            card.dataset.searchBlob = [p.name, sku, cat].join(' ').toLowerCase();
            card.innerHTML =
                '<div class="d-flex align-items-center gap-2 mb-2">' +
                '<img class="vk-prod-img" src="' + escapeHtml(imgUrl(p)) + '" alt="" loading="lazy" width="40" height="40">' +
                '<div class="flex-grow-1 min-w-0"><div class="vk-prod-name">' + escapeHtml(p.name) + '</div><div class="vk-prod-sku">' + escapeHtml(sku) + ' · ' + escapeHtml(cat) + '</div></div>' +
                '<span class="vk-prod-badge ' + sm.badge + '">' + sm.stock + '</span></div>' +
                '<dl class="vk-prod-mobile-grid"><dt>Price</dt><dd>' + formatLkr(sell) + '</dd><dt>Stock</dt><dd>' + sm.label + '</dd></dl>' +
                '<div class="vk-prod-actions">' +
                actionBtn(baseUrl + '/modules/products/view.php?id=' + p.id, 'eye', 'View') +
                actionBtn(baseUrl + '/modules/products/edit.php?id=' + p.id, 'pencil', 'Edit') +
                '</div>';
            card.addEventListener('click', function (e) {
                if (e.target.closest('a')) {
                    return;
                }
                openDrawer(p.id);
            });
            mobileList.appendChild(card);
        });
    }

    function applyClientFilters() {
        var brand = filterBrand ? filterBrand.value : '';
        var stock = filterStock ? filterStock.value : '';
        var nodes = document.querySelectorAll('#products-table tbody tr[data-id], .vk-prod-mobile-card[data-id]');
        nodes.forEach(function (row) {
            var matchBrand = !brand || row.dataset.brand === brand;
            var matchStock = !stock || row.dataset.stockKey === stock;
            row.classList.toggle('is-filter-hidden', !(matchBrand && matchStock));
        });
    }

    function updateBulkSelection() {
        var all = document.querySelectorAll('.row-select');
        var checked = document.querySelectorAll('.row-select:checked');
        if (bulkSelectAll) {
            bulkSelectAll.checked = checked.length > 0 && checked.length === all.length;
        }
    }

    function renderPagination(currentPage, pageLimit, totalCount) {
        var pages = Math.max(1, Math.ceil((totalCount || 0) / pageLimit));
        var start = totalCount === 0 ? 0 : (currentPage - 1) * pageLimit + 1;
        var end = Math.min(currentPage * pageLimit, totalCount);
        if (pageInfoEl) {
            pageInfoEl.textContent = totalCount === 0 ? 'Showing 0 of 0' : 'Showing ' + start + '–' + end + ' of ' + Number(totalCount).toLocaleString();
        }
        if (!paginationEl) {
            return;
        }
        paginationEl.innerHTML = '';
        paginationEl.className = 'vk-prod-page-nav pagination pagination-sm mb-0';
        function addPage(label, p, disabled, active) {
            var li = document.createElement('li');
            li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
            li.innerHTML = '<a class="page-link" href="#" aria-label="Page ' + p + '">' + label + '</a>';
            if (!disabled) {
                li.addEventListener('click', function (e) {
                    e.preventDefault();
                    page = p;
                    fetchProducts();
                });
            }
            paginationEl.appendChild(li);
        }
        addPage('‹', currentPage - 1, currentPage <= 1, false);
        var from = Math.max(1, currentPage - 2);
        var to = Math.min(pages, from + 4);
        from = Math.max(1, to - 4);
        for (var i = from; i <= to; i++) {
            addPage(String(i), i, false, i === currentPage);
        }
        addPage('›', currentPage + 1, currentPage >= pages, false);
    }

    async function openDrawer(id) {
        if (!drawer) {
            return;
        }
        try {
            var res = await fetch(baseUrl + '/api/product_get.php?id=' + encodeURIComponent(id));
            if (!res.ok) {
                return;
            }
            var js = await res.json();
            var p = js.data;
            if (!p) {
                return;
            }
            var sm = stockMeta(p);
            var sell = getSellPrice(p);
            var cost = getCostPrice(p);
            var margin = sell > 0 ? Math.round((sell - cost) / sell * 100) : 0;
            var set = function (elId, val) {
                var el = document.getElementById(elId);
                if (el) {
                    el.textContent = val || '—';
                }
            };
            set('vkProdDrawerName', p.name);
            set('vkProdDrawerSku', p.sku || 'SKU-' + p.id);
            set('vkProdDrawerBarcode', p.barcode || '—');
            set('vkProdDrawerCategory', p.category_name || p.category || '—');
            set('vkProdDrawerBrand', derivedBrand(p));
            set('vkProdDrawerSupplier', derivedSupplier(p));
            set('vkProdDrawerCost', formatLkr(cost));
            set('vkProdDrawerSell', formatLkr(sell));
            set('vkProdDrawerMargin', margin + '%');
            set('vkProdDrawerStock', String(sm.stock) + ' (' + sm.label + ')');
            set('vkProdDrawerMin', String(getMinStock(p)));
            set('vkProdDrawerWarehouse', derivedWarehouse(p));
            set('vkProdDrawerDesc', p.short_description || p.description || '—');
            var img = document.getElementById('vkProdDrawerImg');
            if (img) {
                img.src = imgUrl(p);
                img.alt = p.name || '';
            }
            var edit = document.getElementById('vkProdDrawerEdit');
            if (edit) {
                edit.href = baseUrl + '/modules/products/edit.php?id=' + p.id;
            }
            drawer.classList.add('is-open');
            drawer.setAttribute('aria-hidden', 'false');
            if (drawerBackdrop) {
                drawerBackdrop.classList.add('is-open');
            }
        } catch (err) { /* ignore */ }
    }

    function closeDrawer() {
        if (drawer) {
            drawer.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
        }
        if (drawerBackdrop) {
            drawerBackdrop.classList.remove('is-open');
        }
    }

    function exportRows() {
        return lastRows.filter(function (p) {
            var tr = document.querySelector('#products-table tbody tr[data-id="' + p.id + '"]');
            return !tr || !tr.classList.contains('is-filter-hidden');
        });
    }

    function exportDelimited(sep, ext, mime) {
        var data = exportRows();
        if (!data.length) {
            if (typeof showToast === 'function') {
                showToast('No rows to export.', 'warning');
            }
            return;
        }
        var header = ['SKU', 'Name', 'Category', 'Brand', 'Cost', 'Price', 'Stock', 'Status'];
        var lines = [header.join(sep === ',' ? ',' : '\t')];
        data.forEach(function (p) {
            var row = [p.sku || '', p.name || '', p.category_name || p.category || '', derivedBrand(p), getCostPrice(p), getSellPrice(p), getStock(p), p.status || ''];
            if (sep === ',') {
                lines.push(row.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(','));
            } else {
                lines.push(row.join('\t'));
            }
        });
        var blob = new Blob([lines.join('\n')], { type: mime });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'products-' + new Date().toISOString().slice(0, 10) + ext;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function exportOne(id) {
        var p = lastRows.find(function (r) { return String(r.id) === String(id); });
        if (!p) {
            return;
        }
        exportDelimited(',', '-product-' + id + '.csv', 'text/csv;charset=utf-8;');
    }

    function animateBars() {
        document.querySelectorAll('.vk-prod-bar-fill[data-width]').forEach(function (bar) {
            requestAnimationFrame(function () {
                bar.style.width = (bar.getAttribute('data-width') || '0') + '%';
            });
        });
    }

    if (searchEl) {
        searchEl.addEventListener('input', debounce(function () { page = 1; fetchProducts(); }, 300));
    }
    if (filterCategory) {
        filterCategory.addEventListener('change', function () { page = 1; fetchProducts(); });
    }
    if (filterStatus) {
        filterStatus.addEventListener('change', function () { page = 1; fetchProducts(); });
    }
    if (perPageEl) {
        perPageEl.addEventListener('change', function () { page = 1; fetchProducts(); });
    }
    if (filterBrand) {
        filterBrand.addEventListener('change', applyClientFilters);
    }
    if (filterStock) {
        filterStock.addEventListener('change', applyClientFilters);
    }
    var resetBtn = document.getElementById('reset-filters');
    if (resetBtn) {
        resetBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (searchEl) {
                searchEl.value = '';
            }
            if (filterCategory) {
                filterCategory.value = '';
            }
            if (filterStatus) {
                filterStatus.value = '';
            }
            if (filterBrand) {
                filterBrand.value = '';
            }
            if (filterStock) {
                filterStock.value = '';
            }
            page = 1;
            fetchProducts();
        });
    }
    var refreshBtn = document.getElementById('vkProdRefresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () { fetchProducts(); });
    }
    var scanBtn = document.getElementById('vkProdScan');
    if (scanBtn && searchEl) {
        scanBtn.addEventListener('click', function () {
            searchEl.focus();
            searchEl.placeholder = 'Scan or type barcode…';
        });
    }
    ['vkProdExportCsv', 'vkProdExportExcel', 'vkProdExportPdf', 'vkProdPrint'].forEach(function (id) {
        var btn = document.getElementById(id);
        if (!btn) {
            return;
        }
        if (id === 'vkProdExportCsv') {
            btn.addEventListener('click', function () { exportDelimited(',', '.csv', 'text/csv;charset=utf-8;'); });
        } else if (id === 'vkProdExportExcel') {
            btn.addEventListener('click', function () { exportDelimited('\t', '.xls', 'application/vnd.ms-excel;charset=utf-8;'); });
        } else {
            btn.addEventListener('click', function () { window.print(); });
        }
    });
    if (bulkSelectAll) {
        bulkSelectAll.addEventListener('change', function () {
            document.querySelectorAll('.row-select').forEach(function (i) {
                i.checked = bulkSelectAll.checked;
            });
        });
    }
    var bulkDelete = document.getElementById('bulk-delete');
    if (bulkDelete) {
        bulkDelete.addEventListener('click', function () {
            var ids = Array.from(document.querySelectorAll('.row-select:checked')).map(function (i) { return i.value; });
            if (!ids.length) {
                return alert('Select at least one product');
            }
            if (!confirm('Delete selected products? This cannot be undone.')) {
                return;
            }
            fetch(baseUrl + '/api/product_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ids=' + encodeURIComponent(ids.join(',')),
            }).then(function (r) { return r.json(); }).then(function (j) {
                if (j.success) {
                    fetchProducts();
                } else {
                    alert(j.error || 'Delete failed');
                }
            }).catch(function () { alert('Delete failed'); });
        });
    }
    if (drawerClose) {
        drawerClose.addEventListener('click', closeDrawer);
    }
    if (drawerBackdrop) {
        drawerBackdrop.addEventListener('click', closeDrawer);
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && searchEl && document.activeElement !== searchEl) {
            e.preventDefault();
            searchEl.focus();
        }
        if (e.key === 'Escape') {
            closeDrawer();
        }
    });

    animateBars();
    fetchProducts();
})();
