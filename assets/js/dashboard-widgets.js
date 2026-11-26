(function () {
    "use strict";

    var cacheKey = "vk-dash-stats-v1";
    var cacheTtlMs = 20000;
    var inflight = null;

    function baseUrl() {
        return String(window.VK_BASE_URL || "").replace(/\/$/, "");
    }

    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function setText(selector, value) {
        document.querySelectorAll(selector).forEach(function (el) {
            el.textContent = value;
        });
    }

    function setMetric(name, value) {
        setText('[data-vk-metric="' + name + '"]', value);
    }

    function formatNumber(value, decimals) {
        var n = Number(value);
        if (!Number.isFinite(n)) {
            return "0";
        }
        if (decimals) {
            return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        return n.toLocaleString();
    }

    function readCache() {
        try {
            var raw = sessionStorage.getItem(cacheKey);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (!parsed || !parsed.ts || Date.now() - parsed.ts > cacheTtlMs) return null;
            return parsed.data;
        } catch (e) {
            return null;
        }
    }

    function writeCache(data) {
        try {
            sessionStorage.setItem(cacheKey, JSON.stringify({ ts: Date.now(), data: data }));
        } catch (e) {
            /* ignore quota */
        }
    }

    function fetchStats() {
        if (inflight) {
            return inflight;
        }
        var cached = readCache();
        if (cached) {
            return Promise.resolve(cached);
        }
        inflight = fetch(baseUrl() + "/api/dashboard_stats.php", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        })
            .then(function (res) {
                return res.ok ? res.json() : null;
            })
            .then(function (data) {
                inflight = null;
                if (data && data.ok) {
                    writeCache(data);
                }
                return data;
            })
            .catch(function () {
                inflight = null;
                return null;
            });
        return inflight;
    }

    function applyStats(data) {
        if (!data || !data.ok || !data.stats) {
            return;
        }
        var s = data.stats;
        setMetric("system-pulse", s.system_pulse || "All channels stable");
        setMetric("sales-month", formatMoney(s.sales_month));
        setMetric("sales-month-kpi", formatMoney(s.sales_month));
        setMetric("sales-today", formatMoney(s.sales_today));
        setMetric("workload-completion", String(s.workload_completion || 0) + "%");
        setMetric("repair-completion-label", String(s.repair_completion || 0) + "%");
        setMetric("pending-jobs", String(s.pending_jobs || 0) + " active jobs");
        setMetric("pending-jobs-kpi", String(s.pending_jobs || 0));
        setMetric("repair-finished", String((s.repair_completed || 0) + (s.repair_delivered || 0)) + " finished");
        setMetric("critical-count", String(s.critical_count || 0));
        setMetric("total-bookings", String(s.total_bookings || 0));
        setMetric("total-services", String(s.total_services || 0));
        setMetric("completed-jobs", String(s.completed_jobs || 0));
        setMetric("active-technicians", String(s.active_technicians || 0));
        setMetric("repair-pipeline", String(s.repair_pipeline || 0));
        setMetric("repair-done-total", String((s.repair_completed || 0) + (s.repair_delivered || 0)));
        setMetric("repair-completed", String(s.repair_completed || 0));
        setMetric("repair-delivered", String(s.repair_delivered || 0));
        setMetric("cctv-total", String((s.cctv_active || 0) + (s.cctv_done || 0)));
        setMetric("cctv-active", String(s.cctv_active || 0));
        setMetric("cctv-done", String(s.cctv_done || 0));
        setMetric("active-contracts", String(s.active_contracts || 0));
        setMetric("warranty-expiring", String(s.warranty_expiring || 0));
        setMetric("total-customers", String(s.total_customers || 0));
        setMetric("seo-average", String(s.seo_average || 0) + "%");
        setMetric("quotations-total", String(s.quotations_total || 0));
        setMetric("quotations-pending", String(s.quotations_pending || 0));
        setMetric("quotations-approved", String(s.quotations_approved || 0));
        setMetric("quotations-today", String(s.quotations_today || 0));
        setMetric("invoices-total", String(s.invoices_total || 0));
        setMetric("invoices-today", String(s.invoices_today || 0));
        setMetric("outstanding", formatMoney(s.outstanding));
        setMetric("products-total", String(s.products_total || 0));
        setMetric("stock-items", formatNumber(s.stock_items));
        setMetric("low-stock", String(s.low_stock || 0));
        setMetric("suppliers-total", String(s.suppliers_total || 0));
        setMetric("payments-today-count", String(s.payments_today_count || 0));
        setMetric("collections-today", formatMoney(s.collections_today));
        setMetric("today-activities", String(s.today_activities || 0));

        var bar = document.querySelector('[data-vk-metric-bar="repair-completion"]');
        if (bar) {
            bar.style.width = String(s.repair_completion || 0) + "%";
        }

        var pill = document.querySelector(".vk-status-pill");
        if (pill) {
            var hot = (s.critical_count || 0) > 0;
            pill.textContent = hot ? "Review" : "Stable";
            pill.classList.toggle("vk-pill-hot", hot);
            pill.classList.toggle("vk-pill-calm", !hot);
        }

        if (data.marketing) {
            setMetric("marketing-reach", formatNumber(data.marketing.reach));
            setMetric("marketing-campaigns", String(data.marketing.active_campaigns || 0));
            setMetric("marketing-leads", String(data.marketing.leads || 0));
            setMetric("marketing-conversion", String(data.marketing.conversion_rate || 0));
            setMetric("marketing-whatsapp", String(data.marketing.whatsapp_delivery_rate || 0) + "%");
        }

        renderMaintReminders(data.maint_reminders || []);
        renderRecentBookings(data.recent_web_bookings || []);
        renderRecentJobs(data.recent_jobs || []);
        renderEmergency(data.emergency_bookings || [], data.emergency_repairs || []);
        renderSmtpAlerts(data.smtp_warning);
        renderSchemaAlert(data.schema_needs_v3);

        try {
            window.dispatchEvent(new CustomEvent("vk-dashboard-stats", { detail: data }));
        } catch (e) {
            /* ignore */
        }
    }

    function formatMoney(value) {
        if (typeof formatCurrency === "function") {
            return formatCurrency(value);
        }
        var n = Number(value);
        if (!Number.isFinite(n)) {
            n = 0;
        }
        var fixed = n.toFixed(2);
        var parts = fixed.split(".");
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        return "Rs. " + parts.join(".");
    }

    function renderMaintReminders(rows) {
        var tbody = document.querySelector('[data-vk-table="maint-reminders"]');
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No upcoming dates in this window.</td></tr>';
            return;
        }
        var today = new Date().toISOString().slice(0, 10);
        tbody.innerHTML = rows
            .map(function (m) {
                var due = String(m.next_service_date || "") <= today;
                return (
                    '<tr class="' + (due ? "table-warning" : "") + '">' +
                    '<td><code>' + esc(m.contract_number) + "</code><div class=\"small text-muted\">" + esc(m.title) + "</div></td>" +
                    "<td>" + esc(m.customer_name) + "</td>" +
                    '<td><span class="badge text-bg-' + (due ? "warning text-dark" : "secondary") + '">' + esc(m.next_service_date) + "</span></td>" +
                    "</tr>"
                );
            })
            .join("");
    }

    function renderRecentBookings(rows) {
        var tbody = document.querySelector('[data-vk-table="recent-bookings"]');
        var section = document.querySelector('[data-vk-section="recent-bookings"]');
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No bookings yet.</td></tr>';
            if (section) section.classList.remove("d-none");
            return;
        }
        if (section) section.classList.remove("d-none");
        tbody.innerHTML = rows
            .map(function (wb) {
                var view =
                    wb.id
                        ? '<a class="btn btn-sm btn-outline-primary" href="' + esc(baseUrl() + "/modules/bookings/view.php?id=" + wb.id) + '">View</a>'
                        : "";
                return (
                    "<tr>" +
                    "<td><code>" + esc(wb.booking_number) + "</code></td>" +
                    "<td>" + esc(wb.customer_name) + '<div class="small text-muted">' + esc(wb.phone) + "</div></td>" +
                    "<td>" + esc(String(wb.service_type || "").replace(/_/g, " ")) + "</td>" +
                    '<td><span class="badge text-bg-secondary">' + esc(String(wb.status || "").replace(/_/g, " ")) + "</span></td>" +
                    "<td>" + esc(String(wb.created_at || "").slice(0, 16)) + "</td>" +
                    '<td class="text-end">' + view + "</td>" +
                    "</tr>"
                );
            })
            .join("");
    }

    function renderRecentJobs(rows) {
        var tbody = document.querySelector('[data-vk-table="recent-jobs"]');
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No jobs yet.</td></tr>';
            return;
        }
        tbody.innerHTML = rows
            .map(function (r) {
                var isCctv = r.job_type === "cctv";
                var badge = isCctv ? "info" : "secondary";
                var href = isCctv
                    ? baseUrl() + "/modules/cctv/view.php?id=" + r.id
                    : baseUrl() + "/modules/repairs/view.php?id=" + r.id;
                return (
                    "<tr>" +
                    '<td><span class="badge text-bg-' + badge + '">' + esc(String(r.job_type || "").toUpperCase()) + "</span></td>" +
                    "<td><code>" + esc(r.ref) + "</code></td>" +
                    "<td>" + esc(r.customer_name) + "</td>" +
                    '<td><span class="badge text-bg-secondary">' + esc(String(r.status || "").replace(/_/g, " ")) + "</span></td>" +
                    "<td>" + esc(String(r.created_at || "").slice(0, 16)) + "</td>" +
                    '<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="' + esc(href) + '">View</a></td>' +
                    "</tr>"
                );
            })
            .join("");
    }

    function renderEmergency(bookings, repairs) {
        var host = document.getElementById("vkEmergencyPanel");
        if (!host) return;
        var total = bookings.length + repairs.length;
        if (!total) {
            host.classList.add("d-none");
            host.innerHTML = "";
            return;
        }

        function bookingItem(item) {
            return { type: "Emergency booking", ref: item.booking_number, customer: item.customer_name, extra: item.phone,
                href: baseUrl() + "/modules/bookings/view.php?id=" + item.id, icon: "bi-calendar2-check", tone: "is-emergency" };
        }
        function repairItem(item) {
            return { type: "Priority repair", ref: item.job_number, customer: item.customer_name, extra: "",
                href: baseUrl() + "/modules/repairs/view.php?id=" + item.id, icon: "bi-wrench-adjustable", tone: "is-priority" };
        }
        function renderItems(items) {
            return items.map(function (item) {
                var detail = [item.ref, item.customer, item.extra].filter(Boolean).map(esc).join(" · ");
                return '<a class="vk-dash-critical-item ' + item.tone + '" href="' + esc(item.href) + '">' +
                    '<span class="vk-dash-critical-icon"><i class="bi ' + item.icon + '" aria-hidden="true"></i></span>' +
                    '<span class="vk-dash-critical-copy"><strong>' + esc(item.type) + '</strong><small>' + detail + '</small></span>' +
                    '<i class="bi bi-arrow-up-right vk-dash-critical-open" aria-hidden="true"></i></a>';
            }).join("");
        }

        var preview = bookings.slice(0, 3).map(bookingItem).concat(repairs.slice(0, 2).map(repairItem));
        var remaining = bookings.slice(3).map(bookingItem).concat(repairs.slice(2).map(repairItem));
        host.className = "vk-dash-critical-alerts mb-3";
        host.innerHTML =
            '<div class="vk-dash-critical-head"><div class="vk-dash-critical-heading"><span class="vk-dash-critical-heading-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>' +
            '<div><h2>Emergency &amp; high priority</h2><p>' + bookings.length + ' emergency booking' + (bookings.length === 1 ? '' : 's') + ' · ' + repairs.length + ' priority repair' + (repairs.length === 1 ? '' : 's') + '</p></div></div>' +
            '<span class="vk-dash-critical-count">' + total + ' active</span></div>' +
            '<div class="vk-dash-critical-list">' + renderItems(preview) + '</div>' +
            (remaining.length ? '<details class="vk-dash-critical-more"><summary>View all ' + total + ' active items</summary><div class="vk-dash-critical-list">' + renderItems(remaining) + '</div></details>' : '');
    }
    function renderSmtpAlerts(flag) {
        var host = document.getElementById("vkSmtpAlerts");
        if (!host || !flag) return;
        host.classList.remove("d-none");
        if (flag === "unconfigured") {
            host.innerHTML =
                '<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">' +
                "<span><i class=\"bi bi-envelope-exclamation me-2\"></i>Email system not configured.</span>" +
                '<a class="btn btn-sm btn-outline-warning" href="' + esc(baseUrl() + "/modules/settings/index.php#pane-email") + '">Open Email Settings</a></div>';
        } else if (flag === "missing_password") {
            host.innerHTML =
                '<div class="alert alert-info small d-flex flex-wrap justify-content-between align-items-center gap-2">' +
                "<span><i class=\"bi bi-key me-2\"></i>SMTP password is not stored.</span>" +
                '<a class="btn btn-sm btn-outline-primary" href="' + esc(baseUrl() + "/modules/settings/index.php#pane-email") + '">Add password</a></div>';
        }
    }

    function renderSchemaAlert(needs) {
        var host = document.getElementById("vkSchemaAlert");
        if (!host || !needs) return;
        host.classList.remove("d-none");
        host.innerHTML =
            '<div class="alert alert-warning d-flex flex-column flex-md-row align-items-start gap-2 mb-3" role="alert">' +
            "<div><strong>Database update needed.</strong> Import <code>sql/upgrade_v3_maintenance.sql</code> into <code>vk_billing</code>.</div></div>";
    }

    document.addEventListener("DOMContentLoaded", function () {
        if (!document.querySelector("[data-vk-dashboard=\"async\"]")) {
            return;
        }
        var cached = readCache();
        if (cached) {
            applyStats(cached);
        }
        fetchStats().then(function (data) {
            applyStats(data);
            var err = document.getElementById("vkDashLoadError");
            if (!err) {
                return;
            }
            if (data && data.ok) {
                err.classList.add("d-none");
                err.innerHTML = "";
                return;
            }
            if (cached) {
                return;
            }
            err.classList.remove("d-none");
            err.innerHTML = '<div class="alert alert-warning mb-0">Dashboard stats could not be loaded. Refresh the page to try again.</div>';
        });
    });
})();
