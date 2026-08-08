(function () {
    'use strict';

    const app = document.getElementById('vkCctvApp');
    if (!app) {
        return;
    }

    const baseUrl = (window.VK_BASE_URL || '').replace(/\/$/, '');
    const form = document.getElementById('vkCctvFilterForm');
    const panel = document.getElementById('vkCctvPanel');
    const searchInput = document.getElementById('vkCctvSearch');
    const resetBtn = document.getElementById('vkCctvReset');
    const exportCsvBtn = document.getElementById('vkCctvExportCsv');
    const exportPdfBtn = document.getElementById('vkCctvExportPdf');
    const filteredTotal = parseInt(app.dataset.filteredTotal || '0', 10);
    const pageFrom = parseInt(app.dataset.pageFrom || '0', 10);
    const pageTo = parseInt(app.dataset.pageTo || '0', 10);
    const pageTotal = parseInt(app.dataset.pageTotal || '0', 10);

    function fmtRs(n) {
        const num = Number(n);
        if (Number.isNaN(num)) {
            return '—';
        }
        if (typeof formatCurrency === 'function') {
            return formatCurrency(num);
        }
        const fixed = num.toFixed(2);
        const parts = fixed.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return 'Rs. ' + parts.join('.');
    }

    function setText(id, val, trend) {
        const el = document.getElementById(id);
        const tr = document.getElementById(id + 'Trend');
        if (el) {
            el.textContent = val;
        }
        if (tr && trend) {
            tr.textContent = trend;
        }
    }

    function loadKpiStats() {
        const apiUrl = baseUrl + '/api/dashboard_stats.php';
        fetch(apiUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.ok || !data.stats) {
                    return;
                }
                const s = data.stats;
                const active = Number(s.cctv_active) || 0;
                const done = Number(s.cctv_done) || 0;
                const total = active + done;
                setText('vkCctvStatTotal', String(total), 'All installations');
                setText('vkCctvStatActive', String(active), 'Pending + in progress');
                setText('vkCctvStatCompleted', String(done), 'Completed + delivered');
                setText('vkCctvStatPending', String(active), 'Awaiting completion');
                setText('vkCctvStatCancelled', '0', 'Not tracked');
            })
            .catch(function () {
                /* keep PHP fallbacks */
            });

        const sumEl = document.getElementById('vkCctvStatRevenue');
        const sumTrend = document.getElementById('vkCctvStatRevenueTrend');
        const pageSum = parseFloat(app.dataset.pageRevenue || '0');
        if (sumEl) {
            sumEl.textContent = fmtRs(pageSum);
        }
        if (sumTrend) {
            sumTrend.textContent =
                pageTotal > 0 ? 'Page ' + pageFrom + '–' + pageTo + ' of ' + filteredTotal : 'Current page';
        }
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

    if (searchInput && form) {
        searchInput.addEventListener(
            'input',
            debounce(function () {
                showLoading();
                form.requestSubmit();
            }, 450)
        );
    }

    if (form) {
        form.addEventListener('submit', showLoading);
    }

    if (resetBtn && form) {
        resetBtn.addEventListener('click', function () {
            window.location.href = form.getAttribute('action') || window.location.pathname;
        });
    }

    function tableRowsForExport() {
        const rows = [];
        document.querySelectorAll('#vkCctvTable tbody.vk-cctv-data-body tr[data-export-job]').forEach(function (tr) {
            rows.push({
                job: tr.dataset.exportJob || '',
                customer: tr.dataset.exportCustomer || '',
                location: tr.dataset.exportLocation || '',
                technician: 'Unassigned',
                priority: tr.dataset.exportPriority || '',
                status: tr.dataset.exportStatus || '',
                amount: tr.dataset.exportAmount || '',
                created: tr.dataset.exportCreated || '',
            });
        });
        return rows;
    }

    function exportCsv() {
        const data = tableRowsForExport();
        if (!data.length) {
            if (typeof showToast === 'function') {
                showToast('No rows to export.', 'warning');
            }
            return;
        }
        const header = ['Job No', 'Customer', 'Location', 'Technician', 'Priority', 'Status', 'Amount', 'Created'];
        const lines = [header.join(',')];
        data.forEach(function (r) {
            lines.push(
                [r.job, r.customer, r.location, r.technician, r.priority, r.status, r.amount, r.created]
                    .map(function (v) {
                        return '"' + String(v).replace(/"/g, '""') + '"';
                    })
                    .join(',')
            );
        });
        const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'cctv-installations-' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function exportPdf() {
        window.print();
    }

    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', exportCsv);
    }
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', exportPdf);
    }

    const metaEl = document.getElementById('vkCctvMeta');
    if (metaEl && pageTotal > 0) {
        metaEl.textContent = 'Showing ' + pageFrom + '–' + pageTo + ' of ' + filteredTotal;
    }

    loadKpiStats();
    initTooltips();

    const selectAll = document.getElementById('vkCctvSelectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.vk-cctv-row-cb').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
        });
    }
})();
