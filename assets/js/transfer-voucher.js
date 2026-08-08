(function () {
    'use strict';

    const root = document.getElementById('transferVoucherApp');
    if (!root) {
        return;
    }

    const accounts = JSON.parse(root.dataset.accounts || '{}');
    const form = document.getElementById('tvForm');
    const fromSel = document.getElementById('from_account_id');
    const toSel = document.getElementById('to_account_id');
    const amountEl = document.getElementById('amount');
    const actionEl = document.getElementById('tvFormAction');
    const loading = document.getElementById('tvLoading');
    const msgEl = document.getElementById('tvValidationMsg');

    function moneyPlain(n) {
        return (Math.round((Number(n) || 0) * 100) / 100).toLocaleString('en-LK', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
            useGrouping: true,
        });
    }

    function money(n) {
        if (typeof formatCurrency === 'function') {
            return formatCurrency(n);
        }
        return 'Rs. ' + moneyPlain(n);
    }

    function fillSide(prefix, accountId) {
        const a = accounts[String(accountId)] || null;
        const group = document.getElementById(prefix + '_group');
        const code = document.getElementById(prefix + '_code');
        const bal = document.getElementById(prefix + '_balance');
        if (group) group.value = a ? a.group || a.type || '' : '';
        if (code) code.value = a ? a.code || '' : '';
        if (bal) bal.value = a ? moneyPlain(a.balance) : '';
    }

    function validateLive() {
        const from = Number(fromSel?.value || 0);
        const to = Number(toSel?.value || 0);
        const amount = Number(amountEl?.value || 0);
        const debit = amount;
        const credit = amount;
        const diff = Math.abs(debit - credit);

        document.getElementById('tvDebitTotal').textContent = money(debit);
        document.getElementById('tvCreditTotal').textContent = money(credit);
        document.getElementById('tvDiff').textContent = money(diff);
        document.getElementById('debit_amount').value = String(amount || '');
        document.getElementById('credit_amount').value = String(amount || '');
        const toAmt = document.getElementById('to_amount_display');
        if (toAmt) toAmt.value = amount ? String(amount) : '';

        const badge = document.getElementById('tvBalanceBadge');
        const errors = [];
        if (!from) errors.push('Source account is required.');
        if (!to) errors.push('Destination account is required.');
        if (from && to && from === to) errors.push('Cannot transfer to the same account.');
        if (!(amount > 0)) errors.push('Amount must be greater than zero.');
        if (from && accounts[String(from)] && amount > Number(accounts[String(from)].balance) + 0.0001) {
            errors.push('Insufficient source balance.');
        }

        const balanced = errors.length === 0 && diff < 0.0001 && amount > 0;
        if (badge) {
            badge.textContent = balanced ? 'Balanced' : 'Not Balanced';
            badge.className = 'badge ' + (balanced ? 'text-bg-success' : 'text-bg-warning');
        }
        if (msgEl) {
            if (errors.length) {
                msgEl.textContent = errors[0];
                msgEl.classList.remove('d-none');
            } else {
                msgEl.textContent = '';
                msgEl.classList.add('d-none');
            }
        }
        const postBtn = document.getElementById('tvPostBtn');
        if (postBtn) postBtn.disabled = !balanced;
        return balanced;
    }

    function bindAccount(sel, prefix) {
        if (!sel) return;
        sel.addEventListener('change', () => {
            fillSide(prefix, sel.value);
            validateLive();
        });
        fillSide(prefix, sel.value);
    }

    bindAccount(fromSel, 'from');
    bindAccount(toSel, 'to');
    amountEl?.addEventListener('input', validateLive);

    if (window.TomSelect) {
        document.querySelectorAll('select.tv-account').forEach((el) => {
            if (el.tomselect) return;
            new TomSelect(el, {
                create: false,
                allowEmptyOption: true,
                sortField: { field: 'text', direction: 'asc' },
                maxOptions: 500,
            });
        });
    }

    form?.querySelectorAll('[data-action]').forEach((btn) => {
        btn.addEventListener('click', (ev) => {
            const act = btn.getAttribute('data-action') || 'post_transfer';
            if (actionEl) actionEl.value = act;
            if (act === 'post_transfer' && !validateLive()) {
                ev.preventDefault();
                return;
            }
            if (act === 'cancel_voucher' && !window.confirm('Cancel this pending voucher?')) {
                ev.preventDefault();
                return;
            }
            if (loading) loading.classList.remove('d-none');
        });
    });

    form?.addEventListener('submit', () => {
        if (loading) loading.classList.remove('d-none');
    });

    validateLive();
})();
