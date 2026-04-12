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

    function postJson(path, body) {
        return fetch(BASE + path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) {
                    var err = (data && data.error) || r.statusText || 'Request failed';
                    throw new Error(err);
                }
                return data;
            });
        });
    }

    function rowById(id) {
        return document.querySelector('.vk-menu-admin-row[data-id="' + id + '"]');
    }

    function collectOrder() {
        var ids = [];
        document.querySelectorAll('#vkMenuAdminList .vk-menu-admin-row').forEach(function (li) {
            var id = parseInt(li.getAttribute('data-id') || '0', 10);
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function sendReorder() {
        var order = collectOrder();
        if (!order.length) {
            return;
        }
        postJson('/api/menus_reorder.php', { order: order })
            .then(function () {
                showToast('Order saved', 'success');
            })
            .catch(function (e) {
                showToast(e.message || 'Reorder failed', 'danger');
            });
    }

    var listEl = document.getElementById('vkMenuAdminList');
    if (listEl && typeof Sortable !== 'undefined') {
        new Sortable(listEl, {
            animation: 150,
            handle: '.vk-menu-drag',
            onEnd: function () {
                sendReorder();
            },
        });
    }

    function slugify(s) {
        return s
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 100);
    }

    document.getElementById('vkMenuAddBtn')?.addEventListener('click', function () {
        document.getElementById('vkMenuModalTitle').textContent = 'Add menu';
        document.getElementById('vkMenuFormId').value = '0';
        document.getElementById('vkMenuFormName').value = '';
        document.getElementById('vkMenuFormSlug').value = '';
        document.getElementById('vkMenuFormUrl').value = '';
        document.getElementById('vkMenuFormIcon').value = '';
        document.getElementById('vkMenuFormStatus').value = 'active';
    });

    var slugTouched = false;
    document.getElementById('vkMenuFormSlug')?.addEventListener('input', function () {
        slugTouched = true;
    });
    document.getElementById('vkMenuFormName')?.addEventListener('input', function () {
        if (!slugTouched) {
            document.getElementById('vkMenuFormSlug').value = slugify(this.value);
        }
    });
    document.getElementById('vkMenuModal')?.addEventListener('show.bs.modal', function (ev) {
        var btn = ev.relatedTarget;
        if (btn && btn.classList.contains('vk-menu-edit')) {
            slugTouched = true;
        } else {
            slugTouched = false;
        }
    });

    document.querySelectorAll('.vk-menu-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('vkMenuModalTitle').textContent = 'Edit menu';
            document.getElementById('vkMenuFormId').value = btn.getAttribute('data-id') || '0';
            document.getElementById('vkMenuFormName').value = btn.getAttribute('data-name') || '';
            document.getElementById('vkMenuFormSlug').value = btn.getAttribute('data-slug') || '';
            document.getElementById('vkMenuFormUrl').value = btn.getAttribute('data-url') || '';
            document.getElementById('vkMenuFormIcon').value = btn.getAttribute('data-icon') || '';
            document.getElementById('vkMenuFormStatus').value = btn.getAttribute('data-status') || 'active';
        });
    });

    function syncRowFromForm(id, payload) {
        var row = rowById(id);
        if (!row) {
            location.reload();
            return;
        }
        row.setAttribute('data-name', payload.name);
        row.setAttribute('data-slug', payload.slug);
        row.setAttribute('data-url', payload.url);
        row.setAttribute('data-icon', payload.icon || '');
        row.setAttribute('data-status', payload.status);
        var title = row.querySelector('.fw-semibold');
        if (title) {
            title.textContent = payload.name;
        }
        var sub = row.querySelector('.small.text-muted');
        if (sub) {
            sub.innerHTML =
                '<code class="small">' +
                escapeHtml(payload.slug) +
                '</code> · ' +
                escapeHtml(payload.url);
        }
        var badge = row.querySelector('.vk-menu-status-badge');
        if (badge) {
            var on = payload.status === 'active';
            badge.textContent = on ? 'Active' : 'Hidden';
            badge.className = 'badge vk-menu-status-badge ' + (on ? 'text-bg-success' : 'text-bg-secondary');
        }
        var toggleBtn = row.querySelector('.vk-menu-toggle');
        if (toggleBtn) {
            var next = on ? 'inactive' : 'active';
            toggleBtn.setAttribute('data-next-status', next);
            toggleBtn.innerHTML = on ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        }
        row.querySelectorAll('.vk-menu-edit').forEach(function (b) {
            b.setAttribute('data-name', payload.name);
            b.setAttribute('data-slug', payload.slug);
            b.setAttribute('data-url', payload.url);
            b.setAttribute('data-icon', payload.icon || '');
            b.setAttribute('data-status', payload.status);
        });
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    document.getElementById('vkMenuFormSave')?.addEventListener('click', function () {
        var id = parseInt(document.getElementById('vkMenuFormId').value || '0', 10);
        var name = document.getElementById('vkMenuFormName').value.trim();
        var slug = document.getElementById('vkMenuFormSlug').value.trim().toLowerCase();
        var url = document.getElementById('vkMenuFormUrl').value.trim();
        var icon = document.getElementById('vkMenuFormIcon').value.trim();
        var status = document.getElementById('vkMenuFormStatus').value;
        if (!name || !slug || !url) {
            showToast('Name, slug, and URL are required.', 'warning');
            return;
        }
        postJson('/api/menus_save.php', {
            id: id,
            name: name,
            slug: slug,
            url: url,
            icon: icon,
            status: status,
        })
            .then(function (data) {
                var newId = data.id || id;
                showToast('Saved', 'success');
                var modal = bootstrap.Modal.getInstance(document.getElementById('vkMenuModal'));
                if (modal) {
                    modal.hide();
                }
                if (id <= 0) {
                    location.reload();
                } else {
                    syncRowFromForm(newId, { name: name, slug: slug, url: url, icon: icon, status: status });
                }
            })
            .catch(function (e) {
                showToast(e.message || 'Save failed', 'danger');
            });
    });

    document.querySelectorAll('.vk-menu-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = parseInt(btn.getAttribute('data-id') || '0', 10);
            var status = btn.getAttribute('data-next-status') || 'inactive';
            postJson('/api/menus_toggle.php', { id: id, status: status })
                .then(function () {
                    showToast(status === 'active' ? 'Menu is now visible' : 'Menu hidden', 'success');
                    var row = rowById(id);
                    if (!row) {
                        location.reload();
                        return;
                    }
                    row.setAttribute('data-status', status);
                    var on = status === 'active';
                    var badge = row.querySelector('.vk-menu-status-badge');
                    if (badge) {
                        badge.textContent = on ? 'Active' : 'Hidden';
                        badge.className = 'badge vk-menu-status-badge ' + (on ? 'text-bg-success' : 'text-bg-secondary');
                    }
                    btn.setAttribute('data-next-status', on ? 'inactive' : 'active');
                    btn.innerHTML = on ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
                    row.querySelectorAll('.vk-menu-edit').forEach(function (b) {
                        b.setAttribute('data-status', status);
                    });
                })
                .catch(function (e) {
                    showToast(e.message || 'Toggle failed', 'danger');
                });
        });
    });

    document.querySelectorAll('.vk-menu-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = parseInt(btn.getAttribute('data-id') || '0', 10);
            var nm = btn.getAttribute('data-name') || 'this item';
            if (!id || !confirm('Delete menu “' + nm + '”? This cannot be undone.')) {
                return;
            }
            postJson('/api/menus_delete.php', { id: id })
                .then(function () {
                    showToast('Deleted', 'success');
                    var row = rowById(id);
                    if (row) {
                        row.remove();
                    }
                    sendReorder();
                })
                .catch(function (e) {
                    showToast(e.message || 'Delete failed', 'danger');
                });
        });
    });
})();
