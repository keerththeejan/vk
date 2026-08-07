(() => {
    'use strict';

    const app = document.getElementById('approvalUsersApp');
    if (!app) return;

    const apiUrl = app.dataset.apiUrl || '';
    const csrf = app.dataset.csrf || '';
    let roleOptions = [];
    try {
        roleOptions = JSON.parse(app.dataset.roles || '[]');
    } catch {
        roleOptions = [];
    }

    const qs = (sel, root = document) => root.querySelector(sel);
    const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const tbody = qs('#approvalTableBody');
    const loadingOverlay = qs('#approvalLoadingOverlay');
    const bulkButtons = qsa('[data-bulk]');
    const selectAll = qs('#approvalSelectAll');

    let pendingReject = null;
    let pendingConfirm = null;
    let pendingBulkAction = null;

    const userModal = qs('#approvalUserModal') ? new bootstrap.Modal(qs('#approvalUserModal')) : null;
    const rejectModal = qs('#approvalRejectModal') ? new bootstrap.Modal(qs('#approvalRejectModal')) : null;
    const confirmModal = qs('#approvalConfirmModal') ? new bootstrap.Modal(qs('#approvalConfirmModal')) : null;

    function toast(msg, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type);
        }
    }

    function setLoading(on) {
        loadingOverlay?.classList.toggle('d-none', !on);
        qsa('.approval-action-btn, [data-bulk], .approval-role-select').forEach((el) => {
            if (el.matches('[data-bulk]')) {
                el.disabled = on || selectedIds().length === 0;
            } else if (!on) {
                el.disabled = false;
            } else {
                el.disabled = on;
            }
        });
    }

    function selectedIds() {
        return qsa('.approval-row-check:checked', tbody).map((cb) => parseInt(cb.value, 10)).filter(Boolean);
    }

    function updateBulkState() {
        const count = selectedIds().length;
        bulkButtons.forEach((btn) => { btn.disabled = count === 0; });
        if (selectAll) {
            const checks = qsa('.approval-row-check', tbody);
            selectAll.checked = checks.length > 0 && checks.every((c) => c.checked);
            selectAll.indeterminate = count > 0 && count < checks.length;
        }
    }

    async function apiPost(action, payload = {}) {
        const fd = new FormData();
        fd.set('csrf_token', csrf);
        fd.set('action', action);
        Object.entries(payload).forEach(([k, v]) => {
            if (v === undefined || v === null) return;
            if (k === 'user_ids' && Array.isArray(v)) {
                fd.set('user_ids', JSON.stringify(v));
                v.forEach((item) => fd.append('user_ids[]', String(item)));
            } else {
                fd.set(k, String(v));
            }
        });
        const res = await fetch(apiUrl, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf },
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
            throw new Error(data.error || 'Request failed');
        }
        return data;
    }

    async function apiGet(params = {}) {
        const url = new URL(apiUrl, window.location.origin);
        Object.entries(params).forEach(([k, v]) => { if (v !== '' && v != null) url.searchParams.set(k, v); });
        url.searchParams.set('action', params.action || 'list');
        const res = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) throw new Error(data.error || 'Request failed');
        return data;
    }

    function esc(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function statusBadge(status, label) {
        return `<span class="vk-status-badge vk-status-${esc(status)}" data-status="${esc(status)}">${esc(label)}</span>`;
    }

    function roleSelectHtml(currentRole) {
        return roleOptions.map((r) =>
            `<option value="${esc(r.key)}" ${r.key === currentRole ? 'selected' : ''}>${esc(r.label)}</option>`
        ).join('');
    }

    function renderRow(u) {
        const pendingClass = u.status === 'pending' ? 'table-warning-subtle' : '';
        let actions = `<button type="button" class="btn btn-outline-secondary approval-view-btn" data-user-id="${u.id}" title="View details"><i class="bi bi-eye"></i></button>`;
        if (u.can_approve) actions += `<button type="button" class="btn btn-outline-success approval-action-btn" data-action="approve" data-user-id="${u.id}">Approve</button>`;
        if (u.can_reject) actions += `<button type="button" class="btn btn-outline-warning approval-action-btn" data-action="reject" data-user-id="${u.id}">Reject</button>`;
        if (u.can_suspend) actions += `<button type="button" class="btn btn-outline-danger approval-action-btn" data-action="suspend" data-user-id="${u.id}">Suspend</button>`;
        if (u.can_reactivate) actions += `<button type="button" class="btn btn-outline-primary approval-action-btn" data-action="reactivate" data-user-id="${u.id}">Reactivate</button>`;
        actions += `<button type="button" class="btn btn-outline-info approval-action-btn" data-action="reset_password" data-user-id="${u.id}">Reset</button>`;

        return `<tr data-user-id="${u.id}" class="${pendingClass}">
            <td><input type="checkbox" class="form-check-input approval-row-check" value="${u.id}" aria-label="Select user"></td>
            <td><div class="fw-semibold">${esc(u.fullname || 'Unnamed User')}</div>
                <div class="small text-muted"><code>${esc(u.username)}</code> · ${esc(u.user_uid || 'No ID')}</div>
                <div class="small text-muted d-md-none">${esc(u.email || '-')}</div></td>
            <td class="d-none d-md-table-cell"><div>${esc(u.email || '-')}</div><div class="small text-muted">${esc(u.phone || '-')}</div></td>
            <td class="d-none d-lg-table-cell"><select class="form-select form-select-sm approval-role-select" data-user-id="${u.id}" aria-label="Role">${roleSelectHtml(u.role)}</select></td>
            <td>${statusBadge(u.status, u.status_label)}</td>
            <td class="d-none d-xl-table-cell">${esc((u.created_at || '').slice(0, 16))}</td>
            <td class="d-none d-xl-table-cell">${u.last_login_at ? esc(u.last_login_at.slice(0, 16)) : '<span class="text-muted">Never</span>'}</td>
            <td class="text-end"><div class="btn-group btn-group-sm" role="group">${actions}</div></td>
        </tr>`;
    }

    function renderTable(users) {
        if (!tbody) return;
        if (!users.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5">No users match your filters.</td></tr>';
            return;
        }
        tbody.innerHTML = users.map(renderRow).join('');
        bindRowEvents();
    }

    function updateStats(stats) {
        if (!stats) return;
        Object.entries(stats).forEach(([key, val]) => {
            const el = qs(`[data-stat="${key}"]`);
            if (el) el.textContent = String(val);
        });
    }

    function listParams() {
        const form = qs('#approvalFilterForm');
        const fd = form ? new FormData(form) : new FormData();
        const params = Object.fromEntries(fd.entries());
        params.page = new URLSearchParams(window.location.search).get('page') || '1';
        return params;
    }

    async function refreshListFromPage() {
        const data = await apiGet(listParams());
        renderTable(data.users || []);
        updateStats(data.stats);
        const countEl = qs('#approvalResultCount');
        if (countEl) countEl.textContent = String(data.total || 0);
    }

    async function runAction(action, userId, extra = {}) {
        setLoading(true);
        qsa(`[data-user-id="${userId}"].approval-action-btn`).forEach((b) => { b.disabled = true; });
        try {
            const data = await apiPost(action, { user_id: userId, ...extra });
            if (data.reset_password) {
                toast(`Temporary password: ${data.reset_password}`, 'success');
            } else {
                toast(data.message || 'Action completed.', 'success');
            }
            await refreshListFromPage();
        } catch (err) {
            toast(err.message || 'Action failed.', 'danger');
        } finally {
            setLoading(false);
            updateBulkState();
        }
    }

    async function runBulk(action, extra = {}) {
        const ids = selectedIds();
        if (!ids.length) return;
        setLoading(true);
        try {
            const data = await apiPost(action, { user_ids: ids, ...extra });
            toast(data.message || 'Bulk action completed.', 'success');
            if (selectAll) selectAll.checked = false;
            await refreshListFromPage();
        } catch (err) {
            toast(err.message || 'Bulk action failed.', 'danger');
        } finally {
            setLoading(false);
            updateBulkState();
        }
    }

    function showUserModal(userId) {
        const body = qs('#approvalUserModalBody');
        if (!body || !userModal) return;
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        userModal.show();
        apiGet({ action: 'user', id: String(userId) }).then((data) => {
            const u = data.user;
            const initials = (u.fullname || u.username || '?').trim().split(/\s+/).map((w) => w[0]).join('').slice(0, 2).toUpperCase();
            body.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-4 text-center">
                        <div class="approval-avatar mx-auto mb-2">${esc(initials)}</div>
                        ${statusBadge(u.status, u.status_label || u.status)}
                    </div>
                    <div class="col-md-8">
                        <dl class="row small mb-0 approval-detail-list">
                            <dt class="col-5">Full Name</dt><dd class="col-7">${esc(u.fullname || '-')}</dd>
                            <dt class="col-5">Username</dt><dd class="col-7"><code>${esc(u.username)}</code></dd>
                            <dt class="col-5">Email</dt><dd class="col-7">${esc(u.email || '-')}</dd>
                            <dt class="col-5">Phone</dt><dd class="col-7">${esc(u.phone || '-')}</dd>
                            <dt class="col-5">Role</dt><dd class="col-7">${esc(u.role)}</dd>
                            <dt class="col-5">Department</dt><dd class="col-7">${esc(u.department || '-')}</dd>
                            <dt class="col-5">Registration</dt><dd class="col-7">${esc(u.created_at || '-')}</dd>
                            <dt class="col-5">Last Login</dt><dd class="col-7">${esc(u.last_login_at || 'Never')}</dd>
                            <dt class="col-5">Status</dt><dd class="col-7">${esc(u.status)}</dd>
                            <dt class="col-5">Created By</dt><dd class="col-7">${esc(u.created_by || 'Self-registration')}</dd>
                            <dt class="col-5">Verification</dt><dd class="col-7">${esc(u.verification_status || '-')}</dd>
                            <dt class="col-5">Approved By</dt><dd class="col-7">${esc(u.approved_by_name || '-')}</dd>
                        </dl>
                    </div>
                </div>`;
        }).catch((err) => {
            body.innerHTML = `<div class="alert alert-danger mb-0">${esc(err.message)}</div>`;
        });
    }

    function bindRowEvents() {
        qsa('.approval-row-check', tbody).forEach((cb) => cb.addEventListener('change', updateBulkState));
    }

    app.addEventListener('click', (e) => {
        const viewBtn = e.target.closest('.approval-view-btn');
        if (viewBtn) {
            showUserModal(parseInt(viewBtn.dataset.userId, 10));
            return;
        }

        const actionBtn = e.target.closest('.approval-action-btn');
        if (actionBtn) {
            const action = actionBtn.dataset.action;
            const userId = parseInt(actionBtn.dataset.userId, 10);
            if (!userId) return;

            if (action === 'reject') {
                pendingReject = { userId, bulk: false };
                if (qs('#rejectionReason')) qs('#rejectionReason').value = '';
                rejectModal?.show();
                return;
            }
            if (action === 'suspend' || action === 'reset_password') {
                pendingConfirm = { action, userId };
                qs('#approvalConfirmTitle').textContent = action === 'suspend' ? 'Confirm Suspend' : 'Confirm Password Reset';
                qs('#approvalConfirmBody').textContent = action === 'suspend'
                    ? 'Suspend this user? They will lose access immediately.'
                    : 'Generate a new temporary password for this user?';
                confirmModal?.show();
                return;
            }
            if (action === 'approve') {
                actionBtn.disabled = true;
                runAction('approve', userId);
                return;
            }
            if (action === 'reactivate') {
                runAction('reactivate', userId);
            }
        }
    });

    qs('#approvalRejectConfirm')?.addEventListener('click', () => {
        const reason = qs('#rejectionReason')?.value?.trim() || '';
        rejectModal?.hide();
        if (pendingReject?.bulk) {
            runBulk('bulk_reject', { rejection_reason: reason, note: reason });
        } else if (pendingReject?.userId) {
            runAction('reject', pendingReject.userId, { rejection_reason: reason, note: reason });
        }
        pendingReject = null;
    });

    qs('#approvalConfirmBtn')?.addEventListener('click', () => {
        confirmModal?.hide();
        if (pendingConfirm) {
            runAction(pendingConfirm.action, pendingConfirm.userId);
            pendingConfirm = null;
        } else if (pendingBulkAction) {
            runBulk(pendingBulkAction);
            pendingBulkAction = null;
        }
    });

    bulkButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const action = btn.dataset.bulk;
            if (!selectedIds().length) return;
            if (action === 'bulk_reject') {
                pendingReject = { bulk: true };
                if (qs('#rejectionReason')) qs('#rejectionReason').value = '';
                rejectModal?.show();
                return;
            }
            pendingBulkAction = action;
            qs('#approvalConfirmTitle').textContent = 'Confirm Bulk Action';
            qs('#approvalConfirmBody').textContent = `Apply "${btn.textContent.trim()}" to ${selectedIds().length} selected user(s)?`;
            confirmModal?.show();
        });
    });

    selectAll?.addEventListener('change', () => {
        qsa('.approval-row-check', tbody).forEach((cb) => { cb.checked = selectAll.checked; });
        updateBulkState();
    });

    tbody?.addEventListener('change', async (e) => {
        const sel = e.target.closest('.approval-role-select');
        if (!sel) return;
        const userId = parseInt(sel.dataset.userId, 10);
        const role = sel.value;
        setLoading(true);
        try {
            await apiPost('role', { user_id: userId, role });
            toast('Role updated.', 'success');
        } catch (err) {
            toast(err.message || 'Role update failed.', 'danger');
        } finally {
            setLoading(false);
        }
    });

    let searchTimer;
    qs('#approvalSearch')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            refreshListFromPage().catch((err) => toast(err.message, 'danger'));
        }, 350);
    });

    ['#filterStatus', '#filterRole', '#filterSort', '#filterDateFrom', '#filterDateTo'].forEach((sel) => {
        qs(sel)?.addEventListener('change', () => qs('#approvalFilterForm')?.requestSubmit());
    });

    qs('#approvalPrintBtn')?.addEventListener('click', () => window.print());

    qs('#approvalFilterForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const params = new URLSearchParams(fd);
        window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
        setLoading(true);
        apiGet(Object.fromEntries(fd.entries())).then((data) => {
            renderTable(data.users || []);
            updateStats(data.stats);
            const countEl = qs('#approvalResultCount');
            if (countEl) countEl.textContent = String(data.total || 0);
        }).catch((err) => toast(err.message, 'danger')).finally(() => setLoading(false));
    });

    bindRowEvents();
    updateBulkState();
})();
