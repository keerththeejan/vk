(function () {
    'use strict';

    var cfg = window.VK_INVOICE_CFG || {};
    var base = window.VK_BASE_URL || '';
    var productsUrl = cfg.productsUrl || (base + '/api/invoices_products.php');
    var customersUrl = cfg.customersUrl || (base + '/api/customers_search.php');
    var canViewCost = !!cfg.canViewCost;
    var mode = cfg.mode || 'create';

    var form = document.getElementById('invoiceForm');
    if (!form) return;

    var tbody = document.getElementById('linesBody');
    var tplProduct = document.getElementById('lineTplProduct');
    var tplService = document.getElementById('lineTplService');
    var customerInput = document.getElementById('customer_search');
    var customerId = document.getElementById('customer_id');
    var customerSelected = document.getElementById('customer_selected');
    var resultsEl = document.getElementById('customer_results');
    var activePickerRow = null;
    var pickerModal = null;

    function money(n) {
        return (Math.round((n || 0) * 100) / 100).toFixed(2);
    }

    function num(v) {
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function calcLine(qty, price, discType, discVal, taxPct) {
        qty = Math.max(0, qty);
        price = Math.max(0, price);
        discVal = Math.max(0, discVal);
        taxPct = Math.max(0, taxPct);
        var gross = Math.round(qty * price * 100) / 100;
        var discAmt = discType === 'fixed'
            ? Math.round(discVal * 100) / 100
            : Math.round(gross * Math.min(100, discVal) / 100 * 100) / 100;
        if (discAmt > gross) discAmt = gross;
        var after = Math.round((gross - discAmt) * 100) / 100;
        var taxAmt = Math.round(after * taxPct / 100 * 100) / 100;
        var net = Math.round((after + taxAmt) * 100) / 100;
        var netPrice = qty > 0 ? Math.round((after / qty) * 100) / 100 : 0;
        return { gross: gross, discAmt: discAmt, taxAmt: taxAmt, net: net, netPrice: netPrice };
    }

    function renumberRows() {
        var i = 1;
        tbody.querySelectorAll('tr.line-row').forEach(function (row) {
            var el = row.querySelector('.line-no');
            if (el) el.textContent = String(i++);
        });
    }

    function syncDesc(row) {
        var desc = row.querySelector('.line-desc');
        var hidden = row.querySelector('.line-desc-hidden');
        if (desc && hidden && row.getAttribute('data-line-kind') === 'product') {
            hidden.value = desc.value;
            // Ensure name=line_description for product rows via hidden sync field
            desc.removeAttribute('name');
            hidden.setAttribute('name', 'line_description[]');
        }
    }

    function showPreview(item) {
        var box = document.getElementById('productPreview');
        if (!box || !item) return;
        box.classList.remove('d-none');
        var nameEl = document.getElementById('productPreviewName');
        var stockEl = document.getElementById('productPreviewStock');
        var priceEl = document.getElementById('productPreviewPrice');
        var costEl = document.getElementById('productPreviewCost');
        if (nameEl) nameEl.textContent = item.name || '';
        if (stockEl) stockEl.textContent = item.stock_available != null ? String(item.stock_available) : '—';
        if (priceEl) priceEl.textContent = money(item.unit_price || 0);
        if (costEl && canViewCost) costEl.textContent = money(item.cost_price || 0);
    }

    function fillProduct(row, item) {
        if (!row || !item) return;
        var pid = row.querySelector('.product-id');
        var search = row.querySelector('.product-search');
        var code = row.querySelector('.item-code');
        var desc = row.querySelector('.line-desc');
        var price = row.querySelector('.unit-price-input');
        var unit = row.querySelector('.unit-input');
        var cost = row.querySelector('.cost-price');
        if (pid) pid.value = item.id;
        if (search) search.value = item.name || '';
        if (code) code.value = item.product_code || item.sku || String(item.id);
        if (desc) desc.value = item.description || item.name || '';
        if (price) price.value = money(item.unit_price || 0);
        if (unit) unit.value = item.unit || 'pcs';
        if (cost) cost.value = item.cost_price || 0;
        row.setAttribute('data-stock', item.stock_available != null ? String(item.stock_available) : '');
        syncDesc(row);
        showPreview(item);
        recalc();
    }

    function searchProducts(q, cb) {
        fetch(productsUrl + '?q=' + encodeURIComponent(q) + '&limit=20')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                cb(data.items || []);
            })
            .catch(function () { cb([]); });
    }

    function renderProductResults(container, items, row) {
        container.innerHTML = '';
        if (!items.length) {
            container.classList.add('d-none');
            return;
        }
        items.forEach(function (it) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action py-2';
            btn.innerHTML = '<div class="d-flex justify-content-between gap-2">' +
                '<span>' + escapeHtml(it.name) +
                (it.product_code ? ' <small class="text-muted">(' + escapeHtml(String(it.product_code)) + ')</small>' : '') +
                '</span>' +
                '<span class="text-nowrap">' + money(it.unit_price) +
                (it.stock_available != null ? ' · stk ' + it.stock_available : '') +
                '</span></div>';
            btn.addEventListener('click', function () {
                fillProduct(row, it);
                container.classList.add('d-none');
            });
            container.appendChild(btn);
        });
        container.classList.remove('d-none');
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function wireRow(row) {
        var rm = row.querySelector('.rm-line');
        if (rm) {
            rm.addEventListener('click', function () {
                row.remove();
                renumberRows();
                recalc();
            });
        }
        var dup = row.querySelector('.dup-line');
        if (dup) {
            dup.addEventListener('click', function () {
                var clone = row.cloneNode(true);
                row.parentNode.insertBefore(clone, row.nextSibling);
                wireRow(clone);
                renumberRows();
                recalc();
            });
        }
        var up = row.querySelector('.move-up');
        if (up) {
            up.addEventListener('click', function () {
                if (row.previousElementSibling) {
                    tbody.insertBefore(row, row.previousElementSibling);
                    renumberRows();
                }
            });
        }
        var down = row.querySelector('.move-down');
        if (down) {
            down.addEventListener('click', function () {
                if (row.nextElementSibling) {
                    tbody.insertBefore(row.nextElementSibling, row);
                    renumberRows();
                }
            });
        }

        ['qty-input', 'unit-price-input', 'discount-value', 'tax-pct'].forEach(function (cls) {
            var el = row.querySelector('.' + cls);
            if (el) el.addEventListener('input', recalc);
        });
        var dt = row.querySelector('.discount-type');
        if (dt) dt.addEventListener('change', recalc);

        var desc = row.querySelector('.line-desc');
        if (desc) {
            desc.addEventListener('input', function () { syncDesc(row); });
        }

        if (row.getAttribute('data-line-kind') === 'product') {
            var search = row.querySelector('.product-search');
            var results = row.querySelector('.product-results');
            var pickBtn = row.querySelector('.btn-pick-product');
            var timer;
            if (search && results) {
                search.addEventListener('input', function () {
                    clearTimeout(timer);
                    var q = search.value.trim();
                    timer = setTimeout(function () {
                        if (q.length < 1) {
                            results.classList.add('d-none');
                            return;
                        }
                        searchProducts(q, function (items) {
                            renderProductResults(results, items, row);
                        });
                    }, 220);
                });
                search.addEventListener('focus', function () {
                    if (search.value.trim()) search.dispatchEvent(new Event('input'));
                });
            }
            if (pickBtn) {
                pickBtn.addEventListener('click', function () {
                    activePickerRow = row;
                    openProductPicker();
                });
            }
        }
        syncDesc(row);
    }

    function recalc() {
        var sub = 0;
        var itemDisc = 0;
        var taxTotal = 0;
        var itemCount = 0;

        tbody.querySelectorAll('tr.line-row').forEach(function (row) {
            var qty = num(row.querySelector('.qty-input') && row.querySelector('.qty-input').value);
            var price = num(row.querySelector('.unit-price-input') && row.querySelector('.unit-price-input').value);
            var discType = (row.querySelector('.discount-type') || {}).value || 'percent';
            var discVal = num(row.querySelector('.discount-value') && row.querySelector('.discount-value').value);
            var taxPct = num(row.querySelector('.tax-pct') && row.querySelector('.tax-pct').value);
            var c = calcLine(qty, price, discType, discVal, taxPct);
            sub += c.gross;
            itemDisc += c.discAmt;
            taxTotal += c.taxAmt;
            if (qty > 0) itemCount += qty;
            var da = row.querySelector('.discount-amount');
            if (da) da.textContent = money(c.discAmt);
            var lt = row.querySelector('.line-total');
            if (lt) lt.textContent = money(c.net);
            syncDesc(row);
        });

        var invType = (document.getElementById('invoice_discount_type') || {}).value || 'fixed';
        var invVal = num((document.getElementById('invoice_discount_value') || {}).value);
        var afterItem = Math.round((sub - itemDisc) * 100) / 100;
        var invDisc = invType === 'percent'
            ? Math.round(afterItem * Math.min(100, invVal) / 100 * 100) / 100
            : Math.round(invVal * 100) / 100;
        if (invDisc > afterItem) invDisc = afterItem;

        var shipping = num((document.getElementById('shipping_amount') || {}).value);
        var adjustment = num((document.getElementById('adjustment_amount') || {}).value);
        var roundOff = num((document.getElementById('round_off') || {}).value);
        var grand = Math.round((afterItem - invDisc + taxTotal + shipping + adjustment + roundOff) * 100) / 100;
        if (grand < 0) grand = 0;

        var paid = 0;
        document.querySelectorAll('.pay-amount').forEach(function (el) {
            paid += num(el.value);
        });
        // On edit, unpaid balance uses paid_amount from config
        if (mode === 'edit' && cfg.paidAmount != null) {
            paid = num(cfg.paidAmount);
        }
        var balance = Math.round((grand - paid) * 100) / 100;

        setText('disp_subtotal', money(sub));
        setText('disp_item_discount', money(itemDisc));
        setText('disp_invoice_discount', money(invDisc));
        setText('disp_tax', money(taxTotal));
        setText('disp_grand', money(grand));
        setText('disp_balance', money(balance));

        var taxHidden = document.getElementById('tax');
        if (taxHidden) taxHidden.value = money(taxTotal);

        // Legacy discount mirror if present
        var legacyDisc = document.getElementById('discount');
        if (legacyDisc) legacyDisc.value = money(invDisc);

        recalcPayment(grand, itemCount);
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function recalcPayment(grand, itemCount) {
        if (grand == null) {
            grand = num((document.getElementById('disp_grand') || {}).textContent);
        }
        var totalPaying = 0;
        document.querySelectorAll('.pay-amount').forEach(function (el) {
            totalPaying += num(el.value);
        });
        if (itemCount == null) {
            itemCount = 0;
            tbody.querySelectorAll('tr.line-row .qty-input').forEach(function (el) {
                itemCount += parseInt(el.value, 10) || 0;
            });
        }
        var change = totalPaying > grand ? totalPaying - grand : 0;
        var balance = grand > totalPaying ? grand - totalPaying : 0;
        if (mode === 'edit' && cfg.paidAmount != null) {
            balance = Math.max(0, grand - num(cfg.paidAmount));
            totalPaying = num(cfg.paidAmount);
            change = 0;
        }
        setText('pay_total_items', String(itemCount));
        setText('pay_total_payable', money(grand));
        setText('pay_total_paying', money(totalPaying));
        setText('pay_change_return', money(change));
        setText('pay_balance_due', money(balance));
    }

    /* ─── Payment rows ───────────────────────────────────────────── */
    var payRowIdx = 1;

    function wirePayRow(row) {
        var amt = row.querySelector('.pay-amount');
        if (amt) amt.addEventListener('input', function () { recalc(); });
        var rm = row.querySelector('.rm-pay-row');
        if (rm) {
            rm.addEventListener('click', function () {
                row.remove();
                updatePayRemoveButtons();
                recalc();
            });
        }
    }

    function updatePayRemoveButtons() {
        var rows = document.querySelectorAll('.payment-row');
        rows.forEach(function (row) {
            var btn = row.querySelector('.rm-pay-row');
            if (btn) btn.classList.toggle('d-none', rows.length <= 1);
        });
    }

    function addPaymentRow() {
        var container = document.getElementById('paymentRows');
        if (!container) return;
        var idx = payRowIdx++;
        container.insertAdjacentHTML('beforeend',
            '<div class="row g-2 align-items-end payment-row mb-2" data-payment-row="' + idx + '">' +
            '<div class="col-12 col-sm-3"><label class="form-label small mb-1">Amount</label>' +
            '<input type="number" step="0.01" min="0" class="form-control form-control-sm pay-amount" name="pay_amount[]" placeholder="0.00"></div>' +
            '<div class="col-12 col-sm-3"><label class="form-label small mb-1">Method</label>' +
            '<select class="form-select form-select-sm pay-method" name="pay_method[]">' +
            '<option value="">— Select —</option><option value="cash">Cash</option><option value="card">Card</option>' +
            '<option value="bank">Bank</option><option value="online">Online</option></select></div>' +
            '<div class="col-12 col-sm-4"><label class="form-label small mb-1">Note</label>' +
            '<input type="text" class="form-control form-control-sm pay-note" name="pay_note[]" maxlength="255"></div>' +
            '<div class="col-12 col-sm-2 text-end"><button type="button" class="btn btn-sm btn-outline-danger rm-pay-row"><i class="bi bi-x-lg"></i></button></div></div>'
        );
        wirePayRow(container.lastElementChild);
        updatePayRemoveButtons();
        recalc();
    }

    /* ─── Line items ─────────────────────────────────────────────── */
    function addProductLine(prefill) {
        tbody.appendChild(tplProduct.content.cloneNode(true));
        var row = tbody.lastElementChild;
        wireRow(row);
        if (prefill) fillProduct(row, prefill);
        renumberRows();
        recalc();
        return row;
    }

    function addServiceLine() {
        tbody.appendChild(tplService.content.cloneNode(true));
        wireRow(tbody.lastElementChild);
        renumberRows();
        recalc();
    }

    function openProductPicker(initialQ) {
        var modalEl = document.getElementById('productPickerModal');
        if (!modalEl || !window.bootstrap) return;
        pickerModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        var input = document.getElementById('productPickerSearch');
        if (input) {
            input.value = initialQ || '';
            setTimeout(function () { input.focus(); input.dispatchEvent(new Event('input')); }, 200);
        }
        pickerModal.show();
    }

    var pickerTimer;
    var pickerSearch = document.getElementById('productPickerSearch');
    if (pickerSearch) {
        pickerSearch.addEventListener('input', function () {
            clearTimeout(pickerTimer);
            var q = pickerSearch.value.trim();
            pickerTimer = setTimeout(function () {
                searchProducts(q || '', function (items) {
                    var box = document.getElementById('productPickerResults');
                    if (!box) return;
                    box.innerHTML = '';
                    (items.length ? items : []).forEach(function (it) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action';
                        btn.textContent = it.name + ' — ' + money(it.unit_price) +
                            (it.stock_available != null ? ' (stock ' + it.stock_available + ')' : '');
                        btn.addEventListener('click', function () {
                            if (activePickerRow) {
                                fillProduct(activePickerRow, it);
                            } else {
                                addProductLine(it);
                            }
                            if (pickerModal) pickerModal.hide();
                        });
                        box.appendChild(btn);
                    });
                });
            }, 200);
        });
    }

    /* ─── Barcode ────────────────────────────────────────────────── */
    var barcodeEl = document.getElementById('barcode_search');
    if (barcodeEl) {
        barcodeEl.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var code = barcodeEl.value.trim();
            if (!code) return;
            fetch(productsUrl + '?action=barcode&barcode=' + encodeURIComponent(code))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var item = data.item || (data.items && data.items[0]);
                    if (item) {
                        addProductLine(item);
                        barcodeEl.value = '';
                        if (window.showToast) window.showToast('Added: ' + item.name, 'success');
                    } else if (window.showToast) {
                        window.showToast('Product not found for barcode.', 'warning');
                    }
                })
                .catch(function () {
                    if (window.showToast) window.showToast('Barcode lookup failed.', 'danger');
                });
        });
    }

    function openProductList(action, title) {
        fetch(productsUrl + '?action=' + encodeURIComponent(action) + '&limit=15')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                activePickerRow = null;
                openProductPicker('');
                var modalTitle = document.querySelector('#productPickerModal .modal-title');
                if (modalTitle && title) modalTitle.textContent = title;
                var box = document.getElementById('productPickerResults');
                if (!box) return;
                box.innerHTML = '';
                (data.items || []).forEach(function (it) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action';
                    btn.textContent = it.name + ' — ' + money(it.unit_price);
                    btn.addEventListener('click', function () {
                        addProductLine(it);
                        if (pickerModal) pickerModal.hide();
                    });
                    box.appendChild(btn);
                });
            });
    }

    var recentBtn = document.getElementById('btnRecentProducts');
    if (recentBtn) {
        recentBtn.addEventListener('click', function () {
            openProductList('recent', 'Recent products');
        });
    }
    var favBtn = document.getElementById('btnFavouriteProducts');
    if (favBtn) {
        favBtn.addEventListener('click', function () {
            openProductList('favourite', 'Favourite products');
        });
    }

    /* ─── Customer search ────────────────────────────────────────── */
    function loadCustomers(q) {
        if (!customerInput || !resultsEl) return;
        fetch(customersUrl + '?q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                resultsEl.innerHTML = '';
                if (!data.results || !data.results.length) {
                    resultsEl.classList.add('d-none');
                    return;
                }
                data.results.forEach(function (c) {
                    var a = document.createElement('button');
                    a.type = 'button';
                    a.className = 'list-group-item list-group-item-action';
                    a.textContent = c.name + (c.phone ? ' · ' + c.phone : '');
                    a.addEventListener('click', function () {
                        customerId.value = c.id;
                        customerSelected.textContent = 'Selected: ' + c.name;
                        customerInput.value = c.name;
                        resultsEl.classList.add('d-none');
                    });
                    resultsEl.appendChild(a);
                });
                resultsEl.classList.remove('d-none');
            })
            .catch(function () { resultsEl.classList.add('d-none'); });
    }

    if (customerInput) {
        customerInput.addEventListener('focus', function () {
            if (!customerInput.value.trim()) loadCustomers('');
        });
        var searchTimer;
        customerInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                loadCustomers(customerInput.value.trim());
            }, 250);
        });
    }

    document.addEventListener('click', function (e) {
        if (resultsEl && !resultsEl.contains(e.target) && e.target !== customerInput) {
            resultsEl.classList.add('d-none');
        }
        document.querySelectorAll('.product-results').forEach(function (box) {
            if (!box.contains(e.target) && !(e.target.classList && e.target.classList.contains('product-search'))) {
                box.classList.add('d-none');
            }
        });
    });

    /* ─── Totals inputs ──────────────────────────────────────────── */
    ['invoice_discount_type', 'invoice_discount_value', 'shipping_amount', 'adjustment_amount', 'round_off'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', recalc);
        if (el) el.addEventListener('change', recalc);
    });

    var addProd = document.getElementById('addProductLine');
    var addSvc = document.getElementById('addServiceLine');
    if (addProd) addProd.addEventListener('click', function () { addProductLine(); });
    if (addSvc) addSvc.addEventListener('click', addServiceLine);

    var addPayBtn = document.getElementById('addPaymentRow');
    if (addPayBtn) addPayBtn.addEventListener('click', addPaymentRow);
    var firstPay = document.querySelector('.payment-row');
    if (firstPay) wirePayRow(firstPay);

    /* ─── Form actions / validation ──────────────────────────────── */
    form.querySelectorAll('[data-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var fa = document.getElementById('form_action');
            if (fa) fa.value = btn.getAttribute('data-action') || 'create';
        });
    });

    form.addEventListener('submit', function (e) {
        var action = (document.getElementById('form_action') || {}).value || 'create';
        if (!customerId || !customerId.value) {
            e.preventDefault();
            if (window.showToast) window.showToast('Please select a customer.', 'danger');
            return;
        }
        var rows = tbody.querySelectorAll('tr.line-row');
        if (!rows.length) {
            e.preventDefault();
            if (window.showToast) window.showToast('Add at least one line item.', 'danger');
            return;
        }
        var valid = false;
        var err = null;
        rows.forEach(function (row, idx) {
            var qty = num(row.querySelector('.qty-input') && row.querySelector('.qty-input').value);
            var price = num(row.querySelector('.unit-price-input') && row.querySelector('.unit-price-input').value);
            var kind = row.getAttribute('data-line-kind');
            var pid = row.querySelector('.product-id');
            var desc = row.querySelector('.line-desc');
            if (qty <= 0) {
                err = 'Quantity cannot be zero on line ' + (idx + 1);
                return;
            }
            if (price < 0) {
                err = 'Price cannot be negative on line ' + (idx + 1);
                return;
            }
            if (kind === 'product' && (!pid || !pid.value)) {
                err = 'Select a product on line ' + (idx + 1);
                return;
            }
            if (kind === 'service' && (!desc || !desc.value.trim())) {
                err = 'Service description required on line ' + (idx + 1);
                return;
            }
            var discType = (row.querySelector('.discount-type') || {}).value || 'percent';
            var discVal = num(row.querySelector('.discount-value') && row.querySelector('.discount-value').value);
            var c = calcLine(qty, price, discType, discVal, 0);
            if (c.discAmt > c.gross + 0.001) {
                err = 'Discount exceeds line total on line ' + (idx + 1);
                return;
            }
            valid = true;
            syncDesc(row);
        });
        if (err) {
            e.preventDefault();
            if (window.showToast) window.showToast(err, 'danger');
            return;
        }
        if (!valid) {
            e.preventDefault();
            if (window.showToast) window.showToast('Add at least one valid line.', 'danger');
            return;
        }

        if (action !== 'draft' && mode !== 'edit') {
            var grand = num((document.getElementById('disp_grand') || {}).textContent);
            var totalPaying = 0;
            var payValid = true;
            document.querySelectorAll('.payment-row').forEach(function (row) {
                var amt = num(row.querySelector('.pay-amount') && row.querySelector('.pay-amount').value);
                var method = (row.querySelector('.pay-method') || {}).value || '';
                if (amt > 0 && !method) payValid = false;
                totalPaying += amt;
            });
            if (!payValid) {
                e.preventDefault();
                if (window.showToast) window.showToast('Select a payment method for each payment amount.', 'danger');
                return;
            }
            if (totalPaying > grand + 0.01) {
                e.preventDefault();
                if (window.showToast) window.showToast('Total payment exceeds grand total.', 'danger');
                return;
            }
        }

        // Loading indicator
        form.classList.add('vk-loading');
    });

    /* ─── Keyboard shortcuts ─────────────────────────────────────── */
    document.addEventListener('keydown', function (e) {
        if (!e.altKey) return;
        if (e.key === 'a' || e.key === 'A') {
            e.preventDefault();
            addProductLine();
        }
        if (e.key === 's' || e.key === 'S') {
            e.preventDefault();
            var fa = document.getElementById('form_action');
            if (fa) fa.value = mode === 'edit' ? 'update' : 'create';
            if (form.requestSubmit) form.requestSubmit();
            else form.submit();
        }
    });

    /* ─── Prefill existing lines (edit mode) ──────────────────────── */
    function loadExistingLines() {
        var existing = cfg.existingLines || [];
        if (!existing.length) {
            if (tbody.children.length === 0) addProductLine();
            return;
        }
        existing.forEach(function (ln) {
            var kind = ln.item_type === 'service' ? 'service' : 'product';
            if (kind === 'service') {
                addServiceLine();
            } else {
                addProductLine();
            }
            var row = tbody.lastElementChild;
            if (kind === 'product') {
                fillProduct(row, {
                    id: ln.product_id,
                    name: ln.product_name || ln.line_description || '',
                    product_code: ln.item_code || '',
                    unit_price: ln.unit_price,
                    unit: ln.unit || 'pcs',
                    description: ln.line_description || '',
                    cost_price: ln.cost_price || 0,
                    stock_available: ln.product_stock,
                });
            } else {
                var desc = row.querySelector('.line-desc');
                if (desc) desc.value = ln.line_description || '';
                var price = row.querySelector('.unit-price-input');
                if (price) price.value = money(ln.unit_price);
                var unit = row.querySelector('.unit-input');
                if (unit) unit.value = ln.unit || 'job';
                var code = row.querySelector('.item-code');
                if (code && ln.item_code) code.value = ln.item_code;
            }
            var qty = row.querySelector('.qty-input');
            if (qty) qty.value = ln.quantity || 1;
            var up = row.querySelector('.unit-price-input');
            if (up) up.value = money(ln.unit_price || 0);
            var dt = row.querySelector('.discount-type');
            if (dt) dt.value = ln.discount_type || 'percent';
            var dv = row.querySelector('.discount-value');
            if (dv) dv.value = ln.discount_value || 0;
            var tp = row.querySelector('.tax-pct');
            if (tp) tp.value = ln.tax_pct || 0;
        });
        renumberRows();
        recalc();
    }

    // Expose for edit.php prefill of header fields (already in HTML)
    window.VK_INVOICE_RECALC = recalc;
    window.VK_INVOICE_ADD_PRODUCT = addProductLine;

    loadExistingLines();
    recalc();
})();
