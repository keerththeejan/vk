(function () {
    'use strict';

    const BASE = window.VK_BASE_URL || '';

    function showToast(msg, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type);
        } else {
            alert(msg);
        }
    }

    function toggleTechRow() {
        var role = document.getElementById('vkUserFormRole');
        var wrap = document.getElementById('vkUserFormTechWrap');
        if (!role || !wrap) return;
        wrap.classList.toggle('d-none', role.value !== 'technician');
    }

    function resetModalForAdd() {
        document.getElementById('vkUserModalTitle').textContent = 'Add user';
        document.getElementById('vkUserFormId').value = '0';
        document.getElementById('vkUserFormUsername').value = '';
        document.getElementById('vkUserFormUsername').disabled = false;
        document.getElementById('vkUserFormFullname').value = '';
        document.getElementById('vkUserFormEmail').value = '';
        document.getElementById('vkUserFormPhone').value = '';
        document.getElementById('vkUserFormRole').value = 'staff';
        document.getElementById('vkUserFormStatus').value = 'active';
        document.getElementById('vkUserFormTechnician').value = '';
        document.getElementById('vkUserFormPassword').value = '';
        document.getElementById('vkUserPwdLabel').textContent = 'Password';
        document.getElementById('vkUserPwdHelp').textContent =
            'Minimum 8 characters. Required for new users.';
        document.getElementById('vkUserFormPassword').required = true;
        toggleTechRow();
    }

    document.getElementById('vkUserAddBtn')?.addEventListener('click', resetModalForAdd);

    document.getElementById('vkUserFormRole')?.addEventListener('change', toggleTechRow);

    document.getElementById('vkUserSearch')?.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        document.querySelectorAll('tr.vk-user-row').forEach(function (tr) {
            var hay = (tr.getAttribute('data-vk-search') || '').toLowerCase();
            tr.classList.toggle('d-none', q !== '' && hay.indexOf(q) === -1);
        });
    });

    document.querySelectorAll('.vk-user-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('vkUserModalTitle').textContent = 'Edit user';
            document.getElementById('vkUserFormId').value = btn.getAttribute('data-id') || '0';
            document.getElementById('vkUserFormUsername').value = btn.getAttribute('data-username') || '';
            document.getElementById('vkUserFormFullname').value = btn.getAttribute('data-fullname') || '';
            document.getElementById('vkUserFormEmail').value = btn.getAttribute('data-email') || '';
            document.getElementById('vkUserFormPhone').value = btn.getAttribute('data-phone') || '';
            document.getElementById('vkUserFormRole').value = btn.getAttribute('data-role') || 'staff';
            document.getElementById('vkUserFormStatus').value = btn.getAttribute('data-status') || 'active';
            var tid = btn.getAttribute('data-technician-id') || '';
            document.getElementById('vkUserFormTechnician').value = tid;
            document.getElementById('vkUserFormPassword').value = '';
            document.getElementById('vkUserPwdLabel').textContent = 'New password (optional)';
            document.getElementById('vkUserPwdHelp').textContent =
                'Leave blank to keep the current password. Minimum 8 characters if set.';
            document.getElementById('vkUserFormPassword').required = false;
            toggleTechRow();
            var modal = new bootstrap.Modal(document.getElementById('vkUserModal'));
            modal.show();
        });
    });

    document.getElementById('vkUserFormSave')?.addEventListener('click', async function () {
        var id = parseInt(document.getElementById('vkUserFormId').value, 10) || 0;
        var username = document.getElementById('vkUserFormUsername').value.trim();
        var payload = {
            id: id,
            username: username,
            email: document.getElementById('vkUserFormEmail').value.trim(),
            phone: document.getElementById('vkUserFormPhone').value.trim(),
            fullname: document.getElementById('vkUserFormFullname').value.trim(),
            password: document.getElementById('vkUserFormPassword').value,
            role: document.getElementById('vkUserFormRole').value,
            status: document.getElementById('vkUserFormStatus').value,
            technician_id:
                document.getElementById('vkUserFormRole').value === 'technician'
                    ? document.getElementById('vkUserFormTechnician').value || null
                    : null,
        };
        if (id === 0 && payload.password.length < 8) {
            showToast('Password must be at least 8 characters.', 'danger');
            return;
        }
        try {
            var res = await fetch(BASE + '/api/users_save.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(payload),
            });
            var data = await res.json().catch(function () {
                return {};
            });
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'Save failed');
            }
            bootstrap.Modal.getInstance(document.getElementById('vkUserModal'))?.hide();
            showToast('User saved.', 'success');
            window.location.reload();
        } catch (e) {
            showToast(e.message || 'Save failed', 'danger');
        }
    });

    document.querySelectorAll('.vk-user-deactivate').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var id = parseInt(btn.getAttribute('data-id'), 10);
            var un = btn.getAttribute('data-username') || '';
            if (!id || !window.confirm('Deactivate user "' + un + '"? They will not be able to sign in.')) {
                return;
            }
            try {
                var res = await fetch(BASE + '/api/users_delete.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ id: id }),
                });
                var data = await res.json().catch(function () {
                    return {};
                });
                if (!res.ok || !data.ok) {
                    throw new Error(data.error || 'Request failed');
                }
                showToast('User deactivated.', 'success');
                window.location.reload();
            } catch (e) {
                showToast(e.message || 'Request failed', 'danger');
            }
        });
    });

    toggleTechRow();
})();
