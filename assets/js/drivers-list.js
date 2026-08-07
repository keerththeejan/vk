(function () {
    'use strict';

    var app = document.getElementById('vkDrvApp');
    if (!app) {
        return;
    }

    var searchInput = document.getElementById('vkDrvSearch');
    var filterAvail = document.getElementById('vkDrvFilterAvail');
    var filterActive = document.getElementById('vkDrvFilterActive');
    var filterVehicle = document.getElementById('vkDrvFilterVehicle');
    var perPageSelect = document.getElementById('vkDrvPerPage');
    var resetBtn = document.getElementById('vkDrvReset');
    var refreshBtn = document.getElementById('vkDrvRefresh');
    var selectAll = document.getElementById('vkDrvSelectAll');
    var drawer = document.getElementById('vkDrvDrawer');
    var drawerBackdrop = document.getElementById('vkDrvDrawerBackdrop');
    var drawerClose = document.getElementById('vkDrvDrawerClose');
    var formPanel = document.getElementById('vkDrvFormPanel');
    var formToggle = document.getElementById('vkDrvFormToggle');
    var pageInfo = document.getElementById('vkDrvPageInfo');
    var pageNav = document.getElementById('vkDrvPageNav');
    var clientPage = 1;

    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, ms);
        };
    }

    function initTooltips() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                bootstrap.Tooltip.getOrCreateInstance(el);
            });
        }
    }

    function rows() {
        return document.querySelectorAll('#vkDrvTable tbody tr[data-driver-id], .vk-drv-mobile-card[data-driver-id]');
    }

    function visibleRows() {
        return Array.prototype.filter.call(rows(), function (r) { return !r.classList.contains('is-hidden'); });
    }

    function perPage() {
        var n = parseInt(perPageSelect && perPageSelect.value ? perPageSelect.value : '25', 10);
        return isNaN(n) ? 25 : n;
    }

    function applyClientFilters() {
        var q = (searchInput ? searchInput.value : '').trim().toLowerCase();
        var av = filterAvail ? filterAvail.value : '';
        var ac = filterActive ? filterActive.value : '';
        var veh = filterVehicle ? filterVehicle.value : '';

        rows().forEach(function (row) {
            var hay = (row.dataset.searchBlob || '').toLowerCase();
            var matchQ = !q || hay.indexOf(q) !== -1;
            var matchAv = !av || row.dataset.availability === av;
            var matchAc = !ac || row.dataset.active === ac;
            var matchVeh = !veh || row.dataset.driverId === veh;
            row.classList.toggle('is-hidden', !(matchQ && matchAv && matchAc && matchVeh));
        });
        clientPage = 1;
        applyClientPagination();
    }

    function applyClientPagination() {
        var vis = visibleRows();
        var pp = perPage();
        var total = vis.length;
        var pages = Math.max(1, Math.ceil(total / pp));
        if (clientPage > pages) {
            clientPage = pages;
        }
        var start = (clientPage - 1) * pp;
        var end = start + pp;
        vis.forEach(function (row, i) {
            row.classList.toggle('is-page-hidden', i < start || i >= end);
        });
        rows().forEach(function (row) {
            if (row.classList.contains('is-hidden')) {
                row.classList.remove('is-page-hidden');
            }
        });
        if (pageInfo) {
            pageInfo.textContent = total === 0 ? 'Showing 0 of 0' : 'Showing ' + (start + 1) + '–' + Math.min(end, total) + ' of ' + total.toLocaleString();
        }
        if (!pageNav || pages <= 1) {
            if (pageNav) {
                pageNav.innerHTML = '';
            }
            return;
        }
        pageNav.innerHTML = '';
        function btn(label, page, disabled, active) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'vk-drv-page-link' + (disabled ? ' is-disabled' : '') + (active ? ' is-active' : '');
            b.innerHTML = label;
            if (!disabled) {
                b.addEventListener('click', function () { clientPage = page; applyClientPagination(); });
            }
            pageNav.appendChild(b);
        }
        btn('<i class="bi bi-chevron-left"></i>', clientPage - 1, clientPage <= 1, false);
        for (var p = 1; p <= Math.min(pages, 7); p++) {
            btn(String(p), p, false, p === clientPage);
        }
        btn('<i class="bi bi-chevron-right"></i>', clientPage + 1, clientPage >= pages, false);
    }

    function openDrawer(data) {
        if (!drawer || !data) {
            return;
        }
        var set = function (id, val) {
            var el = document.getElementById(id);
            if (el) {
                el.textContent = val || '—';
            }
        };
        set('vkDrvDrawerName', data.name);
        set('vkDrvDrawerEmpId', data.empId);
        set('vkDrvDrawerPhone', data.phone);
        set('vkDrvDrawerLicense', data.license);
        set('vkDrvDrawerLicenseClass', data.licenseClass);
        set('vkDrvDrawerAvail', data.availability);
        set('vkDrvDrawerStatus', data.status);
        set('vkDrvDrawerVehicle', data.vehicle);
        set('vkDrvDrawerMedical', data.medical);
        set('vkDrvDrawerExpiry', data.expiry);
        set('vkDrvDrawerRating', data.rating);
        set('vkDrvDrawerDept', data.dept);
        set('vkDrvDrawerJoined', data.joined);

        var av = document.getElementById('vkDrvDrawerAvatar');
        if (av) {
            av.textContent = data.initials || 'D';
        }
        var wa = document.getElementById('vkDrvDrawerWa');
        if (wa && data.waUrl) {
            wa.href = data.waUrl;
            wa.classList.remove('d-none');
        } else if (wa) {
            wa.classList.add('d-none');
        }
        var tel = document.getElementById('vkDrvDrawerCall');
        if (tel && data.telUrl) {
            tel.href = data.telUrl;
        }
        var edit = document.getElementById('vkDrvDrawerEdit');
        if (edit && data.editUrl) {
            edit.href = data.editUrl;
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
        document.querySelectorAll('[data-drv-drawer]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                try {
                    openDrawer(JSON.parse(el.getAttribute('data-drv-drawer') || '{}'));
                } catch (err) { /* ignore */ }
            });
        });
        document.querySelectorAll('#vkDrvTable tbody tr[data-driver-id]').forEach(function (tr) {
            tr.addEventListener('click', function (e) {
                if (e.target.closest('.vk-drv-act, .form-check-input, a[href*="delete"], a[href^="tel:"]')) {
                    return;
                }
                var btn = tr.querySelector('[data-drv-drawer]');
                if (btn) {
                    btn.click();
                }
            });
        });
    }

    function exportRows() {
        var data = [];
        visibleRows().forEach(function (tr) {
            if (tr.classList.contains('is-page-hidden')) {
                return;
            }
            data.push({
                empId: tr.dataset.exportEmpId || '',
                name: tr.dataset.exportName || '',
                phone: tr.dataset.exportPhone || '',
                license: tr.dataset.exportLicense || '',
                vehicle: tr.dataset.exportVehicle || '',
                availability: tr.dataset.exportAvail || '',
                status: tr.dataset.exportStatus || '',
                created: tr.dataset.exportCreated || '',
            });
        });
        return data;
    }

    function exportDelimited(sep, ext, mime) {
        var data = exportRows();
        if (!data.length) {
            if (typeof showToast === 'function') {
                showToast('No rows to export.', 'warning');
            }
            return;
        }
        var header = ['Employee ID', 'Name', 'Phone', 'License', 'Vehicle', 'Availability', 'Status', 'Created'];
        var lines = [header.join(sep === ',' ? ',' : '\t')];
        data.forEach(function (r) {
            var row = [r.empId, r.name, r.phone, r.license, r.vehicle, r.availability, r.status, r.created];
            if (sep === ',') {
                lines.push(row.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(','));
            } else {
                lines.push(row.join('\t'));
            }
        });
        var blob = new Blob([lines.join('\n')], { type: mime });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'drivers-' + new Date().toISOString().slice(0, 10) + ext;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function animateBars() {
        document.querySelectorAll('.vk-drv-bar-fill[data-width]').forEach(function (bar) {
            requestAnimationFrame(function () {
                bar.style.width = (bar.getAttribute('data-width') || '0') + '%';
            });
        });
    }

    function animateCounters() {
        document.querySelectorAll('[data-count-to]').forEach(function (el) {
            var target = parseFloat(el.dataset.countTo || '0');
            var suffix = el.dataset.countSuffix || '';
            var isDecimal = (el.dataset.countDecimal || '') === '1';
            var duration = 700;
            var start = performance.now();
            function tick(now) {
                var p = Math.min(1, (now - start) / duration);
                var val = target * p;
                el.textContent = (isDecimal ? val.toFixed(1) : Math.round(val).toLocaleString()) + suffix;
                if (p < 1) {
                    requestAnimationFrame(tick);
                }
            }
            requestAnimationFrame(tick);
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', debounce(applyClientFilters, 200));
    }
    [filterAvail, filterActive, filterVehicle, perPageSelect].forEach(function (el) {
        if (el) {
            el.addEventListener('change', applyClientFilters);
        }
    });
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
            }
            [filterAvail, filterActive, filterVehicle].forEach(function (el) {
                if (el) {
                    el.value = '';
                }
            });
            applyClientFilters();
        });
    }
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () { window.location.reload(); });
    }
    if (formToggle && formPanel) {
        formToggle.addEventListener('click', function () {
            formPanel.classList.toggle('is-collapsed');
        });
    }
    var addBtn = document.getElementById('vkDrvAddBtn');
    if (addBtn && formPanel) {
        addBtn.addEventListener('click', function () {
            formPanel.classList.remove('is-collapsed');
            formPanel.scrollIntoView({ behavior: 'smooth' });
        });
    }
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.vk-drv-row-check').forEach(function (cb) {
                var tr = cb.closest('tr');
                if (tr && !tr.classList.contains('is-hidden')) {
                    cb.checked = selectAll.checked;
                }
            });
        });
    }

    ['vkDrvExportCsv', 'vkDrvExportExcel', 'vkDrvExportPdf', 'vkDrvPrint'].forEach(function (id) {
        var btn = document.getElementById(id);
        if (!btn) {
            return;
        }
        if (id === 'vkDrvExportCsv') {
            btn.addEventListener('click', function () { exportDelimited(',', '.csv', 'text/csv;charset=utf-8;'); });
        } else if (id === 'vkDrvExportExcel') {
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
        if (e.key === '/' && searchInput && document.activeElement !== searchInput) {
            e.preventDefault();
            searchInput.focus();
        }
        if (e.key === 'Escape') {
            closeDrawer();
        }
    });

    bindDrawerTriggers();
    initTooltips();
    applyClientFilters();
    animateBars();
    animateCounters();
    window.setTimeout(function () { app.classList.remove('vk-drv-skeleton'); }, 400);
})();
