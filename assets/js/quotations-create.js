/**
 * Premium ERP Quotation Create — client logic
 * Live calculations, product/customer autocomplete, AJAX save, keyboard nav.
 */
(function () {
    'use strict';

    function money(n) {
        return (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);
    }
    function toast(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type || 'info');
        else alert(msg);
    }
    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    const form = document.getElementById('quotationForm');
    if (!form) return;

    const body = document.getElementById('itemsBody');
    const tpl = document.getElementById('qtnLineTpl');
    const customers = window.QTN_CUSTOMERS || [];
    const loading = document.getElementById('qtnLoading');
    let dragRow = null;
    let saving = false;

    function setLoading(on) {
        if (!loading) return;
        loading.classList.toggle('d-none', !on);
        loading.setAttribute('aria-hidden', on ? 'false' : 'true');
    }

    /* ── Lines ─────────────────────────────────────────────── */
    function wireLine(row) {
        row.querySelectorAll('input, select').forEach(function (inp) {
            inp.addEventListener('input', function () {
                if (inp.classList.contains('discount-pct')) {
                    const qty = parseFloat(row.querySelector('.qty').value) || 0;
                    const price = parseFloat(row.querySelector('.unit-price').value) || 0;
                    const pct = parseFloat(inp.value) || 0;
                    row.querySelector('.discount-amount').value = money(qty * price * pct / 100);
                }
                if (inp.classList.contains('discount-amount')) {
                    const qty = parseFloat(row.querySelector('.qty').value) || 0;
                    const price = parseFloat(row.querySelector('.unit-price').value) || 0;
                    const gross = qty * price;
                    const amt = parseFloat(inp.value) || 0;
                    row.querySelector('.discount-pct').value = gross > 0 ? money((amt / gross) * 100) : '0';
                }
                recalc();
            });
            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    const inputs = Array.from(row.querySelectorAll('input:not([type=hidden]), select'));
                    const idx = inputs.indexOf(inp);
                    if (idx >= 0 && idx < inputs.length - 1) inputs[idx + 1].focus();
                    else addLine({});
                }
            });
        });
        row.querySelector('.rm-line').addEventListener('click', function () {
            row.remove();
            recalc();
        });
        row.querySelector('.dup-line').addEventListener('click', function () {
            const clone = row.cloneNode(true);
            wireLine(clone);
            row.after(clone);
            recalc();
        });
        row.addEventListener('dragstart', function () {
            dragRow = row;
            row.classList.add('dragging');
        });
        row.addEventListener('dragend', function () {
            row.classList.remove('dragging');
            dragRow = null;
        });
        row.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!dragRow || dragRow === row) return;
            const rect = row.getBoundingClientRect();
            if (e.clientY > rect.top + rect.height / 2) row.after(dragRow);
            else row.before(dragRow);
        });
    }

    function addLine(data) {
        const node = tpl.content.firstElementChild.cloneNode(true);
        const wh = window.QTN_DEFAULT_WAREHOUSE || 'Main Warehouse';
        if (data) {
            node.querySelector('.item-type').value = data.item_type || (data.product_id ? 'product' : 'custom');
            node.querySelector('.product-id').value = data.product_id || data.id || '';
            node.querySelector('.product-code').value = data.product_code || '';
            node.querySelector('.barcode').value = data.barcode || '';
            node.querySelector('.product-name').value = data.product_name || data.name || '';
            node.querySelector('.description').value = data.description || '';
            node.querySelector('.category-name').value = data.category_name || '';
            node.querySelector('.unit').value = data.unit || 'pcs';
            node.querySelector('.qty').value = data.quantity != null ? data.quantity : 1;
            node.querySelector('.unit-price').value = data.unit_price != null ? data.unit_price : 0;
            node.querySelector('.cost-price').value = data.cost_price || 0;
            node.querySelector('.discount-pct').value = data.discount_pct || 0;
            node.querySelector('.discount-amount').value = data.discount_amount || 0;
            node.querySelector('.tax-pct').value = data.tax_pct != null ? data.tax_pct : (window.QTN_DEFAULT_TAX || 0);
            if (data.warehouse || data.line_warehouse) {
                node.querySelector('.line-warehouse').value = data.warehouse || data.line_warehouse;
            } else {
                node.querySelector('.line-warehouse').value = wh;
            }
            if (data.stock_available != null && data.stock_available !== '') {
                node.querySelector('.stock-available').value = data.stock_available;
                const badge = node.querySelector('.stock-badge');
                const stock = Number(data.stock_available);
                badge.textContent = String(stock);
                badge.className = 'badge border stock-badge ' + (stock > 0 ? 'text-bg-success' : 'text-bg-warning');
            }
        } else {
            node.querySelector('.line-warehouse').value = wh;
            node.querySelector('.tax-pct').value = window.QTN_DEFAULT_TAX || 0;
        }
        wireLine(node);
        body.appendChild(node);
        recalc();
        const focusEl = node.querySelector('.product-name');
        if (focusEl && !data) focusEl.focus();
        return node;
    }

    document.getElementById('addCustomLine').addEventListener('click', function () {
        addLine({});
    });

    /* ── Totals ────────────────────────────────────────────── */
    function recalc() {
        let subtotal = 0, itemDisc = 0, taxTotal = 0, cost = 0, qtyTotal = 0;
        const taxMethod = (document.getElementById('tax_method') || {}).value || 'exclusive';
        let lines = 0;

        body.querySelectorAll('.qtn-line').forEach(function (row) {
            lines += 1;
            const qty = parseFloat(row.querySelector('.qty').value) || 0;
            const price = parseFloat(row.querySelector('.unit-price').value) || 0;
            let discPct = parseFloat(row.querySelector('.discount-pct').value) || 0;
            let discAmt = parseFloat(row.querySelector('.discount-amount').value) || 0;
            let taxPct = parseFloat(row.querySelector('.tax-pct').value) || 0;
            const c = parseFloat(row.querySelector('.cost-price').value) || 0;
            if (taxMethod === 'none') taxPct = 0;

            const gross = qty * price;
            if (document.activeElement !== row.querySelector('.discount-amount')) {
                discAmt = gross * discPct / 100;
                row.querySelector('.discount-amount').value = money(discAmt);
            } else if (gross > 0) {
                discPct = (discAmt / gross) * 100;
                row.querySelector('.discount-pct').value = money(discPct);
            }
            if (discAmt > gross) discAmt = gross;
            const after = gross - discAmt;
            const tax = after * taxPct / 100;
            const total = after + tax;

            row.querySelector('.tax-amount-disp').textContent = money(tax);
            row.querySelector('.line-total').textContent = money(total);

            subtotal += gross;
            itemDisc += discAmt;
            taxTotal += tax;
            cost += c * qty;
            qtyTotal += qty;
        });

        const afterItem = subtotal - itemDisc;
        let overallPct = parseFloat(document.getElementById('overall_discount_pct').value) || 0;
        let overallAmt = parseFloat(document.getElementById('overall_discount_amount').value) || 0;
        const overallAmtEl = document.getElementById('overall_discount_amount');
        if (document.activeElement !== overallAmtEl && overallPct) {
            overallAmt = afterItem * overallPct / 100;
        }
        const shipping = parseFloat(document.getElementById('shipping_amount').value) || 0;
        const additional = parseFloat(document.getElementById('additional_charges').value) || 0;
        const roundOff = parseFloat(document.getElementById('round_off').value) || 0;
        const grand = afterItem - overallAmt + taxTotal + shipping + additional + roundOff;
        const profit = grand - cost - taxTotal;
        const margin = grand > 0 ? (profit / grand) * 100 : 0;

        document.getElementById('disp_qty').textContent = money(qtyTotal).replace(/\.00$/, '');
        document.getElementById('disp_subtotal').textContent = money(subtotal);
        document.getElementById('disp_item_disc').textContent = money(itemDisc);
        document.getElementById('disp_overall_disc').textContent = money(overallAmt);
        document.getElementById('disp_tax').textContent = money(taxTotal);
        document.getElementById('disp_shipping').textContent = money(shipping);
        document.getElementById('disp_additional').textContent = money(additional);
        document.getElementById('disp_round').textContent = money(roundOff);
        document.getElementById('disp_grand').textContent = money(grand);
        document.getElementById('disp_cost').textContent = money(cost);
        document.getElementById('disp_profit').textContent = money(profit);
        document.getElementById('disp_margin').textContent = money(margin) + '%';
        document.getElementById('disp_line_count').textContent = String(lines);
        const cur = document.getElementById('currency');
        if (cur) document.getElementById('disp_currency').textContent = cur.value;
    }

    ['overall_discount_pct', 'overall_discount_amount', 'shipping_amount', 'additional_charges', 'round_off', 'tax_method', 'currency']
        .forEach(function (id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', recalc);
            el.addEventListener('change', recalc);
        });

    /* ── Customer autocomplete ─────────────────────────────── */
    const custSearch = document.getElementById('customer_search');
    const custResults = document.getElementById('customer_results');

    function selectCustomer(c) {
        document.getElementById('customer_id').value = c.id;
        custSearch.value = c.name;
        document.getElementById('customer_code').value = c.code || c.customer_code || '';
        document.getElementById('company_name').value = c.company_name || c.name || '';
        document.getElementById('contact_person').value = c.contact_person || c.name || '';
        document.getElementById('phone').value = c.phone || '';
        document.getElementById('mobile').value = c.mobile || c.phone || '';
        document.getElementById('email').value = c.email || '';
        document.getElementById('billing_address').value = c.billing_address || c.address || '';
        document.getElementById('shipping_address').value = c.shipping_address || c.address || '';
        if (c.tax_number != null) document.getElementById('tax_number').value = c.tax_number || '';
        if (c.credit_limit != null) document.getElementById('credit_limit').value = c.credit_limit || 0;
        document.getElementById('customer_meta').innerHTML =
            'Code <strong>' + escapeHtml(c.code || '') + '</strong> · Outstanding balance <strong>' +
            money(c.balance || 0) + '</strong> · ' + escapeHtml(c.phone || '') + ' · ' + escapeHtml(c.email || '');
        custResults.classList.add('d-none');
    }

    function renderCustomers(list) {
        if (!list.length) {
            custResults.classList.add('d-none');
            custResults.innerHTML = '';
            return;
        }
        custResults.innerHTML = list.map(function (c) {
            return '<button type="button" data-id="' + c.id + '"><strong>' + escapeHtml(c.name) + '</strong>'
                + ' <span class="text-muted small">' + escapeHtml(c.code || '') + '</span><br>'
                + '<span class="text-muted small">' + escapeHtml(c.phone || '') + ' · ' + escapeHtml(c.email || '') + '</span></button>';
        }).join('');
        custResults.classList.remove('d-none');
        custResults.querySelectorAll('button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const c = customers.find(function (x) { return String(x.id) === btn.getAttribute('data-id'); });
                if (c) selectCustomer(c);
            });
        });
    }

    custSearch.addEventListener('input', function () {
        const q = custSearch.value.trim().toLowerCase();
        if (!q) {
            renderCustomers(customers.slice(0, 10));
            return;
        }
        renderCustomers(customers.filter(function (c) {
            return (c.name || '').toLowerCase().includes(q)
                || (c.phone || '').toLowerCase().includes(q)
                || (c.email || '').toLowerCase().includes(q)
                || (c.code || '').toLowerCase().includes(q);
        }).slice(0, 15));
    });
    custSearch.addEventListener('focus', function () {
        if (!custSearch.value) renderCustomers(customers.slice(0, 10));
    });
    document.addEventListener('click', function (e) {
        if (!custResults.contains(e.target) && e.target !== custSearch) custResults.classList.add('d-none');
    });

    /* ── Product search ────────────────────────────────────── */
    const prodSearch = document.getElementById('product_search');
    const prodResults = document.getElementById('product_results');
    let prodTimer = null;

    prodSearch.addEventListener('input', function () {
        clearTimeout(prodTimer);
        const q = prodSearch.value.trim();
        if (q.length < 1) {
            prodResults.classList.add('d-none');
            return;
        }
        prodTimer = setTimeout(function () {
            fetch(window.QTN_PRODUCT_API + '?q=' + encodeURIComponent(q), {
                headers: { 'X-CSRF-TOKEN': window.VK_CSRF_TOKEN || '', 'Accept': 'application/json' }
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data.ok || !data.items.length) {
                    prodResults.innerHTML = '<div class="p-2 small text-muted">No products found</div>';
                    prodResults.classList.remove('d-none');
                    return;
                }
                prodResults.innerHTML = data.items.map(function (p, i) {
                    return '<button type="button" data-i="' + i + '" class="' + (i === 0 ? 'active' : '') + '"><strong>'
                        + escapeHtml(p.name) + '</strong> <span class="text-muted small">' + escapeHtml(p.product_code || '')
                        + (p.barcode ? ' · ' + escapeHtml(p.barcode) : '') + '</span><br><span class="small">'
                        + money(p.unit_price) + (p.stock_available != null ? ' · Stock ' + p.stock_available : '')
                        + (p.category_name ? ' · ' + escapeHtml(p.category_name) : '') + '</span></button>';
                }).join('');
                prodResults._items = data.items;
                prodResults.classList.remove('d-none');
                prodResults.querySelectorAll('button').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const p = prodResults._items[Number(btn.getAttribute('data-i'))];
                        if (!p) return;
                        addLine({
                            item_type: 'product',
                            product_id: p.id,
                            product_code: p.product_code,
                            barcode: p.barcode,
                            product_name: p.name,
                            description: p.description,
                            category_name: p.category_name,
                            unit: p.unit || 'pcs',
                            unit_price: p.unit_price,
                            cost_price: p.cost_price,
                            stock_available: p.stock_available,
                            tax_pct: window.QTN_DEFAULT_TAX || 0
                        });
                        prodSearch.value = '';
                        prodResults.classList.add('d-none');
                        prodSearch.focus();
                    });
                });
            }).catch(function () {
                toast('Product search failed', 'danger');
            });
        }, 180);
    });

    prodSearch.addEventListener('keydown', function (e) {
        const buttons = prodResults.querySelectorAll('button');
        if (!buttons.length || prodResults.classList.contains('d-none')) {
            if (e.key === 'Enter') e.preventDefault();
            return;
        }
        let idx = Array.from(buttons).findIndex(function (b) { return b.classList.contains('active'); });
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            idx = Math.min(buttons.length - 1, idx + 1);
            buttons.forEach(function (b, i) { b.classList.toggle('active', i === idx); });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            idx = Math.max(0, idx - 1);
            buttons.forEach(function (b, i) { b.classList.toggle('active', i === idx); });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            (buttons[idx] || buttons[0]).click();
        } else if (e.key === 'Escape') {
            prodResults.classList.add('d-none');
        }
    });

    /* ── Dates / terms ─────────────────────────────────────── */
    function syncExpiry() {
        const d = document.getElementById('quotation_date').value;
        const days = parseInt(document.getElementById('validity_days').value, 10) || 30;
        if (!d) return;
        const dt = new Date(d + 'T00:00:00');
        dt.setDate(dt.getDate() + days);
        document.getElementById('expiry_date').value = dt.toISOString().slice(0, 10);
    }
    document.getElementById('quotation_date').addEventListener('change', syncExpiry);
    document.getElementById('validity_days').addEventListener('change', syncExpiry);

    const termsTpl = document.getElementById('terms_template');
    if (termsTpl) {
        termsTpl.addEventListener('change', function () {
            const opt = termsTpl.options[termsTpl.selectedIndex];
            if (!opt || !opt.value) return;
            document.getElementById('terms_html').value = opt.value;
            if (opt.dataset.payment) document.getElementById('payment_terms').value = opt.dataset.payment;
            if (opt.dataset.delivery) document.getElementById('delivery_terms').value = opt.dataset.delivery;
            if (opt.dataset.validity) {
                document.getElementById('validity_days').value = opt.dataset.validity;
                syncExpiry();
            }
        });
    }

    const attInput = document.getElementById('attachments');
    if (attInput) {
        attInput.addEventListener('change', function () {
            const names = Array.from(attInput.files || []).map(function (f) { return f.name; });
            document.getElementById('attachmentPreview').textContent = names.length
                ? ('Selected: ' + names.join(', '))
                : '';
        });
    }

    /* ── Validation + AJAX save ────────────────────────────── */
    function validate() {
        if (!document.getElementById('customer_id').value) {
            toast('Select a customer', 'warning');
            custSearch.focus();
            return false;
        }
        if (!body.querySelectorAll('.qtn-line').length) {
            toast('Add at least one line item', 'warning');
            prodSearch.focus();
            return false;
        }
        let ok = true;
        body.querySelectorAll('.product-name').forEach(function (el) {
            if (!el.value.trim()) {
                el.classList.add('is-invalid');
                ok = false;
            } else el.classList.remove('is-invalid');
        });
        if (!ok) toast('Product name is required on all lines', 'warning');
        return ok;
    }

    function enablePostSaveActions(id) {
        window.QTN_EDIT_ID = id;
        document.getElementById('quotation_id').value = id;
        ['btnPreview', 'btnPrint', 'btnPdf', 'btnEmail'].forEach(function (bid) {
            const b = document.getElementById(bid);
            if (b) b.disabled = false;
        });
    }

    function save(action) {
        if (saving) return;
        if (action !== 'cancel' && !validate()) return;
        saving = true;
        setLoading(true);
        document.getElementById('form_action').value = action;
        document.getElementById('autosaveStatus').textContent = 'Saving…';

        const fd = new FormData(form);
        fd.set('form_action', action);
        fd.set('ajax', '1');
        fd.set('_csrf', window.VK_CSRF_TOKEN || fd.get('_csrf'));

        fetch(window.location.href, {
            method: 'POST',
            body: fd,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.VK_CSRF_TOKEN || ''
            }
        }).then(function (r) { return r.json().then(function (data) { return { status: r.status, data: data }; }); })
            .then(function (res) {
                saving = false;
                setLoading(false);
                const data = res.data || {};
                if (!data.ok) {
                    toast(data.message || data.error || 'Save failed', 'danger');
                    document.getElementById('autosaveStatus').textContent = 'Save failed';
                    return;
                }
                toast(data.message || 'Saved', 'success');
                if (data.number) {
                    document.getElementById('disp_quote_no').textContent = data.number;
                }
                if (data.status) {
                    document.getElementById('disp_status').textContent = data.status.replace(/_/g, ' ');
                }
                if (data.id) enablePostSaveActions(data.id);
                document.getElementById('autosaveStatus').textContent = 'Saved · ' + new Date().toLocaleTimeString();

                if (action === 'cancel' && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                if ((action === 'save' || action === 'submit') && data.redirect) {
                    setTimeout(function () { window.location.href = data.redirect; }, 600);
                }
            })
            .catch(function () {
                saving = false;
                setLoading(false);
                toast('Network error while saving', 'danger');
                document.getElementById('autosaveStatus').textContent = 'Network error';
            });
    }

    function bindSave(id, action) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', function () { save(action); });
    }
    bindSave('btnSaveDraft', 'draft');
    bindSave('btnSaveDraftSide', 'draft');
    bindSave('btnSaveQuote', 'save');
    bindSave('btnSaveQuoteSide', 'save');
    document.getElementById('btnCancel').addEventListener('click', function () {
        if (confirm('Cancel this quotation and leave the page?')) save('cancel');
    });

    function openAction(path) {
        const id = window.QTN_EDIT_ID || document.getElementById('quotation_id').value;
        if (!id) {
            toast('Save the quotation first', 'warning');
            return;
        }
        window.open(window.QTN_VIEW_BASE + path + '?id=' + id, path === 'print.php' ? '_blank' : '_self');
    }
    document.getElementById('btnPreview').addEventListener('click', function () { openAction('view.php'); });
    document.getElementById('btnPrint').addEventListener('click', function () { openAction('print.php'); });
    document.getElementById('btnPdf').addEventListener('click', function () {
        var id = parseInt(document.getElementById('quotation_id').value || '0', 10);
        if (!id) return;
        window.open(window.QTN_VIEW_BASE + 'print.php?id=' + id + '&download=1', '_blank');
    });
    document.getElementById('btnEmail').addEventListener('click', function () { openAction('email.php'); });

    /* ── New customer modal ────────────────────────────────── */
    const ncForm = document.getElementById('newCustomerForm');
    if (ncForm) {
        ncForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(ncForm);
            fd.set('_csrf', window.VK_CSRF_TOKEN || '');
            const btn = document.getElementById('nc_submit');
            btn.disabled = true;
            fetch(window.QTN_CUSTOMER_CREATE_API, {
                method: 'POST',
                body: fd,
                headers: { 'X-CSRF-TOKEN': window.VK_CSRF_TOKEN || '', 'Accept': 'application/json' }
            }).then(function (r) { return r.json(); }).then(function (data) {
                btn.disabled = false;
                if (!data.ok) {
                    toast(data.error || 'Could not create customer', 'danger');
                    return;
                }
                const c = data.customer;
                customers.unshift(c);
                selectCustomer(c);
                const modal = bootstrap.Modal.getInstance(document.getElementById('newCustomerModal'));
                if (modal) modal.hide();
                ncForm.reset();
                toast('Customer created', 'success');
            }).catch(function () {
                btn.disabled = false;
                toast('Network error', 'danger');
            });
        });
    }

    /* ── Autosave draft ────────────────────────────────────── */
    setInterval(function () {
        if (saving || !document.getElementById('customer_id').value || !body.querySelectorAll('.qtn-line').length) return;
        const fd = new FormData(form);
        fd.set('form_action', 'draft');
        fd.set('_csrf', window.VK_CSRF_TOKEN || '');
        if (window.QTN_EDIT_ID) fd.set('id', window.QTN_EDIT_ID);
        fetch(window.QTN_AUTOSAVE_API, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-TOKEN': window.VK_CSRF_TOKEN || '' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                window.QTN_EDIT_ID = data.id;
                document.getElementById('quotation_id').value = data.id;
                if (data.number) document.getElementById('disp_quote_no').textContent = data.number;
                enablePostSaveActions(data.id);
                document.getElementById('autosaveStatus').textContent = 'Autosaved · ' + (data.number || '') + ' · ' + new Date().toLocaleTimeString();
            }
        }).catch(function () {});
    }, 45000);

    /* ── Shortcuts ─────────────────────────────────────────── */
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            save('draft');
        }
    });

    /* Prefill */
    (window.QTN_EXISTING_ITEMS || []).forEach(function (it) { addLine(it); });
    if (!(window.QTN_EXISTING_ITEMS || []).length) {
        /* start empty — user adds via search */
    }
    recalc();
})();
