(() => {
    'use strict';

    const app = document.getElementById('usersManagementApp');
    if (!app) return;

    const apiUrl = app.dataset.apiUrl || '';
    const csrf = app.dataset.csrf || '';
    const canManage = app.dataset.canManage === '1';
    let roleOptions = [];
    try { roleOptions = JSON.parse(app.dataset.roles || '[]'); } catch { roleOptions = []; }

    const qs = (s, r = document) => r.querySelector(s);
    const qsa = (s, r = document) => Array.from(r.querySelectorAll(s));

    const tbody = qs('#umTableBody');
    const loading = qs('#umLoading');

    const userModal = qs('#umUserModal') ? new bootstrap.Modal(qs('#umUserModal')) : null;
    const viewModal = qs('#umViewModal') ? new bootstrap.Modal(qs('#umViewModal')) : null;
    const confirmModal = qs('#umConfirmModal') ? new bootstrap.Modal(qs('#umConfirmModal')) : null;

    let pendingConfirm = null;

    function toast(msg, type = 'info') {
        if (typeof window.showToast === 'function') window.showToast(msg, type);
    }

    function esc(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function setLoading(on) {
        loading?.classList.toggle('d-none', !on);
        qsa('.um-action-btn, .um-edit-btn, .um-delete-btn, .um-bulk-btn, #umFormSave').forEach((el) => {
            el.disabled = on || (el.matches('.um-bulk-btn') && selectedIds().length === 0);
        });
    }

    function selectedIds() {
        return qsa('.um-row-check:checked', tbody).map((c) => parseInt(c.value, 10)).filter(Boolean);
    }

    function updateBulk() {
        const n = selectedIds().length;
        qsa('.um-bulk-btn').forEach((b) => { b.disabled = n === 0; });
        const all = qs('#umSelectAll');
        if (all) {
            const boxes = qsa('.um-row-check', tbody);
            all.checked = boxes.length > 0 && boxes.every((c) => c.checked);
            all.indeterminate = n > 0 && n < boxes.length;
        }
    }

    async function api(method, body = null, query = {}) {
        const url = new URL(apiUrl, window.location.origin);
        if (method === 'GET' && !query.action) {
            query.action = query.id ? 'user' : 'list';
        }
        Object.entries(query).forEach(([k, v]) => { if (v != null && v !== '') url.searchParams.set(k, v); });
        const opts = { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': csrf } };
        if (body) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify({ ...body, csrf_token: csrf });
        }
        const res = await fetch(url.toString(), opts);
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) throw new Error(data.error || 'Request failed');
        return data;
    }

    function statusBadge(status, label) {
        return `<span class="badge um-status um-status-${esc(status)}">${esc(label)}</span>`;
    }

    function renderRow(u) {
        const check = canManage ? `<td><input type="checkbox" class="form-check-input um-row-check" value="${u.id}"></td>` : '';
        let actions = `<button type="button" class="btn btn-outline-secondary um-view-btn" data-id="${u.id}"><i class="bi bi-eye"></i></button>`;
        if (u.can_edit) actions += `<button type="button" class="btn btn-outline-primary um-edit-btn" data-id="${u.id}"><i class="bi bi-pencil"></i></button>`;
        if (u.can_approve) actions += `<button type="button" class="btn btn-outline-success um-action-btn" data-action="approve" data-id="${u.id}"><i class="bi bi-check-lg"></i></button>`;
        if (u.can_reject) actions += `<button type="button" class="btn btn-outline-warning um-action-btn" data-action="reject" data-id="${u.id}"><i class="bi bi-x-lg"></i></button>`;
        if (u.can_delete) actions += `<button type="button" class="btn btn-outline-danger um-delete-btn" data-id="${u.id}" data-name="${esc(u.username)}"><i class="bi bi-trash"></i></button>`;

        return `<tr data-user-id="${u.id}" class="${u.status === 'pending' ? 'um-row-pending' : ''}">
            ${check}
            <td><div class="d-flex align-items-center gap-2"><span class="um-avatar">${esc(u.initials)}</span>
                <div><div class="fw-semibold">${esc(u.fullname || 'Unnamed')}</div><div class="small text-muted"><code>${esc(u.username)}</code></div></div></div></td>
            <td class="d-none d-md-table-cell">${esc(u.email || '—')}</td>
            <td class="d-none d-lg-table-cell">${esc(u.phone || '—')}</td>
            <td><span class="badge um-role-badge">${esc(u.role_label)}</span></td>
            <td class="d-none d-xl-table-cell">${esc(u.department || '—')}</td>
            <td>${statusBadge(u.status, u.status_label)}</td>
            <td class="d-none d-lg-table-cell">${esc((u.created_at || '').slice(0, 16))}</td>
            <td class="d-none d-xl-table-cell">${u.last_login_at ? esc(u.last_login_at.slice(0, 16)) : '<span class="text-muted">Never</span>'}</td>
            <td class="text-end"><div class="btn-group btn-group-sm">${actions}</div></td>
        </tr>`;
    }

    function renderTable(users) {
        if (!tbody) return;
        const cols = canManage ? 10 : 9;
        if (!users.length) {
            tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-muted py-5">No users found.</td></tr>`;
            return;
        }
        tbody.innerHTML = users.map(renderRow).join('');
        qsa('.um-row-check', tbody).forEach((c) => c.addEventListener('change', updateBulk));
        updateBulk();
    }

    function listParams() {
        const fd = new FormData(qs('#umFilterForm'));
        return Object.fromEntries(fd.entries());
    }

    async function refreshList() {
        setLoading(true);
        try {
            const data = await api('GET', null, listParams());
            renderTable(data.users || []);
            Object.entries(data.stats || {}).forEach(([k, v]) => {
                const el = qs(`[data-stat="${k}"]`);
                if (el) el.textContent = String(v);
            });
        } finally {
            setLoading(false);
        }
    }

    function toggleTech() {
        const role = qs('#umFormRole')?.value;
        qs('#umFormTechWrap')?.classList.toggle('d-none', role !== 'technician');
    }

    function resetAddForm() {
        qs('#umUserModalTitle').textContent = 'Add User';
        qs('#umFormId').value = '0';
        qs('#umFormUsername').value = '';
        qs('#umFormUsername').disabled = false;
        qs('#umFormFullname').value = '';
        qs('#umFormEmail').value = '';
        qs('#umFormPhone').value = '';
        qs('#umFormDepartment').value = '';
        qs('#umFormRole').value = 'staff';
        qs('#umFormStatus').value = 'approved';
        qs('#umFormTechnician').value = '';
        qs('#umFormPassword').value = '';
        qs('#umPwdLabel').textContent = 'Password';
        qs('#umPwdHelp').textContent = 'Minimum 8 characters with letters and numbers.';
        qs('#umFormPassword').required = true;
        toggleTech();
    }

    async function loadUserToForm(id) {
        const data = await api('GET', null, { action: 'user', id: String(id) });
        const u = data.user;
        qs('#umUserModalTitle').textContent = 'Edit User';
        qs('#umFormId').value = String(u.id);
        qs('#umFormUsername').value = u.username || '';
        qs('#umFormFullname').value = u.fullname || '';
        qs('#umFormEmail').value = u.email || '';
        qs('#umFormPhone').value = u.phone || '';
        qs('#umFormDepartment').value = u.department || '';
        qs('#umFormRole').value = u.role || 'staff';
        qs('#umFormStatus').value = u.status || 'approved';
        qs('#umFormTechnician').value = u.technician_id || '';
        qs('#umFormPassword').value = '';
        qs('#umFormPassword').required = false;
        qs('#umPwdLabel').textContent = 'New password (optional)';
        toggleTech();
        userModal?.show();
    }

    async function showView(id) {
        const body = qs('#umViewBody');
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border"></div></div>';
        viewModal?.show();
        try {
            const data = await api('GET', null, { action: 'user', id: String(id) });
            const u = data.user;
            const acts = (u.recent_activity || []).map((a) => `<li class="small text-muted">${esc(a.action)} · ${esc(a.created_at)}</li>`).join('') || '<li class="small text-muted">No activity</li>';
            body.innerHTML = `
                <div class="text-center"><div class="um-view-avatar">${esc(u.initials || '?')}</div>${statusBadge(u.status, u.status_label)}</div>
                <dl class="row small mt-3 mb-0">
                    <dt class="col-5">Full Name</dt><dd class="col-7">${esc(u.fullname || '—')}</dd>
                    <dt class="col-5">Username</dt><dd class="col-7"><code>${esc(u.username)}</code></dd>
                    <dt class="col-5">Email</dt><dd class="col-7">${esc(u.email || '—')}</dd>
                    <dt class="col-5">Phone</dt><dd class="col-7">${esc(u.phone || '—')}</dd>
                    <dt class="col-5">Role</dt><dd class="col-7">${esc(u.role_label || u.role)}</dd>
                    <dt class="col-5">Department</dt><dd class="col-7">${esc(u.department || '—')}</dd>
                    <dt class="col-5">Status</dt><dd class="col-7">${esc(u.status)}</dd>
                    <dt class="col-5">Registered</dt><dd class="col-7">${esc(u.created_at || '—')}</dd>
                    <dt class="col-5">Last Login</dt><dd class="col-7">${esc(u.last_login_at || 'Never')}</dd>
                    <dt class="col-5">Approved By</dt><dd class="col-7">${esc(u.approved_by_name || '—')}</dd>
                    <dt class="col-5">Approved At</dt><dd class="col-7">${esc(u.approved_at || '—')}</dd>
                </dl>
                <h3 class="h6 mt-3">Recent Activity</h3><ul class="list-unstyled mb-0">${acts}</ul>`;
        } catch (e) {
            body.innerHTML = `<div class="alert alert-danger">${esc(e.message)}</div>`;
        }
    }

    qs('#umAddUserBtn')?.addEventListener('click', () => { resetAddForm(); userModal?.show(); });
    qs('#umFormRole')?.addEventListener('change', toggleTech);

    qs('#umGenPassword')?.addEventListener('click', async () => {
        try {
            const data = await api('GET', null, { action: 'generate_password' });
            qs('#umFormPassword').value = data.password || '';
        } catch (e) { toast(e.message, 'danger'); }
    });

    qs('#umFormSave')?.addEventListener('click', async () => {
        const id = parseInt(qs('#umFormId').value, 10) || 0;
        const payload = {
            action: 'save',
            id,
            username: qs('#umFormUsername').value.trim(),
            fullname: qs('#umFormFullname').value.trim(),
            email: qs('#umFormEmail').value.trim(),
            phone: qs('#umFormPhone').value.trim(),
            department: qs('#umFormDepartment').value.trim(),
            role: qs('#umFormRole').value,
            status: qs('#umFormStatus').value,
            password: qs('#umFormPassword').value,
            technician_id: qs('#umFormRole').value === 'technician' ? qs('#umFormTechnician').value : null,
        };
        if (!payload.username) { toast('Username is required.', 'danger'); return; }
        setLoading(true);
        try {
            const data = await api('POST', payload);
            userModal?.hide();
            if (data.generated_password) toast(`User created. Password: ${data.generated_password}`, 'success');
            else toast(data.message || 'User saved.', 'success');
            await refreshList();
        } catch (e) { toast(e.message, 'danger'); }
        finally { setLoading(false); }
    });

    app.addEventListener('click', async (e) => {
        const view = e.target.closest('.um-view-btn');
        if (view) { showView(parseInt(view.dataset.id, 10)); return; }

        const edit = e.target.closest('.um-edit-btn');
        if (edit) { loadUserToForm(parseInt(edit.dataset.id, 10)).catch((err) => toast(err.message, 'danger')); return; }

        const act = e.target.closest('.um-action-btn');
        if (act) {
            const action = act.dataset.action;
            const id = parseInt(act.dataset.id, 10);
            if (action === 'reject') {
                pendingConfirm = { action, id };
                qs('#umConfirmTitle').textContent = 'Reject User';
                qs('#umConfirmBody').textContent = 'Reject this user registration?';
                confirmModal?.show();
                return;
            }
            setLoading(true);
            try {
                await api('POST', { action, id });
                toast(action === 'approve' ? 'User approved successfully.' : 'Action completed.', 'success');
                await refreshList();
            } catch (err) { toast(err.message, 'danger'); }
            finally { setLoading(false); }
            return;
        }

        const del = e.target.closest('.um-delete-btn');
        if (del) {
            pendingConfirm = { action: 'delete', id: parseInt(del.dataset.id, 10), name: del.dataset.name };
            qs('#umConfirmTitle').textContent = 'Deactivate User';
            qs('#umConfirmBody').textContent = `Soft-delete user "${del.dataset.name}"? They will not be able to sign in.`;
            confirmModal?.show();
        }
    });

    qs('#umConfirmBtn')?.addEventListener('click', async () => {
        confirmModal?.hide();
        if (!pendingConfirm) return;
        setLoading(true);
        try {
            if (pendingConfirm.user_ids) {
                await api('POST', { action: pendingConfirm.action, user_ids: pendingConfirm.user_ids });
                toast('Bulk action completed.', 'success');
                if (qs('#umSelectAll')) qs('#umSelectAll').checked = false;
            } else if (pendingConfirm.action === 'delete') {
                await api('POST', { action: 'delete', id: pendingConfirm.id });
                toast('User deactivated successfully.', 'success');
            } else {
                await api('POST', { action: pendingConfirm.action, id: pendingConfirm.id });
                toast(pendingConfirm.action === 'approve' ? 'User approved successfully.' : 'Action completed.', 'success');
            }
            await refreshList();
        } catch (e) {
            toast(e.message, 'danger');
        } finally {
            setLoading(false);
            pendingConfirm = null;
        }
    });
        btn.addEventListener('click', () => {
            const action = btn.dataset.bulk;
            const ids = selectedIds();
            if (!ids.length) return;
            if (action === 'bulk_export') {
                api('POST', { action, user_ids: ids }).then((data) => {
                    const rows = data.export || [];
                    const header = ['Name', 'Username', 'Email', 'Phone', 'Role', 'Status', 'Department'];
                    const csv = [header.join(',')].concat(rows.map((u) =>
                        [u.fullname, u.username, u.email, u.phone, u.role, u.status, u.department].map((v) => `"${String(v || '').replace(/"/g, '""')}"`).join(',')
                    )).join('\n');
                    const blob = new Blob([csv], { type: 'text/csv' });
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'users-export.csv';
                    a.click();
                    toast('Export downloaded.', 'success');
                }).catch((e) => toast(e.message, 'danger'));
                return;
            }
            pendingConfirm = { action, user_ids: ids };
            qs('#umConfirmTitle').textContent = 'Confirm Bulk Action';
            qs('#umConfirmBody').textContent = `Apply to ${ids.length} selected user(s)?`;
            confirmModal?.show();
        });
    });

    qs('#umSelectAll')?.addEventListener('change', (e) => {
        qsa('.um-row-check', tbody).forEach((c) => { c.checked = e.target.checked; });
        updateBulk();
    });

    let searchTimer;
    qs('#umSearch')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => refreshList().catch(() => {}), 350);
    });

    qs('#umFilterForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const perPage = fd.get('per_page');
        if (perPage) document.cookie = `vk_users_per_page=${perPage};path=/;max-age=31536000;SameSite=Lax`;
        window.history.replaceState({}, '', `${window.location.pathname}?${new URLSearchParams(fd).toString()}`);
        refreshList().catch((err) => toast(err.message, 'danger'));
    });

    ['#umFilterRole', '#umFilterStatus', '#umFilterFrom', '#umFilterTo', '#umPerPage'].forEach((sel) => {
        qs(sel)?.addEventListener('change', () => qs('#umFilterForm')?.requestSubmit());
    });

    qsa('.um-sortable').forEach((th) => {
        th.addEventListener('click', () => {
            const field = th.dataset.sort;
            const cur = qs('#umSortField').value;
            const dir = qs('#umSortDir').value;
            qs('#umSortField').value = field;
            qs('#umSortDir').value = cur === field && dir === 'desc' ? 'asc' : 'desc';
            qs('#umFilterForm')?.requestSubmit();
        });
    });

    qsa('.um-row-check', tbody).forEach((c) => c.addEventListener('change', updateBulk));
    updateBulk();
    if (canManage) toggleTech();
})();
