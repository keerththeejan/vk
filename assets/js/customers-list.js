(function () {
    'use strict';

    const app = document.getElementById('vkCustApp');
    if (!app) {
        return;
    }

    const form = document.getElementById('vkCustFilterForm');
    const panel = document.getElementById('vkCustPanel');
    const searchInput = document.getElementById('vkCustSearch');
    const filterType = document.getElementById('vkCustFilterType');
    const filterStatus = document.getElementById('vkCustFilterStatus');
    const resetBtn = document.getElementById('vkCustReset');
    const refreshBtn = document.getElementById('vkCustRefresh');
    const selectAll = document.getElementById('vkCustSelectAll');
    const drawer = document.getElementById('vkCustDrawer');
    const drawerBackdrop = document.getElementById('vkCustDrawerBackdrop');
    const drawerClose = document.getElementById('vkCustDrawerClose');
    const searchQuery = (app.dataset.searchQuery || '').trim();

    function debounce(fn, ms) {
        let t;
        return function () {
            const args = arguments;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(null, args);
            }, ms);
        };
    }

    function escapeRegExp(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function formatMoney(amount) {
        if (typeof formatCurrency === 'function') {
            return formatCurrency(amount);
        }
        var n = Number(amount);
        if (!Number.isFinite(n)) n = 0;
        var fixed = n.toFixed(2);
        var parts = fixed.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return 'Rs. ' + parts.join('.');
    }

    function initTooltips() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
            return;
        }
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    function showLoading() {
        if (panel) {
            panel.classList.add('is-loading');
        }
    }

    function customerRows() {
        return document.querySelectorAll('#vkCustTable tbody tr[data-customer-id], .vk-cust-mobile-card[data-customer-id]');
    }

    function applyClientFilters() {
        const type = filterType ? filterType.value : '';
        const st = filterStatus ? filterStatus.value : '';

        customerRows().forEach(function (row) {
            const matchType = !type || row.dataset.type === type;
            const matchSt = !st || row.dataset.status === st;
            row.classList.toggle('is-hidden', !(matchType && matchSt));
        });
    }

    function highlightSearch() {
        if (!searchQuery || searchQuery.length < 2) {
            return;
        }
        const re = new RegExp('(' + escapeRegExp(searchQuery) + ')', 'gi');
        document.querySelectorAll('.vk-cust-highlight-target').forEach(function (el) {
            const text = el.textContent || '';
            if (!re.test(text)) {
                return;
            }
            el.innerHTML = text.replace(re, '<mark>$1</mark>');
        });
    }

    function openDrawer(data) {
        if (!drawer || !data) {
            return;
        }
        const set = function (id, val) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = val || '—';
            }
        };

        set('vkCustDrawerName', data.name);
        set('vkCustDrawerCompany', data.company);
        set('vkCustDrawerPhone', data.phone);
        set('vkCustDrawerEmail', data.email);
        set('vkCustDrawerAddress', data.address);
        set('vkCustDrawerSince', data.created);
        set('vkCustDrawerBalance', data.balance);
        set('vkCustDrawerAccount', data.account);
        set('vkCustDrawerRepairs', data.repairs);
        set('vkCustDrawerCctv', data.cctv);
        set('vkCustDrawerMaint', data.maint);
        set('vkCustDrawerInvoices', data.invoices);
        set('vkCustDrawerLastService', data.lastService);

        const avatar = document.getElementById('vkCustDrawerAvatar');
        if (avatar) {
            avatar.textContent = data.initials || 'C';
        }

        const profileLink = document.getElementById('vkCustDrawerProfile');
        if (profileLink && data.id) {
            profileLink.href = (window.VK_BASE_URL || '').replace(/\/$/, '') + '/modules/customers/profile.php?id=' + data.id;
        }

        const editLink = document.getElementById('vkCustDrawerEdit');
        if (editLink && data.id) {
            editLink.href = (window.VK_BASE_URL || '').replace(/\/$/, '') + '/modules/customers/edit.php?id=' + data.id;
        }

        drawer.classList.add('is-open');
        if (drawerBackdrop) {
            drawerBackdrop.classList.add('is-open');
        }
        drawer.setAttribute('aria-hidden', 'false');
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

    function bindDrawerTriggers() {
        document.querySelectorAll('[data-customer-drawer]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                try {
                    openDrawer(JSON.parse(el.getAttribute('data-customer-drawer') || '{}'));
                } catch (err) {
                    /* ignore */
                }
            });
        });

        document.querySelectorAll('#vkCustTable tbody tr[data-customer-id]').forEach(function (tr) {
            tr.addEventListener('click', function (e) {
                if (e.target.closest('.vk-cust-act, .form-check-input, a[href*="delete"]')) {
                    return;
                }
                const btn = tr.querySelector('[data-customer-drawer]');
                if (btn) {
                    btn.click();
                }
            });
        });
    }

    function tableRowsForExport() {
        const rows = [];
        document.querySelectorAll('#vkCustTable tbody tr[data-customer-id]').forEach(function (tr) {
            if (tr.classList.contains('is-hidden')) {
                return;
            }
            rows.push({
                id: tr.dataset.exportId || '',
                name: tr.dataset.exportName || '',
                company: tr.dataset.exportCompany || '',
                phone: tr.dataset.exportPhone || '',
                email: tr.dataset.exportEmail || '',
                city: tr.dataset.exportCity || '',
                type: tr.dataset.exportType || '',
                status: tr.dataset.exportStatus || '',
                balance: tr.dataset.exportBalance || '',
                lastService: tr.dataset.exportLastService || '',
                created: tr.dataset.exportCreated || '',
            });
        });
        return rows;
    }

    function exportDelimited(sep, ext, mime) {
        const data = tableRowsForExport();
        if (!data.length) {
            if (typeof showToast === 'function') {
                showToast('No rows to export.', 'warning');
            }
            return;
        }
        const header = ['Customer ID', 'Name', 'Company', 'Phone', 'Email', 'City', 'Type', 'Status', 'Outstanding', 'Last Service', 'Created'];
        const lines = [header.join(sep === ',' ? ',' : '\t')];
        data.forEach(function (r) {
            const row = [r.id, r.name, r.company, r.phone, r.email, r.city, r.type, r.status, r.balance, r.lastService, r.created];
            if (sep === ',') {
                lines.push(row.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(','));
            } else {
                lines.push(row.join('\t'));
            }
        });
        const blob = new Blob([lines.join('\n')], { type: mime });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'customers-' + new Date().toISOString().slice(0, 10) + ext;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function animateBars() {
        document.querySelectorAll('.vk-cust-bar-fill[data-width]').forEach(function (bar) {
            requestAnimationFrame(function () {
                bar.style.width = (bar.dataset.width || '0') + '%';
            });
        });
    }

    function animateCounters() {
        document.querySelectorAll('[data-count-to]').forEach(function (el) {
            const target = parseFloat(el.dataset.countTo || '0');
            const suffix = el.dataset.countSuffix || '';
            const prefix = el.dataset.countPrefix || '';
            const isMoney = (el.dataset.countMoney || '') === '1';
            const duration = 700;
            const start = performance.now();
            function tick(now) {
                const p = Math.min(1, (now - start) / duration);
                const val = target * p;
                if (isMoney) {
                    el.textContent = prefix + formatMoney(val) + suffix;
                } else {
                    el.textContent = prefix + Math.round(val).toLocaleString('en-IN') + suffix;
                }
                if (p < 1) {
                    requestAnimationFrame(tick);
                }
            }
            requestAnimationFrame(tick);
        });
    }

    if (searchInput && form) {
        searchInput.addEventListener('input', debounce(function () {
            showLoading();
            form.requestSubmit();
        }, 300));
    }

    if (form) {
        form.addEventListener('submit', showLoading);
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            window.location.href = window.location.pathname;
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            showLoading();
            window.location.reload();
        });
    }

    [filterType, filterStatus].forEach(function (el) {
        if (el) {
            el.addEventListener('change', applyClientFilters);
        }
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.vk-cust-row-check').forEach(function (cb) {
                const tr = cb.closest('tr');
                if (tr && !tr.classList.contains('is-hidden')) {
                    cb.checked = selectAll.checked;
                }
            });
        });
    }

    ['vkCustExportCsv', 'vkCustExportExcel', 'vkCustExportPdf', 'vkCustPrint'].forEach(function (id) {
        const btn = document.getElementById(id);
        if (!btn) {
            return;
        }
        if (id === 'vkCustExportCsv') {
            btn.addEventListener('click', function () { exportDelimited(',', '.csv', 'text/csv;charset=utf-8;'); });
        } else if (id === 'vkCustExportExcel') {
            btn.addEventListener('click', function () { exportDelimited('\t', '.xls', 'application/vnd.ms-excel;charset=utf-8;'); });
        } else {
            btn.addEventListener('click', function () { window.print(); });
        }
    });

    if (drawerClose) {
        drawerClose.addEventListener('click', closeDrawer);
    }
    if (drawerBackdrop) {
        drawerBackdrop.addEventListener('click', closeDrawer);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && document.activeElement !== searchInput && searchInput) {
            e.preventDefault();
            searchInput.focus();
        }
        if (e.key === 'Escape') {
            closeDrawer();
        }
    });

    bindDrawerTriggers();
    initTooltips();
    highlightSearch();
    applyClientFilters();
    animateBars();
    animateCounters();

    window.setTimeout(function () {
        app.classList.remove('vk-cust-skeleton');
    }, 400);
})();
