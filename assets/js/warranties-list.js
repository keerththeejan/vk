(function () {
    'use strict';

    var app = document.getElementById('vkWarApp');
    if (!app) return;

    var form = document.getElementById('vkWarFilterForm');
    var tableBody = document.getElementById('vkWarTableBody');
    var footer = document.getElementById('vkWarFooter');
    var loading = document.getElementById('vkWarLoading');
    var bulkBar = document.getElementById('vkWarBulkBar');
    var selectAll = document.getElementById('vkWarSelectAll');
    var debounceTimer = null;
    var baseUrl = app.getAttribute('data-base') || '';
    var csrf = (window.VK_CSRF_TOKEN || app.getAttribute('data-csrf') || '');

    function setLoading(on) {
        if (loading) loading.classList.toggle('is-on', !!on);
    }

    function selectedIds() {
        return Array.prototype.map.call(
            app.querySelectorAll('.vk-war-row-check:checked'),
            function (el) { return el.value; }
        );
    }

    function syncBulk() {
        var ids = selectedIds();
        if (bulkBar) {
            bulkBar.classList.toggle('is-on', ids.length > 0);
            var countEl = document.getElementById('vkWarBulkCount');
            if (countEl) countEl.textContent = String(ids.length);
        }
    }

    function queryFromForm() {
        if (!form) return new URLSearchParams();
        return new URLSearchParams(new FormData(form));
    }

    function fetchPartial(params) {
        params.set('partial', '1');
        setLoading(true);
        fetch(baseUrl + '/modules/warranties/list.php?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) throw new Error((data && data.message) || 'Load failed');
                if (tableBody) tableBody.innerHTML = data.tbody || '';
                if (footer) footer.innerHTML = data.footer || '';
                if (selectAll) selectAll.checked = false;
                syncBulk();
                var url = new URL(window.location.href);
                params.delete('partial');
                url.search = params.toString();
                window.history.replaceState({}, '', url.toString());
            })
            .catch(function () {
                /* fallback: full reload */
                params.delete('partial');
                window.location.search = params.toString();
            })
            .finally(function () { setLoading(false); });
    }

    function scheduleSearch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            var params = queryFromForm();
            params.set('p', '1');
            fetchPartial(params);
        }, 320);
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var params = queryFromForm();
            params.set('p', '1');
            fetchPartial(params);
        });

        form.querySelectorAll('[data-instant]').forEach(function (el) {
            el.addEventListener('input', scheduleSearch);
            el.addEventListener('change', scheduleSearch);
        });

        var resetBtn = document.getElementById('vkWarReset');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                form.reset();
                fetchPartial(new URLSearchParams());
            });
        }
    }

    app.addEventListener('click', function (e) {
        var sortBtn = e.target.closest('[data-sort]');
        if (sortBtn) {
            e.preventDefault();
            var params = queryFromForm();
            var col = sortBtn.getAttribute('data-sort') || 'end_date';
            var cur = params.get('sort') || 'end_date';
            var dir = params.get('dir') || 'asc';
            if (cur === col) {
                params.set('dir', dir === 'asc' ? 'desc' : 'asc');
            } else {
                params.set('sort', col);
                params.set('dir', 'asc');
            }
            params.set('p', '1');
            fetchPartial(params);
            return;
        }

        var pageLink = e.target.closest('[data-page]');
        if (pageLink) {
            e.preventDefault();
            var params2 = queryFromForm();
            params2.set('p', pageLink.getAttribute('data-page') || '1');
            fetchPartial(params2);
        }
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            app.querySelectorAll('.vk-war-row-check').forEach(function (el) {
                el.checked = selectAll.checked;
            });
            syncBulk();
        });
    }

    app.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('vk-war-row-check')) syncBulk();
    });

    function postAction(action, extra) {
        var ids = selectedIds();
        if (!ids.length) return;
        var fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('action', action);
        ids.forEach(function (id) { fd.append('ids[]', id); });
        if (extra) {
            Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
        }
        setLoading(true);
        fetch(baseUrl + '/modules/warranties/actions.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                if (data && data.download) {
                    window.location.href = data.download;
                    return;
                }
                alert((data && data.message) || 'Done');
                fetchPartial(queryFromForm());
            })
            .catch(function () { alert('Action failed'); })
            .finally(function () { setLoading(false); });
    }

    app.querySelectorAll('[data-bulk-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.getAttribute('data-bulk-action');
            if (!action) return;
            if ((action === 'delete' || action === 'deactivate') && !window.confirm('Continue with this bulk action?')) {
                return;
            }
            postAction(action);
        });
    });

    app.addEventListener('click', function (e) {
        var act = e.target.closest('[data-war-action]');
        if (!act) return;
        e.preventDefault();
        var action = act.getAttribute('data-war-action');
        var id = act.getAttribute('data-id');
        if (!action || !id) return;
        if ((action === 'delete' || action === 'deactivate') && !window.confirm('Are you sure?')) return;

        var fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('action', action);
        fd.append('ids[]', id);
        setLoading(true);
        fetch(baseUrl + '/modules/warranties/actions.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                alert((data && data.message) || 'Done');
                fetchPartial(queryFromForm());
            })
            .catch(function () { alert('Action failed'); })
            .finally(function () { setLoading(false); });
    });
})();
