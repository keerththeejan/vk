(function () {
    'use strict';

    const app = document.getElementById('vkBookApp');
    if (!app) {
        return;
    }

    const form = document.getElementById('vkBookFilterForm');
    const panel = document.getElementById('vkBookPanel');
    const searchInput = document.getElementById('vkBookSearch');
    const filterStatus = document.getElementById('vkBookFilterStatus');
    const filterService = document.getElementById('vkBookFilterService');
    const filterPriority = document.getElementById('vkBookFilterPriority');
    const filterTech = document.getElementById('vkBookFilterTech');
    const filterDateFrom = document.getElementById('vkBookFilterDateFrom');
    const filterDateTo = document.getElementById('vkBookFilterDateTo');
    const filterTimeSlot = document.getElementById('vkBookFilterTimeSlot');
    const perPageSelect = document.getElementById('vkBookPerPage');
    const resetBtn = document.getElementById('vkBookReset');
    const refreshBtn = document.getElementById('vkBookRefresh');
    const selectAll = document.getElementById('vkBookSelectAll');
    const drawer = document.getElementById('vkBookDrawer');
    const drawerBackdrop = document.getElementById('vkBookDrawerBackdrop');
    const drawerClose = document.getElementById('vkBookDrawerClose');
    const tableWrap = document.getElementById('vkBookTableWrap');
    const calendarEl = document.getElementById('vkBookCalendar');
    const searchQuery = (app.dataset.searchQuery || '').trim();
    const viewTabs = document.querySelectorAll('.vk-book-view-tab');

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

    function bookingRows() {
        return document.querySelectorAll('#vkBookTable tbody tr[data-booking-id], .vk-book-mobile-card[data-booking-id]');
    }

    function rowDate(row) {
        return row.dataset.bookDate || '';
    }

    function rowTimeSlot(row) {
        const h = parseInt(row.dataset.bookHour || '-1', 10);
        if (h < 0) {
            return '';
        }
        if (h < 12) {
            return 'morning';
        }
        if (h < 17) {
            return 'afternoon';
        }
        return 'evening';
    }

    function applyClientFilters() {
        const st = filterStatus ? filterStatus.value : '';
        const svc = filterService ? filterService.value : '';
        const pri = filterPriority ? filterPriority.value : '';
        const tech = filterTech ? filterTech.value : '';
        const from = filterDateFrom ? filterDateFrom.value : '';
        const to = filterDateTo ? filterDateTo.value : '';
        const slot = filterTimeSlot ? filterTimeSlot.value : '';

        bookingRows().forEach(function (row) {
            const matchSt = !st || row.dataset.uiStatus === st;
            const matchSvc = !svc || row.dataset.service === svc;
            const matchPri = !pri || row.dataset.priority === pri;
            const matchTech = !tech || row.dataset.tech === tech;
            const d = rowDate(row);
            const matchFrom = !from || (d && d >= from);
            const matchTo = !to || (d && d <= to);
            const matchSlot = !slot || rowTimeSlot(row) === slot;
            row.classList.toggle('is-hidden', !(matchSt && matchSvc && matchPri && matchTech && matchFrom && matchTo && matchSlot));
        });
    }

    function highlightSearch() {
        if (!searchQuery || searchQuery.length < 2) {
            return;
        }
        const re = new RegExp('(' + escapeRegExp(searchQuery) + ')', 'gi');
        document.querySelectorAll('.vk-book-highlight-target').forEach(function (el) {
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

        set('vkBookDrawerNo', data.bookingNo);
        set('vkBookDrawerCustomer', data.customer);
        set('vkBookDrawerPhone', data.phone);
        set('vkBookDrawerEmail', data.email);
        set('vkBookDrawerAddress', data.address);
        set('vkBookDrawerService', data.service);
        set('vkBookDrawerStatus', data.statusLabel);
        set('vkBookDrawerTech', data.tech);
        set('vkBookDrawerDate', data.date);
        set('vkBookDrawerTime', data.time);
        set('vkBookDrawerCost', data.cost);
        set('vkBookDrawerPayment', data.payment);
        set('vkBookDrawerProblem', data.problem);
        set('vkBookDrawerCreated', data.created);

        const avatar = document.getElementById('vkBookDrawerAvatar');
        if (avatar) {
            avatar.textContent = data.initials || 'B';
        }

        const manageLink = document.getElementById('vkBookDrawerManage');
        if (manageLink && data.id) {
            manageLink.href = (window.VK_BASE_URL || '').replace(/\/$/, '') + '/modules/bookings/view.php?id=' + data.id;
        }

        const repairLink = document.getElementById('vkBookDrawerRepair');
        if (repairLink) {
            if (data.repairJobId) {
                repairLink.href = (window.VK_BASE_URL || '').replace(/\/$/, '') + '/modules/repairs/view.php?id=' + data.repairJobId;
                repairLink.classList.remove('d-none');
            } else {
                repairLink.classList.add('d-none');
            }
        }

        const waLink = document.getElementById('vkBookDrawerWa');
        if (waLink) {
            waLink.href = data.waUrl || '#';
            waLink.classList.toggle('d-none', !data.waUrl);
        }

        const mapLink = document.getElementById('vkBookDrawerMap');
        if (mapLink) {
            mapLink.href = data.mapUrl || '#';
            mapLink.classList.toggle('d-none', !data.mapUrl);
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
        document.querySelectorAll('[data-booking-drawer]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                try {
                    openDrawer(JSON.parse(el.getAttribute('data-booking-drawer') || '{}'));
                } catch (err) {
                    /* ignore */
                }
            });
        });

        document.querySelectorAll('#vkBookTable tbody tr[data-booking-id]').forEach(function (tr) {
            tr.addEventListener('click', function (e) {
                if (e.target.closest('.vk-book-act, .form-check-input, a[href]')) {
                    return;
                }
                const btn = tr.querySelector('[data-booking-drawer]');
                if (btn) {
                    btn.click();
                }
            });
        });
    }

    function tableRowsForExport() {
        const rows = [];
        document.querySelectorAll('#vkBookTable tbody tr[data-booking-id]').forEach(function (tr) {
            if (tr.classList.contains('is-hidden')) {
                return;
            }
            rows.push({
                no: tr.dataset.exportNo || '',
                customer: tr.dataset.exportCustomer || '',
                phone: tr.dataset.exportPhone || '',
                service: tr.dataset.exportService || '',
                tech: tr.dataset.exportTech || '',
                date: tr.dataset.exportDate || '',
                time: tr.dataset.exportTime || '',
                priority: tr.dataset.exportPriority || '',
                status: tr.dataset.exportStatus || '',
                location: tr.dataset.exportLocation || '',
                cost: tr.dataset.exportCost || '',
                payment: tr.dataset.exportPayment || '',
                createdBy: tr.dataset.exportCreatedBy || '',
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
        const header = ['Booking No', 'Customer', 'Phone', 'Service', 'Technician', 'Date', 'Time', 'Priority', 'Status', 'Location', 'Est. Cost', 'Payment', 'Created By'];
        const lines = [header.join(sep === ',' ? ',' : '\t')];
        data.forEach(function (r) {
            const row = [r.no, r.customer, r.phone, r.service, r.tech, r.date, r.time, r.priority, r.status, r.location, r.cost, r.payment, r.createdBy];
            if (sep === ',') {
                lines.push(row.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(','));
            } else {
                lines.push(row.join('\t'));
            }
        });
        const blob = new Blob([lines.join('\n')], { type: mime });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'bookings-' + new Date().toISOString().slice(0, 10) + ext;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function buildCalendar() {
        if (!calendarEl) {
            return;
        }
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const todayStr = now.toISOString().slice(0, 10);

        const eventsByDay = {};
        bookingRows().forEach(function (row) {
            if (row.classList.contains('is-hidden')) {
                return;
            }
            const d = row.dataset.bookDate;
            if (!d) {
                return;
            }
            if (!eventsByDay[d]) {
                eventsByDay[d] = [];
            }
            eventsByDay[d].push({
                no: row.dataset.exportNo || '',
                customer: row.dataset.exportCustomer || '',
                id: row.dataset.bookingId || '',
                emergency: row.dataset.emergency === '1',
            });
        });

        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        let html = '<div class="d-flex justify-content-between align-items-center mb-3">';
        html += '<h3 class="h6 mb-0 fw-bold">' + monthNames[month] + ' ' + year + '</h3>';
        html += '<span class="small text-muted">Month view · current page bookings</span></div>';
        html += '<div class="vk-book-cal-grid" role="grid" aria-label="Booking calendar">';
        ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(function (d) {
            html += '<div class="vk-book-cal-head" role="columnheader">' + d + '</div>';
        });

        for (let i = 0; i < firstDay; i++) {
            html += '<div class="vk-book-cal-day" aria-hidden="true"></div>';
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            const isToday = dateStr === todayStr;
            html += '<div class="vk-book-cal-day' + (isToday ? ' is-today' : '') + '" role="gridcell" aria-label="' + dateStr + '">';
            html += '<strong>' + day + '</strong>';
            (eventsByDay[dateStr] || []).slice(0, 3).forEach(function (ev) {
                const base = (window.VK_BASE_URL || '').replace(/\/$/, '');
                html += '<a class="vk-book-cal-ev' + (ev.emergency ? ' is-emergency' : '') + '" href="' + base + '/modules/bookings/view.php?id=' + ev.id + '" title="' + ev.customer + '">' + ev.no + '</a>';
            });
            if ((eventsByDay[dateStr] || []).length > 3) {
                html += '<span class="text-muted">+' + (eventsByDay[dateStr].length - 3) + ' more</span>';
            }
            html += '</div>';
        }
        html += '</div>';
        calendarEl.innerHTML = html;
    }

    function setView(mode) {
        viewTabs.forEach(function (tab) {
            tab.classList.toggle('is-active', tab.dataset.view === mode);
            tab.setAttribute('aria-selected', tab.dataset.view === mode ? 'true' : 'false');
        });
        if (mode === 'calendar') {
            if (tableWrap) {
                tableWrap.classList.add('d-none');
            }
            document.querySelectorAll('.vk-book-mobile-only').forEach(function (el) {
                el.classList.add('d-none');
            });
            if (calendarEl) {
                calendarEl.classList.add('is-visible');
                buildCalendar();
            }
        } else {
            if (tableWrap) {
                tableWrap.classList.remove('d-none');
            }
            document.querySelectorAll('.vk-book-mobile-only').forEach(function (el) {
                el.classList.remove('d-none');
            });
            if (calendarEl) {
                calendarEl.classList.remove('is-visible');
            }
        }
    }

    function animateBars() {
        document.querySelectorAll('.vk-book-bar-fill[data-width]').forEach(function (bar) {
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
            const isDecimal = (el.dataset.countDecimal || '') === '1';
            const duration = 700;
            const start = performance.now();
            function tick(now) {
                const p = Math.min(1, (now - start) / duration);
                const val = target * p;
                if (isMoney) {
                    el.textContent = prefix + '₹' + Math.round(val).toLocaleString('en-IN') + suffix;
                } else if (isDecimal) {
                    el.textContent = prefix + val.toFixed(1) + suffix;
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
        const emergencyCheck = document.getElementById('vkBookEmergency');
        if (emergencyCheck) {
            emergencyCheck.addEventListener('change', function () {
                showLoading();
                form.requestSubmit();
            });
        }
    }

    if (perPageSelect && form) {
        perPageSelect.addEventListener('change', function () {
            showLoading();
            form.requestSubmit();
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

    [filterStatus, filterService, filterPriority, filterTech, filterDateFrom, filterDateTo, filterTimeSlot].forEach(function (el) {
        if (el) {
            el.addEventListener('change', applyClientFilters);
        }
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.vk-book-row-check').forEach(function (cb) {
                const tr = cb.closest('tr');
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

    ['vkBookExportCsv', 'vkBookExportExcel', 'vkBookExportPdf', 'vkBookPrint'].forEach(function (id) {
        const btn = document.getElementById(id);
        if (!btn) {
            return;
        }
        if (id === 'vkBookExportCsv') {
            btn.addEventListener('click', function () { exportDelimited(',', '.csv', 'text/csv;charset=utf-8;'); });
        } else if (id === 'vkBookExportExcel') {
            btn.addEventListener('click', function () { exportDelimited('\t', '.xls', 'application/vnd.ms-excel;charset=utf-8;'); });
        } else {
            btn.addEventListener('click', function () { window.print(); });
        }
    });

    const calendarBtn = document.getElementById('vkBookCalendarBtn');
    if (calendarBtn) {
        calendarBtn.addEventListener('click', function () {
            setView('calendar');
            calendarEl && calendarEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

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
        app.classList.remove('vk-book-skeleton');
    }, 400);
})();
