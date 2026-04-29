(function () {
    'use strict';

    const base = window.VK_BASE_URL || '';
    const form = document.getElementById('invoiceForm');
    const tbody = document.getElementById('linesBody');
    const tplProduct = document.getElementById('lineTplProduct');
    const tplService = document.getElementById('lineTplService');
    const customerInput = document.getElementById('customer_search');
    const customerId = document.getElementById('customer_id');
    const customerSelected = document.getElementById('customer_selected');
    const resultsEl = document.getElementById('customer_results');
    const discountEl = document.getElementById('discount');
    const taxEl = document.getElementById('tax');

    function money(n) {
        return (Math.round(n * 100) / 100).toFixed(2);
    }

    function wireRow(row) {
        row.querySelector('.rm-line').addEventListener('click', function () {
            row.remove();
            recalc();
        });
        const kind = row.getAttribute('data-line-kind');
        if (kind === 'product') {
            row.querySelector('.product-select').addEventListener('change', recalc);
            row.querySelector('.qty-input').addEventListener('input', recalc);
        } else {
            row.querySelector('.service-unit').addEventListener('input', recalc);
            row.querySelector('.qty-input').addEventListener('input', recalc);
        }
    }

    function recalc() {
        let sub = 0;
        let itemCount = 0;
        tbody.querySelectorAll('tr.line-row').forEach(function (row) {
            const kind = row.getAttribute('data-line-kind');
            const qty = parseInt(row.querySelector('.qty-input').value, 10) || 0;
            let price = 0;
            if (kind === 'product') {
                const sel = row.querySelector('.product-select');
                const opt = sel.options[sel.selectedIndex];
                price = opt ? parseFloat(opt.getAttribute('data-price') || '0') || 0 : 0;
                const up = row.querySelector('.unit-price');
                if (up) up.textContent = money(price);
            } else {
                const su = row.querySelector('.service-unit');
                price = su ? parseFloat(su.value) || 0 : 0;
            }
            const line = price * qty;
            sub += line;
            if (qty > 0 && price > 0) itemCount += qty;
            row.querySelector('.line-total').textContent = money(line);
        });
        const disc = parseFloat(discountEl.value) || 0;
        const tax = parseFloat(taxEl.value) || 0;
        const grand = sub - disc + tax;
        document.getElementById('disp_subtotal').textContent = money(sub);
        document.getElementById('disp_discount').textContent = money(disc);
        document.getElementById('disp_tax').textContent = money(tax);
        document.getElementById('disp_grand').textContent = money(grand);
        recalcPayment();
    }

    /* ─── Payment Section ─────────────────────────────────────────── */

    var payRowIdx = 1;

    function recalcPayment() {
        var grand = parseFloat(document.getElementById('disp_grand').textContent) || 0;
        var totalPaying = 0;
        document.querySelectorAll('.pay-amount').forEach(function (el) {
            totalPaying += parseFloat(el.value) || 0;
        });
        var change = totalPaying > grand ? totalPaying - grand : 0;
        var balance = grand > totalPaying ? grand - totalPaying : 0;

        var itemCount = 0;
        tbody.querySelectorAll('tr.line-row .qty-input').forEach(function (el) {
            itemCount += parseInt(el.value, 10) || 0;
        });

        var elItems = document.getElementById('pay_total_items');
        var elPayable = document.getElementById('pay_total_payable');
        var elPaying = document.getElementById('pay_total_paying');
        var elChange = document.getElementById('pay_change_return');
        var elBalance = document.getElementById('pay_balance_due');
        if (elItems) elItems.textContent = itemCount;
        if (elPayable) elPayable.textContent = money(grand);
        if (elPaying) elPaying.textContent = money(totalPaying);
        if (elChange) elChange.textContent = money(change);
        if (elBalance) elBalance.textContent = money(balance);
    }

    function wirePayRow(row) {
        row.querySelector('.pay-amount').addEventListener('input', recalcPayment);
        row.querySelector('.rm-pay-row').addEventListener('click', function () {
            row.remove();
            updatePayRemoveButtons();
            recalcPayment();
        });
    }

    function updatePayRemoveButtons() {
        var rows = document.querySelectorAll('.payment-row');
        rows.forEach(function (row) {
            var btn = row.querySelector('.rm-pay-row');
            if (btn) {
                if (rows.length > 1) {
                    btn.classList.remove('d-none');
                } else {
                    btn.classList.add('d-none');
                }
            }
        });
    }

    function addPaymentRow() {
        var container = document.getElementById('paymentRows');
        var idx = payRowIdx++;
        var html = '<div class="row g-2 align-items-end payment-row mb-2" data-payment-row="' + idx + '">' +
            '<div class="col-12 col-sm-3">' +
            '<label class="form-label small mb-1">Amount</label>' +
            '<input type="number" step="0.01" min="0" class="form-control form-control-sm pay-amount" name="pay_amount[]" id="pay_amount_' + idx + '" placeholder="0.00">' +
            '</div>' +
            '<div class="col-12 col-sm-3">' +
            '<label class="form-label small mb-1">Method</label>' +
            '<select class="form-select form-select-sm pay-method" name="pay_method[]" id="pay_method_' + idx + '">' +
            '<option value="">— Select —</option>' +
            '<option value="cash">Cash</option>' +
            '<option value="card">Card</option>' +
            '<option value="bank">Bank</option>' +
            '<option value="online">Online</option>' +
            '</select>' +
            '</div>' +
            '<div class="col-12 col-sm-4">' +
            '<label class="form-label small mb-1">Note</label>' +
            '<input type="text" class="form-control form-control-sm pay-note" name="pay_note[]" id="pay_note_' + idx + '" maxlength="255" placeholder="Optional note">' +
            '</div>' +
            '<div class="col-12 col-sm-2 text-end">' +
            '<button type="button" class="btn btn-sm btn-outline-danger rm-pay-row" title="Remove"><i class="bi bi-x-lg"></i></button>' +
            '</div>' +
            '</div>';
        container.insertAdjacentHTML('beforeend', html);
        var newRow = container.lastElementChild;
        wirePayRow(newRow);
        updatePayRemoveButtons();
        recalcPayment();
    }

    // Auto-fill first payment amount with grand total on focus
    document.addEventListener('focus', function (e) {
        if (e.target.classList && e.target.classList.contains('pay-amount') && !e.target.value) {
            var grand = parseFloat(document.getElementById('disp_grand').textContent) || 0;
            if (grand > 0) {
                e.target.value = money(grand);
                recalcPayment();
            }
        }
    });

    var addPayBtn = document.getElementById('addPaymentRow');
    if (addPayBtn) {
        addPayBtn.addEventListener('click', addPaymentRow);
    }

    // Wire initial payment row
    var firstPayRow = document.querySelector('.payment-row');
    if (firstPayRow) {
        wirePayRow(firstPayRow);
    }

    /* ─── Line Items ──────────────────────────────────────────────── */

    function addProductLine() {
        tbody.appendChild(tplProduct.content.cloneNode(true));
        const row = tbody.lastElementChild;
        wireRow(row);
        recalc();
    }

    function addServiceLine() {
        tbody.appendChild(tplService.content.cloneNode(true));
        const row = tbody.lastElementChild;
        wireRow(row);
        recalc();
    }

    document.getElementById('addProductLine').addEventListener('click', addProductLine);
    document.getElementById('addServiceLine').addEventListener('click', addServiceLine);
    discountEl.addEventListener('input', recalc);
    taxEl.addEventListener('input', recalc);

    function loadCustomers(q) {
        fetch(base + '/api/customers_search.php?q=' + encodeURIComponent(q))
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                resultsEl.innerHTML = '';
                if (!data.results || !data.results.length) {
                    resultsEl.classList.add('d-none');
                    return;
                }
                data.results.forEach(function (c) {
                    const a = document.createElement('button');
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
            .catch(function () {
                resultsEl.classList.add('d-none');
            });
    }

    customerInput.addEventListener('focus', function () {
        if (!customerInput.value.trim()) {
            loadCustomers('');
        }
    });

    let searchTimer;
    customerInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        const q = customerInput.value.trim();
        searchTimer = setTimeout(function () {
            loadCustomers(q);
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (!resultsEl.contains(e.target) && e.target !== customerInput) {
            resultsEl.classList.add('d-none');
        }
    });

    form.addEventListener('submit', function (e) {
        if (!customerId.value) {
            e.preventDefault();
            if (window.showToast) window.showToast('Please select a customer.', 'danger');
            return;
        }
        if (!tbody.querySelector('tr.line-row')) {
            e.preventDefault();
            if (window.showToast) window.showToast('Add at least one line.', 'danger');
            return;
        }
        // Validate payment rows: if amount entered, method is required
        var payValid = true;
        document.querySelectorAll('.payment-row').forEach(function (row) {
            var amt = parseFloat(row.querySelector('.pay-amount').value) || 0;
            var method = row.querySelector('.pay-method').value;
            if (amt > 0 && !method) {
                payValid = false;
            }
            if (amt < 0) {
                payValid = false;
            }
        });
        if (!payValid) {
            e.preventDefault();
            if (window.showToast) window.showToast('Select a payment method for each payment row with an amount.', 'danger');
            return;
        }
        // Prevent overpayment
        var grand = parseFloat(document.getElementById('disp_grand').textContent) || 0;
        var totalPaying = 0;
        document.querySelectorAll('.pay-amount').forEach(function (el) {
            totalPaying += parseFloat(el.value) || 0;
        });
        if (totalPaying > grand + 0.01) {
            e.preventDefault();
            if (window.showToast) window.showToast('Total payment exceeds grand total by ' + money(totalPaying - grand) + '.', 'danger');
            return;
        }
    });

    if (tbody.children.length === 0) {
        addProductLine();
    }
})();
