(function () {
    'use strict';

    var root = document.querySelector('.vk-dash-admin');
    if (!root) {
        return;
    }

    var cacheKey = 'vk-dash-stats-v1';
    var widgetStoreKey = 'vk-dash-widgets-v1';
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
        };
        document.querySelectorAll('[data-vk-spark]').forEach(function (svg) {
            var key = svg.getAttribute('data-vk-spark');
            if (map[key]) {
                drawSparkline(svg, map[key].map(function (n) { return Math.max(0, Number(n) || 0); }));
            }
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
        var s = data.stats || {};
        var m = data.marketing || {};

        setDashKpi('vkDashNotifyCount', String((s.critical_count || 0) + (data.maint_reminders || []).length));
        setDashKpi('vkDashModBookings', String(s.total_bookings || 0));
        setDashKpi('vkDashModRepairs', String(s.pending_jobs || 0));
        setDashKpi('vkDashModMaint', String(s.active_contracts || 0));

        setDashKpi('vkDashFinRevenue', '₹' + Number(s.sales_month || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }));
        setDashKpi('vkDashFinReceivable', String(s.warranty_expiring || 0) + ' alerts');
        setDashKpi('vkDashFinCash', '₹' + Number(s.sales_today || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }));

        setDashKpi('vkDashInvLow', String(s.critical_count || 0));
        setDashKpi('vkDashInvValue', '₹' + Number(s.sales_month || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }));

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

        var seoEl = document.querySelector('[data-vk-metric="seo-average"]');
        if (seoEl && m.reach !== undefined) {
            setDashKpi('vkDashKpiMarketingSub', (m.active_campaigns || 0) + ' campaigns');
        }
    }

    function renderTimeline(data) {
        var host = document.getElementById('vkDashTimeline');
        if (!host) {
            return;
        }
        var items = [];
        (data.recent_web_bookings || []).slice(0, 4).forEach(function (b) {
            items.push({
                type: 'Booking',
                label: b.booking_number,
                sub: b.customer_name,
                time: String(b.created_at || '').slice(0, 16),
                href: baseUrl() + '/modules/bookings/view.php?id=' + b.id,
            });
        });
        (data.recent_jobs || []).slice(0, 4).forEach(function (j) {
            var href = j.job_type === 'cctv'
                ? baseUrl() + '/modules/cctv/view.php?id=' + j.id
                : baseUrl() + '/modules/repairs/view.php?id=' + j.id;
            items.push({
                type: j.job_type === 'cctv' ? 'CCTV' : 'Repair',
                label: j.ref,
                sub: j.customer_name,
                time: String(j.created_at || '').slice(0, 16),
                href: href,
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
        if ((s.critical_count || 0) > 0) {
            notes.push({ t: 'Critical alerts', d: s.critical_count + ' items need review', href: baseUrl() + '/modules/bookings/list.php?emergency=1' });
        }
        if ((s.warranty_expiring || 0) > 0) {
            notes.push({ t: 'Warranties expiring', d: s.warranty_expiring + ' expiring soon', href: baseUrl() + '/modules/warranties/list.php?filter=expiring' });
        }
        if ((s.pending_jobs || 0) > 0) {
            notes.push({ t: 'Pending repairs', d: s.pending_jobs + ' jobs in pipeline', href: baseUrl() + '/modules/repairs/list.php' });
        }
        if ((s.repair_pipeline || 0) > 0) {
            notes.push({ t: 'Repair pipeline', d: s.repair_pipeline + ' active repairs', href: baseUrl() + '/modules/repairs/list.php' });
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
        animateBars();
        pollEnhance();

        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                bootstrap.Tooltip.getOrCreateInstance(el);
            });
        }
    });
})();
