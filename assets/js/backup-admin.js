(function () {
    'use strict';

    var root = document.getElementById('vkBackupApp');
    if (!root) return;

    var api = root.getAttribute('data-api') || '';
    var csrf = root.getAttribute('data-csrf') || (window.VK_CSRF_TOKEN || '');
    var busy = false;

    function $(sel, el) { return (el || root).querySelector(sel); }
    function $all(sel, el) { return Array.prototype.slice.call((el || root).querySelectorAll(sel)); }

    function toast(msg, ok) {
        var box = $('#vkBkAlert');
        if (!box) {
            window.alert(msg);
            return;
        }
        box.classList.remove('d-none', 'alert-success', 'alert-danger');
        box.classList.add(ok ? 'alert-success' : 'alert-danger');
        box.textContent = msg;
    }

    function setProgress(on, pct, label) {
        var wrap = $('#vkBkProgress');
        var bar = $('#vkBkProgressBar');
        var text = $('#vkBkProgressLabel');
        if (!wrap) return;
        wrap.classList.toggle('is-on', !!on);
        if (bar) {
            bar.style.width = (pct || 0) + '%';
            bar.setAttribute('aria-valuenow', String(pct || 0));
        }
        if (text) text.textContent = label || 'Working…';
    }

    function selectedComponents() {
        return $all('[name="bk_component"]:checked').map(function (el) { return el.value; });
    }

    function postForm(action, fields, fileInput) {
        var fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('action', action);
        Object.keys(fields || {}).forEach(function (k) {
            var v = fields[k];
            if (Array.isArray(v)) {
                v.forEach(function (item) { fd.append(k + '[]', item); });
            } else if (typeof v === 'boolean') {
                if (v) fd.append(k, '1');
            } else if (v !== undefined && v !== null) {
                fd.append(k, String(v));
            }
        });
        if (fileInput && fileInput.files && fileInput.files[0]) {
            fd.append('backup_file', fileInput.files[0]);
        }
        return fetch(api + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }
        }).then(function (r) { return r.json(); });
    }

    function getJson(action, params) {
        var q = new URLSearchParams(params || {});
        q.set('action', action);
        return fetch(api + '?' + q.toString(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); });
    }

    function renderKpis(data) {
        var map = {
            total: data.total_backups,
            latest: data.latest_label,
            size: data.latest_size,
            dbver: data.database_version,
            storage: data.storage_used,
            auto: data.auto_backup && data.auto_backup.enabled ? 'Enabled (' + data.auto_backup.frequency + ')' : 'Disabled'
        };
        Object.keys(map).forEach(function (k) {
            var el = $('[data-kpi="' + k + '"]');
            if (el) el.textContent = map[k] == null ? '—' : String(map[k]);
        });
        var sys = {
            php: data.php_version,
            mysql: data.database_version,
            dbsize: data.database_size,
            server: data.server_storage,
            free: data.free_space,
            folder: data.backup_folder,
            last: data.latest_label
        };
        Object.keys(sys).forEach(function (k) {
            var el = $('[data-sys="' + k + '"]');
            if (el) el.textContent = sys[k] == null ? '—' : String(sys[k]);
        });
    }

    function statusBadge(status) {
        var s = String(status || 'unknown');
        var cls = s === 'completed' ? 'vk-bk-badge-ok' : (s === 'failed' ? 'vk-bk-badge-err' : 'vk-bk-badge-warn');
        return '<span class="vk-bk-badge ' + cls + '">' + s + '</span>';
    }

    function renderTable(items) {
        var body = $('#vkBkTableBody');
        if (!body) return;
        if (!items || !items.length) {
            body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No backups yet. Create your first backup above.</td></tr>';
            return;
        }
        body.innerHTML = items.map(function (it) {
            var id = it.id || '';
            var created = it.created_label || it.created_at || '—';
            var date = created.slice(0, 10);
            var time = created.length > 10 ? created.slice(11, 16) : '—';
            return '<tr>' +
                '<td><strong>' + escapeHtml(it.name || it.filename || id) + '</strong><div class="small text-muted">' + escapeHtml(it.filename || '') + '</div></td>' +
                '<td>' + escapeHtml(it.type || '—') + '</td>' +
                '<td>' + escapeHtml(it.created_by || '—') + '</td>' +
                '<td>' + escapeHtml(date) + '</td>' +
                '<td>' + escapeHtml(time) + '</td>' +
                '<td>' + escapeHtml(it.size_label || '—') + '</td>' +
                '<td>' + statusBadge(it.status) + '</td>' +
                '<td>' + escapeHtml(it.location || 'storage/backups') + '</td>' +
                '<td>' +
                    '<div class="d-inline-flex gap-1">' +
                    '<a class="vk-bk-act" title="Download" href="' + api + '?action=download&id=' + encodeURIComponent(id) + '"><i class="bi bi-download"></i></a>' +
                    '<button type="button" class="vk-bk-act" data-act="restore" data-id="' + escapeAttr(id) + '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>' +
                    '<button type="button" class="vk-bk-act" data-act="verify" data-id="' + escapeAttr(id) + '" title="Verify"><i class="bi bi-shield-check"></i></button>' +
                    '<button type="button" class="vk-bk-act" data-act="details" data-id="' + escapeAttr(id) + '" title="Details"><i class="bi bi-info-circle"></i></button>' +
                    '<button type="button" class="vk-bk-act" data-act="rename" data-id="' + escapeAttr(id) + '" title="Rename"><i class="bi bi-pencil"></i></button>' +
                    '<button type="button" class="vk-bk-act" data-act="delete" data-id="' + escapeAttr(id) + '" title="Delete"><i class="bi bi-trash"></i></button>' +
                    '</div>' +
                '</td>' +
                '</tr>';
        }).join('');
    }

    function renderLogs(logs) {
        var el = $('#vkBkOpsLog');
        if (!el) return;
        if (!logs || !logs.length) {
            el.textContent = 'No backup operations logged yet.';
            return;
        }
        el.textContent = logs.map(function (l) {
            return '[' + (l.at || '') + '] ' + (l.action || '') + ' · ' + (l.status || '') + ' · ' + (l.user || '') + ' · ' + (l.ip || '') + (l.detail ? ' · ' + l.detail : '');
        }).join('\n');
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }
    function escapeAttr(s) { return escapeHtml(s).replace(/`/g, ''); }

    function refreshAll() {
        return Promise.all([
            getJson('dashboard'),
            getJson('list'),
            getJson('logs'),
            getJson('schedule_get')
        ]).then(function (res) {
            if (res[0] && res[0].ok) renderKpis(res[0].data || {});
            if (res[1] && res[1].ok) renderTable(res[1].items || []);
            if (res[2] && res[2].ok) renderLogs(res[2].logs || []);
            if (res[3] && res[3].ok) fillSchedule(res[3].schedule || {});
        }).catch(function () {
            toast('Failed to load backup dashboard.', false);
        });
    }

    function fillSchedule(s) {
        var en = $('#bk_auto_enabled');
        if (en) en.checked = !!s.enabled;
        var freq = $('#bk_auto_frequency');
        if (freq) freq.value = s.frequency || 'daily';
        var time = $('#bk_auto_time');
        if (time) time.value = s.time || '02:00';
        var ret = $('#bk_retention');
        if (ret) ret.value = String(s.retention || 10);
        var comps = s.components || [];
        $all('[name="bk_auto_component"]').forEach(function (el) {
            el.checked = comps.indexOf(el.value) !== -1;
        });
    }

    function createBackup(type) {
        if (busy) return;
        var components = selectedComponents();
        if (type === 'database') components = ['database'];
        if (type === 'files' && components.indexOf('database') !== -1) {
            components = components.filter(function (c) { return c !== 'database'; });
        }
        if (!components.length && type !== 'database') {
            toast('Select at least one backup component.', false);
            return;
        }
        var encrypt = !!($('#bk_encrypt') && $('#bk_encrypt').checked);
        var password = ($('#bk_password') && $('#bk_password').value) || '';
        if (encrypt && !password) {
            toast('Enter an encryption password.', false);
            return;
        }
        if (!window.confirm('Create ' + type + ' backup now?')) return;
        busy = true;
        setProgress(true, 35, 'Creating backup…');
        postForm('create', {
            type: type,
            components: components,
            compress: !!(!$('#bk_compress') || $('#bk_compress').checked),
            encrypt: encrypt,
            gzip: !!($('#bk_gzip') && $('#bk_gzip').checked),
            password: password,
            name: ($('#bk_name') && $('#bk_name').value) || ''
        }).then(function (data) {
            toast((data && data.message) || (data && data.ok ? 'Done' : 'Failed'), !!(data && data.ok));
            return refreshAll();
        }).catch(function () {
            toast('Backup creation failed.', false);
        }).finally(function () {
            busy = false;
            setProgress(false, 0, '');
        });
    }

    $all('[data-bk-create]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            createBackup(btn.getAttribute('data-bk-create') || 'database');
        });
    });

    root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-act]');
        if (!btn) return;
        var act = btn.getAttribute('data-act');
        var id = btn.getAttribute('data-id');
        if (!act || !id) return;

        if (act === 'delete') {
            if (!window.confirm('Delete this backup permanently?')) return;
            postForm('delete', { id: id }).then(function (data) {
                toast((data && data.message) || 'Done', !!(data && data.ok));
                refreshAll();
            });
            return;
        }
        if (act === 'rename') {
            var name = window.prompt('New backup name');
            if (!name) return;
            postForm('rename', { id: id, name: name }).then(function (data) {
                toast((data && data.message) || 'Done', !!(data && data.ok));
                refreshAll();
            });
            return;
        }
        if (act === 'verify') {
            setProgress(true, 50, 'Verifying backup…');
            postForm('verify', { id: id }).then(function (data) {
                toast((data && data.message) || 'Done', !!(data && data.ok));
                var log = $('#vkBkRestoreLog');
                if (log && data && data.checks) {
                    log.textContent = JSON.stringify(data.checks, null, 2);
                }
            }).finally(function () { setProgress(false, 0, ''); });
            return;
        }
        if (act === 'details') {
            getJson('details', { id: id }).then(function (data) {
                if (!data || !data.ok) {
                    toast((data && data.message) || 'Not found', false);
                    return;
                }
                var log = $('#vkBkRestoreLog');
                if (log) log.textContent = JSON.stringify(data.item, null, 2);
                toast('Details loaded in restore log panel.', true);
            });
            return;
        }
        if (act === 'restore') {
            var mode = window.prompt('Restore mode: database | files | everything', 'database');
            if (!mode) return;
            mode = String(mode).toLowerCase().trim();
            if (['database', 'files', 'everything'].indexOf(mode) === -1) {
                toast('Invalid restore mode.', false);
                return;
            }
            if (!window.confirm('WARNING: Restore will overwrite live data (' + mode + '). Continue?')) return;
            var password = window.prompt('Encryption password (leave blank if none)', '') || '';
            busy = true;
            setProgress(true, 40, 'Restoring backup…');
            postForm('restore', { id: id, mode: mode, password: password }).then(function (data) {
                toast((data && data.message) || 'Done', !!(data && data.ok));
                var log = $('#vkBkRestoreLog');
                if (log && data && data.log) log.textContent = (data.log || []).join('\n');
            }).catch(function () {
                toast('Restore failed.', false);
            }).finally(function () {
                busy = false;
                setProgress(false, 0, '');
            });
        }
    });

    var scheduleForm = $('#vkBkScheduleForm');
    if (scheduleForm) {
        scheduleForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var comps = $all('[name="bk_auto_component"]:checked').map(function (el) { return el.value; });
            postForm('schedule_save', {
                enabled: !!($('#bk_auto_enabled') && $('#bk_auto_enabled').checked),
                frequency: ($('#bk_auto_frequency') && $('#bk_auto_frequency').value) || 'daily',
                time: ($('#bk_auto_time') && $('#bk_auto_time').value) || '02:00',
                retention: ($('#bk_retention') && $('#bk_retention').value) || '10',
                components: comps
            }).then(function (data) {
                toast((data && data.message) || 'Saved', !!(data && data.ok));
                refreshAll();
            });
        });
    }

    var uploadForm = $('#vkBkUploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fileInput = $('#bk_upload_file');
            if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                toast('Choose a backup file first.', false);
                return;
            }
            var restoreNow = !!($('#bk_upload_restore_now') && $('#bk_upload_restore_now').checked);
            if (restoreNow && !window.confirm('Upload and restore now? This overwrites live data.')) return;
            busy = true;
            setProgress(true, 45, restoreNow ? 'Uploading & restoring…' : 'Uploading backup…');
            postForm('upload_restore', {
                mode: ($('#bk_upload_mode') && $('#bk_upload_mode').value) || 'database',
                password: ($('#bk_upload_password') && $('#bk_upload_password').value) || '',
                restore_now: restoreNow
            }, fileInput).then(function (data) {
                toast((data && data.message) || 'Done', !!(data && data.ok));
                var log = $('#vkBkRestoreLog');
                if (log && data && data.log) log.textContent = (data.log || []).join('\n');
                return refreshAll();
            }).catch(function () {
                toast('Upload/restore failed.', false);
            }).finally(function () {
                busy = false;
                setProgress(false, 0, '');
            });
        });
    }

    // Load when backup tab is shown
    document.querySelectorAll('[data-bs-target="#pane-backup"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function () { refreshAll(); });
    });

    if (document.querySelector('#pane-backup.show, #pane-backup.active')) {
        refreshAll();
    }
})();
