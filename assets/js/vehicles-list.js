(function () {
    'use strict';

    var app = document.getElementById('vkVehApp');
    if (!app) {
        return;
    }

    var searchInput = document.getElementById('vkVehSearch');
    var filterType = document.getElementById('vkVehFilterType');
    var filterStatus = document.getElementById('vkVehFilterStatus');
    var filterDriver = document.getElementById('vkVehFilterDriver');
    var perPageSelect = document.getElementById('vkVehPerPage');
    var resetBtn = document.getElementById('vkVehReset');
    var refreshBtn = document.getElementById('vkVehRefresh');
    var selectAll = document.getElementById('vkVehSelectAll');
    var drawer = document.getElementById('vkVehDrawer');
    var drawerBackdrop = document.getElementById('vkVehDrawerBackdrop');
    var drawerClose = document.getElementById('vkVehDrawerClose');
    var formPanel = document.getElementById('vkVehFormPanel');
    var formToggle = document.getElementById('vkVehFormToggle');
    var pageInfo = document.getElementById('vkVehPageInfo');
    var pageNav = document.getElementById('vkVehPageNav');
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
        return document.querySelectorAll('#vkVehTable tbody tr[data-vehicle-id], .vk-veh-mobile-card[data-vehicle-id]');
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
        var type = filterType ? filterType.value : '';
        var st = filterStatus ? filterStatus.value : '';
        var dr = filterDriver ? filterDriver.value : '';

        rows().forEach(function (row) {
            var hay = (row.dataset.searchBlob || '').toLowerCase();
            var matchQ = !q || hay.indexOf(q) !== -1;
            var matchType = !type || row.dataset.vehicleType === type;
            var matchSt = !st || row.dataset.status === st;
            var matchDr = !dr || row.dataset.driverId === dr;
            row.classList.toggle('is-hidden', !(matchQ && matchType && matchSt && matchDr));
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
        if (!pageNav) {
            return;
        }
        pageNav.innerHTML = '';
        if (pages <= 1) {
            return;
        }
        function addBtn(label, page, disabled, active) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'vk-veh-page-link' + (disabled ? ' is-disabled' : '') + (active ? ' is-active' : '');
            b.innerHTML = label;
            if (!disabled) {
                b.addEventListener('click', function () {
                    clientPage = page;
                    applyClientPagination();
                });
            }
            pageNav.appendChild(b);
        }
        addBtn('<i class="bi bi-chevron-left"></i>', clientPage - 1, clientPage <= 1, false);
        for (var p = 1; p <= Math.min(pages, 7); p++) {
            addBtn(String(p), p, false, p === clientPage);
        }
        addBtn('<i class="bi bi-chevron-right"></i>', clientPage + 1, clientPage >= pages, false);
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
        set('vkVehDrawerName', data.name);
        set('vkVehDrawerReg', data.reg);
        set('vkVehDrawerType', data.type);
        set('vkVehDrawerStatus', data.status);
        set('vkVehDrawerDriver', data.driver);
        set('vkVehDrawerRates', data.rates);
        set('vkVehDrawerSeats', data.seats);
        set('vkVehDrawerMileage', data.mileage);
        set('vkVehDrawerFuel', data.fuel);
        set('vkVehDrawerTrans', data.trans);
        set('vkVehDrawerBrand', data.brand);
        set('vkVehDrawerModel', data.model);
        set('vkVehDrawerYear', data.year);
        set('vkVehDrawerInsurance', data.insurance);
        set('vkVehDrawerLicense', data.license);
        set('vkVehDrawerService', data.service);
        set('vkVehDrawerLocation', data.location);

        var thumb = document.getElementById('vkVehDrawerThumb');
        if (thumb) {
            thumb.innerHTML = data.image ? '<img src="' + data.image + '" alt="">' : '<i class="bi bi-car-front"></i>';
        }
        var editLink = document.getElementById('vkVehDrawerEdit');
        if (editLink && data.editUrl) {
            editLink.href = data.editUrl;
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
        document.querySelectorAll('[data-veh-drawer]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                try {
                    openDrawer(JSON.parse(el.getAttribute('data-veh-drawer') || '{}'));
                } catch (err) { /* ignore */ }
            });
        });
        document.querySelectorAll('#vkVehTable tbody tr[data-vehicle-id]').forEach(function (tr) {
            tr.addEventListener('click', function (e) {
                if (e.target.closest('.vk-veh-act, .form-check-input, a[href*="delete"]')) {
                    return;
                }
                var btn = tr.querySelector('[data-veh-drawer]');
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
                name: tr.dataset.exportName || '',
                reg: tr.dataset.exportReg || '',
                type: tr.dataset.exportType || '',
                driver: tr.dataset.exportDriver || '',
                status: tr.dataset.exportStatus || '',
                rates: tr.dataset.exportRates || '',
                seats: tr.dataset.exportSeats || '',
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
        var header = ['Name', 'Registration', 'Type', 'Driver', 'Status', 'Rates', 'Seats', 'Created'];
        var lines = [header.join(sep === ',' ? ',' : '\t')];
        data.forEach(function (r) {
            var row = [r.name, r.reg, r.type, r.driver, r.status, r.rates, r.seats, r.created];
            if (sep === ',') {
                lines.push(row.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(','));
            } else {
                lines.push(row.join('\t'));
            }
        });
        var blob = new Blob([lines.join('\n')], { type: mime });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'vehicles-' + new Date().toISOString().slice(0, 10) + ext;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function animateBars() {
        document.querySelectorAll('.vk-veh-bar-fill[data-width]').forEach(function (bar) {
            requestAnimationFrame(function () {
                bar.style.width = (bar.getAttribute('data-width') || '0') + '%';
            });
        });
    }

    function animateCounters() {
        document.querySelectorAll('[data-count-to]').forEach(function (el) {
            var target = parseFloat(el.dataset.countTo || '0');
            var suffix = el.dataset.countSuffix || '';
            var prefix = el.dataset.countPrefix || '';
            var isMoney = (el.dataset.countMoney || '') === '1';
            var isDecimal = (el.dataset.countDecimal || '') === '1';
            var duration = 700;
            var start = performance.now();
            function tick(now) {
                var p = Math.min(1, (now - start) / duration);
                var val = target * p;
                if (isMoney) {
                    el.textContent = (prefix || 'LKR ') + Math.round(val).toLocaleString('en-LK') + suffix;
                } else if (isDecimal) {
                    el.textContent = prefix + val.toFixed(1) + suffix;
                } else {
                    el.textContent = prefix + Math.round(val).toLocaleString() + suffix;
                }
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
    [filterType, filterStatus, filterDriver, perPageSelect].forEach(function (el) {
        if (el) {
            el.addEventListener('change', applyClientFilters);
        }
    });
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
            }
            [filterType, filterStatus, filterDriver].forEach(function (el) {
                if (el) {
                    el.value = '';
                }
            });
            applyClientFilters();
        });
    }
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            window.location.reload();
        });
    }
    if (formToggle && formPanel) {
        formToggle.addEventListener('click', function () {
            formPanel.classList.toggle('is-collapsed');
            if (!formPanel.classList.contains('is-collapsed')) {
                formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.vk-veh-row-check').forEach(function (cb) {
                var tr = cb.closest('tr');
                if (tr && !tr.classList.contains('is-hidden')) {
                    cb.checked = selectAll.checked;
                }
            });
        });
    }

    ['vkVehExportCsv', 'vkVehExportExcel', 'vkVehExportPdf', 'vkVehPrint'].forEach(function (id) {
        var btn = document.getElementById(id);
        if (!btn) {
            return;
        }
        if (id === 'vkVehExportCsv') {
            btn.addEventListener('click', function () { exportDelimited(',', '.csv', 'text/csv;charset=utf-8;'); });
        } else if (id === 'vkVehExportExcel') {
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
    applyClientFilters();
    animateBars();
    animateCounters();

    window.setTimeout(function () {
        app.classList.remove('vk-veh-skeleton');
    }, 400);
})();
