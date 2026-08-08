(function () {
    'use strict';

    var root = document.querySelector('.vk-dash-admin');
    if (!root) {
        return;
    }

    var cacheKey = 'vk-dash-stats-v1';
    var widgetStoreKey = 'vk-dash-widgets-v1';
    var chartInstances = {};
    var latestStats = null;
    var activeRecentTab = 'quotations';
    var searchRoutes = {
        customers: '/modules/customers/list.php?q=',
        invoices: '/modules/invoices/list.php?q=',
        repairs: '/modules/repairs/list.php?q=',
        products: '/modules/products/list.php?q=',
        maintenance: '/modules/maintenance/list.php?q=',
        bookings: '/modules/bookings/list.php?q=',
        cctv: '/modules/cctv/list.php?q=',
        technicians: '/modules/technicians/list.php?q=',
    };

    function baseUrl() {
        return (window.VK_BASE_URL || '').replace(/\/$/, '');
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function readCache() {
        try {
            var raw = sessionStorage.getItem(cacheKey);
            if (!raw) {
                return null;
            }
            var parsed = JSON.parse(raw);
            return parsed && parsed.data ? parsed.data : null;
        } catch (e) {
            return null;
        }
    }

    function greeting() {
        var h = new Date().getHours();
        if (h < 12) {
            return 'Good morning';
        }
        if (h < 17) {
            return 'Good afternoon';
        }
        return 'Good evening';
    }

    function updateClock() {
        var now = new Date();
        var dateEl = document.getElementById('vkDashDate');
        var timeEl = document.getElementById('vkDashTime');
        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
        }
        if (timeEl) {
            timeEl.textContent = now.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
    }

    function drawSparkline(svg, values) {
        if (!svg || !values.length) {
            return;
        }
        var w = 48;
        var h = 16;
        var max = Math.max.apply(null, values.concat([1]));
        var pts = values.map(function (v, i) {
            var x = (i / Math.max(1, values.length - 1)) * w;
            var y = h - (v / max) * h;
            return x.toFixed(1) + ',' + y.toFixed(1);
        }).join(' ');
        svg.innerHTML = '<polyline fill="none" stroke="currentColor" stroke-width="1.5" points="' + pts + '"/>';
    }

    function initSparklines(data) {
        if (!data || !data.stats) {
            return;
        }
        var s = data.stats;
        var map = {
            customers: [s.total_customers * 0.7, s.total_customers * 0.85, s.total_customers],
            revenue: [s.sales_today, s.sales_month * 0.4, s.sales_month * 0.7, s.sales_month],
            repairs: [s.repair_pipeline, s.repair_completed, s.repair_delivered],
            bookings: [s.total_bookings * 0.6, s.total_bookings * 0.8, s.total_bookings],
            quotes: [s.quotations_pending, s.quotations_approved, s.quotations_total],
            activity: [s.quotations_today, s.invoices_today, s.payments_today_count, s.today_activities],
        };
        document.querySelectorAll('[data-vk-spark]').forEach(function (svg) {
            var key = svg.getAttribute('data-vk-spark');
            if (map[key]) {
                drawSparkline(svg, map[key].map(function (n) { return Math.max(0, Number(n) || 0); }));
            }
        });
    }

    function chartColors() {
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        return {
            text: dark ? '#CBD5E1' : '#334155',
            grid: dark ? 'rgba(148,163,184,0.15)' : 'rgba(15,23,42,0.08)',
            primary: '#3B82F6',
            success: '#22C55E',
            warning: '#F59E0B',
            danger: '#EF4444',
            purple: '#8B5CF6',
            cyan: '#06B6D4',
        };
    }

    function upsertChart(id, config) {
        var canvas = document.getElementById(id);
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }
        if (chartInstances[id]) {
            chartInstances[id].data = config.data;
            chartInstances[id].options = config.options || chartInstances[id].options;
            chartInstances[id].update('none');
            return;
        }
        chartInstances[id] = new window.Chart(canvas.getContext('2d'), config);
    }

    function renderCharts(data) {
        var charts = (data && data.charts) || {};
        var c = chartColors();
        var monthly = charts.monthly_sales || { labels: [], values: [] };
        var quoteStatus = charts.quotation_status || { labels: [], values: [] };
        var growth = charts.customer_growth || { labels: [], values: [] };

        upsertChart('vkChartMonthlySales', {
            type: 'bar',
            data: {
                labels: monthly.labels || [],
                datasets: [{
                    label: 'Sales',
                    data: monthly.values || [],
                    backgroundColor: c.primary,
                    borderRadius: 6,
                    maxBarThickness: 28,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: c.text, font: { size: 10 } }, grid: { display: false } },
                    y: { ticks: { color: c.text, font: { size: 10 } }, grid: { color: c.grid } },
                },
            },
        });

        upsertChart('vkChartQuoteStatus', {
            type: 'doughnut',
            data: {
                labels: quoteStatus.labels || [],
                datasets: [{
                    data: quoteStatus.values || [],
                    backgroundColor: [c.grid, c.warning, c.success, c.danger, '#64748B', c.purple],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: c.text, boxWidth: 10, font: { size: 10 } } },
                },
            },
        });

        upsertChart('vkChartRevenue', {
            type: 'line',
            data: {
                labels: monthly.labels || [],
                datasets: [{
                    label: 'Revenue',
                    data: monthly.values || [],
                    borderColor: c.success,
                    backgroundColor: 'rgba(34,197,94,0.15)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: c.text, font: { size: 10 } }, grid: { display: false } },
                    y: { ticks: { color: c.text, font: { size: 10 } }, grid: { color: c.grid } },
                },
            },
        });

        upsertChart('vkChartCustomers', {
            type: 'bar',
            data: {
                labels: growth.labels || [],
                datasets: [{
                    label: 'New customers',
                    data: growth.values || [],
                    backgroundColor: c.cyan,
                    borderRadius: 6,
                    maxBarThickness: 28,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: c.text, font: { size: 10 } }, grid: { display: false } },
                    y: { ticks: { color: c.text, font: { size: 10 } }, grid: { color: c.grid }, beginAtZero: true },
                },
            },
        });
    }

    function statusBadge(status) {
        var s = String(status || '').toLowerCase();
        var map = {
            draft: 'secondary', pending: 'warning', pending_approval: 'warning', approved: 'success',
            rejected: 'danger', expired: 'dark', accepted: 'info', converted_invoice: 'primary',
            unpaid: 'danger', partial: 'warning', paid: 'success',
            completed: 'success', delivered: 'primary', in_progress: 'info',
        };
        var tone = map[s] || 'secondary';
        return '<span class="badge text-bg-' + tone + '">' + esc(s.replace(/_/g, ' ')) + '</span>';
    }

    function renderRecentTab(tab, data) {
        var head = document.getElementById('vkDashRecentHead');
        var body = document.getElementById('vkDashRecentBody');
        if (!head || !body) {
            return;
        }
        var rows = [];
        if (tab === 'quotations') {
            head.innerHTML = '<th>Quotation</th><th>Customer</th><th>Status</th><th>Date</th><th></th>';
            rows = (data.recent_quotations || []).map(function (r) {
                return '<tr><td><code>' + esc(r.quotation_number) + '</code></td><td>' + esc(r.customer_name) + '</td><td>' +
                    statusBadge(r.status) + '</td><td>' + esc(String(r.quotation_date || r.created_at || '').slice(0, 16)) +
                    '</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="' + esc(baseUrl() + '/modules/quotations/view.php?id=' + r.id) + '">View</a></td></tr>';
            });
        } else if (tab === 'customers') {
            head.innerHTML = '<th>Customer</th><th>Phone</th><th>Email</th><th>Joined</th><th></th>';
            rows = (data.recent_customers || []).map(function (r) {
                return '<tr><td>' + esc(r.name) + '</td><td>' + esc(r.phone || '—') + '</td><td>' + esc(r.email || '—') +
                    '</td><td>' + esc(String(r.created_at || '').slice(0, 16)) +
                    '</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="' + esc(baseUrl() + '/modules/customers/profile.php?id=' + r.id) + '">View</a></td></tr>';
            });
        } else if (tab === 'payments') {
            head.innerHTML = '<th>Amount</th><th>Customer</th><th>Method</th><th>When</th><th></th>';
            rows = (data.recent_payments || []).map(function (r) {
                var href = r.invoice_id
                    ? baseUrl() + '/modules/invoices/view.php?id=' + r.invoice_id
                    : baseUrl() + '/modules/payments/list.php';
                return '<tr><td><strong>₹' + esc(Number(r.amount || 0).toLocaleString()) + '</strong></td><td>' +
                    esc(r.customer_name || r.invoice_number || '—') + '</td><td>' + statusBadge(r.method) +
                    '</td><td>' + esc(String(r.paid_at || '').slice(0, 16)) +
                    '</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="' + esc(href) + '">Open</a></td></tr>';
            });
        } else if (tab === 'invoices') {
            head.innerHTML = '<th>Invoice</th><th>Customer</th><th>Status</th><th>Date</th><th></th>';
            rows = (data.recent_invoices || []).map(function (r) {
                return '<tr><td><code>' + esc(r.invoice_number) + '</code></td><td>' + esc(r.customer_name || '—') + '</td><td>' +
                    statusBadge(r.status) + '</td><td>' + esc(String(r.invoice_date || r.created_at || '').slice(0, 16)) +
                    '</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="' + esc(baseUrl() + '/modules/invoices/view.php?id=' + r.id) + '">View</a></td></tr>';
            });
        } else {
            head.innerHTML = '<th>Type</th><th>Job</th><th>Customer</th><th>Status</th><th></th>';
            rows = (data.recent_jobs || []).map(function (r) {
                var isCctv = r.job_type === 'cctv';
                var href = isCctv
                    ? baseUrl() + '/modules/cctv/view.php?id=' + r.id
                    : baseUrl() + '/modules/repairs/view.php?id=' + r.id;
                return '<tr><td><span class="badge text-bg-' + (isCctv ? 'info' : 'secondary') + '">' +
                    esc(String(r.job_type || '').toUpperCase()) + '</span></td><td><code>' + esc(r.ref) + '</code></td><td>' +
                    esc(r.customer_name) + '</td><td>' + statusBadge(r.status) +
                    '</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="' + esc(href) + '">View</a></td></tr>';
            });
        }
        body.innerHTML = rows.length
            ? rows.join('')
            : '<tr><td colspan="5" class="text-center text-muted py-4">No records yet.</td></tr>';
    }

    function initRecentTabs() {
        document.querySelectorAll('.vk-dash-tab[data-vk-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                activeRecentTab = btn.getAttribute('data-vk-tab') || 'quotations';
                document.querySelectorAll('.vk-dash-tab[data-vk-tab]').forEach(function (el) {
                    var on = el === btn;
                    el.classList.toggle('is-active', on);
                    el.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                if (latestStats) {
                    renderRecentTab(activeRecentTab, latestStats);
                }
            });
        });
    }

    function animateBars() {
        document.querySelectorAll('.vk-dash-bar-fill[data-width]').forEach(function (bar) {
            requestAnimationFrame(function () {
                bar.style.width = (bar.getAttribute('data-width') || '0') + '%';
            });
        });
    }

    function setDashKpi(id, value) {
        var el = document.getElementById(id);
        if (el) {
            el.textContent = value;
        }
    }

    function enhanceFromData(data) {
        if (!data || !data.ok) {
            return;
        }
        latestStats = data;
        var s = data.stats || {};

        var notifyCount = (s.critical_count || 0) + (data.maint_reminders || []).length + (s.low_stock || 0) + (s.quotations_pending || 0);
        setDashKpi('vkDashNotifyCount', String(notifyCount));
        var dot = document.getElementById('vkDashNotifyDot');
        if (dot) {
            dot.classList.toggle('d-none', notifyCount <= 0);
        }

        setDashKpi('vkDashFinRevenue', '₹' + Number(s.sales_month || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }));
        setDashKpi('vkDashFinReceivable', '₹' + Number(s.outstanding || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }));
        setDashKpi('vkDashFinCash', '₹' + Number(s.collections_today || s.sales_today || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }));
        setDashKpi('vkDashInvLow', String(s.low_stock || 0));
        setDashKpi('vkDashInvValue', String(s.stock_items || 0));

        var barRepair = document.querySelector('[data-vk-dash-bar="repairs"]');
        if (barRepair) {
            barRepair.setAttribute('data-width', String(s.repair_completion || 0));
        }
        var barWorkload = document.querySelector('[data-vk-dash-bar="workload"]');
        if (barWorkload) {
            barWorkload.setAttribute('data-width', String(s.workload_completion || 0));
        }
        animateBars();

        renderTimeline(data);
        renderSchedule(data);
        renderNotifications(data);
        renderSystemStatus(data);
        initSparklines(data);
        renderCharts(data);
        renderRecentTab(activeRecentTab, data);

        var mini = document.getElementById('vkDashNotifyMini');
        var list = document.getElementById('vkDashNotifyList');
        if (mini && list) {
            mini.innerHTML = list.innerHTML;
        }
    }

    function renderTimeline(data) {
        var host = document.getElementById('vkDashTimeline');
        if (!host) {
            return;
        }
        var items = [];
        (data.recent_quotations || []).slice(0, 3).forEach(function (q) {
            items.push({
                type: 'Quote',
                label: q.quotation_number,
                sub: q.customer_name,
                time: String(q.created_at || q.quotation_date || '').slice(0, 16),
                href: baseUrl() + '/modules/quotations/view.php?id=' + q.id,
            });
        });
        (data.recent_invoices || []).slice(0, 3).forEach(function (inv) {
            items.push({
                type: 'Invoice',
                label: inv.invoice_number,
                sub: inv.customer_name || '',
                time: String(inv.created_at || inv.invoice_date || '').slice(0, 16),
                href: baseUrl() + '/modules/invoices/view.php?id=' + inv.id,
            });
        });
        (data.recent_web_bookings || []).slice(0, 2).forEach(function (b) {
            items.push({
                type: 'Booking',
                label: b.booking_number,
                sub: b.customer_name,
                time: String(b.created_at || '').slice(0, 16),
                href: baseUrl() + '/modules/bookings/view.php?id=' + b.id,
            });
        });
        if (!items.length) {
            host.innerHTML = '<li class="text-muted small">No recent activity yet.</li>';
            return;
        }
        host.innerHTML = items.slice(0, 8).map(function (it) {
            return '<li><span class="vk-dash-timeline-av" aria-hidden="true">' + esc(it.type.charAt(0)) + '</span><div><a href="' + esc(it.href) + '">' + esc(it.label) + '</a> · ' + esc(it.sub) + '<time datetime="' + esc(it.time) + '">' + esc(it.time) + '</time></div></li>';
        }).join('');
    }

    function renderSchedule(data) {
        var host = document.getElementById('vkDashSchedule');
        if (!host) {
            return;
        }
        var rows = [];
        (data.maint_reminders || []).slice(0, 5).forEach(function (m) {
            rows.push('<a class="vk-dash-schedule-item" href="' + esc(baseUrl() + '/modules/maintenance/list.php') + '"><i class="bi bi-wrench text-warning"></i><div><strong>' + esc(m.contract_number) + '</strong><div class="text-muted">' + esc(m.customer_name) + ' · ' + esc(m.next_service_date) + '</div></div></a>');
        });
        (data.recent_web_bookings || []).slice(0, 3).forEach(function (b) {
            if (String(b.status) === 'cancelled') {
                return;
            }
            rows.push('<a class="vk-dash-schedule-item" href="' + esc(baseUrl() + '/modules/bookings/view.php?id=' + b.id) + '"><i class="bi bi-calendar-check text-primary"></i><div><strong>' + esc(b.booking_number) + '</strong><div class="text-muted">' + esc(b.customer_name) + '</div></div></a>');
        });
        host.innerHTML = rows.length ? rows.join('') : '<p class="small text-muted mb-0">No scheduled items in this window.</p>';
    }

    function renderNotifications(data) {
        var host = document.getElementById('vkDashNotifyList');
        if (!host || !data.stats) {
            return;
        }
        var s = data.stats;
        var notes = [];
        if ((s.quotations_pending || 0) > 0) {
            notes.push({ t: 'Pending quotations', d: s.quotations_pending + ' awaiting approval', href: baseUrl() + '/modules/quotations/approval.php' });
        }
        if ((s.low_stock || 0) > 0) {
            notes.push({ t: 'Low stock alerts', d: s.low_stock + ' products below threshold', href: baseUrl() + '/modules/products/list.php' });
        }
        if ((s.outstanding || 0) > 0) {
            notes.push({ t: 'Outstanding payments', d: '₹' + Number(s.outstanding).toLocaleString() + ' receivable', href: baseUrl() + '/modules/accounts/list.php' });
        }
        if ((s.critical_count || 0) > 0) {
            notes.push({ t: 'Critical alerts', d: s.critical_count + ' items need review', href: baseUrl() + '/modules/bookings/list.php?emergency=1' });
        }
        if ((s.warranty_expiring || 0) > 0) {
            notes.push({ t: 'Warranties expiring', d: s.warranty_expiring + ' expiring soon', href: baseUrl() + '/modules/warranties/list.php?filter=expiring' });
        }
        if ((s.pending_jobs || 0) > 0) {
            notes.push({ t: 'Pending jobs', d: s.pending_jobs + ' jobs in pipeline', href: baseUrl() + '/modules/repairs/list.php' });
        }
        if (data.smtp_warning) {
            notes.push({ t: 'Email system', d: 'SMTP configuration needs attention', href: baseUrl() + '/modules/settings/index.php#pane-mail' });
        }
        host.innerHTML = notes.length
            ? notes.map(function (n) {
                return '<div class="vk-dash-notify-item"><strong>' + esc(n.t) + '</strong><div class="text-muted small">' + esc(n.d) + '</div><a href="' + esc(n.href) + '">View</a></div>';
            }).join('')
            : '<p class="small text-muted">All clear — no pending alerts.</p>';
    }

    function renderSystemStatus(data) {
        var host = document.getElementById('vkDashSystemStatus');
        if (!host) {
            return;
        }
        var ok = data && data.ok;
        var dbOk = !data.schema_needs_v3;
        var mailWarn = !!data.smtp_warning;
        var rows = [
            { label: 'Application', state: ok ? 'ok' : 'bad', text: ok ? 'Healthy' : 'Degraded' },
            { label: 'Database', state: dbOk ? 'ok' : 'warn', text: dbOk ? 'Connected' : 'Schema update needed' },
            { label: 'API / Stats', state: ok ? 'ok' : 'bad', text: ok ? 'Online' : 'Offline' },
            { label: 'Mail queue', state: mailWarn ? 'warn' : 'ok', text: mailWarn ? 'Needs config' : 'Ready' },
            { label: 'WhatsApp CRM', state: 'ok', text: 'Module active' },
            { label: 'Backup', state: 'ok', text: 'Manual via settings' },
        ];
        host.innerHTML = rows.map(function (r) {
            return '<div class="vk-dash-status-row"><span><span class="vk-dash-status-dot is-' + (r.state === 'ok' ? 'ok' : r.state === 'warn' ? 'warn' : 'bad') + '"></span> ' + esc(r.label) + '</span><span class="text-muted">' + esc(r.text) + '</span></div>';
        }).join('');
    }

    function initGlobalSearch() {
        var input = document.getElementById('vkDashGlobalSearch');
        var form = document.getElementById('vkDashSearchForm');
        var scope = document.getElementById('vkDashSearchScope');
        if (!form || !input) {
            return;
        }
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var q = input.value.trim();
            if (!q) {
                return;
            }
            var key = scope ? scope.value : 'customers';
            var path = searchRoutes[key] || searchRoutes.customers;
            window.location.href = baseUrl() + path + encodeURIComponent(q);
        });
    }

    function initNotifications() {
        var btn = document.getElementById('vkDashNotifyBtn');
        var panel = document.getElementById('vkDashNotifyPanel');
        var backdrop = document.getElementById('vkDashNotifyBackdrop');
        var close = document.getElementById('vkDashNotifyClose');
        function open() {
            if (panel) panel.classList.add('is-open');
            if (backdrop) backdrop.classList.add('is-open');
        }
        function shut() {
            if (panel) panel.classList.remove('is-open');
            if (backdrop) backdrop.classList.remove('is-open');
        }
        if (btn) btn.addEventListener('click', open);
        if (close) close.addEventListener('click', shut);
        if (backdrop) backdrop.addEventListener('click', shut);
    }

    function initWidgets() {
        var container = document.getElementById('vkDashWidgetsCol');
        if (!container) {
            return;
        }
        var widgets = Array.prototype.slice.call(container.querySelectorAll('.vk-dash-widget[data-widget-id]'));
        var store = {};
        try {
            store = JSON.parse(localStorage.getItem(widgetStoreKey) || '{}') || {};
        } catch (e) {
            store = {};
        }

        widgets.forEach(function (w) {
            var id = w.getAttribute('data-widget-id');
            if (store[id] && store[id].collapsed) {
                w.classList.add('is-collapsed');
            }
            var toggle = w.querySelector('[data-widget-toggle]');
            if (toggle) {
                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    w.classList.toggle('is-collapsed');
                    store[id] = store[id] || {};
                    store[id].collapsed = w.classList.contains('is-collapsed');
                    localStorage.setItem(widgetStoreKey, JSON.stringify(store));
                });
            }
        });

        if (store.order && Array.isArray(store.order)) {
            store.order.forEach(function (id) {
                var el = container.querySelector('[data-widget-id="' + id + '"]');
                if (el) {
                    container.appendChild(el);
                }
            });
        }

        var dragId = null;
        widgets.forEach(function (w) {
            w.setAttribute('draggable', 'true');
            w.addEventListener('dragstart', function () {
                dragId = w.getAttribute('data-widget-id');
                w.classList.add('opacity-50');
            });
            w.addEventListener('dragend', function () {
                w.classList.remove('opacity-50');
                dragId = null;
                var order = Array.prototype.map.call(container.querySelectorAll('.vk-dash-widget[data-widget-id]'), function (el) {
                    return el.getAttribute('data-widget-id');
                });
                store.order = order;
                localStorage.setItem(widgetStoreKey, JSON.stringify(store));
            });
            w.addEventListener('dragover', function (e) {
                e.preventDefault();
            });
            w.addEventListener('drop', function (e) {
                e.preventDefault();
                if (!dragId) {
                    return;
                }
                var dragged = container.querySelector('[data-widget-id="' + dragId + '"]');
                if (dragged && dragged !== w) {
                    container.insertBefore(dragged, w);
                }
            });
        });
    }

    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function (e) {
            if (e.target.matches('input, textarea, select')) {
                return;
            }
            var map = {
                c: baseUrl() + '/modules/customers/add.php',
                i: baseUrl() + '/modules/invoices/create.php',
                r: baseUrl() + '/modules/repairs/add.php',
                m: baseUrl() + '/modules/maintenance/add.php',
                b: baseUrl() + '/modules/bookings/list.php',
            };
            if (e.altKey && map[e.key.toLowerCase()]) {
                e.preventDefault();
                window.location.href = map[e.key.toLowerCase()];
            }
            if (e.key === '/' && document.getElementById('vkDashGlobalSearch')) {
                e.preventDefault();
                document.getElementById('vkDashGlobalSearch').focus();
            }
        });
    }

    function initGreeting() {
        var el = document.getElementById('vkDashGreeting');
        if (el) {
            el.textContent = greeting();
        }
    }

    function pollEnhance() {
        var cached = readCache();
        if (cached) {
            enhanceFromData(cached);
            root.classList.remove('vk-dash-skeleton');
            return;
        }
        var attempts = 0;
        var timer = setInterval(function () {
            attempts++;
            var data = readCache();
            if (data) {
                enhanceFromData(data);
                root.classList.remove('vk-dash-skeleton');
                clearInterval(timer);
            } else if (attempts > 20) {
                clearInterval(timer);
                root.classList.remove('vk-dash-skeleton');
            }
        }, 400);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initGreeting();
        updateClock();
        setInterval(updateClock, 1000);
        initGlobalSearch();
        initNotifications();
        initWidgets();
        initKeyboardShortcuts();
        initRecentTabs();
        animateBars();
        pollEnhance();

        window.addEventListener('vk-dashboard-stats', function (ev) {
            if (ev && ev.detail) {
                enhanceFromData(ev.detail);
                root.classList.remove('vk-dash-skeleton');
            }
        });

        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                bootstrap.Tooltip.getOrCreateInstance(el);
            });
        }
    });
})();
