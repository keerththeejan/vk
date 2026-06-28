(function () {
    "use strict";

    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    document.addEventListener("DOMContentLoaded", function () {
        if (!document.querySelector("[data-vk-admin]")) {
            return;
        }
        var base = window.VK_BASE_URL || "";
        fetch(base + "/api/dashboard_admin_stats.php", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        })
            .then(function (res) {
                return res.ok ? res.json() : null;
            })
            .then(function (data) {
                if (!data || !data.ok || !data.admin) {
                    return;
                }
                var a = data.admin;
                var pending = document.querySelector('[data-vk-admin="pending"]');
                if (pending) pending.textContent = String(a.pending_approvals || 0);
                var summary = document.querySelector('[data-vk-admin="users-summary"]');
                if (summary) summary.textContent = String(a.total_users || 0) + " total users";
                var approved = document.querySelector('[data-vk-admin="approved-suspended"]');
                if (approved) {
                    approved.textContent = String(a.approved_users || 0) + " / " + String(a.suspended_users || 0);
                }
                var badge = document.querySelector('[data-vk-admin="pending-badge"]');
                if (badge) badge.textContent = "New Registrations " + String(a.pending_approvals || 0);

                var regHost = document.querySelector('[data-vk-admin="registrations"]');
                if (regHost) {
                    var regs = a.recent_registrations || [];
                    regHost.innerHTML = regs.length
                        ? regs
                              .map(function (reg) {
                                  return (
                                      '<div class="d-flex justify-content-between gap-3 border-bottom border-light border-opacity-10 py-2">' +
                                      "<div><div class=\"fw-semibold\">" + esc(reg.fullname || reg.username) + "</div>" +
                                      '<div class="small text-muted">' + esc(reg.email) + " · " + esc(reg.department || "No department") + "</div></div>" +
                                      '<span class="vk-status-badge vk-status-' + esc(reg.status) + ' align-self-start">' + esc(reg.status) + "</span></div>"
                                  );
                              })
                              .join("")
                        : '<div class="text-muted">No registrations yet.</div>';
                }

                var logHost = document.querySelector('[data-vk-admin="logins"]');
                if (logHost) {
                    var logs = a.recent_logins || [];
                    logHost.innerHTML = logs.length
                        ? logs
                              .map(function (log) {
                                  var ok = log.status === "success";
                                  return (
                                      '<div class="d-flex justify-content-between gap-3 border-bottom border-light border-opacity-10 py-2">' +
                                      "<div><div class=\"fw-semibold\">" + esc(log.display_name) + "</div>" +
                                      '<div class="small text-muted">' + esc(String(log.created_at || "").slice(0, 16)) + " · " + esc(log.ip_address) + "</div></div>" +
                                      '<span class="badge text-bg-' + (ok ? "success" : "secondary") + ' align-self-start">' + esc(log.status) + "</span></div>"
                                  );
                              })
                              .join("")
                        : '<div class="text-muted">No login activity yet.</div>';
                }
            })
            .catch(function () {
                /* keep placeholders */
            });
    });
})();
