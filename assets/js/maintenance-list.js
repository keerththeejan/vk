(function () {
    'use strict';

    const app = document.getElementById('vkMaintApp');
    if (!app) {
        return;
    }

    const baseUrl = (window.VK_BASE_URL || '').replace(/\/$/, '');
    const form = document.getElementById('vkMaintFilterForm');
    const panel = document.getElementById('vkMaintPanel');
    const searchInput = document.getElementById('vkMaintSearch');
    const resetBtn = document.getElementById('vkMaintReset');
    const refreshBtn = document.getElementById('vkMaintRefresh');
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
        document.querySelectorAll('.vk-maint-highlight-target').forEach(function (el) {
            const text = el.textContent || '';
            if (!re.test(text)) {
                return;
            }
            el.innerHTML = text.replace(re, '<mark class="vk-maint-highlight">$1</mark>');
        });
    }

    function loadGlobalKpi() {
        /* KPI cards use page-level PHP stats; global active count available via dashboard API if needed later */
    }

    function tableRowsForExport() {
        const rows = [];
        document.querySelectorAll('#vkMaintTable tbody.vk-maint-data-body tr[data-export-no]').forEach(function (tr) {
            rows.push({
                no: tr.dataset.exportNo || '',
                customer: tr.dataset.exportCustomer || '',
                asset: tr.dataset.exportAsset || '',
                technician: tr.dataset.exportTechnician || '',
                type: tr.dataset.exportType || '',
                priority: tr.dataset.exportPriority || '',
                status: tr.dataset.exportStatus || '',
                scheduled: tr.dataset.exportScheduled || '',
                due: tr.dataset.exportDue || '',
                cost: tr.dataset.exportCost || '',
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
        const header = [
            'Maintenance No',
            'Customer',
            'Asset',
            'Technician',
            'Service Type',
            'Priority',
            'Status',
            'Scheduled',
            'Next Due',
            'Cost',
            'Created',
        ];
        const lines = [header.join(sep)];
        data.forEach(function (r) {
            const row = [
                r.no,
                r.customer,
                r.asset,
                r.technician,
                r.type,
                r.priority,
                r.status,
                r.scheduled,
                r.due,
                r.cost,
                r.created,
            ];
            if (sep === ',') {
                lines.push(row.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(sep));
            } else {
                lines.push(row.join(sep));
            }
        });
        const blob = new Blob([lines.join('\n')], { type: mime });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'maintenance-contracts-' + new Date().toISOString().slice(0, 10) + ext;
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

    const exportCsvBtn = document.getElementById('vkMaintExportCsv');
    const exportExcelBtn = document.getElementById('vkMaintExportExcel');
    const exportPdfBtn = document.getElementById('vkMaintExportPdf');
    const printBtn = document.getElementById('vkMaintPrint');

    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', function () {
            exportDelimited(',', '.csv', 'text/csv;charset=utf-8;');
        });
    }
    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', function () {
            exportDelimited('\t', '.xls', 'application/vnd.ms-excel;charset=utf-8;');
        });
    }
    if (exportPdfBtn || printBtn) {
        const printFn = function () {
            window.print();
        };
        if (exportPdfBtn) {
            exportPdfBtn.addEventListener('click', printFn);
        }
        if (printBtn) {
            printBtn.addEventListener('click', printFn);
        }
    }

    const metaEl = document.getElementById('vkMaintMeta');
    if (metaEl && pageTotal > 0) {
        metaEl.textContent = 'Showing ' + pageFrom + '–' + pageTo + ' of ' + filteredTotal;
    }

    const revenueEl = document.getElementById('vkMaintStatRevenue');
    if (revenueEl) {
        revenueEl.textContent = fmtRs(app.dataset.pageRevenue || '0');
    }

    const rateEl = document.getElementById('vkMaintStatRate');
    if (rateEl && app.dataset.completionRate) {
        rateEl.textContent = app.dataset.completionRate + '%';
    }

    const selectAll = document.getElementById('vkMaintSelectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.vk-maint-row-cb').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
        });
    }

    loadGlobalKpi();
    highlightSearch();
    initTooltips();
})();
