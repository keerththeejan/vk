(function () {
    'use strict';
    var modal = document.querySelector('[data-collect-payment]');
    if (!modal) return;
    var form = document.getElementById('collectPaymentForm');
    var amount = document.getElementById('collectPaymentAmount');
    var message = document.getElementById('collectPaymentMessage');
    var submit = document.getElementById('submitCollectPayment');
    var due = Math.max(0, Math.round((Number(modal.dataset.grandTotal) - Number(modal.dataset.paid)) * 100) / 100);
    function money(n) { return 'LKR ' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
    function setMessage(text, ok) {
        message.textContent = text;
        message.className = 'alert mt-3 mb-0 alert-' + (ok ? 'success' : 'danger');
    }
    function newToken() {
        var bytes = new Uint8Array(24);
        window.crypto.getRandomValues(bytes);
        return Array.from(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
    }
    modal.querySelectorAll('[data-payment-amount]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.dataset.paymentAmount === 'full') amount.value = due.toFixed(2);
            else { amount.value = ''; amount.focus(); }
        });
    });
    amount.addEventListener('input', function () {
        var n = Number(amount.value);
        amount.setCustomValidity(!Number.isFinite(n) || n <= 0 || n > due ? 'Enter an amount greater than zero and no more than the outstanding balance.' : '');
    });
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!form.reportValidity()) return;
        submit.disabled = true;
        message.classList.add('d-none');
        var body = new FormData(form);
        body.set('invoice_id', modal.dataset.invoiceId);
        body.set('request_token', modal.dataset.token);
        body.set('_csrf', document.querySelector('input[name="_csrf"]')?.value || '');
        try {
            var response = await fetch(modal.dataset.api, {method: 'POST', body: body, credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            var data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.error || 'Could not record the payment.');
            var payment = data.payment;
            var row = document.createElement('tr');
            [payment.reference, payment.payment_date, payment.method.charAt(0).toUpperCase() + payment.method.slice(1), payment.transaction_reference || '—', payment.received_by, money(payment.amount)].forEach(function (value, i) {
                var cell = document.createElement('td'); cell.textContent = value; if (i === 5) cell.className = 'text-end'; row.appendChild(cell);
            });
            var action = document.createElement('td'); action.className = 'text-end';
            var link = document.createElement('a'); link.className = 'btn btn-sm btn-outline-secondary'; link.target = '_blank'; link.href = window.location.origin + window.location.pathname.replace(/\/modules\/invoices\/edit\.php$/, '/modules/payments/receipt.php') + '?id=' + encodeURIComponent(payment.id); link.textContent = 'Receipt'; action.appendChild(link); row.appendChild(action);
            var rows = document.getElementById('invoicePaymentHistoryRows');
            var empty = rows.querySelector('.payment-history-empty'); if (empty) empty.remove();
            rows.prepend(row);
            document.getElementById('paymentHistoryTotal').textContent = money(Array.from(rows.querySelectorAll('tr')).reduce(function (sum, tr) { return sum + (Number(tr.children[5]?.textContent.replace(/[^0-9.-]/g, '')) || 0); }, 0));
            document.getElementById('invoicePaidAmount').textContent = money(data.paid_amount);
            document.getElementById('disp_balance').textContent = money(data.balance);
            document.getElementById('collectPreviouslyPaid').textContent = money(data.paid_amount);
            document.getElementById('collectOutstanding').textContent = money(data.balance);
            due = data.balance;
            modal.dataset.paid = data.paid_amount;
            modal.dataset.token = newToken();
            amount.max = data.balance.toFixed(2);
            amount.value = data.balance.toFixed(2);
            if (window.VK_INVOICE_CFG) window.VK_INVOICE_CFG.paidAmount = data.paid_amount;
            if (typeof window.VK_INVOICE_RECALC === 'function') window.VK_INVOICE_RECALC();
            var statusBadge = document.getElementById('invoiceStatusBadge');
            if (statusBadge) {
                statusBadge.textContent = data.status;
                statusBadge.className = 'badge ms-1 text-bg-' + (data.status === 'draft' ? 'warning' : (data.status === 'paid' ? 'success' : (data.status === 'partial' ? 'info' : 'secondary')));
            }
            var collectButton = document.getElementById('collectPaymentButton');
            if (data.balance <= 0 && collectButton) collectButton.remove();
            form.reset();
            document.getElementById('collectPaymentDate').value = new Date().toISOString().slice(0, 10);
            amount.value = data.balance.toFixed(2);
            setMessage(data.duplicate ? 'This payment was already recorded. No duplicate was added.' : 'Payment recorded successfully. Draft status is unchanged.', true);
            message.classList.remove('d-none');
        } catch (error) {
            setMessage(error.message || 'Could not record the payment.', false);
            message.classList.remove('d-none');
        } finally {
            submit.disabled = false;
        }
    });
})();
