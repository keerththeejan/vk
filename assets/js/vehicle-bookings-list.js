(function () {
    'use strict';

    var app = document.getElementById('vkVbApp');
    if (!app) {
        return;
    }

    var form = document.getElementById('vkVbFilterForm');
    var panel = document.getElementById('vkVbPanel');
    var searchInput = document.getElementById('vkVbSearch');
    var filterStatus = document.getElementById('vkVbFilterStatus');
    var filterVehicle = document.getElementById('vkVbFilterVehicle');
    var filterVType = document.getElementById('vkVbFilterVType');
    var filterDriver = document.getElementById('vkVbFilterDriver');
    var filterDept = document.getElementById('vkVbFilterDept');
    var filterPriority = document.getElementById('vkVbFilterPriority');
    var filterPickup = document.getElementById('vkVbFilterPickup');
    var filterReturn = document.getElementById('vkVbFilterReturn');
    var perPageSelect = document.getElementById('vkVbPerPage');
    var resetBtn = document.getElementById('vkVbReset');
    var refreshBtn = document.getElementById('vkVbRefresh');
    var selectAll = document.getElementById('vkVbSelectAll');
    var drawer = document.getElementById('vkVbDrawer');
    var drawerBackdrop = document.getElementById('vkVbDrawerBackdrop');
    var drawerClose = document.getElementById('vkVbDrawerClose');
    var tableWrap = document.getElementById('vkVbTableWrap');
    var calendarEl = document.getElementById('vkVbCalendar');
    var fleetEl = document.getElementById('vkVbFleetGrid');
    var viewTabs = document.querySelectorAll('.vk-vb-view-tab');
    var pageInfo = document.getElementById('vkVbPageInfo');
    var pageNav = document.getElementById('vkVbPageNav');
    var searchQuery = (app.dataset.searchQuery || '').trim();

    var clientPage = 1;

    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, ms);
        };
    }

    function escapeRegExp(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
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

    function rows() {
        return document.querySelectorAll('#vkVbTable tbody tr[data-booking-id], .vk-vb-mobile-card[data-booking-id]');
    }

    function visibleRows() {
        return Array.prototype.filter.call(rows(), function (r) { return !r.classList.contains('is-hidden'); });
    }

    function perPage() {
        var n = parseInt(perPageSelect && perPageSelect.value ? perPageSelect.value : '25', 10);
        return isNaN(n) ? 25 : n;
    }

    function applyClientFilters() {
        var st = filterStatus ? filterStatus.value : '';
        var veh = filterVehicle ? filterVehicle.value : '';
        var vt = filterVType ? filterVType.value : '';
        var dr = filterDriver ? filterDriver.value : '';
        var dept = filterDept ? filterDept.value : '';
        var pri = filterPriority ? filterPriority.value : '';
        var pu = filterPickup ? filterPickup.value : '';
        var ret = filterReturn ? filterReturn.value : '';

        rows().forEach(function (row) {
            var matchSt = !st || row.dataset.status === st;
            var matchVeh = !veh || row.dataset.vehicleId === veh;
            var matchVt = !vt || row.dataset.vehicleType === vt;
            var matchDr = !dr || row.dataset.driverId === dr;
            var matchDept = !dept || row.dataset.dept === dept;
            var matchPri = !pri || row.dataset.priority === pri;
            var matchPu = !pu || (row.dataset.pickupDate && row.dataset.pickupDate >= pu);
            var matchRet = !ret || (row.dataset.returnDate && row.dataset.returnDate <= ret);
            row.classList.toggle('is-hidden', !(matchSt && matchVeh && matchVt && matchDr && matchDept && matchPri && matchPu && matchRet));
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
            if (total === 0) {
                pageInfo.textContent = 'Showing 0 of 0';
            } else {
                pageInfo.textContent = 'Showing ' + (start + 1) + '–' + Math.min(end, total) + ' of ' + total.toLocaleString();
            }
        }
        if (pageNav) {
            pageNav.innerHTML = '';
            if (pages <= 1) {
                return;
            }
            var prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'vk-vb-page-link' + (clientPage <= 1 ? ' is-disabled' : '');
            prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
            prev.setAttribute('aria-label', 'Previous page');
            prev.addEventListener('click', function () {
                if (clientPage > 1) {
                    clientPage--;
                    applyClientPagination();
                }
            });
            pageNav.appendChild(prev);
            for (var p = 1; p <= Math.min(pages, 7); p++) {
                var a = document.createElement('button');
                a.type = 'button';
                a.className = 'vk-vb-page-link' + (p === clientPage ? ' is-active' : '');
                a.textContent = String(p);
                (function (page) {
                    a.addEventListener('click', function () {
                        clientPage = page;
                        applyClientPagination();
                    });
                })(p);
                pageNav.appendChild(a);
            }
            var next = document.createElement('button');
            next.type = 'button';
            next.className = 'vk-vb-page-link' + (clientPage >= pages ? ' is-disabled' : '');
            next.innerHTML = '<i class="bi bi-chevron-right"></i>';
            next.setAttribute('aria-label', 'Next page');
            next.addEventListener('click', function () {
                if (clientPage < pages) {
                    clientPage++;
                    applyClientPagination();
                }
            });
            pageNav.appendChild(next);
        }
    }

    function highlightSearch() {
        if (!searchQuery || searchQuery.length < 2) {
            return;
        }
        var re = new RegExp('(' + escapeRegExp(searchQuery) + ')', 'gi');
        document.querySelectorAll('.vk-vb-highlight-target').forEach(function (el) {
            var text = el.textContent || '';
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
        var set = function (id, val) {
            var el = document.getElementById(id);
            if (el) {
                el.textContent = val || '—';
            }
        };
        set('vkVbDrawerRef', data.ref);
        set('vkVbDrawerCustomer', data.customer);
        set('vkVbDrawerPhone', data.phone);
        set('vkVbDrawerVehicle', data.vehicle);
        set('vkVbDrawerReg', data.reg);
        set('vkVbDrawerDriver', data.driver);
        set('vkVbDrawerPickup', data.pickup);
        set('vkVbDrawerDrop', data.drop);
        set('vkVbDrawerPickupDate', data.pickupDate);
        set('vkVbDrawerReturnDate', data.returnDate);
        set('vkVbDrawerDistance', data.distance);
        set('vkVbDrawerCost', data.cost);
        set('vkVbDrawerStatus', data.status);
        set('vkVbDrawerNotes', data.notes);
        set('vkVbDrawerType', data.vtype);

        var thumb = document.getElementById('vkVbDrawerThumb');
        if (thumb) {
            if (data.image) {
                thumb.innerHTML = '<img src="' + data.image + '" alt="">';
            } else {
                thumb.innerHTML = '<i class="bi bi-car-front"></i>';
            }
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
        document.querySelectorAll('[data-vb-drawer]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                try {
                    openDrawer(JSON.parse(el.getAttribute('data-vb-drawer') || '{}'));
                } catch (err) { /* ignore */ }
            });
        });
        document.querySelectorAll('#vkVbTable tbody tr[data-booking-id]').forEach(function (tr) {
            tr.addEventListener('click', function (e) {
                if (e.target.closest('.vk-vb-act, .vk-vb-assign-wrap, .form-check-input, form, select, button')) {
                    return;
                }
                var btn = tr.querySelector('[data-vb-drawer]');
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
                ref: tr.dataset.exportRef || '',
                vehicle: tr.dataset.exportVehicle || '',
                reg: tr.dataset.exportReg || '',
                vtype: tr.dataset.exportVtype || '',
                driver: tr.dataset.exportDriver || '',
                dept: tr.dataset.exportDept || '',
                customer: tr.dataset.exportCustomer || '',
                pickup: tr.dataset.exportPickupDate || '',
                returnDate: tr.dataset.exportReturnDate || '',
                pickupLoc: tr.dataset.exportPickupLoc || '',
                dest: tr.dataset.exportDest || '',
                purpose: tr.dataset.exportPurpose || '',
                distance: tr.dataset.exportDistance || '',
                fuel: tr.dataset.exportFuel || '',
                status: tr.dataset.exportStatus || '',
                approval: tr.dataset.exportApproval || '',
                cost: tr.dataset.exportCost || '',
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
        var header = ['Booking No', 'Vehicle', 'Reg', 'Type', 'Driver', 'Dept', 'Customer', 'Pickup', 'Return', 'Pickup Loc', 'Destination', 'Purpose', 'Distance', 'Fuel', 'Status', 'Approval', 'Cost', 'Created'];
        var lines = [header.join(sep === ',' ? ',' : '\t')];
        data.forEach(function (r) {
            var row = [r.ref, r.vehicle, r.reg, r.vtype, r.driver, r.dept, r.customer, r.pickup, r.returnDate, r.pickupLoc, r.dest, r.purpose, r.distance, r.fuel, r.status, r.approval, r.cost, r.created];
            if (sep === ',') {
                lines.push(row.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(','));
            } else {
                lines.push(row.join('\t'));
            }
        });
        var blob = new Blob([lines.join('\n')], { type: mime });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'vehicle-bookings-' + new Date().toISOString().slice(0, 10) + ext;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function buildCalendar() {
        if (!calendarEl) {
            return;
        }
        var now = new Date();
        var year = now.getFullYear();
        var month = now.getMonth();
        var firstDay = new Date(year, month, 1).getDay();
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var eventsByDay = {};
        visibleRows().forEach(function (row) {
            var d = row.dataset.pickupDate;
            if (!d) {
                return;
            }
            if (!eventsByDay[d]) {
                eventsByDay[d] = [];
            }
            eventsByDay[d].push({ ref: row.dataset.exportRef || '', status: row.dataset.status || '' });
        });
        var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        var html = '<div class="d-flex justify-content-between mb-3"><h3 class="h6 mb-0 fw-bold">' + monthNames[month] + ' ' + year + '</h3><span class="small text-muted">Fleet schedule</span></div>';
        html += '<div class="vk-vb-cal-grid">';
        ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(function (d) {
            html += '<div class="vk-vb-cal-head">' + d + '</div>';
        });
        for (var i = 0; i < firstDay; i++) {
            html += '<div class="vk-vb-cal-day"></div>';
        }
        for (var day = 1; day <= daysInMonth; day++) {
            var dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            html += '<div class="vk-vb-cal-day"><strong>' + day + '</strong>';
            (eventsByDay[dateStr] || []).slice(0, 3).forEach(function (ev) {
                html += '<span class="vk-vb-cal-ev">' + ev.ref + '</span>';
            });
            html += '</div>';
        }
        html += '</div>';
        calendarEl.innerHTML = html;
    }

    function setView(mode) {
        viewTabs.forEach(function (tab) {
            tab.classList.toggle('is-active', tab.dataset.view === mode);
        });
        if (tableWrap) {
            tableWrap.classList.toggle('d-none', mode !== 'table');
        }
        document.querySelectorAll('.vk-vb-mobile-only').forEach(function (el) {
            el.classList.toggle('d-none', mode !== 'table');
        });
        if (calendarEl) {
            calendarEl.classList.toggle('is-visible', mode === 'calendar');
            if (mode === 'calendar') {
                buildCalendar();
            }
        }
        if (fleetEl) {
            fleetEl.classList.toggle('is-visible', mode === 'fleet');
        }
    }

    function animateBars() {
        document.querySelectorAll('.vk-vb-bar-fill[data-width]').forEach(function (bar) {
            requestAnimationFrame(function () {
                bar.style.width = (bar.getAttribute('data-width') || '0') + '%';
            });
        });
    }

    function animateCounters() {
        document.querySelectorAll('[data-count-to]').forEach(function (el) {
            var target = parseFloat(el.dataset.countTo || '0');
            var isMoney = (el.dataset.countMoney || '') === '1';
            var isDecimal = (el.dataset.countDecimal || '') === '1';
            var suffix = el.dataset.countSuffix || '';
            var duration = 700;
            var start = performance.now();
            function tick(now) {
                var p = Math.min(1, (now - start) / duration);
                var val = target * p;
                if (isMoney) {
                    el.textContent = (el.dataset.countPrefix || 'LKR ') + Math.round(val).toLocaleString('en-LK') + suffix;
                } else if (isDecimal) {
                    el.textContent = val.toFixed(1) + suffix;
                } else {
                    el.textContent = Math.round(val).toLocaleString() + suffix;
                }
                if (p < 1) {
                    requestAnimationFrame(tick);
                }
            }
            requestAnimationFrame(tick);
        });
    }

    function bindAssignToggles() {
        document.querySelectorAll('.vk-vb-assign-toggle').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var wrap = btn.closest('.vk-vb-assign-wrap');
                if (!wrap) {
                    return;
                }
                document.querySelectorAll('.vk-vb-assign-wrap.is-open').forEach(function (w) {
                    if (w !== wrap) {
                        w.classList.remove('is-open');
                    }
                });
                wrap.classList.toggle('is-open');
            });
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.vk-vb-assign-wrap')) {
                document.querySelectorAll('.vk-vb-assign-wrap.is-open').forEach(function (w) {
                    w.classList.remove('is-open');
                });
            }
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
    if (perPageSelect) {
        perPageSelect.addEventListener('change', function () {
            clientPage = 1;
            applyClientPagination();
        });
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

    [filterStatus, filterVehicle, filterVType, filterDriver, filterDept, filterPriority, filterPickup, filterReturn].forEach(function (el) {
        if (el) {
            el.addEventListener('change', applyClientFilters);
        }
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.vk-vb-row-check').forEach(function (cb) {
                var tr = cb.closest('tr');
                if (tr && !tr.classList.contains('is-hidden')) {
                    cb.checked = selectAll.checked;
                }
            });
        });
    }

    viewTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            setView(tab.dataset.view || 'table');
        });
    });

    ['vkVbExportCsv', 'vkVbExportExcel', 'vkVbExportPdf', 'vkVbPrint'].forEach(function (id) {
        var btn = document.getElementById(id);
        if (!btn) {
            return;
        }
        if (id === 'vkVbExportCsv') {
            btn.addEventListener('click', function () { exportDelimited(',', '.csv', 'text/csv;charset=utf-8;'); });
        } else if (id === 'vkVbExportExcel') {
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
            document.querySelectorAll('.vk-vb-assign-wrap.is-open').forEach(function (w) {
                w.classList.remove('is-open');
            });
        }
    });

    bindDrawerTriggers();
    bindAssignToggles();
    initTooltips();
    highlightSearch();
    applyClientFilters();
    animateBars();
    animateCounters();

    window.setTimeout(function () {
        app.classList.remove('vk-vb-skeleton');
    }, 400);

    var style = document.createElement('style');
    style.textContent = '#vkVbTable tbody tr.is-page-hidden, .vk-vb-mobile-card.is-page-hidden { display: none !important; }';
    document.head.appendChild(style);
})();
