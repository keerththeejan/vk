(function () {
    'use strict';

    const app = document.getElementById('vkRepairApp');
    if (!app) {
        return;
    }

    const baseUrl = (window.VK_BASE_URL || '').replace(/\/$/, '');
    const form = document.getElementById('vkRepairFilterForm');
    const panel = document.getElementById('vkRepairPanel');
    const searchInput = document.getElementById('vkRepairSearch');
    const resetBtn = document.getElementById('vkRepairReset');
    const refreshBtn = document.getElementById('vkRepairRefresh');
    const filteredTotal = parseInt(app.dataset.filteredTotal || '0', 10);
    const pageFrom = parseInt(app.dataset.pageFrom || '0', 10);
    const pageTo = parseInt(app.dataset.pageTo || '0', 10);
    const pageTotal = parseInt(app.dataset.pageTotal || '0', 10);
    const searchQuery = (app.dataset.searchQuery || '').trim();

    function fmtRs(n) {
        const num = Number(n);
        if (Number.isNaN(num)) {
            return '—';
        }
        return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function showLoading() {
        if (panel) {
            panel.classList.add('is-loading');
        }
    }

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

    function initTooltips() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
            return;
        }
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    function escapeRegExp(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlightSearch() {
        if (!searchQuery || searchQuery.length < 2) {
            return;
        }
        const re = new RegExp('(' + escapeRegExp(searchQuery) + ')', 'gi');
        document.querySelectorAll('.vk-repair-highlight-target').forEach(function (el) {
            const text = el.textContent || '';
            if (!re.test(text)) {
                return;
            }
            el.innerHTML = text.replace(re, '<mark class="vk-repair-highlight">$1</mark>');
        });
    }

    function loadGlobalKpi() {
        fetch(baseUrl + '/api/dashboard_stats.php', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.ok || !data.stats) {
                    return;
                }
                const s = data.stats;
                const pipeline = Number(s.repair_pipeline) || 0;
                const completed = Number(s.repair_completed) || 0;
                const delivered = Number(s.repair_delivered) || 0;
                setKpi('vkRepairStatInProgressGlobal', pipeline, 'Active pipeline');
                setKpi('vkRepairStatReadyGlobal', completed, 'Completed jobs');
                setKpi('vkRepairStatDeliveredGlobal', delivered, 'Delivered jobs');
            })
            .catch(function () {});
    }

    function setKpi(id, val, trend) {
        const el = document.getElementById(id);
        const tr = document.getElementById(id + 'Trend');
        if (el) {
            el.textContent = String(val);
        }
        if (tr && trend) {
            tr.textContent = trend;
        }
    }

    function tableRowsForExport() {
        const rows = [];
        document.querySelectorAll('#vkRepairTable tbody.vk-repair-data-body tr[data-export-no]').forEach(function (tr) {
            rows.push({
                no: tr.dataset.exportNo || '',
                customer: tr.dataset.exportCustomer || '',
                phone: tr.dataset.exportPhone || '',
                device: tr.dataset.exportDevice || '',
                brand: tr.dataset.exportBrand || '',
                model: tr.dataset.exportModel || '',
                serial: tr.dataset.exportSerial || '',
                imei: tr.dataset.exportImei || '',
                problem: tr.dataset.exportProblem || '',
                technician: tr.dataset.exportTechnician || '',
                priority: tr.dataset.exportPriority || '',
                status: tr.dataset.exportStatus || '',
                payment: tr.dataset.exportPayment || '',
                estimate: tr.dataset.exportEstimate || '',
                paid: tr.dataset.exportPaid || '',
                balance: tr.dataset.exportBalance || '',
                created: tr.dataset.exportCreated || '',
                expected: tr.dataset.exportExpected || '',
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
        const header = [
            'Repair No', 'Customer', 'Phone', 'Device', 'Brand', 'Model', 'Serial', 'IMEI', 'Problem',
            'Technician', 'Priority', 'Status', 'Payment', 'Estimate', 'Paid', 'Balance', 'Created', 'Expected Delivery',
        ];
        const lines = [header.join(sep === ',' ? ',' : '\t')];
        data.forEach(function (r) {
            const row = [
                r.no, r.customer, r.phone, r.device, r.brand, r.model, r.serial, r.imei, r.problem,
                r.technician, r.priority, r.status, r.payment, r.estimate, r.paid, r.balance, r.created, r.expected,
            ];
            if (sep === ',') {
                lines.push(row.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(','));
            } else {
                lines.push(row.join('\t'));
            }
        });
        const blob = new Blob([lines.join('\n')], { type: mime });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'repair-jobs-' + new Date().toISOString().slice(0, 10) + ext;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    if (searchInput && form) {
        searchInput.addEventListener(
            'input',
            debounce(function () {
                showLoading();
                form.requestSubmit();
            }, 300)
        );
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

    ['vkRepairExportCsv', 'vkRepairExportExcel', 'vkRepairExportPdf', 'vkRepairPrint'].forEach(function (id, i) {
        const btn = document.getElementById(id);
        if (!btn) {
            return;
        }
        if (id === 'vkRepairExportCsv') {
            btn.addEventListener('click', function () { exportDelimited(',', '.csv', 'text/csv;charset=utf-8;'); });
        } else if (id === 'vkRepairExportExcel') {
            btn.addEventListener('click', function () { exportDelimited('\t', '.xls', 'application/vnd.ms-excel;charset=utf-8;'); });
        } else {
            btn.addEventListener('click', function () { window.print(); });
        }
    });

    const metaEl = document.getElementById('vkRepairMeta');
    if (metaEl && pageTotal > 0) {
        metaEl.textContent = 'Showing ' + pageFrom + '–' + pageTo + ' of ' + filteredTotal;
    }

    const revEl = document.getElementById('vkRepairStatRevenue');
    if (revEl) {
        revEl.textContent = fmtRs(app.dataset.pageRevenue || '0');
    }
    const todayEl = document.getElementById('vkRepairStatToday');
    if (todayEl) {
        todayEl.textContent = fmtRs(app.dataset.todayRevenue || '0');
    }

    const selectAll = document.getElementById('vkRepairSelectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.vk-repair-row-cb').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && document.activeElement !== searchInput && searchInput) {
            e.preventDefault();
            searchInput.focus();
        }
    });

    loadGlobalKpi();
    highlightSearch();
    initTooltips();
})();
